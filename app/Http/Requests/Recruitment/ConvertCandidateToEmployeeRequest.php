<?php

namespace App\Http\Requests\Recruitment;

use App\Domain\Hr\Services\ActiveInternalUserEligibility;
use App\Models\Branch;
use App\Models\Candidate;
use App\Models\Employee;
use App\Models\Project;
use App\Models\User;
use App\Services\Security\CompanyScopeService;
use App\Support\MoneyInputPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ConvertCandidateToEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $candidate = $this->route('candidate');

        return $candidate instanceof Candidate
            && ($this->user()?->can('convert', $candidate) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'employee_code' => ['nullable', 'string', 'max:32', 'regex:/^[A-Z0-9-]+$/', Rule::unique('employees', 'employee_code')],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'manager_employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')],
            'designation' => ['nullable', 'string', 'max:120'],
            'department' => ['nullable', 'string', 'max:120'],
            'grade' => ['nullable', 'string', 'max:16'],
            'employment_type' => ['nullable', 'string', Rule::in(['full_time', 'part_time', 'contract', 'intern', 'consultant'])],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
            'joined_on' => ['nullable', 'date'],
            'statutory_state' => ['nullable', 'string', 'max:8'],
            'monthly_ctc' => ['nullable', 'numeric', 'min:0', app(MoneyInputPolicy::class)->hrAmountMaxRule()],
            'sensitive_profile' => ['nullable', 'array'],
            'sensitive_profile.*' => ['nullable', 'string', 'max:255'],
            'acceptance_note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [
            $this->validateConversionPreconditions(...),
            $this->validateEmployeeCompanyScope(...),
        ];
    }

    protected function validateConversionPreconditions(Validator $validator): void
    {
        $candidate = $this->route('candidate');
        $actor = $this->user();

        if (! $candidate instanceof Candidate || ! $actor) {
            $validator->errors()->add('candidate', 'The selected candidate is invalid.');

            return;
        }

        if (! app(CompanyScopeService::class)->allows($actor, $candidate->company_id)) {
            $validator->errors()->add('candidate', 'The selected candidate is outside your company scope.');

            return;
        }

        if ($candidate->status !== 'active') {
            $validator->errors()->add('candidate', 'Only active candidates can be converted to employees.');
        }

        if ($candidate->employee_id !== null) {
            $validator->errors()->add('candidate', 'This candidate is already linked to an employee record.');
        }

        $offer = $candidate->offer()->first();

        if (! $offer || $offer->status !== 'released') {
            $validator->errors()->add('candidate', 'Candidate conversion requires a released offer.');
        }
    }

    protected function validateEmployeeCompanyScope(Validator $validator): void
    {
        $candidate = $this->route('candidate');

        if (! $candidate instanceof Candidate) {
            return;
        }

        $companyId = (int) $candidate->company_id;

        if ($this->filled('branch_id')) {
            $branch = Branch::find($this->integer('branch_id'));

            if ($branch && (int) $branch->company_id !== $companyId) {
                $validator->errors()->add('branch_id', 'The selected branch does not belong to the candidate company.');
            }
        }

        if ($this->filled('project_id')) {
            $project = Project::find($this->integer('project_id'));

            if ($project && (int) $project->company_id !== $companyId) {
                $validator->errors()->add('project_id', 'The selected project does not belong to the candidate company.');
            }
        }

        if ($this->filled('manager_employee_id')) {
            $manager = Employee::find($this->integer('manager_employee_id'));

            if ($manager && (int) $manager->company_id !== $companyId) {
                $validator->errors()->add('manager_employee_id', 'The reporting manager must belong to the candidate company.');
            }
        }

        if ($this->filled('user_id')) {
            $user = User::find($this->integer('user_id'));

            if ($user && $this->user() && ! app(ActiveInternalUserEligibility::class)->isEligible($this->user(), $user, $companyId)) {
                $validator->errors()->add('user_id', 'The linked user must be an active internal user in the candidate company.');
            }

            if (Employee::query()->where('user_id', $this->integer('user_id'))->exists()) {
                $validator->errors()->add('user_id', 'The selected user is already linked to an employee profile.');
            }
        }
    }
}
