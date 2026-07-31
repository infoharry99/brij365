<?php

namespace App\Http\Requests\Collaboration;

use App\Models\Project;
use App\Models\User;
use App\Models\WorkTask;
use App\Services\Security\CompanyScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreWorkTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', WorkTask::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'client_token' => ['nullable', 'uuid'],
            'template_id' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:5000'],
            'assigned_to_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'assigned_to_user_ids' => ['nullable', 'array', 'max:20'],
            'assigned_to_user_ids.*' => ['integer', 'exists:users,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'priority' => ['required', 'string', Rule::in(['low', 'medium', 'high', 'critical'])],
            'due_at' => ['nullable', 'date'],
            'module_context' => ['nullable', 'string', 'max:64'],
            'related_type' => ['nullable', 'string', 'max:255'],
            'related_id' => ['nullable', 'integer', 'min:1'],
            'checklist' => ['nullable', 'array', 'max:50'],
            'checklist.*.label' => ['required_with:checklist', 'string', 'max:255'],
            'checklist.*.done' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array'],
            // Integration modules already attach their own scalar context keys.
            // Retain those keys while validating task lifecycle fields below.
            'metadata.*' => ['nullable'],
            'metadata.estimated_hours' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'metadata.planned_start_at' => ['nullable', 'date'],
            'metadata.department' => ['nullable', 'string', 'max:120'],
            'metadata.recurrence_frequency' => ['nullable', Rule::in(['none', 'daily', 'weekly', 'monthly'])],
            'metadata.recurrence_interval' => ['nullable', 'integer', 'min:1', 'max:12'],
            'metadata.recurrence_until' => ['nullable', 'date'],
            'metadata.reminder_minutes_before' => ['nullable', 'array', 'max:5'],
            'metadata.reminder_minutes_before.*' => ['integer', Rule::in([0, 15, 30, 60, 240, 1440, 2880, 10080])],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $actor = $this->user();

                if (! $actor) {
                    return;
                }

                $companyScope = app(CompanyScopeService::class);
                $assigneeId = $this->integer('assigned_to_user_id') ?: $actor->id;
                $assignee = User::query()->whereKey($assigneeId)->first();
                $assigneeCompanyId = $assignee?->company_id
                    ?? ((int) $assigneeId === (int) $actor->id ? $companyScope->companyIdFor($actor) : null);
                $explicitCompanyId = $this->filled('company_id') ? $this->integer('company_id') : null;

                if ($explicitCompanyId !== null && ! $companyScope->allows($actor, $explicitCompanyId)) {
                    $validator->errors()->add('company_id', 'The selected company is outside your company scope.');
                }

                if ($assignee && (int) $assigneeId !== (int) $actor->id && ! $companyScope->allows($actor, $assigneeCompanyId)) {
                    $validator->errors()->add('assigned_to_user_id', 'The assignee must belong to your company.');
                }

                if ($explicitCompanyId !== null && $assigneeCompanyId !== null && (int) $assigneeCompanyId !== $explicitCompanyId) {
                    $validator->errors()->add('assigned_to_user_id', 'The assignee must belong to the selected company.');
                }

                if (! $actor->hasPermission('collaboration.manage') && $assigneeId !== $actor->id) {
                    $validator->errors()->add('assigned_to_user_id', 'Self-service users can create tasks only for themselves.');
                }

                $project = null;
                if ($this->filled('project_id')) {
                    $project = Project::query()->whereKey($this->integer('project_id'))->first();

                    if ($project && ! $companyScope->allows($actor, $project->company_id)) {
                        $validator->errors()->add('project_id', 'The selected project is not available for your company.');
                    }

                    if ($project && $explicitCompanyId !== null && (int) $project->company_id !== $explicitCompanyId) {
                        $validator->errors()->add('company_id', 'The selected company must match the selected project company.');
                    }
                }

                if (
                    $companyScope->hasUnrestrictedCompanyScope($actor)
                    && $explicitCompanyId === null
                    && ! $project
                    && $assignee?->company_id === null
                ) {
                    $validator->errors()->add('company_id', 'A company is required when creating an unassigned company-level task as a global user.');
                }
            },
        ];
    }
}
