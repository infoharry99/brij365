<?php

namespace App\Http\Requests\Payroll;

use App\Models\CommissionRule;
use App\Models\CommissionRun;
use App\Services\Security\CompanyScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCommissionRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CommissionRun::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'commission_rule_id' => ['required', 'integer', Rule::exists('commission_rules', 'id')],
            'period_year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'period_month' => ['required', 'integer', 'min:1', 'max:12'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $rule = CommissionRule::query()->whereKey($this->integer('commission_rule_id'))->first();
            $user = $this->user();

            if (! $rule || ! $user || ! app(CompanyScopeService::class)->allows($user, $rule->company_id)) {
                $validator->errors()->add('commission_rule_id', 'The selected commission rule is outside your company scope.');

                return;
            }

            $exists = CommissionRun::query()
                ->where('company_id', $rule->company_id)
                ->where('commission_rule_id', $rule->id)
                ->where('period_year', $this->integer('period_year'))
                ->where('period_month', $this->integer('period_month'))
                ->exists();

            if ($exists) {
                $validator->errors()->add('period_month', 'A commission run already exists for this rule and period.');
            }
        });
    }
}
