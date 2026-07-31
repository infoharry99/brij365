<?php

namespace App\Http\Requests\Scoring;

use App\Models\ScoringRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateScoringRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('scoringRule')) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $rule = $this->route('scoringRule');
        $isPerformanceRule = $rule instanceof ScoringRule && $rule->rule_key === 'employee_performance';
        $defaultRatingMax = $this->input('rating_max', 5);

        $criteria = collect($this->input('criteria', []))->map(static function (mixed $criterion) use ($isPerformanceRule, $defaultRatingMax): mixed {
            if (! is_array($criterion)) {
                return $criterion;
            }

            if (array_key_exists('remove', $criterion)) {
                $criterion['remove'] = filter_var($criterion['remove'], FILTER_VALIDATE_BOOL);
            }

            if (array_key_exists('required', $criterion)) {
                $criterion['required'] = filter_var($criterion['required'], FILTER_VALIDATE_BOOL);
            }

            if (! ($criterion['remove'] ?? false)) {
                $normalization = (string) ($criterion['normalization'] ?? (empty($criterion['conditions'] ?? []) ? 'rating_scale' : 'points'));
                $criterion['input_scale'] = is_array($criterion['input_scale'] ?? null)
                    ? $criterion['input_scale']
                    : [];
                $criterion['input_scale']['min'] = $criterion['input_scale']['min'] ?? 0;
                $criterion['input_scale']['max'] = $criterion['input_scale']['max'] ?? match ($normalization) {
                    'percentage' => 100,
                    'points' => $criterion['max_points'] ?? 100,
                    default => $defaultRatingMax,
                };
            }

            // Governance fields are mandatory and explicit for employee-performance
            // formulas. Existing business-scoring rule payloads predate that contract,
            // so normalize their equivalent defaults before validation rather than
            // making previously valid rule drafts impossible to edit.
            if (! $isPerformanceRule && ! ($criterion['remove'] ?? false)) {
                $required = array_key_exists('required', $criterion)
                    ? (bool) $criterion['required']
                    : true;

                $criterion['source'] = filled($criterion['source'] ?? null)
                    ? $criterion['source']
                    : ($criterion['key'] ?? null);
                $criterion['normalization'] = filled($criterion['normalization'] ?? null)
                    ? $criterion['normalization']
                    : (empty($criterion['conditions'] ?? []) ? 'rating_scale' : 'points');
                $criterion['required'] = $required;
                $criterion['missing_data_behavior'] = filled($criterion['missing_data_behavior'] ?? null)
                    ? $criterion['missing_data_behavior']
                    : ($required ? 'block' : 'zero');
            }

            return $criterion;
        })->all();

        $this->merge([
            'criteria' => $criteria,
            'override_allowed' => $this->boolean('override_allowed'),
            'override_reason_required' => $this->boolean('override_reason_required'),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:140'],
            'change_reason' => ['required', 'string', 'min:12', 'max:2000'],
            'effective_at' => ['nullable', 'date'],
            'criteria' => ['required', 'array', 'min:1', 'max:20'],
            'criteria.*.key' => ['required_unless:criteria.*.remove,1', 'nullable', 'regex:/^[a-z][a-z0-9_]{1,49}$/'],
            'criteria.*.label' => ['required_unless:criteria.*.remove,1', 'nullable', 'string', 'max:120'],
            'criteria.*.weight' => ['required_unless:criteria.*.remove,1', 'nullable', 'numeric', 'between:0,100'],
            'criteria.*.max_points' => ['required_unless:criteria.*.remove,1', 'nullable', 'numeric', 'gt:0', 'lte:100'],
            'criteria.*.source' => ['required_unless:criteria.*.remove,1', 'nullable', 'regex:/^[a-z][a-z0-9_]{1,49}$/'],
            'criteria.*.normalization' => ['required_unless:criteria.*.remove,1', 'nullable', Rule::in(['rating_scale', 'percentage', 'points'])],
            'criteria.*.input_scale' => ['required_unless:criteria.*.remove,1', 'array:min,max'],
            'criteria.*.input_scale.min' => ['required_unless:criteria.*.remove,1', 'numeric', 'gte:0', 'lt:criteria.*.input_scale.max'],
            'criteria.*.input_scale.max' => ['required_unless:criteria.*.remove,1', 'numeric', 'gt:criteria.*.input_scale.min', 'lte:1000000'],
            'criteria.*.required' => ['required_unless:criteria.*.remove,1', 'boolean'],
            'criteria.*.missing_data_behavior' => ['required_unless:criteria.*.remove,1', 'nullable', Rule::in(['block', 'zero', 'reweight'])],
            'criteria.*.remove' => ['nullable', 'boolean'],
            'criteria.*.conditions' => ['nullable', 'array', 'max:30'],
            'criteria.*.conditions.*.key' => ['nullable', 'regex:/^[a-z][a-z0-9_]{1,49}$/'],
            'criteria.*.conditions.*.label' => ['nullable', 'string', 'max:120'],
            'criteria.*.conditions.*.operator' => ['nullable', Rule::in(['equals', 'not_equals', 'greater_or_equal', 'less_or_equal', 'between', 'in', 'boolean'])],
            'criteria.*.conditions.*.value' => ['nullable', 'string', 'max:255'],
            'criteria.*.conditions.*.points' => ['nullable', 'numeric', 'between:-100,100'],
            'criteria.*.conditions.*.remove' => ['nullable', 'boolean'],
            'bands' => ['required', 'array', 'min:1', 'max:10'],
            'bands.*.key' => ['required_unless:bands.*.remove,1', 'nullable', 'regex:/^[a-z][a-z0-9_]{1,49}$/'],
            'bands.*.label' => ['required_unless:bands.*.remove,1', 'nullable', 'string', 'max:120'],
            'bands.*.min_score' => ['required_unless:bands.*.remove,1', 'nullable', 'integer', 'between:0,100'],
            'bands.*.outcome' => ['required_unless:bands.*.remove,1', 'nullable', 'string', 'max:255'],
            'bands.*.remove' => ['nullable', 'boolean'],
            'rating_min' => ['required', 'integer', 'between:0,100'],
            'rating_max' => ['required', 'integer', 'between:1,100', 'gt:rating_min'],
            'passing_score' => ['required', 'numeric', 'between:0,100'],
            'pip_score' => ['required', 'numeric', 'between:0,100', 'lte:passing_score'],
            'rounding_method' => ['required', Rule::in(['half_up', 'half_even', 'floor', 'ceil'])],
            'rounding_precision' => ['required', 'integer', 'between:0,4'],
            'minimum_sample_size' => ['required', 'integer', 'between:1,10000'],
            'override_allowed' => ['required', 'boolean'],
            'override_reason_required' => ['required', 'boolean'],
        ];
    }
}
