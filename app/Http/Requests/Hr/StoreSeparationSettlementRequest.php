<?php

namespace App\Http\Requests\Hr;

use App\Models\Employee;
use App\Models\EmployeeSeparationSettlement;
use App\Services\Security\CompanyScopeService;
use App\Support\MoneyInputPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSeparationSettlementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', EmployeeSeparationSettlement::class) === true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')],
            'separation_type' => ['required', 'string', Rule::in(['resignation', 'termination', 'retirement', 'contract_end'])],
            'resignation_date' => ['nullable', 'date', 'before_or_equal:last_working_date'],
            'last_working_date' => ['required', 'date'],
            'settlement_date' => ['nullable', 'date', 'after_or_equal:last_working_date'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'bonus_amount' => ['nullable', 'numeric', 'min:0', app(MoneyInputPolicy::class)->hrAmountMaxRule()],
            'gratuity_amount' => ['nullable', 'numeric', 'min:0', app(MoneyInputPolicy::class)->hrAmountMaxRule()],
            'notice_recovery_amount' => ['nullable', 'numeric', 'min:0', app(MoneyInputPolicy::class)->hrAmountMaxRule()],
            'tax_recovery_amount' => ['nullable', 'numeric', 'min:0', app(MoneyInputPolicy::class)->hrAmountMaxRule()],
        ];
    }

    public function after(): array
    {
        return [$this->validateCompanyAndDuplicate(...)];
    }

    protected function validateCompanyAndDuplicate(Validator $validator): void
    {
        $employee = Employee::find($this->integer('employee_id'));
        $actor = $this->user();

        if (! $employee) {
            return;
        }

        if (! $actor || ! app(CompanyScopeService::class)->allows($actor, $employee->company_id)) {
            $validator->errors()->add('employee_id', 'The employee does not belong to your company.');
        }

        if (EmployeeSeparationSettlement::query()
            ->where('employee_id', $employee->id)
            ->whereIn('status', ['initiated', 'hr_approved', 'finance_approved'])
            ->exists()) {
            $validator->errors()->add('employee_id', 'An active separation settlement already exists for this employee.');
        }
    }
}