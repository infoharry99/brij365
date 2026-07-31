<?php

namespace App\Http\Requests\Hr;

use App\Domain\Hr\Services\EmployeeHierarchyService;
use App\Models\Employee;
use App\Models\EmployeeMovement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ApproveEmployeeMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $movement = $this->route('employeeMovement');

        return $movement instanceof EmployeeMovement
            && $movement->employee
            && $this->user()?->can('update', $movement->employee) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [$this->validateEffectiveDate(...)];
    }

    protected function validateEffectiveDate(Validator $validator): void
    {
        $movement = $this->route('employeeMovement');

        if (! $movement instanceof EmployeeMovement) {
            return;
        }

        if ($movement->status !== 'pending') {
            $validator->errors()->add('status', 'Only pending employee movements can be approved.');
        }

        if ($movement->effective_on && $movement->effective_on->isFuture()) {
            $validator->errors()->add('effective_on', 'Future-dated movements cannot be approved before their effective date.');
        }

        $managerId = data_get($movement->new_values, 'manager_employee_id');
        $employee = $movement->employee;

        if ($managerId !== null
            && $employee instanceof Employee
            && app(EmployeeHierarchyService::class)->wouldCreateCycle($employee, (int) $managerId)) {
            $validator->errors()->add('new_values.manager_employee_id', 'The reporting relationship would create a management cycle.');
        }
    }
}
