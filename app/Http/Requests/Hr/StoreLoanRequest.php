<?php

namespace App\Http\Requests\Hr;

use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Services\Security\CompanyScopeService;
use App\Support\MoneyInputPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', EmployeeLoan::class) === true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')],
            'loan_type' => ['required', 'string', Rule::in(['salary_advance', 'emergency', 'welfare', 'other'])],
            'principal_amount' => ['required', 'numeric', 'min:1000', app(MoneyInputPolicy::class)->hrAmountMaxRule()],
            'installment_months' => ['required', 'integer', 'min:1', 'max:60'],
            'requested_on' => ['nullable', 'date', 'before_or_equal:today'],
            'purpose' => ['required', 'string', 'min:10', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $employee = Employee::find($this->integer('employee_id'));
            $actor = $this->user();
            if (! $employee || ! $actor) {
                return;
            }
            $selfServiceOnly = $actor->hasPermission('employee.self_service')
                && ! $actor->hasPermission('loans.manage');

            if ($selfServiceOnly && (int) $employee->user_id !== (int) $actor->id) {
                $validator->errors()->add('employee_id', 'Employees can request loans only for their own profile.');

                return;
            }
            if (! app(CompanyScopeService::class)->allows($actor, $employee->company_id)) {
                $validator->errors()->add('employee_id', 'The selected employee is not available in your company.');
            }
        }];
    }
}
