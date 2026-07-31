<?php

namespace App\Http\Requests\Hr;

use App\Domain\Hr\Services\ActiveInternalUserEligibility;
use App\Domain\Hr\Services\EmployeeHierarchyService;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Project;
use App\Models\User;
use App\Support\MoneyInputPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateEmployeeRequest extends FormRequest
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
        $employeeId = $this->route('employee')?->id;

        return [
            'lock_version' => ['required', 'integer', 'min:1'],
            'branch_id' => ['sometimes', 'nullable', 'integer', Rule::exists('branches', 'id')],
            'project_id' => ['sometimes', 'nullable', 'integer', Rule::exists('projects', 'id')],
            'user_id' => ['sometimes', 'nullable', 'integer', Rule::exists('users', 'id')],
            'manager_employee_id' => ['sometimes', 'nullable', 'integer', Rule::exists('employees', 'id')],
            'employee_code' => ['sometimes', 'required', 'string', 'max:32', 'regex:/^[A-Z0-9-]+$/', Rule::unique('employees', 'employee_code')->ignore($employeeId)],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'designation' => ['sometimes', 'required', 'string', 'max:120'],
            'department' => ['sometimes', 'required', 'string', 'max:120'],
            'grade' => ['sometimes', 'nullable', 'string', 'max:16'],
            'employment_type' => ['sometimes', 'required', 'string', Rule::in(['full_time', 'part_time', 'contract', 'intern', 'consultant'])],
            'status' => ['sometimes', 'required', 'string', Rule::in(['active', 'inactive', 'on_notice', 'separated'])],
            'joined_on' => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
            'statutory_state' => ['sometimes', 'nullable', 'string', 'max:8'],
            'monthly_ctc' => ['sometimes', 'nullable', 'numeric', 'min:0', app(MoneyInputPolicy::class)->hrAmountMaxRule()],
            'sensitive_profile' => ['sometimes', 'nullable', 'array'],
            'sensitive_profile.*' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [$this->validateCompanyScope(...)];
    }

    protected function validateCompanyScope(Validator $validator): void
    {
        $employee = $this->route('employee');

        if (! $employee instanceof Employee) {
            return;
        }

        $companyId = (int) $employee->company_id;

        if ($this->filled('branch_id')) {
            $branch = Branch::find($this->integer('branch_id'));

            if ($branch && (int) $branch->company_id !== $companyId) {
                $validator->errors()->add('branch_id', 'The selected branch does not belong to the employee company.');
            }
        }

        if ($this->filled('project_id')) {
            $project = Project::find($this->integer('project_id'));

            if ($project && (int) $project->company_id !== $companyId) {
                $validator->errors()->add('project_id', 'The selected project does not belong to the employee company.');
            }
        }

        if ($this->filled('manager_employee_id')) {
            $managerId = $this->integer('manager_employee_id');
            $manager = Employee::find($managerId);

            if ($managerId === (int) $employee->id) {
                $validator->errors()->add('manager_employee_id', 'An employee cannot report to themselves.');
            }

            if ($manager && (int) $manager->company_id !== $companyId) {
                $validator->errors()->add('manager_employee_id', 'The reporting manager must belong to the same company.');
            }

            if ($manager && app(EmployeeHierarchyService::class)->wouldCreateCycle($employee, $managerId)) {
                $validator->errors()->add('manager_employee_id', 'The reporting relationship would create a management cycle.');
            }
        }

        if ($this->filled('user_id')) {
            $user = User::find($this->integer('user_id'));
            $actor = $this->user();

            if ($user && $actor && ! app(ActiveInternalUserEligibility::class)->isEligible($actor, $user, $companyId)) {
                $validator->errors()->add('user_id', 'The linked user must be an active internal user in the employee company.');
            }

            if (Employee::query()
                ->where('user_id', $this->integer('user_id'))
                ->whereKeyNot($employee->id)
                ->exists()) {
                $validator->errors()->add('user_id', 'The selected user is already linked to another employee profile.');
            }
        }
    }
}
