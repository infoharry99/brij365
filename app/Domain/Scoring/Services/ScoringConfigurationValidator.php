<?php

namespace App\Domain\Scoring\Services;

use Illuminate\Validation\ValidationException;

final class ScoringConfigurationValidator
{
    public function __construct(private readonly PerformanceScoringSourceRegistry $performanceSources) {}

    /**
     * Validate the configuration and any domain-specific activation contract.
     *
     * Editable legacy drafts may still omit fields that the draft editor
     * materializes on save. A rule cannot, however, enter the governed
     * lifecycle without every calculation input being explicit.
     *
     * @param  array<string, mixed>  $configuration
     */
    public function validateForRule(string $ruleKey, array $configuration): void
    {
        $this->validate($configuration);

        if ($ruleKey !== 'employee_performance') {
            return;
        }

        foreach ((array) ($configuration['criteria'] ?? []) as $index => $criterion) {
            $source = is_array($criterion) ? (string) ($criterion['source'] ?? $criterion['key'] ?? '') : '';
            if (! $this->performanceSources->supports($source)) {
                throw ValidationException::withMessages([
                    "configuration.criteria.{$index}.source" => 'Select a supported Employee Performance input source.',
                ]);
            }

            $inputScale = is_array($criterion) ? ($criterion['input_scale'] ?? null) : null;
            if (! is_array($inputScale)
                || ! is_numeric($inputScale['min'] ?? null)
                || ! is_numeric($inputScale['max'] ?? null)) {
                throw ValidationException::withMessages([
                    "configuration.criteria.{$index}.input_scale" => 'Employee Performance criteria require an explicit input scale before validation and activation.',
                ]);
            }
        }
    }

