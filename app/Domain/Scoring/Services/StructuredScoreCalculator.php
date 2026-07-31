<?php

namespace App\Domain\Scoring\Services;

use App\Application\Scoring\DTOs\ScoreCalculationResultData;
use App\Models\ScoringRule;
use Illuminate\Validation\ValidationException;

final class StructuredScoreCalculator
{
    public function __construct(
        private readonly ScoringConfigurationValidator $validator,
        private readonly ScoringRuleIntegrityService $integrity,
        private readonly ScoringRoundingPolicy $rounding,
    ) {}

    /** @param array<string, mixed> $inputs */
    public function calculate(ScoringRule $rule, array $inputs): ScoreCalculationResultData
    {
        $this->integrity->assertUntampered($rule);
        $configuration = $rule->configuration ?? [];
        $this->validator->validate($configuration);
        $components = [];
        $weights = [];
        $total = 0.0;
        $criteria = collect($configuration['criteria']);
        $missingRequired = $criteria->filter(static function (array $criterion) use ($inputs): bool {
            $missing = ! array_key_exists($criterion['key'], $inputs) || $inputs[$criterion['key']] === null || $inputs[$criterion['key']] === '';
            return $missing && (($criterion['required'] ?? true) || ($criterion['missing_data_behavior'] ?? 'block') === 'block');
        })->pluck('label')->all();
        if ($missingRequired !== []) {
            throw ValidationException::withMessages([
                'source_inputs' => 'Required scoring inputs are missing for: '.implode(', ', $missingRequired).'.',
            ]);
        }

        $applicableCriteria = $criteria->reject(static function (array $criterion) use ($inputs): bool {
            $missing = ! array_key_exists($criterion['key'], $inputs) || $inputs[$criterion['key']] === null || $inputs[$criterion['key']] === '';
            return $missing && ($criterion['required'] ?? true) === false && ($criterion['missing_data_behavior'] ?? 'zero') === 'reweight';
        });
        $applicableWeight = (float) $applicableCriteria->sum(static fn (array $criterion): float => (float) $criterion['weight']);
        $reweight = $applicableCriteria->count() !== $criteria->count();

        foreach ($applicableCriteria as $criterion) {
            $key = $criterion['key'];
            $maxPoints = (float) $criterion['max_points'];
            $configuredWeight = (float) $criterion['weight'];
            $weight = $reweight && $applicableWeight > 0 ? ($configuredWeight / $applicableWeight) * 100 : $configuredWeight;
            $value = $inputs[$key] ?? null;
            $conditions = $criterion['conditions'] ?? [];
            $rawPoints = count($conditions) > 0 && ! is_numeric($value)
                ? collect($conditions)->filter(fn (array $condition): bool => $this->matches($value, $condition))->sum(fn (array $condition): float => (float) $condition['points'])
                : (is_numeric($value) ? (float) $value : 0.0);
            $rawPoints = max(0.0, min($maxPoints, $rawPoints));
            $normalized = $maxPoints > 0 ? ($rawPoints / $maxPoints) * 100 : 0.0;
            $contribution = $normalized * ($weight / 100);
            $components[$key] = [
                'label' => $criterion['label'], 'input' => $value, 'raw_points' => $rawPoints,
                'max_points' => $maxPoints, 'normalized_score' => $this->rounding->apply($normalized, $configuration),
                'weighted_contribution' => $this->rounding->apply($contribution, $configuration),
            ];
            $weights[$key] = $this->rounding->apply($weight, $configuration);
            $total += $contribution;
        }

        $mandatoryFailures = collect($configuration['mandatory_conditions'] ?? [])->reject(
            fn (array $condition): bool => $this->matches($inputs[$condition['criterion_key'] ?? ''] ?? null, $condition),
        )->map(fn (array $condition): string => (string) ($condition['label'] ?? $condition['criterion_key'] ?? 'Mandatory condition'))->values()->all();
        if ($mandatoryFailures !== []) {
            $total = 0.0;
        }
        $total = $this->rounding->apply(max(0.0, min(100.0, $total)), $configuration);
        $band = collect($configuration['bands'])->sortByDesc('min_score')->first(
            static fn (array $candidate): bool => $total >= (float) $candidate['min_score'],
        );

        return new ScoreCalculationResultData(
            ruleId: $rule->id, ruleKey: $rule->rule_key, ruleVersion: $rule->version,
            totalScore: $total, scoreBand: $band['key'] ?? null, componentScores: $components,
            appliedWeights: $weights, inputHash: hash('sha256', json_encode($this->canonicalize($inputs), JSON_THROW_ON_ERROR)),
            mandatoryFailures: $mandatoryFailures,
        );
    }

    /** @param array<string, mixed> $condition */
    private function matches(mixed $actual, array $condition): bool
    {
        $expected = $condition['value'] ?? null;
        return match ($condition['operator'] ?? 'equals') {
            'equals' => (string) $actual === (string) $expected,
            'not_equals' => (string) $actual !== (string) $expected,
            'greater_or_equal' => is_numeric($actual) && is_numeric($expected) && (float) $actual >= (float) $expected,
            'less_or_equal' => is_numeric($actual) && is_numeric($expected) && (float) $actual <= (float) $expected,
            'between' => $this->between($actual, $expected),
            'in' => in_array((string) $actual, array_map('trim', explode(',', (string) $expected)), true),
            'boolean' => $this->booleanMatches($actual, $expected),
            default => throw ValidationException::withMessages(['condition.operator' => 'Unsupported scoring condition operator.']),
        };
    }

    private function between(mixed $actual, mixed $expected): bool
    {
        $range = array_map('trim', explode(',', (string) $expected, 2));
        return count($range) === 2 && is_numeric($actual) && is_numeric($range[0]) && is_numeric($range[1])
            && (float) $actual >= (float) $range[0] && (float) $actual <= (float) $range[1];
    }

    private function booleanMatches(mixed $actual, mixed $expected): bool
    {
        $actualBoolean = filter_var($actual, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        $expectedBoolean = filter_var($expected, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        return $actualBoolean !== null && $expectedBoolean !== null && $actualBoolean === $expectedBoolean;
    }

    /** @param array<string, mixed> $value @return array<string, mixed> */
    private function canonicalize(array $value): array
    {
        ksort($value);
        foreach ($value as &$child) {
            if (is_array($child)) {
                $child = $this->canonicalize($child);
            }
        }
        return $value;
    }
}
