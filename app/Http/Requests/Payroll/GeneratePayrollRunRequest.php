<?php

namespace App\Http\Requests\Payroll;

use App\Models\PayrollRun;
use App\Services\Payroll\PayrollRunControlPolicy;
use App\Services\Security\CompanyScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class GeneratePayrollRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', PayrollRun::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $policy = app(PayrollRunControlPolicy::class);

        return [
            'period_year' => ['required', 'integer', 'min:'.$policy->minPeriodYear(), 'max:'.$policy->maxPeriodYear()],
            'period_month' => ['required', 'integer', 'min:1', 'max:12'],
            'working_days' => ['required', 'integer', 'min:'.$policy->minWorkingDays(), 'max:'.$policy->maxWorkingDays()],
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

            if ($companyId === null || $companyId === 0) {
                $validator->errors()->add('period_month', 'Payroll generation requires a valid company scope.');

                return;
            }

            $exists = PayrollRun::query()
                ->where('company_id', $companyId)
                ->where('period_year', $this->integer('period_year'))
                ->where('period_month', $this->integer('period_month'))
                ->exists();

            if ($exists) {
                $validator->errors()->add('period_month', 'A payroll run already exists for this company and period.');
            }
        });
    }
}
