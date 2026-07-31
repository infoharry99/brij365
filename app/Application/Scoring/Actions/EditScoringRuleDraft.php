<?php

namespace App\Application\Scoring\Actions;

use App\Application\Scoring\DTOs\ScoringBandData;
use App\Application\Scoring\DTOs\ScoringConditionData;
use App\Application\Scoring\DTOs\ScoringCriterionData;
use App\Application\Scoring\DTOs\ScoringRuleEditorPageData;
use App\Domain\Scoring\Services\PerformanceScoringSourceRegistry;
use App\Models\ScoringRule;

final class EditScoringRuleDraft
{
    public function __construct(private readonly PerformanceScoringSourceRegistry $performanceSources) {}

    public function handle(ScoringRule $rule): ScoringRuleEditorPageData
    {
        $configuration = $rule->configuration ?? [];
        $criteria = collect($configuration['criteria'] ?? [])->map(static function (array $criterion) use ($configuration): ScoringCriterionData {
            $conditions = collect($criterion['conditions'] ?? [])->map(static fn (array $condition): ScoringConditionData => new ScoringConditionData(
                key: (string) ($condition['key'] ?? ''), label: (string) ($condition['label'] ?? ''),
                operator: (string) ($condition['operator'] ?? 'equals'), value: (string) ($condition['value'] ?? ''),
                points: (float) ($condition['points'] ?? 0),
            ))->values()->all();
            return new ScoringCriterionData(
                key: (string) ($criterion['key'] ?? ''), label: (string) ($criterion['label'] ?? ''),
                weight: (float) ($criterion['weight'] ?? 0), maxPoints: (float) ($criterion['max_points'] ?? 0),
                source: (string) ($criterion['source'] ?? $criterion['key'] ?? ''),
                normalization: (string) ($criterion['normalization'] ?? (empty($criterion['conditions']) ? 'rating_scale' : 'points')),
                inputScaleMin: (float) data_get($criterion, 'input_scale.min', 0),
                inputScaleMax: (float) data_get(
                    $criterion,
                    'input_scale.max',
                    ($criterion['normalization'] ?? 'rating_scale') === 'percentage'
                        ? 100
                        : (($criterion['normalization'] ?? 'rating_scale') === 'points'
                            ? ($criterion['max_points'] ?? 100)
                            : data_get($configuration, 'rating_scale.max', 5)),
                ),
                required: (bool) ($criterion['required'] ?? true),
                missingDataBehavior: (string) ($criterion['missing_data_behavior'] ?? ((bool) ($criterion['required'] ?? true) ? 'block' : 'zero')),
                conditions: $conditions,
            );
        })->values()->all();
        $bands = collect($configuration['bands'] ?? [])->map(static fn (array $band): ScoringBandData => new ScoringBandData(
            key: (string) ($band['key'] ?? ''), label: (string) ($band['label'] ?? ''),
            minScore: (int) ($band['min_score'] ?? 0), outcome: (string) ($band['outcome'] ?? ''),
        ))->values()->all();

        return new ScoringRuleEditorPageData(
            id: $rule->id, ruleKey: $rule->rule_key, name: $rule->name, version: $rule->version,
            status: str($rule->status)->headline()->toString(), changeReason: $rule->change_reason,
            effectiveAt: $rule->effective_at?->format('Y-m-d\TH:i'), criteria: $criteria, bands: $bands,
            ratingMin: (int) data_get($configuration, 'rating_scale.min', 1),
            ratingMax: (int) data_get($configuration, 'rating_scale.max', 5),
            passingScore: (float) data_get($configuration, 'thresholds.passing_score', 60),
            pipScore: (float) data_get($configuration, 'thresholds.pip_score', 40),
            roundingMethod: (string) data_get($configuration, 'rounding.method', 'half_up'),
            roundingPrecision: (int) data_get($configuration, 'rounding.precision', 2),
            minimumSampleSize: (int) ($configuration['minimum_sample_size'] ?? 1),
            overrideAllowed: (bool) data_get($configuration, 'override.allowed', true),
            overrideReasonRequired: (bool) data_get($configuration, 'override.reason_required', true),
            performanceSourceOptions: $rule->rule_key === 'employee_performance'
                ? $this->performanceSources->options()
                : [],
        );
    }
}
