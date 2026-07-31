<?php

namespace App\Http\Requests\Collaboration;

use App\Domain\Collaboration\TaskLifecycle;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkTask;
use App\Services\Security\CompanyScopeService;
use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class WorkTaskIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', WorkTask::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', Rule::in(array_keys(TaskLifecycle::statuses()))],
            'priority' => ['nullable', 'string', Rule::in(['low', 'medium', 'high', 'critical'])],
            'assigned_to_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'module_context' => ['nullable', 'string', 'max:64'],
            'due_from' => ['nullable', 'date'],
            'due_to' => ['nullable', 'date', 'after_or_equal:due_from'],
            'q' => ['nullable', 'string', 'max:120'],
            'format' => ['nullable', 'string', Rule::in(['csv', 'pdf'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'scope' => ['nullable', 'string', Rule::in(['dashboard', 'mine', 'assigned-to-me', 'assigned-by-me', 'team', 'department', 'all', 'due-today', 'due-week', 'overdue', 'pending', 'completed', 'archived', 'activity', 'reports', 'analytics', 'templates', 'settings'])],
            'view' => ['nullable', 'string', Rule::in(['board', 'list', 'calendar'])],
            'task_id' => ['nullable', 'integer', 'min:1'],
            'sort' => ['nullable', 'string', Rule::in(['task_number', 'title', 'priority', 'status', 'due_at', 'created_at'])],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'create' => ['nullable', 'boolean'],
            'template' => ['nullable', 'string', 'max:120'],
            'focus_date' => ['nullable', 'date_format:Y-m-d'],
            'settings_tab' => ['nullable', 'string', Rule::in(['statuses', 'workflow', 'permissions', 'notifications'])],
            'activity_filter' => ['nullable', 'string', Rule::in(['all', 'comments', 'status', 'transfers', 'approvals', 'attachments', 'time'])],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                app(QueryFilterPolicy::class)->rejectUnexpected(
                    $validator,
                    $this->query(),
                    ['status', 'priority', 'assigned_to_user_id', 'project_id', 'module_context', 'due_from', 'due_to', 'q', 'format', 'page', 'scope', 'view', 'task_id', 'sort', 'direction', 'create', 'template', 'focus_date', 'settings_tab', 'activity_filter'],
                );

                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $user = $this->user();

                if (! $user) {
                    return;
                }

                if ($this->filled('assigned_to_user_id')) {
                    $assignee = User::query()
                        ->whereKey($this->integer('assigned_to_user_id'))
                        ->first();

                    if (! $assignee || ! app(CompanyScopeService::class)->allows($user, $assignee->company_id)) {
                        $validator->errors()->add('assigned_to_user_id', 'The selected assignee is not available for your company.');
                    }

                    if (! $user->hasPermission('collaboration.view') && ! $user->hasPermission('collaboration.manage') && (int) $assignee?->id !== (int) $user->id) {
                        $validator->errors()->add('assigned_to_user_id', 'Self-service users can filter only their own assigned tasks.');
                    }
                }

                if ($this->filled('project_id')) {
                    $projectCompanyId = Project::query()
                        ->whereKey($this->integer('project_id'))
                        ->value('company_id');

                    if (! app(CompanyScopeService::class)->allows($user, $projectCompanyId)) {
                        $validator->errors()->add('project_id', 'The selected project is not available for your company.');
                    }
                }
            },
        ];
    }
}
