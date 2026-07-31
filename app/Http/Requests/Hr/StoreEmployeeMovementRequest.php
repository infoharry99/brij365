<?php

namespace App\Http\Requests\Hr;

use App\Domain\Hr\Services\EmployeeHierarchyService;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Project;
use App\Support\MoneyInputPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreEmployeeMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $employee = $this->route('employee');

        return $employee instanceof Employee
            && $this->user()?->can('update', $employee) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'movement_type' => ['required', 'string', Rule::in(['transfer', 'promotion', 'department_change', 'reporting_change', 'salary_change', 'status_change', 'grade_change'])],
            'effective_on' => ['required', 'date'],
            'status' => ['sometimes', 'string', Rule::in(['pending', 'approved'])],
            'new_values' => ['required', 'array', 'min:1'],
            'new_values.branch_id' => ['sometimes', 'nullable', 'integer', Rule::exists('branches', 'id')],
            'new_values.project_id' => ['sometimes', 'nullable', 'integer', Rule::exists('projects', 'id')],
            'new_values.manager_employee_id' => ['sometimes', 'nullable', 'integer', Rule::exists('employees', 'id')],
            'new_values.designation' => ['sometimes', 'string', 'max:120'],
            'new_values.department' => ['sometimes', 'string', 'max:120'],
            'new_values.grade' => ['sometimes', 'nullable', 'string', 'max:16'],
            'new_values.status' => ['sometimes', 'string', Rule::in(['active', 'inactive', 'on_notice', 'separated'])],
            'new_values.monthly_ctc' => ['sometimes', 'nullable', 'numeric', 'min:0', app(MoneyInputPolicy::class)->hrAmountMaxRule()],
            'reason' => ['nullable', 'string', 'max:2000'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [$this->validateMovementPayload(...)];
    }

    protected function validateMovementPayload(Validator $validator): void
    {
        $employee = $this->route('employee');

        if (! $employee instanceof Employee) {
            return;
        }

        $movementType = (string) $this->input('movement_type');
        $newValues = (array) $this->input('new_values', []);
        $allowedKeys = $this->allowedKeys($movementType);
        $submittedKeys = array_keys($newValues);
        $unsupportedKeys = array_diff($submittedKeys, $allowedKeys);

        foreach ($unsupportedKeys as $key) {
            $validator->errors()->add('new_values.'.$key, 'This field is not allowed for the selected movement type.');
        }

        if (array_intersect($submittedKeys, $allowedKeys) === []) {
            $validator->errors()->add('new_values', 'At least one valid field is required for the selected movement type.');
        }

        $companyId = (int) $employee->company_id;

        if (array_key_exists('branch_id', $newValues) && $newValues['branch_id'] !== null) {
            $branch = Branch::find($newValues['branch_id']);

            if ($branch && (int) $branch->company_id !== $companyId) {
                $validator->errors()->add('new_values.branch_id', 'The selected branch does not belong to the employee company.');
            }
        }

        if (array_key_exists('project_id', $newValues) && $newValues['project_id'] !== null) {
            $project = Project::find($newValues['project_id']);

            if ($project && (int) $project->company_id !== $companyId) {
                $validator->errors()->add('new_values.project_id', 'The selected project does not belong to the employee company.');
            }
        }

        if (array_key_exists('manager_employee_id', $newValues) && $newValues['manager_employee_id'] !== null) {
            $managerId = (int) $newValues['manager_employee_id'];
            $manager = Employee::find($managerId);

            if ($managerId === (int) $employee->id) {
                $validator->errors()->add('new_values.manager_employee_id', 'An employee cannot report to themselves.');
            }

            if ($manager && (int) $manager->company_id !== $companyId) {
                $validator->errors()->add('new_values.manager_employee_id', 'The reporting manager must belong to the same company.');
            }

            if ($manager && app(EmployeeHierarchyService::class)->wouldCreateCycle($employee, $managerId)) {
                $validator->errors()->add('new_values.manager_employee_id', 'The reporting relationship would create a management cycle.');
            }
        }

        if ($this->input('status') === 'approved' && strtotime((string) $this->input('effective_on')) > strtotime(today()->toDateString())) {
            $validator->errors()->add('effective_on', 'Future-dated movements must remain pending until their effective date.');
        }
    }

    /**
     * @return array<int, string>
     */
    private function allowedKeys(string $movementType): array
    {
        return match ($movementType) {
            'transfer' => ['branch_id', 'project_id', 'department'],
            'promotion' => ['designation', 'grade', 'monthly_ctc'],
            'department_change' => ['department'],
            'reporting_change' => ['manager_employee_id'],
            'salary_change' => ['monthly_ctc'],
            'status_change' => ['status'],
            'grade_change' => ['grade'],
            default => [],
        };
    }
}
