<?php

namespace App\Http\Requests\Crm;

use App\Models\Lead;
use App\Models\LeadQualification;
use App\Services\Crm\LeadQualityScoreService;
use App\Services\Security\CompanyScopeService;
use App\Support\MoneyInputPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreLeadQualificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', LeadQualification::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'lead_id' => ['required', 'integer', 'exists:leads,id'],
            'status' => ['required', 'string', Rule::in(['qualified', 'nurture', 'disqualified'])],
            'quality_conditions' => ['nullable', 'array'],
            'quality_conditions.*' => ['nullable', 'string', 'max:80'],
            'preferred_configuration' => ['nullable', 'string', 'max:80'],
            'verified_budget_min' => ['nullable', 'numeric', 'min:0', app(MoneyInputPolicy::class)->enterpriseAmountMaxRule()],
            'verified_budget_max' => ['nullable', 'numeric', 'min:0', 'gte:verified_budget_min', app(MoneyInputPolicy::class)->enterpriseAmountMaxRule()],
            'expected_booking_date' => ['nullable', 'date'],
            'decision_notes' => ['required', 'string', 'max:5000'],
            'requirements' => ['nullable', 'array'],
            'metadata' => ['nullable', 'array'],
        ];

        foreach ($this->activeCriteria() as $criterionKey => $criterion) {
            $rules[$criterionKey.'_score'] = [
                'nullable',
                'integer',
                'min:0',
                'max:'.(int) ($criterion['max_points'] ?? 100),
            ];
            $rules['quality_conditions.'.$criterionKey] = ['nullable', 'string', 'max:80'];
        }

        return $rules;
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $actor = $this->user();
                $lead = Lead::query()->whereKey($this->integer('lead_id'))->first();

                if ($actor && $lead && ! app(CompanyScopeService::class)->allows($actor, $lead->company_id)) {
                    $validator->errors()->add('lead_id', 'The selected lead is not available for your company.');
                }

                $criteriaKeys = array_keys($this->activeCriteria($lead?->company_id));
                $hasAllScores = collect($criteriaKeys)
                    ->every(fn (string $field): bool => $this->filled($field.'_score'));
                $conditions = $this->input('quality_conditions', []);
                $hasAllConditions = is_array($conditions)
                    && collect($criteriaKeys)
                        ->every(fn (string $field): bool => filled($conditions[$field] ?? null));

                if (is_array($conditions)) {
                    foreach (array_diff(array_keys($conditions), $criteriaKeys) as $unsupportedCriterion) {
                        $validator->errors()->add('quality_conditions.'.$unsupportedCriterion, 'The selected quality-score criterion is not configured in the active scoring rule.');
                    }
                }

                if (! $hasAllScores && ! $hasAllConditions) {
                    $validator->errors()->add('quality_conditions', 'Select all quality conditions or enter component scores for every active scoring criterion.');
                }
            },
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function activeCriteria(?int $companyId = null): array
    {
        if ($companyId === null) {
            $lead = Lead::query()->whereKey($this->integer('lead_id'))->first();
            $companyId = $lead?->company_id;
        }

        $criteria = app(LeadQualityScoreService::class)->rulesForCompany($companyId)['criteria'] ?? [];

        return is_array($criteria) ? $criteria : [];
    }
}