    /** @param array<string, mixed> $configuration */
    public function validate(array $configuration): void
    {
        $this->rejectExecutableKeys($configuration);
        $criteria = $configuration['criteria'] ?? null;
        $bands = $configuration['bands'] ?? null;

        if (! is_array($criteria) || count($criteria) < 1 || count($criteria) > 20) {
            throw ValidationException::withMessages(['configuration.criteria' => 'A scoring rule requires between 1 and 20 structured criteria.']);
        }

        $keys = [];
        $weightTotal = 0.0;
        foreach ($criteria as $index => $criterion) {
            if (! is_array($criterion)) {
                throw ValidationException::withMessages(["configuration.criteria.{$index}" => 'Each criterion must be a structured record.']);
            }
            $key = (string) ($criterion['key'] ?? '');
            if (! preg_match('/^[a-z][a-z0-9_]{1,49}$/', $key) || in_array($key, $keys, true)) {
                throw ValidationException::withMessages(["configuration.criteria.{$index}.key" => 'Criterion keys must be unique safe identifiers.']);
            }
            $keys[] = $key;
            if (trim((string) ($criterion['label'] ?? '')) === '') {
                throw ValidationException::withMessages(["configuration.criteria.{$index}.label" => 'Every criterion requires a label.']);
            }
            $source = (string) ($criterion['source'] ?? $key);
            if (! preg_match('/^[a-z][a-z0-9_]{1,49}$/', $source)) {
                throw ValidationException::withMessages(["configuration.criteria.{$index}.source" => 'Every criterion source must be a safe structured-input identifier.']);
            }
            if (! in_array($criterion['normalization'] ?? (empty($criterion['conditions']) ? 'rating_scale' : 'points'), ['rating_scale', 'percentage', 'points'], true)) {
                throw ValidationException::withMessages(["configuration.criteria.{$index}.normalization" => 'Select rating scale, percentage or points normalization.']);
            }
            $inputScale = $criterion['input_scale'] ?? null;
            if ($inputScale !== null) {
                $inputMinimum = data_get($inputScale, 'min');
                $inputMaximum = data_get($inputScale, 'max');
                if (! is_array($inputScale)
                    || ! is_numeric($inputMinimum)
                    || ! is_numeric($inputMaximum)
                    || (float) $inputMinimum < 0
                    || (float) $inputMaximum <= (float) $inputMinimum) {
                    throw ValidationException::withMessages(["configuration.criteria.{$index}.input_scale" => 'Criterion input scale requires a non-negative minimum and a greater maximum.']);
                }
            }
            $weight = (float) ($criterion['weight'] ?? -1);
            if ($weight < 0 || $weight > 100) {
                throw ValidationException::withMessages(["configuration.criteria.{$index}.weight" => 'Criterion weight must be between 0 and 100.']);
            }
            $weightTotal += $weight;
            $maxPoints = (float) ($criterion['max_points'] ?? 0);
            if ($maxPoints <= 0 || $maxPoints > 100) {
                throw ValidationException::withMessages(["configuration.criteria.{$index}.max_points" => 'Maximum points must be greater than 0 and not exceed 100.']);
            }
            if (array_key_exists('required', $criterion) && ! is_bool($criterion['required'])) {
                throw ValidationException::withMessages(["configuration.criteria.{$index}.required" => 'Criterion required state must be a boolean.']);
            }
            $missingBehavior = $criterion['missing_data_behavior'] ?? ((bool) ($criterion['required'] ?? true) ? 'block' : 'zero');
            if (! in_array($missingBehavior, ['block', 'zero', 'reweight'], true)) {
                throw ValidationException::withMessages(["configuration.criteria.{$index}.missing_data_behavior" => 'Select block, zero or reweight for missing criterion data.']);
            }
            if (($criterion['required'] ?? true) && $missingBehavior !== 'block') {
                throw ValidationException::withMessages(["configuration.criteria.{$index}.missing_data_behavior" => 'A required criterion must block final calculation when its input is missing.']);
            }

            $conditions = $criterion['conditions'] ?? [];
            if (! is_array($conditions) || count($conditions) > 30) {
                throw ValidationException::withMessages(["configuration.criteria.{$index}.conditions" => 'A criterion may contain up to 30 structured conditions.']);
            }
            $conditionKeys = [];
            foreach ($conditions as $conditionIndex => $condition) {
                $conditionKey = (string) ($condition['key'] ?? '');
                if (! is_array($condition)
                    || ! preg_match('/^[a-z][a-z0-9_]{1,49}$/', $conditionKey)
                    || in_array($conditionKey, $conditionKeys, true)) {
                    throw ValidationException::withMessages(["configuration.criteria.{$index}.conditions.{$conditionIndex}.key" => 'Condition keys must be unique safe identifiers within the criterion.']);
                }
                $conditionKeys[] = $conditionKey;
                if (trim((string) ($condition['label'] ?? '')) === '') {
                    throw ValidationException::withMessages(["configuration.criteria.{$index}.conditions.{$conditionIndex}.label" => 'Every condition requires a label.']);
                }
                if (! in_array($condition['operator'] ?? null, ['equals', 'not_equals', 'greater_or_equal', 'less_or_equal', 'between', 'in', 'boolean'], true)) {
                    throw ValidationException::withMessages(["configuration.criteria.{$index}.conditions.{$conditionIndex}.operator" => 'Select an approved condition operator.']);
                }
                $points = (float) ($condition['points'] ?? 0);
                if ($points < -100 || $points > 100) {
                    throw ValidationException::withMessages(["configuration.criteria.{$index}.conditions.{$conditionIndex}.points" => 'Condition points must be between -100 and 100.']);
                }
            }
        }

        if (round($weightTotal, 2) !== 100.0) {
            throw ValidationException::withMessages(['configuration.criteria' => 'Applicable criterion weights must total exactly 100%.']);
        }

        if (! is_array($bands) || count($bands) < 1 || count($bands) > 10) {
            throw ValidationException::withMessages(['configuration.bands' => 'A scoring rule requires between 1 and 10 score bands.']);
        }
        $minimums = [];
        foreach ($bands as $index => $band) {
            if (! is_array($band) || trim((string) ($band['label'] ?? '')) === '') {
                throw ValidationException::withMessages(["configuration.bands.{$index}" => 'Every score band requires a label.']);
            }
            $minimum = (int) ($band['min_score'] ?? -1);
            if ($minimum < 0 || $minimum > 100 || in_array($minimum, $minimums, true)) {
                throw ValidationException::withMessages(["configuration.bands.{$index}.min_score" => 'Band thresholds must be unique values between 0 and 100.']);
            }
            $minimums[] = $minimum;
        }
        if (! in_array(0, $minimums, true)) {
            throw ValidationException::withMessages(['configuration.bands' => 'Score bands must include a zero-floor band.']);
        }

        $ratingMin = (int) data_get($configuration, 'rating_scale.min', -1);
        $ratingMax = (int) data_get($configuration, 'rating_scale.max', -1);
        if ($ratingMin < 0 || $ratingMax <= $ratingMin || $ratingMax > 100) {
            throw ValidationException::withMessages(['configuration.rating_scale' => 'Rating scale maximum must be greater than its minimum and not exceed 100.']);
        }
        $passingScore = (float) data_get($configuration, 'thresholds.passing_score', -1);
        $pipScore = (float) data_get($configuration, 'thresholds.pip_score', -1);
        if ($passingScore < 0 || $passingScore > 100 || $pipScore < 0 || $pipScore > $passingScore) {
            throw ValidationException::withMessages(['configuration.thresholds' => 'Performance-improvement threshold cannot exceed the passing threshold; both must be between 0 and 100.']);
        }

        $method = data_get($configuration, 'rounding.method');
        if (! in_array($method, ['half_up', 'half_even', 'floor', 'ceil'], true)) {
            throw ValidationException::withMessages(['configuration.rounding.method' => 'Select an approved rounding method.']);
        }
        $precision = (int) data_get($configuration, 'rounding.precision', -1);
        if ($precision < 0 || $precision > 4) {
            throw ValidationException::withMessages(['configuration.rounding.precision' => 'Rounding precision must be between 0 and 4.']);
        }
        $sample = (int) ($configuration['minimum_sample_size'] ?? 0);
        if ($sample < 1 || $sample > 10000) {
            throw ValidationException::withMessages(['configuration.minimum_sample_size' => 'Minimum sample size must be between 1 and 10,000.']);
        }
    }

    /** @param array<string, mixed> $value */
    private function rejectExecutableKeys(array $value, string $path = 'configuration'): void
    {
        foreach ($value as $key => $child) {
            $key = (string) $key;
            if (preg_match('/(formula|callback|class|script|expression|php|sql|javascript|command)/i', $key)) {
                throw ValidationException::withMessages([$path.'.'.$key => 'Executable formulas, code references and class names are not allowed.']);
            }
            if (is_array($child)) {
                $this->rejectExecutableKeys($child, $path.'.'.$key);
            }
        }
    }
}
