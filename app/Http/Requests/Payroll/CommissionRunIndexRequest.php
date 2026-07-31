<?php

namespace App\Http\Requests\Payroll;

use App\Models\CommissionRule;
use App\Models\CommissionRun;
use App\Services\Security\CompanyScopeService;
use App\Support\PaginationPolicy;
use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CommissionRunIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', CommissionRun::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', Rule::in(['generated', 'approved', 'rejected'])],
            'commission_rule_id' => ['nullable', 'integer', Rule::exists('commission_rules', 'id')],
            'period_year' => ['nullable', 'integer', 'min:2020', 'max:2100'],
            'period_month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'per_page' => app(PaginationPolicy::class)->largeRule(),
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            app(QueryFilterPolicy::class)->rejectUnexpected(
                $validator,
                $this->query(),
                ['status', 'commission_rule_id', 'period_year', 'period_month', 'per_page', 'page'],
            );

            if ($validator->errors()->isNotEmpty() || ! $this->filled('commission_rule_id')) {
                return;
            }

            $rule = CommissionRule::find($this->integer('commission_rule_id'));
            $user = $this->user();

            if ($rule && $user && ! app(CompanyScopeService::class)->allows($user, $rule->company_id)) {
                $validator->errors()->add('commission_rule_id', 'The selected commission rule is outside your company scope.');
            }
        });
    }
}
