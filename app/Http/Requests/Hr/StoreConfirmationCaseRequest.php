<?php

namespace App\Http\Requests\Hr;

use App\Models\Employee;
use App\Models\EmployeeConfirmationCase;
use App\Services\Security\CompanyScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreConfirmationCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', EmployeeConfirmationCase::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')],
            'manager_employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')],
            'probation_starts_on' => ['nullable', 'date', 'before_or_equal:probation_ends_on'],
            'probation_ends_on' => ['required', 'date'],
            'review_due_on' => ['nullable', 'date', 'after_or_equal:probation_starts_on'],
        ];
    }

    public function after(): array
    {
        return [$this->validateCompanyAndDuplicates(...)];
    }

    protected function validateCompanyAndDuplicates(Validator $validator): void
    {
        $actor = $this->user();
        $employee = Employee::find($this->integer('employee_id'));

        if (! $employee) {
            return;
        }

        if (! $actor || ! app(CompanyScopeService::class)->allows($actor, $employee->company_id)) {
            $validator->errors()->add('employee_id', 'The employee does not belong to your company.');
        }

        if ($this->filled('manager_employee_id')) {
            $manager = Employee::find($this->integer('manager_employee_id'));

            if ($manager && (int) $manager->company_id !== (int) $employee->company_id) {
                $validator->errors()->add('manager_employee_id', 'The manager must belong to the same company as the employee.');
            }

            if ($manager && (int) $manager->id === (int) $employee->id) {
                $validator->errors()->add('manager_employee_id', 'An employee cannot be their own confirmation manager.');
            }
        }

        if (EmployeeConfirmationCase::query()
            ->where('employee_id', $employee->id)
            ->whereDate('probation_ends_on', $this->date('probation_ends_on')?->toDateString())
            ->exists()) {
            $validator->errors()->add('employee_id', 'A confirmation case already exists for this employee and probation end date.');
        }
    }
}
