<?php

namespace App\Http\Requests\Hr;

use App\Domain\Hr\Services\ActiveInternalUserEligibility;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Project;
use App\Models\User;
use App\Services\Security\CompanyScopeService;
use App\Support\MoneyInputPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Employee::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', Rule::exists('companies', 'id')],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'manager_employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')],
            'employee_code' => ['required', 'string', 'max:32', 'regex:/^[A-Z0-9-]+$/', Rule::unique('employees', 'employee_code')],
            'name' => ['required', 'string', 'max:255'],
            'designation' => ['required', 'string', 'max:120'],
            'department' => ['required', 'string', 'max:120'],
            'grade' => ['nullable', 'string', 'max:16'],
            'employment_type' => ['required', 'string', Rule::in(['full_time', 'part_time', 'contract', 'intern', 'consultant'])],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive', 'on_notice', 'separated'])],
            'joined_on' => ['nullable', 'date', 'before_or_equal:today'],
            'statutory_state' => ['nullable', 'string', 'max:8'],
            'monthly_ctc' => ['nullable', 'numeric', 'min:0', app(MoneyInputPolicy::class)->hrAmountMaxRule()],
            'sensitive_profile' => ['nullable', 'array'],
            'sensitive_profile.*' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [$this->validateCompanyScope(...)];
    }

    protected function validateCompanyScope(Validator $validator): void
    {
        $actor = $this->user();
        $companyId = $this->integer('company_id');
        $companyScope = app(CompanyScopeService::class);

        if (! $actor || ! $companyScope->allows($actor, $companyId)) {
            $validator->errors()->add('company_id', 'HR users can create employees only in their own company.');
        }

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
            $manager = Employee::find($this->integer('manager_employee_id'));

            if ($manager && (int) $manager->company_id !== $companyId) {
                $validator->errors()->add('manager_employee_id', 'The reporting manager must belong to the same company.');
            }
        }

        if ($this->filled('user_id')) {
            $user = User::find($this->integer('user_id'));

            if ($user && $actor && ! app(ActiveInternalUserEligibility::class)->isEligible($actor, $user, $companyId)) {
                $validator->errors()->add('user_id', 'The linked user must be an active internal user in the employee company.');
            }

            if (Employee::query()->where('user_id', $this->integer('user_id'))->exists()) {
                $validator->errors()->add('user_id', 'The selected user is already linked to an employee profile.');
            }
        }
    }
}
