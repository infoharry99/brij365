<?php

namespace App\Http\Requests\Projects;

use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectTeamAssignment;
use App\Models\User;
use App\Services\Security\CompanyScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProjectTeamAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Project|null $project */
        $project = $this->route('project');

        return $project instanceof Project
            && $this->user()?->can('create', ProjectTeamAssignment::class) === true
            && app(CompanyScopeService::class)->allows($this->user(), $project->company_id);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('status', 'active')],
            'employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')->whereNull('deleted_at')],
            'role_label' => ['required', 'string', 'max:120'],
            'department' => ['nullable', 'string', 'max:120'],
            'access_level' => ['required', 'string', Rule::in(['read', 'contribute', 'manage', 'approve'])],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var Project|null $project */
                $project = $this->route('project');

                if (! $project instanceof Project) {
                    return;
                }

                $assignee = User::query()
                    ->with('employee')
                    ->whereKey($this->integer('user_id'))
                    ->first();

                if (! $assignee || (int) $assignee->company_id !== (int) $project->company_id) {
                    $validator->errors()->add('user_id', 'The selected user must be active and belong to the project company.');
                }

                if ($this->filled('employee_id')) {
                    $employee = Employee::query()
                        ->whereKey($this->integer('employee_id'))
                        ->first(['id', 'company_id', 'user_id', 'project_id']);

                    if (! $employee || (int) $employee->company_id !== (int) $project->company_id) {
                        $validator->errors()->add('employee_id', 'The selected employee must belong to the project company.');
                    }

                    if ($employee && $assignee && $employee->user_id && (int) $employee->user_id !== (int) $assignee->id) {
                        $validator->errors()->add('employee_id', 'The selected employee profile is linked to a different user.');
                    }
                }

                $activeExists = ProjectTeamAssignment::query()
                    ->where('project_id', $project->id)
                    ->where('user_id', $this->integer('user_id'))
                    ->where('status', 'active')
                    ->exists();

                if ($activeExists) {
                    $validator->errors()->add('user_id', 'This user is already active on the selected project team.');
                }
            },
        ];
    }
}
