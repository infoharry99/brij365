<?php

namespace App\Http\Requests\Payroll;

use App\Models\CommissionRule;
use App\Models\Project;
use App\Services\Security\CompanyScopeService;
use App\Support\MoneyInputPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCommissionRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CommissionRule::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rule_code' => ['required', 'string', 'max:40', 'regex:/^[A-Z0-9\\-]+$/'],
            'name' => ['required', 'string', 'max:160'],
            'rule_type' => ['required', 'string', Rule::in(['fixed', 'percentage', 'slab', 'target'])],
            'basis' => ['required', 'string', Rule::in(['booking_value', 'collection_received'])],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
            'rate_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'fixed_amount' => ['nullable', 'numeric', 'min:0', app(MoneyInputPolicy::class)->commissionFixedAmountMaxRule()],
            'target_amount' => ['nullable', 'numeric', 'min:0', app(MoneyInputPolicy::class)->commissionTargetAmountMaxRule()],
            'slab_rules' => ['nullable', 'array'],
            'slab_rules.*.from' => ['required_with:slab_rules', 'numeric', 'min:0'],
            'slab_rules.*.to' => ['nullable', 'numeric', 'gte:slab_rules.*.from'],
            'slab_rules.*.rate_percent' => ['required_with:slab_rules', 'numeric', 'min:0', 'max:100'],
            'eligibility_rules' => ['nullable', 'array'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'status' => ['nullable', 'string', Rule::in(['draft', 'active', 'inactive'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $user = $this->user();
            $companyId = $user ? app(CompanyScopeService::class)->companyIdFor($user) : 0;

            if ($this->integer('project_id') > 0) {
                $project = Project::query()->whereKey($this->integer('project_id'))->first();

                if (! $project || ! $user || ! app(CompanyScopeService::class)->allows($user, $project->company_id)) {
                    $validator->errors()->add('project_id', 'The project must belong to your company.');

                    return;
                }

                $companyId = $project->company_id;
            }

            if ($companyId === 0 || $companyId === null) {
                $validator->errors()->add('rule_code', 'A company assignment is required before creating commission rules.');

                return;
            }

            $exists = CommissionRule::query()
                ->where('company_id', $companyId)
                ->where('rule_code', strtoupper((string) $this->input('rule_code')))
                ->exists();

            if ($exists) {
                $validator->errors()->add('rule_code', 'A commission rule with this code already exists for your company.');
            }

            $type = $this->string('rule_type')->toString();
            if ($type === 'percentage' && (float) $this->input('rate_percent', 0) <= 0) {
                $validator->errors()->add('rate_percent', 'Percentage commission rules require a positive rate.');
            }
            if ($type === 'fixed' && (float) $this->input('fixed_amount', 0) <= 0) {
                $validator->errors()->add('fixed_amount', 'Fixed commission rules require a positive amount.');
            }
            if ($type === 'slab' && count((array) $this->input('slab_rules', [])) === 0) {
                $validator->errors()->add('slab_rules', 'Slab commission rules require at least one slab.');
            }
            if ($type === 'target' && ((float) $this->input('target_amount', 0) <= 0 || (float) $this->input('rate_percent', 0) <= 0)) {
                $validator->errors()->add('target_amount', 'Target commission rules require target amount and positive rate.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('rule_code')) {
            $this->merge(['rule_code' => strtoupper((string) $this->input('rule_code'))]);
        }
    }
}