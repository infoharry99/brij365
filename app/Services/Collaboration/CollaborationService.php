<?php

namespace App\Services\Collaboration;

use App\Domain\Collaboration\TaskLifecycle;
use App\Domain\Collaboration\Services\TaskCompletionApprovalService;
use App\Domain\Collaboration\Services\TaskMutationGuard;
use App\Domain\Collaboration\Services\TaskPeopleCandidates;
use App\Domain\Collaboration\Services\TaskRecurrenceService;
use App\Domain\Collaboration\Services\TaskSettingsResolver;
use App\Domain\Collaboration\Services\TaskTransitionService;
use App\Models\CalendarEvent;
use App\Models\Booking;
use App\Models\CollaborationMessage;
use App\Models\InternalMailboxDispatch;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Project;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\WorkTask;
use App\Models\WorkTaskComment;
use App\Models\WorkTaskSubtask;
use App\Models\WorkTaskTimeLog;
use App\Models\WorkTaskTransferRequest;
use App\Models\WorkTaskCompletionApproval;
use App\Domain\Collaboration\Services\CalendarLifecycleManager;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\NotificationCenterService;
use App\Services\Security\CompanyScopeService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CollaborationService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly NotificationCenterService $notifications,
        private readonly CompanyScopeService $companyScope,
        private readonly TaskMutationGuard $taskMutations,
        private readonly TaskPeopleCandidates $taskPeople,
        private readonly TaskTransitionService $taskTransitions,
        private readonly TaskSettingsResolver $taskSettings,
        private readonly TaskCompletionApprovalService $completionApprovals,
        private readonly TaskRecurrenceService $taskRecurrence,
        private readonly CalendarLifecycleManager $calendarLifecycle,
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     * @return Builder<WorkTask>
     */
    public function taskIndexQuery(User $user, array $filters = []): Builder
    {
        $tasksQuery = WorkTask::query()
            ->with($this->taskRelations());

        $this->companyScope->apply($tasksQuery, $user);

        $isDirector = $user->isDirector();

        return $tasksQuery
            ->when(! $isDirector, function (Builder $query) use ($user): void {
                $query->where(function (Builder $query) use ($user): void {
                    $query->where('created_by_user_id', $user->id)
                        ->orWhere('assigned_to_user_id', $user->id)
                        ->orWhereJsonContains('metadata->watcher_user_ids', $user->id)
                        ->orWhereJsonContains('metadata->watcher_user_ids', (string) $user->id);
                });
            })
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(isset($filters['priority']), fn (Builder $query) => $query->where('priority', $filters['priority']))
            ->when(isset($filters['assigned_to_user_id']), fn (Builder $query) => $query->where('assigned_to_user_id', $filters['assigned_to_user_id']))
            ->when(isset($filters['project_id']), fn (Builder $query) => $query->where('project_id', $filters['project_id']))
            ->when(isset($filters['module_context']), fn (Builder $query) => $query->where('module_context', $filters['module_context']))
            ->when(isset($filters['due_from']), fn (Builder $query) => $query->whereDate('due_at', '>=', $filters['due_from']))
            ->when(isset($filters['due_to']), fn (Builder $query) => $query->whereDate('due_at', '<=', $filters['due_to']))
            ->when(isset($filters['q']), function (Builder $query) use ($filters): void {
                $query->where(function (Builder $query) use ($filters): void {
                    $query->where('title', 'like', '%'.$filters['q'].'%')
                        ->orWhere('task_number', 'like', '%'.$filters['q'].'%');
                });
            })
            ->orderByRaw("case when status = 'open' then 0 when status = 'in_progress' then 1 when status = 'blocked' then 2 when status = 'completed' then 3 else 4 end")
            ->orderByRaw('due_at is null')
            ->orderBy('due_at');
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function taskExportRows(User $user, array $filters, int $limit): array
    {
        return $this->taskIndexQuery($user, $filters)
            ->limit($limit)
            ->get()
            ->map(fn (WorkTask $task): array => [
                'task_number' => $task->task_number,
                'title' => $task->title,
                'status' => $task->status,
                'priority' => $task->priority,
                'module_context' => $task->module_context,
                'company' => $task->company?->name,
                'project' => $task->project?->name,
                'created_by' => $task->createdBy?->name,
                'assigned_to' => $task->assignedTo?->name,
                'due_at' => $task->due_at?->toDateString(),
                'started_at' => $task->started_at?->toDateString(),
                'completed_at' => $task->completed_at?->toDateString(),
                'checklist_total' => count($task->checklist ?? []),
                'checklist_done' => collect($task->checklist ?? [])->where('done', true)->count(),
                'subtasks_total' => $task->subtasks->count(),
                'subtasks_done' => $task->subtasks->where('status', 'completed')->count(),
                'comments_total' => $task->comments->count(),
                'time_logged_hours' => round($task->timeLogs->sum('minutes') / 60, 2),
                'created_at' => $task->created_at?->toDateTimeString(),
                'updated_at' => $task->updated_at?->toDateTimeString(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param array<string, mixed> $filters
     * @return Builder<CollaborationMessage>
     */
    public function messageIndexQuery(User $user, array $filters = []): Builder
    {
        $folder = $filters['folder'] ?? 'inbox';
        $messagesQuery = CollaborationMessage::query()
            ->with($this->messageRelations());

        $this->companyScope->apply($messagesQuery, $user);

        return $messagesQuery
            ->where(function (Builder $query) use ($user): void {
                $query->where('recipient_user_id', $user->id)
                    ->orWhere('sender_user_id', $user->id);
            })
            ->where(function (Builder $query) use ($user): void {
                $query->whereNotIn('status', ['scheduled', 'cancelled'])
                    ->orWhere('sender_user_id', $user->id);
            })
            ->when($folder === 'inbox', fn (Builder $query) => $query->where('recipient_user_id', $user->id)->whereNotIn('status', ['scheduled', 'cancelled']))
            ->when($folder === 'sent', fn (Builder $query) => $query->where('sender_user_id', $user->id))
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(! isset($filters['status']) && $folder === 'inbox', fn (Builder $query) => $query->where('status', '!=', 'archived'))
            ->when(isset($filters['priority']), fn (Builder $query) => $query->where('priority', $filters['priority']))
            ->when(isset($filters['project_id']), fn (Builder $query) => $query->where('project_id', $filters['project_id']))
            ->when(isset($filters['message_id']), fn (Builder $query) => $query->whereKey($filters['message_id']))
            ->when(isset($filters['thread_key']), fn (Builder $query) => $query->where('thread_key', $filters['thread_key']))
            ->when(isset($filters['q']), function (Builder $query) use ($filters): void {
                $query->where(function (Builder $query) use ($filters): void {
                    $query->where('subject', 'like', '%'.$filters['q'].'%')
                        ->orWhere('message_number', 'like', '%'.$filters['q'].'%');
                });
            })
            ->latest();
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function messageExportRows(User $user, array $filters, int $limit): array
    {
        return $this->messageIndexQuery($user, $filters)
            ->limit($limit)
            ->get()
            ->map(fn (CollaborationMessage $message): array => [
                'message_number' => $message->message_number,
                'thread_key' => $message->thread_key,
                'subject' => $message->subject,
                'priority' => $message->priority,
                'status' => $message->status,
                'company' => $message->company?->name,
                'project' => $message->project?->name,
                'sender' => $message->sender?->name,
                'sender_email' => $message->sender?->email,
                'recipient' => $message->recipient?->name,
                'recipient_email' => $message->recipient?->email,
                'crm_link' => $this->messageCrmLinkLabel($message),
                'body_preview' => str($message->body)->stripTags()->squish()->limit(500)->toString(),
                'sent_at' => $message->sent_at?->toDateTimeString(),
                'scheduled_for' => $message->scheduled_for?->toDateTimeString(),
                'read_at' => $message->read_at?->toDateTimeString(),
                'created_at' => $message->created_at?->toDateTimeString(),
                'updated_at' => $message->updated_at?->toDateTimeString(),
            ])
            ->values()
            ->all();
    }

    private function messageCrmLinkLabel(CollaborationMessage $message): ?string
    {
        $metadata = $message->metadata ?? [];
        $link = is_array($metadata['crm_link'] ?? null) ? $metadata['crm_link'] : [];

        if (empty($link['record_type']) || empty($link['record_id'])) {
            return null;
        }

        return trim(ucfirst((string) $link['record_type']).' #'.$link['record_id'].' '.($link['label'] ?? ''));
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createTask(array $data, User $actor, ?Request $request = null): WorkTask
    {
        if (! $actor->can('create', WorkTask::class)) {
            throw new AuthorizationException('This task action is not available for your role.');
        }

        return DB::transaction(function () use ($data, $actor, $request): WorkTask {
            if (! empty($data['client_token'])) {
                $existing = WorkTask::query()
                    ->where('client_token', $data['client_token'])
                    ->where('created_by_user_id', $actor->id)
                    ->first();

                if ($existing) {
                    return $existing->load($this->taskRelations());
                }
            }

            $rawAssigneeIds = ! empty($data['assigned_to_user_ids']) && is_array($data['assigned_to_user_ids'])
                ? $data['assigned_to_user_ids']
                : [$data['assigned_to_user_id'] ?? null];

            $assigneeIds = collect($rawAssigneeIds)
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            $primaryAssigneeId = $assigneeIds->first() ?: (int) $actor->id;

            if ($assigneeIds->isEmpty()) {
                $assigneeIds = collect([$primaryAssigneeId]);
            }

            if (! $actor->hasPermission('collaboration.manage') && $assigneeIds->contains(fn ($id) => $id !== (int) $actor->id)) {
                throw ValidationException::withMessages(['assigned_to_user_ids' => 'Self-service users can create tasks only for themselves.']);
            }

            $assignees = User::query()->whereIn('id', $assigneeIds->all())->get();
            $primaryAssignee = $assignees->firstWhere('id', $primaryAssigneeId) ?: User::query()->whereKey($primaryAssigneeId)->firstOrFail();

            $assigneeCompanyId = $primaryAssignee->company_id
                ?? ((int) $primaryAssignee->id === (int) $actor->id ? $this->companyScope->companyIdFor($actor) : null);

            if (! $this->companyScope->allows($actor, $assigneeCompanyId)) {
                throw ValidationException::withMessages(['assigned_to_user_id' => 'The assignee must belong to your company.']);
            }

            $project = null;
            if (! empty($data['project_id'])) {
                $project = Project::query()->whereKey($data['project_id'])->firstOrFail();

                if (! $this->companyScope->allows($actor, $project->company_id)) {
                    throw ValidationException::withMessages(['project_id' => 'The selected project is not available for your company.']);
                }
            }

            $companyId = $this->companyIdForCreation($actor, $primaryAssignee, $project, [], $data['company_id'] ?? null);
            $template = collect((array) data_get($this->taskSettings->forCompany($companyId), 'templates', []))
                ->first(fn (array $candidate): bool => isset($data['template_id']) && (string) ($candidate['id'] ?? '') === (string) $data['template_id']);
            if (isset($data['template_id']) && ! $template) {
                throw ValidationException::withMessages(['template_id' => 'The selected task template is not active.']);
            }
            $templateSteps = collect((array) ($template['steps'] ?? []))->filter(fn ($step): bool => is_string($step) && trim($step) !== '')->values();
            $checklist = $data['checklist'] ?? $templateSteps->map(fn (string $step): array => ['label' => $step, 'done' => false])->all();

            $task = WorkTask::create([
                'company_id' => $companyId,
                'project_id' => $data['project_id'] ?? null,
                'created_by_user_id' => $actor->id,
                'assigned_to_user_id' => $primaryAssignee->id,
                'task_number' => $this->nextTaskNumber(),
                'client_token' => $data['client_token'] ?? null,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'priority' => $data['priority'],
                'status' => 'open',
                'due_at' => $data['due_at'] ?? null,
                'module_context' => $data['module_context'] ?? null,
                'related_type' => $data['related_type'] ?? null,
                'related_id' => $data['related_id'] ?? null,
                'checklist' => $this->normalizeChecklist($checklist),
                'workflow_history' => [
                    $this->workflowEvent('open', $actor, 'Task created'),
                ],
                'metadata' => $data['metadata'] ?? [],
            ]);

            // Sync assignees pivot table
            if (Schema::hasTable('work_task_assignees')) {
                $task->assignees()->sync($assignees->pluck('id')->all());
            }

            foreach ($templateSteps as $step) {
                WorkTaskSubtask::query()->create([
                    'company_id' => $task->company_id,
                    'work_task_id' => $task->id,
                    'created_by_user_id' => $actor->id,
                    'assigned_to_user_id' => $task->assigned_to_user_id,
                    'title' => $step,
                    'status' => 'open',
                    'priority' => $task->priority,
                    'metadata' => ['template_id' => $data['template_id'] ?? null],
                ]);
            }

            $this->auditLogger->record(
                $actor,
                'collaboration.task.created',
                'Created collaboration task',
                $task,
                ['task_number' => $task->task_number, 'assigned_to' => $assignees->pluck('email')->implode(', '), 'priority' => $task->priority],
                $request,
            );

            foreach ($assignees as $assigneeUser) {
                if ($assigneeUser->id !== $actor->id) {
                    $this->notifications->sendToUser($assigneeUser, [
                        'category' => 'collaboration',
                        'severity' => $task->priority === 'critical' ? 'critical' : 'info',
                        'title' => "Task {$task->task_number} assigned",
                        'body' => $task->title,
                        'action_url' => '/collaboration/tasks?assigned_to_user_id='.$assigneeUser->id,
                        'payload' => ['task_number' => $task->task_number, 'priority' => $task->priority],
                    ], $actor, $task);
                }
            }

            $this->taskRecurrence->synchronize($task);

            return $task->load($this->taskRelations());
        });
    }

    /** @param array<string, mixed> $data */
    public function duplicateTask(WorkTask $workTask, array $data, User $actor, ?Request $request = null): WorkTask
    {
        if (! $actor->can('view', $workTask) || ! $actor->can('create', WorkTask::class)) {
            throw new AuthorizationException('Task duplication is not available for your role.');
        }

        return DB::transaction(function () use ($workTask, $data, $actor, $request): WorkTask {
            $source = WorkTask::query()->withTrashed()->whereKey($workTask->id)->lockForUpdate()->firstOrFail();
            $existing = WorkTask::query()->where('client_token', $data['client_token'])->where('created_by_user_id', $actor->id)->first();
            if ($existing) {
                return $existing->load($this->taskRelations());
            }

            $metadata = $source->metadata ?? [];
            $metadata['duplicated_from_task_id'] = $source->id;
            unset($metadata['watcher_user_ids'], $metadata['watcher_count'], $metadata['reminders_sent'], $metadata['recurrence_next_task_id']);

            $duplicate = WorkTask::create([
                'company_id' => $source->company_id,
                'project_id' => $source->project_id,
                'created_by_user_id' => $actor->id,
                'assigned_to_user_id' => $source->assigned_to_user_id,
                'task_number' => $this->nextTaskNumber(),
                'client_token' => $data['client_token'],
                'title' => 'Copy of '.$source->title,
                'description' => $source->description,
                'priority' => $source->priority,
                'status' => $source->assigned_to_user_id ? 'assigned' : 'draft',
                'due_at' => null,
                'module_context' => $source->module_context,
                'related_type' => $source->related_type,
                'related_id' => $source->related_id,
                'checklist' => collect($source->checklist ?? [])->map(fn (array $item): array => [...$item, 'done' => false])->all(),
                'workflow_history' => [$this->workflowEvent('duplicated', $actor, 'Duplicated from '.$source->task_number)],
                'metadata' => $metadata,
            ]);

            foreach ($source->subtasks as $subtask) {
                WorkTaskSubtask::create([
                    'company_id' => $duplicate->company_id,
                    'work_task_id' => $duplicate->id,
                    'created_by_user_id' => $actor->id,
                    'assigned_to_user_id' => $subtask->assigned_to_user_id,
                    'title' => $subtask->title,
                    'status' => 'open',
                    'priority' => $subtask->priority,
                    'metadata' => $subtask->metadata,
                ]);
            }

            $this->auditLogger->record($actor, 'collaboration.task.duplicated', 'Duplicated collaboration task', $duplicate, [
                'source_task_id' => $source->id,
                'source_task_number' => $source->task_number,
                'task_number' => $duplicate->task_number,
            ], $request);

            return $duplicate->load($this->taskRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function assignTask(WorkTask $workTask, array $data, User $actor, ?Request $request = null): WorkTask
    {
        return DB::transaction(function () use ($workTask, $data, $actor, $request): WorkTask {
            $task = WorkTask::query()->whereKey($workTask->id)->lockForUpdate()->firstOrFail();
            $this->authorizeTaskAction($actor, 'assign', $task);
            $this->taskMutations->assertVersion($task, $data['lock_version'] ?? null);

            if ($task->status === 'completed') {
                throw ValidationException::withMessages(['task' => 'Completed tasks cannot be reassigned.']);
            }

            $assignee = User::query()->whereKey($data['assigned_to_user_id'])->firstOrFail();
            $this->taskPeople->assertEligible($actor, $task, $assignee);

            if ($task->assigned_to_user_id) {
                if ((int) $task->assigned_to_user_id === (int) $assignee->id) {
                    throw ValidationException::withMessages(['assigned_to_user_id' => 'This employee is already assigned to the task.']);
                }

                throw ValidationException::withMessages([
                    'assigned_to_user_id' => 'Use the transfer workflow to replace the current assignee.',
                ]);
            }

            $history = $task->workflow_history ?? [];
            $history[] = $this->workflowEvent('assigned', $actor, $data['note'] ?? "Assigned to {$assignee->name}");

            $task->forceFill([
                'assigned_to_user_id' => $assignee->id,
                'workflow_history' => $history,
            ])->save();

            $this->auditLogger->record(
                $actor,
                'collaboration.task.assigned',
                'Assigned collaboration task',
                $task,
                ['task_number' => $task->task_number, 'assigned_to' => $assignee->email],
                $request,
            );

            if ($assignee->id !== $actor->id) {
                $this->notifications->sendToUser($assignee, [
                    'category' => 'collaboration',
                    'severity' => $task->priority === 'critical' ? 'critical' : 'info',
                    'title' => "Task {$task->task_number} assigned",
                    'body' => $task->title,
                    'action_url' => '/collaboration/tasks?assigned_to_user_id='.$assignee->id,
                    'payload' => ['task_number' => $task->task_number, 'priority' => $task->priority],
                ], $actor, $task);
            }

            return $task->load($this->taskRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function requestTaskTransferApproval(WorkTask $workTask, array $data, User $actor, ?Request $request = null): WorkTaskTransferRequest
    {
        return DB::transaction(function () use ($workTask, $data, $actor, $request): WorkTaskTransferRequest {
            $task = WorkTask::query()->whereKey($workTask->id)->lockForUpdate()->firstOrFail();
            $this->authorizeTaskAction($actor, 'requestTransfer', $task);
            $this->taskMutations->assertVersion($task, $data['lock_version'] ?? null);

            if (in_array($task->status, TaskLifecycle::terminalStatuses(), true)) {
                throw ValidationException::withMessages(['task' => 'Completed or cancelled tasks cannot be transferred.']);
            }

            if (! $this->companyScope->allows($actor, $task->company_id)) {
                throw ValidationException::withMessages(['task' => 'The selected task is outside your company scope.']);
            }

            $assignee = User::query()->whereKey($data['assigned_to_user_id'])->firstOrFail();
            $this->taskPeople->assertEligible($actor, $task, $assignee);

            if ($assignee->status !== 'active') {
                throw ValidationException::withMessages(['assigned_to_user_id' => 'The proposed assignee must be an active user.']);
            }

            if ((int) $assignee->company_id !== (int) $task->company_id) {
                throw ValidationException::withMessages(['assigned_to_user_id' => 'The proposed assignee must belong to the task company.']);
            }

            if ((int) $assignee->id === (int) $task->assigned_to_user_id) {
                throw ValidationException::withMessages(['assigned_to_user_id' => 'The proposed assignee is already the current owner.']);
            }

            $pendingExists = WorkTaskTransferRequest::query()
                ->where('work_task_id', $task->id)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->exists();

            if ($pendingExists) {
                throw ValidationException::withMessages(['task' => 'A transfer approval request is already pending for this task.']);
            }

            $reason = trim((string) $data['reason']);
            $requiresApproval = (bool) data_get(
                $this->taskSettings->forCompany($task->company_id),
                'transfer_requires_approval',
                true,
            );
            $transferStatus = $requiresApproval ? 'pending' : 'approved';
            $transferRequest = WorkTaskTransferRequest::create([
                'company_id' => $task->company_id,
                'work_task_id' => $task->id,
                'requested_by_user_id' => $actor->id,
                'from_user_id' => $task->assigned_to_user_id,
                'to_user_id' => $assignee->id,
                'approved_by_user_id' => $requiresApproval ? null : $actor->id,
                'status' => $transferStatus,
                'reason' => $reason,
                'approval_note' => $requiresApproval ? null : 'Transferred immediately under the active task workflow.',
                'requested_at' => now(),
                'resolved_at' => $requiresApproval ? null : now(),
                'workflow_history' => [
                    $this->workflowEvent($transferStatus, $actor, $reason),
                ],
                'metadata' => $data['metadata'] ?? [],
            ]);

            $history = $task->workflow_history ?? [];
            $history[] = $this->workflowEvent(
                $requiresApproval ? 'transfer_requested' : 'transfer_completed',
                $actor,
                ($requiresApproval ? 'Transfer requested' : 'Transferred')." to {$assignee->name}: {$reason}",
            );
            $task->forceFill([
                'assigned_to_user_id' => $requiresApproval ? $task->assigned_to_user_id : $assignee->id,
                'workflow_history' => $history,
            ])->save();

            $this->auditLogger->record(
                $actor,
                $requiresApproval ? 'collaboration.task.transfer_requested' : 'collaboration.task.transferred',
                $requiresApproval ? 'Requested collaboration task transfer approval' : 'Transferred collaboration task',
                $task,
                [
                    'task_number' => $task->task_number,
                    'transfer_request_id' => $transferRequest->id,
                    'from_user_id' => $transferRequest->from_user_id,
                    'to_user_id' => $assignee->id,
                ],
                $request,
            );

            if ($requiresApproval) {
                $this->notifyTaskTransferApprovers($task, $transferRequest, $actor);
            } else {
                $this->notifyTaskTransferResolution($task, $transferRequest, $actor, 'approved');
            }

            return $transferRequest->load($this->transferRequestRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function resolveTaskTransferApproval(WorkTaskTransferRequest $workTaskTransferRequest, array $data, User $actor, ?Request $request = null): WorkTask
    {
        return DB::transaction(function () use ($workTaskTransferRequest, $data, $actor, $request): WorkTask {
            $transferRequest = WorkTaskTransferRequest::query()
                ->whereKey($workTaskTransferRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($transferRequest->status !== 'pending') {
                throw ValidationException::withMessages(['transfer_request' => 'Only pending transfer requests can be resolved.']);
            }

            if (! $this->companyScope->allows($actor, $transferRequest->company_id)) {
                throw ValidationException::withMessages(['transfer_request' => 'The transfer request is outside your company scope.']);
            }

            if (! $actor->hasPermission('collaboration.manage')) {
                throw ValidationException::withMessages(['transfer_request' => 'Only collaboration managers can resolve transfer requests.']);
            }

            if ((int) $transferRequest->requested_by_user_id === (int) $actor->id) {
                throw ValidationException::withMessages(['transfer_request' => 'The requester cannot approve or reject the same transfer request.']);
            }

            $task = WorkTask::query()
                ->whereKey($transferRequest->work_task_id)
                ->lockForUpdate()
                ->firstOrFail();

            $action = $data['action'];
            $note = trim((string) ($data['note'] ?? ($action === 'approved' ? 'Transfer approved' : 'Transfer rejected')));
            $history = $task->workflow_history ?? [];
            $transferHistory = $transferRequest->workflow_history ?? [];

            if ($action === 'approved') {
                if (in_array($task->status, TaskLifecycle::terminalStatuses(), true)) {
                    throw ValidationException::withMessages(['task' => 'Completed or cancelled tasks cannot be transferred.']);
                }

                $assignee = User::query()->whereKey($transferRequest->to_user_id)->firstOrFail();

                if ((int) $assignee->company_id !== (int) $task->company_id || $assignee->status !== 'active') {
                    throw ValidationException::withMessages(['assigned_to_user_id' => 'The approved assignee is no longer valid for this task company.']);
                }

                $task->forceFill([
                    'assigned_to_user_id' => $assignee->id,
                    'workflow_history' => [
                        ...$history,
                        $this->workflowEvent('transfer_approved', $actor, $note),
                    ],
                ])->save();
            } else {
                $task->forceFill([
                    'workflow_history' => [
                        ...$history,
                        $this->workflowEvent('transfer_rejected', $actor, $note),
                    ],
                ])->save();
            }

            $transferRequest->forceFill([
                'status' => $action,
                'approved_by_user_id' => $actor->id,
                'approval_note' => $note,
                'resolved_at' => now(),
                'workflow_history' => [
                    ...$transferHistory,
                    $this->workflowEvent($action, $actor, $note),
                ],
            ])->save();

            $this->auditLogger->record(
                $actor,
                $action === 'approved' ? 'collaboration.task.transfer_approved' : 'collaboration.task.transfer_rejected',
                $action === 'approved' ? 'Approved collaboration task transfer' : 'Rejected collaboration task transfer',
                $task,
                [
                    'task_number' => $task->task_number,
                    'transfer_request_id' => $transferRequest->id,
                    'from_user_id' => $transferRequest->from_user_id,
                    'to_user_id' => $transferRequest->to_user_id,
                    'note' => $note,
                ],
                $request,
            );

            $this->notifyTaskTransferResolution($task, $transferRequest, $actor, $action);

            return $task->load($this->taskRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateTaskDetails(WorkTask $workTask, array $data, User $actor, ?Request $request = null): WorkTask
    {
        return DB::transaction(function () use ($workTask, $data, $actor, $request): WorkTask {
            $task = WorkTask::query()->whereKey($workTask->id)->lockForUpdate()->firstOrFail();
            $this->authorizeTaskAction($actor, 'updateDetails', $task);
            $this->taskMutations->assertVersion($task, $data['lock_version'] ?? null);

            if (in_array($task->status, TaskLifecycle::terminalStatuses(), true)) {
                throw ValidationException::withMessages(['task' => 'Completed or cancelled tasks cannot be updated.']);
            }

            if (! $this->companyScope->allows($actor, $task->company_id)) {
                throw ValidationException::withMessages(['task' => 'The selected task is outside your company scope.']);
            }

            $updates = [];
            foreach (['title', 'description', 'priority', 'due_at'] as $field) {
                if (array_key_exists($field, $data)) {
                    $updates[$field] = is_string($data[$field]) ? trim($data[$field]) : $data[$field];
                }
            }

            $before = collect($updates)
                ->keys()
                ->mapWithKeys(fn (string $field): array => [$field => $task->{$field} instanceof Carbon ? $task->{$field}->toISOString() : $task->{$field}])
                ->all();

            $history = $task->workflow_history ?? [];
            $history[] = $this->workflowEvent('details_updated', $actor, $data['note'] ?? 'Task details updated');
            $updates['workflow_history'] = $history;

            $task->forceFill($updates)->save();

            $after = collect($before)
                ->keys()
                ->mapWithKeys(fn (string $field): array => [$field => $task->{$field} instanceof Carbon ? $task->{$field}->toISOString() : $task->{$field}])
                ->all();

            $this->auditLogger->record(
                $actor,
                'collaboration.task.details_updated',
                'Updated collaboration task details',
                $task,
                [
                    'task_number' => $task->task_number,
                    'changed_fields' => array_keys($before),
                    'before' => $before,
                    'after' => $after,
                ],
                $request,
            );

            return $task->load($this->taskRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateTaskStatus(WorkTask $workTask, array $data, User $actor, ?Request $request = null): WorkTask
    {
        return DB::transaction(function () use ($workTask, $data, $actor, $request): WorkTask {
            $task = WorkTask::query()->whereKey($workTask->id)->lockForUpdate()->firstOrFail();
            $this->authorizeTaskAction($actor, 'updateStatus', $task);
            $this->taskMutations->assertVersion($task, $data['lock_version'] ?? null);

            if (in_array($task->status, TaskLifecycle::terminalStatuses(), true)) {
                throw ValidationException::withMessages(['task' => 'Completed or cancelled tasks cannot be updated.']);
            }

            $status = $data['status'];

            if ($task->status === 'waiting_approval' && in_array($status, ['in_progress', 'open'], true)) {
                WorkTaskCompletionApproval::query()
                    ->where('work_task_id', $task->id)
                    ->where('status', 'pending')
                    ->update(['status' => 'rejected', 'decision_note' => 'Withdrawn by user to resume work']);
            }

            $this->taskTransitions->assertAllowed($task, $status, $actor);
            $taskSetting = $this->taskSettings->forCompany($task->company_id);
            $completionApprovalRequired = $status === 'completed'
                && (bool) data_get($taskSetting, 'require_completion_approval', false)
                && ! $actor->hasPermission('collaboration.manage');
            if ($completionApprovalRequired) {
                $this->completionApprovals->request($task, $actor, $data['note'] ?? 'Completion approval requested', $request);
                return $task->fresh()->load($this->taskRelations());
            }
            $history = $task->workflow_history ?? [];
            $history[] = $this->workflowEvent($status, $actor, $data['note']);

            $task->forceFill([
                'status' => $status,
                'started_at' => $status === 'in_progress' ? ($task->started_at ?? now()) : $task->started_at,
                'completed_at' => $status === 'completed' ? now() : $task->completed_at,
                'workflow_history' => $history,
            ])->save();

            if ($status === 'completed') {
                $this->taskRecurrence->synchronize($task);
                $this->taskRecurrence->generateNextForTask($task);
            }

            $this->auditLogger->record(
                $actor,
                'collaboration.task.status_updated',
                'Updated collaboration task status',
                $task,
                ['task_number' => $task->task_number, 'status' => $status],
                $request,
            );

            return $task->load($this->taskRelations());
        });
    }

    /**
     * @param array{action: string, note?: string|null} $data
     */
    public function updateTaskWatcher(WorkTask $workTask, array $data, User $actor, ?Request $request = null): WorkTask
    {
        return DB::transaction(function () use ($workTask, $data, $actor, $request): WorkTask {
            $task = WorkTask::query()->whereKey($workTask->id)->lockForUpdate()->firstOrFail();
            $this->authorizeTaskAction($actor, 'watch', $task);

            if (! $this->companyScope->allows($actor, $task->company_id)) {
                throw ValidationException::withMessages(['task' => 'The selected task is outside your company scope.']);
            }

            if (in_array($task->status, ['rejected', 'cancelled'], true)) {
                throw ValidationException::withMessages(['task' => 'Cancelled tasks cannot be watched.']);
            }

            $metadata = $task->metadata ?? [];
            $watcherIds = collect($metadata['watcher_user_ids'] ?? [])
                ->map(fn ($id): int => (int) $id)
                ->filter()
                ->unique()
                ->values();

            $isWatching = $watcherIds->contains((int) $actor->id);
            $action = $data['action'];
            $nextWatching = $action === 'toggle' ? ! $isWatching : $action === 'watch';

            $nextWatcherIds = $nextWatching
                ? $watcherIds->push((int) $actor->id)->unique()->values()
                : $watcherIds->reject(fn (int $id): bool => $id === (int) $actor->id)->values();

            $metadata['watcher_user_ids'] = $nextWatcherIds->all();
            $metadata['watcher_count'] = $nextWatcherIds->count();

            $history = $task->workflow_history ?? [];
            $history[] = $this->workflowEvent(
                $nextWatching ? 'watch_started' : 'watch_stopped',
                $actor,
                $data['note'] ?? ($nextWatching ? 'Started watching task' : 'Stopped watching task'),
            );

            $task->forceFill([
                'metadata' => $metadata,
                'workflow_history' => $history,
            ])->save();

            $this->auditLogger->record(
                $actor,
                $nextWatching ? 'collaboration.task.watch_started' : 'collaboration.task.watch_stopped',
                $nextWatching ? 'Started watching collaboration task' : 'Stopped watching collaboration task',
                $task,
                [
                    'task_number' => $task->task_number,
                    'watching' => $nextWatching,
                    'watcher_count' => $nextWatcherIds->count(),
                ],
                $request,
            );

            return $task->load($this->taskRelations());
        });
    }

    /**
     * @param array{dependency_task_ids: array<int, int|string>, note?: string|null} $data
     */
    public function updateTaskDependencies(WorkTask $workTask, array $data, User $actor, ?Request $request = null): WorkTask
    {
        return DB::transaction(function () use ($workTask, $data, $actor, $request): WorkTask {
            $task = WorkTask::query()->whereKey($workTask->id)->lockForUpdate()->firstOrFail();
            $this->authorizeTaskAction($actor, 'updateDetails', $task);
            $this->taskMutations->assertVersion($task, $data['lock_version'] ?? null);

            if (in_array($task->status, TaskLifecycle::terminalStatuses(), true)) {
                throw ValidationException::withMessages(['task' => 'Completed or cancelled tasks cannot be updated.']);
            }

            if (! $this->companyScope->allows($actor, $task->company_id)) {
                throw ValidationException::withMessages(['task' => 'The selected task is outside your company scope.']);
            }

            $dependencyIds = collect($data['dependency_task_ids'] ?? [])
                ->map(fn ($id): int => (int) $id)
                ->filter()
                ->unique()
                ->values();

            $dependencies = $dependencyIds->isEmpty()
                ? collect()
                : WorkTask::query()
                    ->whereIn('id', $dependencyIds->all())
                    ->where('company_id', $task->company_id)
                    ->orderBy('task_number')
                    ->get(['id', 'task_number', 'title', 'status', 'priority']);

            if ($dependencies->count() !== $dependencyIds->count()) {
                throw ValidationException::withMessages(['dependency_task_ids' => 'One or more selected dependencies are not available for this task company.']);
            }

            $metadata = $task->metadata ?? [];
            $beforeIds = collect($metadata['dependency_task_ids'] ?? [])
                ->map(fn ($id): int => (int) $id)
                ->filter()
                ->values()
                ->all();

            $dependencyRows = $dependencies
                ->map(fn (WorkTask $dependency): array => [
                    'id' => $dependency->id,
                    'task_number' => $dependency->task_number,
                    'title' => $dependency->title,
                    'status' => $dependency->status,
                    'priority' => $dependency->priority,
                ])
                ->values()
                ->all();

            $metadata['dependency_task_ids'] = $dependencies->pluck('id')->map(fn ($id): int => (int) $id)->values()->all();
            $metadata['task_dependencies'] = $dependencyRows;
            $metadata['dependency_count'] = count($dependencyRows);

            $history = $task->workflow_history ?? [];
            $history[] = $this->workflowEvent(
                'dependencies_updated',
                $actor,
                $data['note'] ?? 'Task dependencies updated',
            );

            $task->forceFill([
                'metadata' => $metadata,
                'workflow_history' => $history,
            ])->save();

            $this->auditLogger->record(
                $actor,
                'collaboration.task.dependencies_updated',
                'Updated collaboration task dependencies',
                $task,
                [
                    'task_number' => $task->task_number,
                    'before_dependency_ids' => $beforeIds,
                    'after_dependency_ids' => $metadata['dependency_task_ids'],
                    'dependency_count' => count($dependencyRows),
                ],
                $request,
            );

            return $task->load($this->taskRelations());
        });
    }

    /**
     * @param array{task_ids: array<int, int|string>, status?: string, priority?: string, note?: string|null} $data
     * @return EloquentCollection<int, WorkTask>
     */
    public function bulkUpdateTasks(array $data, User $actor, ?Request $request = null): EloquentCollection
    {
        return DB::transaction(function () use ($data, $actor, $request): EloquentCollection {
            $taskIds = collect($data['task_ids'])
                ->map(fn ($id): int => (int) $id)
                ->filter()
                ->unique()
                ->values()
                ->all();

            $tasks = WorkTask::query()
                ->whereIn('id', $taskIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($tasks->count() !== count($taskIds)) {
                throw ValidationException::withMessages(['task_ids' => 'All selected tasks must exist and be active.']);
            }

            $updated = new EloquentCollection();
            $note = $data['note'] ?? 'Tasks updated from Task Management bulk action.';

            foreach ($taskIds as $taskId) {
                /** @var WorkTask $task */
                $task = $tasks->get($taskId);

                if (array_key_exists('status', $data)) {
                    $this->authorizeTaskAction($actor, 'updateStatus', $task);
                }

                if (array_key_exists('priority', $data)) {
                    $this->authorizeTaskAction($actor, 'updateDetails', $task);
                }

                if (in_array($task->status, TaskLifecycle::terminalStatuses(), true)) {
                    throw ValidationException::withMessages(['task_ids' => 'Completed or cancelled tasks cannot be updated.']);
                }

                if (! $this->companyScope->allows($actor, $task->company_id)) {
                    throw ValidationException::withMessages(['task_ids' => 'One or more selected tasks are outside your company scope.']);
                }

                $history = $task->workflow_history ?? [];

                if (array_key_exists('status', $data)) {
                    $status = $data['status'];
                    $history[] = $this->workflowEvent($status, $actor, $note);

                    $task->forceFill([
                        'status' => $status,
                        'started_at' => $status === 'in_progress' ? ($task->started_at ?? now()) : $task->started_at,
                        'completed_at' => $status === 'completed' ? now() : $task->completed_at,
                        'workflow_history' => $history,
                    ])->save();

                    $this->auditLogger->record(
                        $actor,
                        'collaboration.task.status_updated',
                        'Updated collaboration task status',
                        $task,
                        [
                            'task_number' => $task->task_number,
                            'status' => $status,
                            'bulk' => true,
                        ],
                        $request,
                    );
                }

                if (array_key_exists('priority', $data)) {
                    $beforePriority = $task->priority;
                    $history = $task->workflow_history ?? [];
                    $history[] = $this->workflowEvent('details_updated', $actor, $note);

                    $task->forceFill([
                        'priority' => $data['priority'],
                        'workflow_history' => $history,
                    ])->save();

                    $this->auditLogger->record(
                        $actor,
                        'collaboration.task.details_updated',
                        'Updated collaboration task details',
                        $task,
                        [
                            'task_number' => $task->task_number,
                            'changed_fields' => ['priority'],
                            'before' => ['priority' => $beforePriority],
                            'after' => ['priority' => $task->priority],
                            'bulk' => true,
                        ],
                        $request,
                    );
                }

                $updated->push($task);
            }

            return $updated->load($this->taskRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function archiveTask(WorkTask $workTask, array $data, User $actor, ?Request $request = null): WorkTask
    {
        return DB::transaction(function () use ($workTask, $data, $actor, $request): WorkTask {
            $task = WorkTask::query()->whereKey($workTask->id)->lockForUpdate()->firstOrFail();
            $this->authorizeTaskAction($actor, 'archive', $task);

            if (! $this->companyScope->allows($actor, $task->company_id)) {
                throw ValidationException::withMessages(['task' => 'The selected task is outside your company scope.']);
            }

            $history = $task->workflow_history ?? [];
            $history[] = $this->workflowEvent('archived', $actor, $data['note'] ?? 'Task archived');

            $task->forceFill([
                'workflow_history' => $history,
            ])->save();

            $this->auditLogger->record(
                $actor,
                'collaboration.task.archived',
                'Archived collaboration task',
                $task,
                [
                    'task_number' => $task->task_number,
                    'status' => $task->status,
                    'note' => $data['note'] ?? null,
                ],
                $request,
            );

            $task->delete();

            return $task->load($this->taskRelations());
        });
    }

    /**
     * @param array{task_ids: array<int, int|string>, note?: string|null} $data
     * @return array<int, array{id: int, task_number: string, archived: bool, deleted_at: string|null}>
     */
    public function bulkArchiveTasks(array $data, User $actor, ?Request $request = null): array
    {
        return DB::transaction(function () use ($data, $actor, $request): array {
            $taskIds = collect($data['task_ids'])
                ->map(fn ($id): int => (int) $id)
                ->filter()
                ->unique()
                ->values()
                ->all();

            $tasks = WorkTask::query()
                ->whereIn('id', $taskIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($tasks->count() !== count($taskIds)) {
                throw ValidationException::withMessages(['task_ids' => 'All selected tasks must exist and be active.']);
            }

            $archived = [];
            $note = $data['note'] ?? 'Tasks archived from Task Management bulk action.';

            foreach ($taskIds as $taskId) {
                /** @var WorkTask $task */
                $task = $tasks->get($taskId);
                $this->authorizeTaskAction($actor, 'archive', $task);

                if (! $this->companyScope->allows($actor, $task->company_id)) {
                    throw ValidationException::withMessages(['task_ids' => 'One or more selected tasks are outside your company scope.']);
                }

                $history = $task->workflow_history ?? [];
                $history[] = $this->workflowEvent('archived', $actor, $note);

                $task->forceFill([
                    'workflow_history' => $history,
                ])->save();

                $this->auditLogger->record(
                    $actor,
                    'collaboration.task.archived',
                    'Archived collaboration task',
                    $task,
                    [
                        'task_number' => $task->task_number,
                        'status' => $task->status,
                        'note' => $note,
                        'bulk' => true,
                    ],
                    $request,
                );

                $task->delete();

                $archived[] = [
                    'id' => $task->id,
                    'task_number' => $task->task_number,
                    'archived' => $task->trashed(),
                    'deleted_at' => $task->deleted_at?->toISOString(),
                ];
            }

            return $archived;
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function addTaskComment(WorkTask $workTask, array $data, User $actor, ?Request $request = null): WorkTask
    {
        return DB::transaction(function () use ($workTask, $data, $actor, $request): WorkTask {
            $task = WorkTask::query()->whereKey($workTask->id)->lockForUpdate()->firstOrFail();
            $this->authorizeTaskAction($actor, 'comment', $task);

            if (! $this->companyScope->allows($actor, $task->company_id)) {
                throw ValidationException::withMessages(['task' => 'The selected task is outside your company scope.']);
            }

            $mentions = $this->validateTaskMentionIds($data['mentions'] ?? [], $task);

            $comment = WorkTaskComment::create([
                'company_id' => $task->company_id,
                'work_task_id' => $task->id,
                'author_user_id' => $actor->id,
                'body' => trim((string) $data['body']),
                'mentions' => $mentions,
                'metadata' => $data['metadata'] ?? [],
            ]);

            $history = $task->workflow_history ?? [];
            $history[] = $this->workflowEvent('commented', $actor, 'Comment added');
            $task->forceFill(['workflow_history' => $history])->save();

            $this->auditLogger->record(
                $actor,
                'collaboration.task.comment_added',
                'Added collaboration task comment',
                $task,
                ['task_number' => $task->task_number, 'comment_id' => $comment->id],
                $request,
            );

            $recipientIds = collect([$task->created_by_user_id, $task->assigned_to_user_id])
                ->merge($mentions)
                ->filter()
                ->unique()
                ->reject(fn (int $userId): bool => $userId === $actor->id)
                ->values()
                ->all();

            User::query()
                ->whereIn('id', $recipientIds)
                ->get()
                ->each(fn (User $recipient) => $this->notifications->sendToUser($recipient, [
                    'category' => 'collaboration',
                    'severity' => $task->priority === 'critical' ? 'critical' : 'info',
                    'title' => "Comment on {$task->task_number}",
                    'body' => Str::limit($comment->body, 160),
                    'action_url' => '/collaboration/tasks?q='.$task->task_number,
                    'payload' => ['task_number' => $task->task_number, 'comment_id' => $comment->id],
                ], $actor, $task));

            return $task->load($this->taskRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateTaskChecklist(WorkTask $workTask, array $data, User $actor, ?Request $request = null): WorkTask
    {
        return DB::transaction(function () use ($workTask, $data, $actor, $request): WorkTask {
            $task = WorkTask::query()->whereKey($workTask->id)->lockForUpdate()->firstOrFail();
            $this->authorizeTaskAction($actor, 'updateChecklist', $task);
            $this->taskMutations->assertVersion($task, $data['lock_version'] ?? null);

            if (in_array($task->status, TaskLifecycle::terminalStatuses(), true)) {
                throw ValidationException::withMessages(['task' => 'Completed or cancelled tasks cannot be updated.']);
            }

            $checklist = $this->normalizeChecklist($data['checklist'] ?? []);
            if (filled($data['new_item'] ?? null)) {
                $checklist[] = ['label' => trim((string) $data['new_item']), 'done' => false];
            }
            $history = $task->workflow_history ?? [];
            $history[] = $this->workflowEvent('checklist_updated', $actor, $data['note'] ?? 'Checklist updated');

            $task->forceFill([
                'checklist' => $checklist,
                'workflow_history' => $history,
            ])->save();

            $this->auditLogger->record(
                $actor,
                'collaboration.task.checklist_updated',
                'Updated collaboration task checklist',
                $task,
                [
                    'task_number' => $task->task_number,
                    'items' => count($checklist),
                    'done' => collect($checklist)->where('done', true)->count(),
                ],
                $request,
            );

            return $task->load($this->taskRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createTaskSubtask(WorkTask $workTask, array $data, User $actor, ?Request $request = null): WorkTask
    {
        return DB::transaction(function () use ($workTask, $data, $actor, $request): WorkTask {
            $task = WorkTask::query()->whereKey($workTask->id)->lockForUpdate()->firstOrFail();
            $this->authorizeTaskAction($actor, 'manageSubtasks', $task);

            if (in_array($task->status, TaskLifecycle::terminalStatuses(), true)) {
                throw ValidationException::withMessages(['task' => 'Completed or cancelled tasks cannot be updated.']);
            }

            if (! $this->companyScope->allows($actor, $task->company_id)) {
                throw ValidationException::withMessages(['task' => 'The selected task is outside your company scope.']);
            }

            $assigneeId = $this->validatedSubtaskAssigneeId($data['assigned_to_user_id'] ?? $task->assigned_to_user_id, $task);
            $status = $data['status'] ?? 'open';

            $subtask = WorkTaskSubtask::create([
                'company_id' => $task->company_id,
                'work_task_id' => $task->id,
                'assigned_to_user_id' => $assigneeId,
                'created_by_user_id' => $actor->id,
                'title' => trim((string) $data['title']),
                'status' => $status,
                'priority' => $data['priority'] ?? 'medium',
                'due_at' => $data['due_at'] ?? null,
                'completed_at' => $status === 'completed' ? now() : null,
                'metadata' => $data['metadata'] ?? [],
            ]);

            $history = $task->workflow_history ?? [];
            $history[] = $this->workflowEvent('subtask_created', $actor, 'Subtask added: '.$subtask->title);
            $task->forceFill(['workflow_history' => $history])->save();

            $this->auditLogger->record(
                $actor,
                'collaboration.task.subtask_created',
                'Created collaboration task subtask',
                $task,
                [
                    'task_number' => $task->task_number,
                    'subtask_id' => $subtask->id,
                    'assigned_to_user_id' => $assigneeId,
                    'status' => $status,
                ],
                $request,
            );

            if ($assigneeId && (int) $assigneeId !== (int) $actor->id) {
                $assignee = User::query()->whereKey($assigneeId)->first();

                if ($assignee) {
                    $this->notifications->sendToUser($assignee, [
                        'category' => 'collaboration',
                        'severity' => $subtask->priority === 'critical' ? 'critical' : 'info',
                        'title' => "Subtask added on {$task->task_number}",
                        'body' => $subtask->title,
                        'action_url' => '/collaboration/tasks?q='.$task->task_number,
                        'payload' => ['task_number' => $task->task_number, 'subtask_id' => $subtask->id],
                    ], $actor, $task);
                }
            }

            return $task->load($this->taskRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateTaskSubtaskStatus(WorkTask $workTask, WorkTaskSubtask $workTaskSubtask, array $data, User $actor, ?Request $request = null): WorkTask
    {
        return DB::transaction(function () use ($workTask, $workTaskSubtask, $data, $actor, $request): WorkTask {
            $task = WorkTask::query()->whereKey($workTask->id)->lockForUpdate()->firstOrFail();
            $subtask = WorkTaskSubtask::query()->whereKey($workTaskSubtask->id)->lockForUpdate()->firstOrFail();
            $this->authorizeTaskAction($actor, 'manageSubtasks', $task);

            if ((int) $subtask->work_task_id !== (int) $task->id) {
                throw ValidationException::withMessages(['subtask' => 'The selected subtask does not belong to this task.']);
            }

            if (in_array($task->status, TaskLifecycle::terminalStatuses(), true)) {
                throw ValidationException::withMessages(['task' => 'Completed or cancelled tasks cannot be updated.']);
            }

            $status = $data['status'];
            $subtask->forceFill([
                'status' => $status,
                'completed_at' => $status === 'completed' ? ($subtask->completed_at ?? now()) : null,
            ])->save();

            $history = $task->workflow_history ?? [];
            $history[] = $this->workflowEvent('subtask_'.$status, $actor, $data['note'] ?? 'Subtask status updated: '.$subtask->title);
            $task->forceFill(['workflow_history' => $history])->save();

            $this->auditLogger->record(
                $actor,
                'collaboration.task.subtask_status_updated',
                'Updated collaboration task subtask status',
                $task,
                [
                    'task_number' => $task->task_number,
                    'subtask_id' => $subtask->id,
                    'status' => $status,
                ],
                $request,
            );

            return $task->load($this->taskRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function logTaskTime(WorkTask $workTask, array $data, User $actor, ?Request $request = null): WorkTask
    {
        return DB::transaction(function () use ($workTask, $data, $actor, $request): WorkTask {
            $task = WorkTask::query()->whereKey($workTask->id)->lockForUpdate()->firstOrFail();
            $this->authorizeTaskAction($actor, 'logTime', $task);

            if (in_array($task->status, TaskLifecycle::terminalStatuses(), true)) {
                throw ValidationException::withMessages(['task' => 'Completed or cancelled tasks cannot accept time logs.']);
            }

            if (! $this->companyScope->allows($actor, $task->company_id)) {
                throw ValidationException::withMessages(['task' => 'The selected task is outside your company scope.']);
            }

            $timeLog = WorkTaskTimeLog::create([
                'company_id' => $task->company_id,
                'work_task_id' => $task->id,
                'user_id' => $actor->id,
                'logged_on' => $data['logged_on'] ?? now()->toDateString(),
                'minutes' => (int) $data['minutes'],
                'note' => $data['note'] ?? null,
                'source' => $data['source'] ?? 'manual',
                'metadata' => $data['metadata'] ?? [],
            ]);

            $history = $task->workflow_history ?? [];
            $history[] = $this->workflowEvent('time_logged', $actor, 'Logged '.round($timeLog->minutes / 60, 2).' hours');
            $task->forceFill(['workflow_history' => $history])->save();

            $this->auditLogger->record(
                $actor,
                'collaboration.task.time_logged',
                'Logged collaboration task time',
                $task,
                [
                    'task_number' => $task->task_number,
                    'time_log_id' => $timeLog->id,
                    'minutes' => $timeLog->minutes,
                    'logged_on' => $timeLog->logged_on?->toDateString(),
                ],
                $request,
            );

            return $task->load($this->taskRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createCalendarEvent(array $data, User $actor, ?Request $request = null): CalendarEvent
    {
        return DB::transaction(function () use ($data, $actor, $request): CalendarEvent {
            if (! empty($data['client_token'])) {
                $existing = CalendarEvent::query()->where('organizer_user_id', $actor->id)->where('client_token', $data['client_token'])->first();
                if ($existing) {
                    return $existing->load($this->eventRelations());
                }
            }
            $attendees = $this->normalizeAttendees($data['attendees'] ?? [], $actor);

            if (! $actor->hasPermission('collaboration.manage')) {
                $otherAttendees = collect($attendees)->filter(fn (array $attendee): bool => (int) $attendee['user_id'] !== $actor->id);

                if ($otherAttendees->isNotEmpty()) {
                    throw ValidationException::withMessages(['attendees' => 'Self-service users can create only private self events.']);
                }
            }

            $timezone = (string) ($data['timezone'] ?? 'Asia/Kolkata');
            $startsAt = $this->calendarLifecycle->instant($data['starts_at'], $timezone);
            $endsAt = $this->calendarLifecycle->instant($data['ends_at'], $timezone);
            $participantIds = collect($attendees)->pluck('user_id')->push($actor->id)->unique()->values()->all();

            if (! empty($data['project_id'])) {
                $project = Project::query()->whereKey($data['project_id'])->firstOrFail();

                if (! $this->companyScope->allows($actor, $project->company_id)) {
                    throw ValidationException::withMessages(['project_id' => 'The selected project is not available for your company.']);
                }
            }

            $companyId = $this->companyIdForCreation($actor, null, $project ?? null, $attendees, $data['company_id'] ?? null);

            $guestEmails = collect($data['guests'] ?? [])->pluck('email')->filter()->map(fn ($email) => strtolower(trim((string) $email)))->unique()->all();
            $this->ensureNoCalendarConflicts($participantIds, $startsAt, $endsAt, null, $guestEmails);

            $event = CalendarEvent::create([
                'company_id' => $companyId,
                'project_id' => $data['project_id'] ?? null,
                'organizer_user_id' => $actor->id,
                'event_number' => $this->nextEventNumber(),
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'event_type' => $data['event_type'],
                'status' => 'scheduled',
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'timezone' => $timezone,
                'location' => $data['location'] ?? null,
                'meeting_url' => $data['meeting_url'] ?? null,
                'visibility' => $data['visibility'] ?? 'internal',
                'attendees' => $attendees,
                'reminders' => $data['reminders'] ?? [['minutes_before' => 30]],
                'related_type' => $data['related_type'] ?? null,
                'related_id' => $data['related_id'] ?? null,
                'workflow_history' => [
                    $this->workflowEvent('scheduled', $actor, 'Calendar event scheduled'),
                ],
                'metadata' => $data['metadata'] ?? [],
                'client_token' => $data['client_token'] ?? null,
                'lock_version' => 1,
            ]);

            $event = $this->calendarLifecycle->synchronize($event, $data, $actor);

            $this->auditLogger->record(
                $actor,
                'collaboration.calendar_event.created',
                'Created calendar event',
                $event,
                ['event_number' => $event->event_number, 'event_type' => $event->event_type],
                $request,
            );

            $this->notifyAttendees($event, $actor);

            return $event->load($this->eventRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateCalendarEvent(CalendarEvent $calendarEvent, array $data, User $actor, ?Request $request = null): CalendarEvent
    {
        return DB::transaction(function () use ($calendarEvent, $data, $actor, $request): CalendarEvent {
            $event = CalendarEvent::query()->whereKey($calendarEvent->id)->lockForUpdate()->firstOrFail();
            $this->calendarLifecycle->assertVersion($event, $data['lock_version'] ?? null);

            if ($event->status === 'cancelled') {
                throw ValidationException::withMessages(['event' => 'Cancelled calendar events cannot be updated.']);
            }

            $organizerId = (int) $event->organizer_user_id;
            $attendees = $this->normalizeAttendees($data['attendees'] ?? [], $actor, $organizerId);

            if (! $actor->hasPermission('collaboration.manage')) {
                $otherAttendees = collect($attendees)->filter(fn (array $attendee): bool => (int) $attendee['user_id'] !== $actor->id);

                if ($otherAttendees->isNotEmpty()) {
                    throw ValidationException::withMessages(['attendees' => 'Self-service users can update only private self events.']);
                }
            }

            $timezone = (string) ($data['timezone'] ?? $event->timezone ?? 'Asia/Kolkata');
            $startsAt = $this->calendarLifecycle->instant($data['starts_at'], $timezone);
            $endsAt = $this->calendarLifecycle->instant($data['ends_at'], $timezone);
            $participantIds = collect($attendees)->pluck('user_id')->push($organizerId)->unique()->values()->all();
            $project = null;

            if (! empty($data['project_id'])) {
                $project = Project::query()->whereKey($data['project_id'])->firstOrFail();

                if (! $this->companyScope->allows($actor, $project->company_id)) {
                    throw ValidationException::withMessages(['project_id' => 'The selected project is not available for your company.']);
                }
            }

            $companyId = $this->companyIdForCreation(
                $actor,
                null,
                $project,
                $attendees,
                $data['company_id'] ?? $event->company_id,
            );

            $guestEmails = collect($data['guests'] ?? [])->pluck('email')->filter()->map(fn ($email) => strtolower(trim((string) $email)))->unique()->all();
            $this->ensureNoCalendarConflicts($participantIds, $startsAt, $endsAt, $event->id, $guestEmails);

            $history = $event->workflow_history ?? [];
            $timeChanged = ! $event->starts_at?->equalTo($startsAt) || ! $event->ends_at?->equalTo($endsAt);
            $history[] = $this->workflowEvent(
                $timeChanged ? 'rescheduled' : 'updated',
                $actor,
                $data['note'] ?? ($timeChanged ? 'Calendar event rescheduled' : 'Calendar event updated'),
            );

            $event->forceFill([
                'company_id' => $companyId,
                'project_id' => $data['project_id'] ?? null,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'event_type' => $data['event_type'],
                'status' => $timeChanged ? 'rescheduled' : $event->status,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'timezone' => $timezone,
                'location' => $data['location'] ?? null,
                'meeting_url' => $data['meeting_url'] ?? null,
                'visibility' => $data['visibility'] ?? 'internal',
                'attendees' => $attendees,
                'reminders' => $data['reminders'] ?? [['minutes_before' => 30]],
                'related_type' => $data['related_type'] ?? $event->related_type,
                'related_id' => $data['related_id'] ?? $event->related_id,
                'workflow_history' => $history,
                'metadata' => array_merge($event->metadata ?? [], $data['metadata'] ?? []),
                'lock_version' => ((int) $event->lock_version) + 1,
            ])->save();

            $event = $this->calendarLifecycle->synchronize($event, $data, $actor);

            $this->auditLogger->record(
                $actor,
                'collaboration.calendar_event.updated',
                $timeChanged ? 'Rescheduled calendar event' : 'Updated calendar event',
                $event,
                [
                    'event_number' => $event->event_number,
                    'event_type' => $event->event_type,
                    'status' => $event->status,
                    'starts_at' => $event->starts_at?->toISOString(),
                    'ends_at' => $event->ends_at?->toISOString(),
                ],
                $request,
            );

            $this->notifyAttendees($event, $actor);

            return $event->load($this->eventRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function cancelCalendarEvent(CalendarEvent $calendarEvent, array $data, User $actor, ?Request $request = null): CalendarEvent
    {
        return DB::transaction(function () use ($calendarEvent, $data, $actor, $request): CalendarEvent {
            $event = CalendarEvent::query()->whereKey($calendarEvent->id)->lockForUpdate()->firstOrFail();
            $this->calendarLifecycle->assertVersion($event, $data['lock_version'] ?? null);

            if ($event->status === 'cancelled') {
                throw ValidationException::withMessages(['event' => 'The calendar event is already cancelled.']);
            }

            $history = $event->workflow_history ?? [];
            $history[] = $this->workflowEvent('cancelled', $actor, $data['reason']);

            $event->forceFill([
                'status' => 'cancelled',
                'workflow_history' => $history,
                'lock_version' => ((int) $event->lock_version) + 1,
            ])->save();
            $this->calendarLifecycle->cancelPendingReminders($event);

            $this->auditLogger->record(
                $actor,
                'collaboration.calendar_event.cancelled',
                'Cancelled calendar event',
                $event,
                ['event_number' => $event->event_number],
                $request,
            );

            return $event->load($this->eventRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function completeCalendarEvent(CalendarEvent $calendarEvent, array $data, User $actor, ?Request $request = null): CalendarEvent
    {
        return DB::transaction(function () use ($calendarEvent, $data, $actor, $request): CalendarEvent {
            $event = CalendarEvent::query()->whereKey($calendarEvent->id)->lockForUpdate()->firstOrFail();
            $this->calendarLifecycle->assertVersion($event, $data['lock_version'] ?? null);

            if ($event->status === 'cancelled') {
                throw ValidationException::withMessages(['event' => 'Cancelled calendar events cannot be completed.']);
            }

            if ($event->status === 'completed') {
                throw ValidationException::withMessages(['event' => 'The calendar event is already completed.']);
            }

            $history = $event->workflow_history ?? [];
            $history[] = $this->workflowEvent('completed', $actor, $data['note'] ?? 'Calendar event completed');

            $event->forceFill([
                'status' => 'completed',
                'workflow_history' => $history,
                'lock_version' => ((int) $event->lock_version) + 1,
            ])->save();
            $this->calendarLifecycle->cancelPendingReminders($event);

            $this->auditLogger->record(
                $actor,
                'collaboration.calendar_event.completed',
                'Completed calendar event',
                $event,
                [
                    'event_number' => $event->event_number,
                    'event_type' => $event->event_type,
                    'starts_at' => $event->starts_at?->toISOString(),
                    'ends_at' => $event->ends_at?->toISOString(),
                ],
                $request,
            );

            $this->notifyAttendees($event, $actor);

            return $event->load($this->eventRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function deleteCalendarEvent(CalendarEvent $calendarEvent, array $data, User $actor, ?Request $request = null): CalendarEvent
    {
        return DB::transaction(function () use ($calendarEvent, $data, $actor, $request): CalendarEvent {
            $event = CalendarEvent::query()->whereKey($calendarEvent->id)->lockForUpdate()->firstOrFail();
            $this->calendarLifecycle->assertVersion($event, $data['lock_version'] ?? null);

            $history = $event->workflow_history ?? [];
            $history[] = $this->workflowEvent('archived', $actor, $data['reason'] ?? 'Calendar event archived from Calendar Management screen.');

            $metadata = $event->metadata ?? [];
            $metadata['archived_by_user_id'] = $actor->id;
            $metadata['archived_reason'] = $data['reason'] ?? null;
            $metadata['archived_at'] = now()->toISOString();

            $event->forceFill([
                'status' => 'cancelled',
                'workflow_history' => $history,
                'metadata' => $metadata,
                'lock_version' => ((int) $event->lock_version) + 1,
            ])->save();
            $this->calendarLifecycle->cancelPendingReminders($event);

            $this->auditLogger->record(
                $actor,
                'collaboration.calendar_event.archived',
                'Archived calendar event',
                $event,
                [
                    'event_number' => $event->event_number,
                    'event_type' => $event->event_type,
                    'reason' => $data['reason'] ?? null,
                ],
                $request,
            );

            $event->delete();

            return $event;
        });
    }

    /**
     * @param array<string, mixed> $data
     * @return EloquentCollection<int, CollaborationMessage>
     */
    public function sendMessage(array $data, User $actor, ?Request $request = null): EloquentCollection
    {
        return DB::transaction(function () use ($data, $actor, $request): EloquentCollection {
            $recipients = User::query()
                ->with('role')
                ->whereIn('id', $data['recipient_user_ids'])
                ->lockForUpdate()
                ->get();

            if ($recipients->count() !== count(array_unique($data['recipient_user_ids']))) {
                throw ValidationException::withMessages(['recipient_user_ids' => 'All recipients must exist.']);
            }

            $this->assertInternalRecipients($recipients, $actor);

            $project = null;
            if (! empty($data['project_id'])) {
                $project = Project::query()->whereKey($data['project_id'])->firstOrFail();

                if (! $this->companyScope->allows($actor, $project->company_id)) {
                    throw ValidationException::withMessages(['project_id' => 'The selected project is not available for your company.']);
                }
            }

            $parent = null;
            if (! empty($data['parent_message_id'])) {
                $parent = CollaborationMessage::query()
                    ->whereKey($data['parent_message_id'])
                    ->firstOrFail();

                if (! $parent->isParticipant($actor)) {
                    throw ValidationException::withMessages(['parent_message_id' => 'You can reply only to a message thread where you are a participant.']);
                }
            }

            $companyId = $this->companyIdForCreation(
                $actor,
                null,
                $project,
                $recipients->map(fn (User $recipient): array => ['user_id' => $recipient->id])->all(),
                $data['company_id'] ?? null,
            );

            $threadKey = $parent?->thread_key ?? $this->nextMessageThreadKey();
            $createdMessages = new EloquentCollection();
            $scheduledFor = ! empty($data['scheduled_for']) ? Carbon::parse((string) $data['scheduled_for']) : null;
            $isScheduled = $scheduledFor !== null;
            $sentAt = $isScheduled ? null : now();

            foreach ($recipients as $recipient) {
                if ((int) $recipient->id === (int) $actor->id) {
                    throw ValidationException::withMessages(['recipient_user_ids' => 'Messages cannot be sent to yourself.']);
                }

                if ($recipient->company_id !== null && (int) $recipient->company_id !== (int) $companyId) {
                    throw ValidationException::withMessages(['recipient_user_ids' => 'All recipients must belong to the message company.']);
                }

                $message = CollaborationMessage::create([
                    'company_id' => $companyId,
                    'project_id' => $project?->id,
                    'parent_message_id' => $parent?->id,
                    'sender_user_id' => $actor->id,
                    'recipient_user_id' => $recipient->id,
                    'message_number' => $this->nextMessageNumber(),
                    'thread_key' => $threadKey,
                    'subject' => $data['subject'],
                    'body' => $data['body'],
                    'priority' => $data['priority'] ?? 'normal',
                    'status' => $isScheduled ? 'scheduled' : 'unread',
                    'scheduled_for' => $scheduledFor,
                    'sent_at' => $sentAt,
                    'metadata' => $data['metadata'] ?? [],
                ]);

                $this->auditLogger->record(
                    $actor,
                    $isScheduled ? 'collaboration.message.scheduled' : 'collaboration.message.sent',
                    $isScheduled ? 'Scheduled collaboration mailbox message' : 'Sent collaboration mailbox message',
                    $message,
                    [
                        'message_number' => $message->message_number,
                        'thread_key' => $message->thread_key,
                        'recipient' => $recipient->email,
                        'priority' => $message->priority,
                        'scheduled_for' => $message->scheduled_for?->toISOString(),
                    ],
                    $request,
                );

                if (! $isScheduled) {
                    $this->notifyMailboxRecipient($message, $recipient, $actor);
                }

                $createdMessages->push($message);
            }

            return $createdMessages->load($this->messageRelations());
        });
    }

    public function releaseDueScheduledMessages(?Carbon $now = null): int
    {
        $releasedCount = 0;
        $releaseAt = $now ?? now();

        DB::transaction(function () use ($releaseAt, &$releasedCount): void {
            $messages = CollaborationMessage::query()
                ->with(['sender', 'recipient'])
                ->where('status', 'scheduled')
                ->whereNotNull('scheduled_for')
                ->where('scheduled_for', '<=', $releaseAt)
                ->lockForUpdate()
                ->get();

            foreach ($messages as $message) {
                $metadata = $message->metadata ?? [];
                $metadata['scheduled_release'] = [
                    'released_at' => $releaseAt->toISOString(),
                    'release_source' => 'scheduler',
                ];

                $message->forceFill([
                    'status' => 'unread',
                    'sent_at' => $releaseAt,
                    'metadata' => $metadata,
                ])->save();

                $this->auditLogger->record(
                    $message->sender,
                    'collaboration.message.scheduled_released',
                    'Released scheduled collaboration mailbox message',
                    $message,
                    [
                        'message_number' => $message->message_number,
                        'thread_key' => $message->thread_key,
                        'scheduled_for' => $message->scheduled_for?->toISOString(),
                        'released_at' => $releaseAt->toISOString(),
                    ],
                );

                if ($message->recipient) {
                    $this->notifyMailboxRecipient($message, $message->recipient, $message->sender);
                }

                $releasedCount++;
            }

            $dispatchIds = $messages->pluck('internal_mailbox_dispatch_id')->filter()->unique();
            foreach ($dispatchIds as $dispatchId) {
                $hasScheduledMessages = CollaborationMessage::query()
                    ->where('internal_mailbox_dispatch_id', $dispatchId)
                    ->where('status', 'scheduled')
                    ->exists();

                if (! $hasScheduledMessages) {
                    InternalMailboxDispatch::query()
                        ->whereKey($dispatchId)
                        ->where('state', 'scheduled')
                        ->update(['state' => 'sent', 'sent_at' => $releaseAt, 'failed_at' => null]);
                }
            }
        });

        return $releasedCount;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function cancelScheduledMessage(CollaborationMessage $message, array $data, User $actor, ?Request $request = null): CollaborationMessage
    {
        return DB::transaction(function () use ($message, $data, $actor, $request): CollaborationMessage {
            $lockedMessage = CollaborationMessage::query()
                ->whereKey($message->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $lockedMessage->sender_user_id !== (int) $actor->id || $lockedMessage->status !== 'scheduled') {
                throw ValidationException::withMessages(['message' => 'Only the sender can cancel a scheduled message before it is released.']);
            }

            $metadata = $lockedMessage->metadata ?? [];
            $metadata['scheduled_cancel'] = [
                'cancelled_at' => now()->toISOString(),
                'cancelled_by_user_id' => $actor->id,
                'reason' => $data['reason'] ?? null,
            ];

            $lockedMessage->forceFill([
                'status' => 'cancelled',
                'metadata' => $metadata,
            ])->save();

            $this->auditLogger->record(
                $actor,
                'collaboration.message.scheduled_cancelled',
                'Cancelled scheduled collaboration mailbox message',
                $lockedMessage,
                [
                    'message_number' => $lockedMessage->message_number,
                    'thread_key' => $lockedMessage->thread_key,
                    'scheduled_for' => $lockedMessage->scheduled_for?->toISOString(),
                    'reason' => $data['reason'] ?? null,
                ],
                $request,
            );

            return $lockedMessage->load($this->messageRelations());
        });
    }

    public function markMessageRead(CollaborationMessage $message, User $actor, ?Request $request = null): CollaborationMessage
    {
        return DB::transaction(function () use ($message, $actor, $request): CollaborationMessage {
            $lockedMessage = CollaborationMessage::query()
                ->whereKey($message->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $lockedMessage->recipient_user_id !== (int) $actor->id) {
                throw ValidationException::withMessages(['message' => 'Only the recipient can mark this message as read.']);
            }

            if (! in_array($lockedMessage->status, ['unread', 'read'], true)) {
                throw ValidationException::withMessages(['message' => 'Only delivered mailbox messages can be marked as read.']);
            }

            if ($lockedMessage->status !== 'archived') {
                $lockedMessage->forceFill([
                    'status' => 'read',
                    'read_at' => $lockedMessage->read_at ?? now(),
                ])->save();

                $this->auditLogger->record(
                    $actor,
                    'collaboration.message.read',
                    'Marked collaboration mailbox message as read',
                    $lockedMessage,
                    ['message_number' => $lockedMessage->message_number, 'thread_key' => $lockedMessage->thread_key],
                    $request,
                );
            }

            return $lockedMessage->load($this->messageRelations());
        });
    }

    public function archiveMessage(CollaborationMessage $message, User $actor, ?Request $request = null): CollaborationMessage
    {
        return DB::transaction(function () use ($message, $actor, $request): CollaborationMessage {
            $lockedMessage = CollaborationMessage::query()
                ->whereKey($message->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $lockedMessage->recipient_user_id !== (int) $actor->id) {
                throw ValidationException::withMessages(['message' => 'Only the recipient can archive this message.']);
            }

            if ($lockedMessage->status === 'scheduled') {
                throw ValidationException::withMessages(['message' => 'Scheduled messages cannot be archived before they are released.']);
            }

            $lockedMessage->forceFill([
                'status' => 'archived',
                'read_at' => $lockedMessage->read_at ?? now(),
                'recipient_archived_at' => $lockedMessage->recipient_archived_at ?? now(),
            ])->save();

            $this->auditLogger->record(
                $actor,
                'collaboration.message.archived',
                'Archived collaboration mailbox message',
                $lockedMessage,
                ['message_number' => $lockedMessage->message_number, 'thread_key' => $lockedMessage->thread_key],
                $request,
            );

            return $lockedMessage->load($this->messageRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateMessageCrmLink(CollaborationMessage $message, array $data, User $actor, ?Request $request = null): CollaborationMessage
    {
        return DB::transaction(function () use ($message, $data, $actor, $request): CollaborationMessage {
            $lockedMessage = CollaborationMessage::query()
                ->whereKey($message->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedMessage->isParticipant($actor)) {
                throw ValidationException::withMessages(['message' => 'Only mailbox message participants can update CRM links.']);
            }

            if (! $this->companyScope->allows($actor, $lockedMessage->company_id)) {
                throw ValidationException::withMessages(['message' => 'The mailbox message is outside your company scope.']);
            }

            $metadata = $lockedMessage->metadata ?? [];

            if (($data['action'] ?? null) === 'unlink') {
                unset($metadata['crm_link']);

                $lockedMessage->forceFill([
                    'metadata' => $metadata,
                ])->save();

                $this->auditLogger->record(
                    $actor,
                    'collaboration.message.crm_unlinked',
                    'Unlinked collaboration mailbox message from CRM record',
                    $lockedMessage,
                    [
                        'message_number' => $lockedMessage->message_number,
                        'thread_key' => $lockedMessage->thread_key,
                    ],
                    $request,
                );

                return $lockedMessage->load($this->messageRelations());
            }

            $recordType = (string) ($data['record_type'] ?? '');
            $recordId = (int) ($data['record_id'] ?? 0);
            $record = $this->crmRecordForLink($recordType, $recordId);

            if (! $record) {
                throw ValidationException::withMessages(['record_id' => 'The selected CRM record was not found.']);
            }

            $recordCompanyId = $this->companyIdForCrmRecord($record);

            if (! $recordCompanyId || (int) $recordCompanyId !== (int) $lockedMessage->company_id) {
                throw ValidationException::withMessages(['record_id' => 'The selected CRM record must belong to the same company as the mailbox message.']);
            }

            if (! $this->companyScope->allows($actor, $recordCompanyId)) {
                throw ValidationException::withMessages(['record_id' => 'The selected CRM record is outside your company scope.']);
            }

            $metadata['crm_link'] = [
                'record_type' => $recordType,
                'record_id' => $recordId,
                'label' => $this->crmRecordLabel($recordType, $record),
                'linked_by_user_id' => $actor->id,
                'linked_by_name' => $actor->name,
                'linked_at' => now()->toISOString(),
                'note' => trim((string) ($data['note'] ?? '')) ?: null,
            ];

            $lockedMessage->forceFill([
                'metadata' => $metadata,
            ])->save();

            $this->auditLogger->record(
                $actor,
                'collaboration.message.crm_linked',
                'Linked collaboration mailbox message to CRM record',
                $lockedMessage,
                [
                    'message_number' => $lockedMessage->message_number,
                    'thread_key' => $lockedMessage->thread_key,
                    'record_type' => $recordType,
                    'record_id' => $recordId,
                ],
                $request,
            );

            return $lockedMessage->load($this->messageRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateMessageState(CollaborationMessage $message, array $data, User $actor, ?Request $request = null): CollaborationMessage
    {
        return DB::transaction(function () use ($message, $data, $actor, $request): CollaborationMessage {
            $lockedMessage = CollaborationMessage::query()
                ->whereKey($message->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedMessage->isParticipant($actor)) {
                throw ValidationException::withMessages(['message' => 'Only mailbox message participants can update mailbox state.']);
            }

            if (! $this->companyScope->allows($actor, $lockedMessage->company_id)) {
                throw ValidationException::withMessages(['message' => 'The mailbox message is outside your company scope.']);
            }

            if ($actor->hasPermission('partner.portal') || $actor->hasPermission('buyer.view')) {
                throw ValidationException::withMessages(['message' => 'External portal users cannot update internal mailbox state.']);
            }

            $action = (string) $data['action'];
            $metadata = $lockedMessage->metadata ?? [];
            $allUserState = is_array($metadata['mailbox_user_state'] ?? null) ? $metadata['mailbox_user_state'] : [];
            $userKey = 'user_'.$actor->id;
            $legacyUserKey = (string) $actor->id;
            $userState = is_array($allUserState[$userKey] ?? null)
                ? $allUserState[$userKey]
                : (is_array($allUserState[$legacyUserKey] ?? null) ? $allUserState[$legacyUserKey] : []);

            if ($action === 'set_flags') {
                if (array_key_exists('starred', $data)) {
                    $userState['starred'] = (bool) $data['starred'];
                }

                if (array_key_exists('important', $data)) {
                    $userState['important'] = (bool) $data['important'];
                }
            }

            if ($action === 'set_labels') {
                $userState['labels'] = collect($data['labels'] ?? [])
                    ->map(fn (string $label): string => trim($label))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();
            }

            if ($action === 'move') {
                $folder = (string) $data['folder'];

                if ($folder === 'inbox') {
                    unset($userState['folder']);
                } else {
                    $userState['folder'] = $folder;
                }
            }

            if ($action === 'snooze') {
                $snoozedUntil = Carbon::parse((string) $data['snoozed_until']);

                $userState['folder'] = 'snoozed';
                $userState['snoozed_until'] = $snoozedUntil->toISOString();

                if (array_key_exists('note', $data) && trim((string) $data['note']) !== '') {
                    $userState['snooze_note'] = trim((string) $data['note']);
                }
            }

            if ($action === 'mark_read' || $action === 'mark_unread') {
                if ((int) $lockedMessage->recipient_user_id !== (int) $actor->id) {
                    throw ValidationException::withMessages(['action' => 'Only the message recipient can change read or unread state.']);
                }

                $lockedMessage->forceFill([
                    'status' => $action === 'mark_read' ? 'read' : 'unread',
                    'read_at' => $action === 'mark_read' ? ($lockedMessage->read_at ?? now()) : null,
                ]);
            }

            $userState['updated_at'] = now()->toISOString();
            $userState['updated_by_user_id'] = $actor->id;
            unset($allUserState[$legacyUserKey]);
            $allUserState[$userKey] = $userState;
            $metadata['mailbox_user_state'] = $allUserState;

            $lockedMessage->forceFill([
                'metadata' => $metadata,
            ])->save();

            $this->auditLogger->record(
                $actor,
                'collaboration.message.mailbox_state_updated',
                'Updated collaboration mailbox message user state',
                $lockedMessage,
                [
                    'message_number' => $lockedMessage->message_number,
                    'thread_key' => $lockedMessage->thread_key,
                    'action' => $action,
                    'folder' => $data['folder'] ?? null,
                    'labels_count' => count($userState['labels'] ?? []),
                    'starred' => $userState['starred'] ?? null,
                    'important' => $userState['important'] ?? null,
                    'snoozed_until' => $userState['snoozed_until'] ?? null,
                    'snooze_note_present' => isset($userState['snooze_note']),
                ],
                $request,
            );

            return $lockedMessage->load($this->messageRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateMessageReaction(CollaborationMessage $message, array $data, User $actor, ?Request $request = null): CollaborationMessage
    {
        return DB::transaction(function () use ($message, $data, $actor, $request): CollaborationMessage {
            $lockedMessage = CollaborationMessage::query()
                ->whereKey($message->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedMessage->isParticipant($actor)) {
                throw ValidationException::withMessages(['message' => 'Only mailbox message participants can react to this message.']);
            }

            if (! $this->companyScope->allows($actor, $lockedMessage->company_id)) {
                throw ValidationException::withMessages(['message' => 'The mailbox message is outside your company scope.']);
            }

            if ($actor->hasPermission('partner.portal') || $actor->hasPermission('buyer.view')) {
                throw ValidationException::withMessages(['message' => 'External portal users cannot react to internal mailbox messages.']);
            }

            if (! in_array($lockedMessage->status, ['unread', 'read', 'archived'], true)) {
                throw ValidationException::withMessages(['message' => 'Only delivered mailbox messages can receive reactions.']);
            }

            $emoji = trim((string) $data['emoji']);
            $action = (string) $data['action'];
            $metadata = $lockedMessage->metadata ?? [];
            $allReactions = is_array($metadata['reactions'] ?? null) ? $metadata['reactions'] : [];
            $reactionRows = collect(is_array($allReactions[$emoji] ?? null) ? $allReactions[$emoji] : [])
                ->filter(fn ($row): bool => is_array($row))
                ->values();
            $alreadyReacted = $reactionRows->contains(function (array $row) use ($actor): bool {
                return (int) ($row['user_id'] ?? 0) === (int) $actor->id
                    || (string) ($row['user_key'] ?? '') === 'user_'.$actor->id;
            });

            $reactionRows = $reactionRows
                ->reject(function (array $row) use ($actor): bool {
                    return (int) ($row['user_id'] ?? 0) === (int) $actor->id
                        || (string) ($row['user_key'] ?? '') === 'user_'.$actor->id;
                })
                ->values();

            $shouldAdd = $action === 'add' || ($action === 'toggle' && ! $alreadyReacted);

            if ($shouldAdd) {
                $reactionRows->push([
                    'user_id' => $actor->id,
                    'user_key' => 'user_'.$actor->id,
                    'user_name' => $actor->name,
                    'emoji' => $emoji,
                    'reacted_at' => now()->toISOString(),
                ]);
            }

            if ($reactionRows->isEmpty()) {
                unset($allReactions[$emoji]);
            } else {
                $allReactions[$emoji] = $reactionRows->values()->all();
            }

            $metadata['reactions'] = $allReactions;

            $lockedMessage->forceFill([
                'metadata' => $metadata,
            ])->save();

            $this->auditLogger->record(
                $actor,
                'collaboration.message.reaction_updated',
                'Updated collaboration mailbox message reaction',
                $lockedMessage,
                [
                    'message_number' => $lockedMessage->message_number,
                    'thread_key' => $lockedMessage->thread_key,
                    'emoji' => $emoji,
                    'action' => $action,
                    'reacted' => $shouldAdd,
                    'reaction_count' => count($allReactions[$emoji] ?? []),
                ],
                $request,
            );

            return $lockedMessage->load($this->messageRelations());
        });
    }

    /**
     * @param array<int, array<string, mixed>> $checklist
     * @return array<int, array<string, mixed>>
     */
    private function normalizeChecklist(array $checklist): array
    {
        return collect($checklist)
            ->map(fn (array $item): array => [
                'label' => $item['label'] ?? $item['text'],
                'done' => (bool) ($item['done'] ?? false),
            ])
            ->values()
            ->all();
    }

    private function crmRecordForLink(string $type, int $id): ?Model
    {
        return match ($type) {
            'project' => Project::query()->whereKey($id)->first(),
            'lead' => Lead::query()->with('customer:id,name,code')->whereKey($id)->first(),
            'booking' => Booking::query()->with(['customer:id,name,code', 'project:id,code,name'])->whereKey($id)->first(),
            'customer' => Customer::query()
                ->whereKey($id)
                ->where(function (Builder $query): void {
                    $query->whereHas('leads')
                        ->orWhereHas('bookings');
                })
                ->first(),
            default => null,
        };
    }

    private function companyIdForCrmRecord(Model $record): ?int
    {
        if ($record instanceof Customer) {
            $companyId = $record->bookings()->value('company_id')
                ?? $record->leads()->value('company_id');

            return $companyId ? (int) $companyId : null;
        }

        $companyId = $record->getAttribute('company_id');

        return $companyId ? (int) $companyId : null;
    }

    private function crmRecordLabel(string $type, Model $record): string
    {
        if ($record instanceof Project) {
            return trim($record->code.' · '.$record->name, ' ·');
        }

        if ($record instanceof Lead) {
            return trim($record->lead_code.' · '.($record->customer?->name ?? 'Lead'), ' ·');
        }

        if ($record instanceof Booking) {
            $parts = array_filter([
                $record->booking_code,
                $record->customer?->name,
                $record->project?->code,
            ]);

            return implode(' · ', $parts);
        }

        if ($record instanceof Customer) {
            return trim(($record->code ? $record->code.' · ' : '').$record->name);
        }

        return ucfirst($type).' #'.$record->getKey();
    }

    /**
     * @param array<int, int> $mentions
     * @return array<int, int>
     */
    private function validateTaskMentionIds(array $mentions, WorkTask $task): array
    {
        $mentionIds = collect($mentions)
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($mentionIds->isEmpty()) {
            return [];
        }

        $validCount = User::query()
            ->whereIn('id', $mentionIds->all())
            ->where('company_id', $task->company_id)
            ->count();

        if ($validCount !== $mentionIds->count()) {
            throw ValidationException::withMessages(['mentions' => 'Mentioned users must belong to the task company.']);
        }

        return $mentionIds->all();
    }

    private function validatedSubtaskAssigneeId(int|string|null $assigneeId, WorkTask $task): ?int
    {
        if (! $assigneeId) {
            return null;
        }

        $assignee = User::query()->whereKey($assigneeId)->firstOrFail();

        if ((int) $assignee->company_id !== (int) $task->company_id) {
            throw ValidationException::withMessages(['assigned_to_user_id' => 'The subtask assignee must belong to the task company.']);
        }

        return (int) $assignee->id;
    }

    /**
     * @param array<int, array<string, mixed>> $attendees
     * @return array<int, array<string, mixed>>
     */
    private function normalizeAttendees(array $attendees, User $actor, ?int $requiredUserId = null): array
    {
        return collect($attendees)
            ->push(['user_id' => $requiredUserId ?? $actor->id, 'response' => 'accepted'])
            ->unique(fn (array $attendee): int => (int) $attendee['user_id'])
            ->map(function (array $attendee) use ($actor): array {
                $user = User::query()->whereKey((int) $attendee['user_id'])->firstOrFail();
                $attendeeCompanyId = $user->company_id
                    ?? ((int) $user->id === (int) $actor->id ? $this->companyScope->companyIdFor($actor) : null);

                if (! $this->companyScope->allows($actor, $attendeeCompanyId)) {
                    throw ValidationException::withMessages(['attendees' => 'All attendees must belong to your company.']);
                }

                return [
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'response' => $attendee['response'] ?? 'pending',
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param array<int, int> $participantIds
     */
    private function ensureNoCalendarConflicts(array $participantIds, Carbon $startsAt, Carbon $endsAt, ?int $ignoreEventId = null, array $guestEmails = []): void
    {
        $candidateEvents = CalendarEvent::query()
            ->whereIn('status', ['scheduled', 'rescheduled'])
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->when($ignoreEventId !== null, fn ($query) => $query->whereKeyNot($ignoreEventId))
            ->with(['organizer:id,name', 'attendeeRecords:id,calendar_event_id,user_id,name'])
            ->get();

        foreach ($candidateEvents as $event) {
            $eventParticipantIds = collect($event->attendees ?? [])
                ->pluck('user_id')
                ->push($event->organizer_user_id)
                ->unique()
                ->values();

            $eventGuestEmails = $event->attendeeRecords->where('attendee_type', 'guest')->pluck('email')->map(fn ($email) => strtolower((string) $email));
            $conflictingGuestEmails = $eventGuestEmails->intersect($guestEmails);

            if ($eventParticipantIds->intersect($participantIds)->isNotEmpty() || $conflictingGuestEmails->isNotEmpty()) {
                $conflictingIds = $eventParticipantIds->intersect($participantIds);
                $names = $event->attendeeRecords->whereIn('user_id', $conflictingIds)->pluck('name')
                    ->merge($event->attendeeRecords->whereIn('email', $conflictingGuestEmails)->pluck('name'));
                if ($conflictingIds->contains($event->organizer_user_id) && $event->organizer) {
                    $names->push($event->organizer->name);
                }
                $names = $names->filter()->unique()->take(3)->implode(', ');
                throw ValidationException::withMessages([
                    'starts_at' => 'Scheduling conflict with '.$event->event_number.' from '.$event->starts_at->setTimezone($event->timezone)->format('d M, g:i A').' to '.$event->ends_at->setTimezone($event->timezone)->format('g:i A').'.',
                    'attendees' => ($names ?: 'A selected participant').' is already scheduled during this time.',
                ]);
            }
        }
    }

    private function notifyAttendees(CalendarEvent $event, User $actor): void
    {
        collect($event->attendees ?? [])
            ->pluck('user_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->reject(fn (int $userId): bool => $userId === $actor->id)
            ->each(function (int $userId) use ($event, $actor): void {
                $recipient = User::query()->whereKey($userId)->first();

                if (! $recipient) {
                    return;
                }

                $this->notifications->sendToUser($recipient, [
                    'category' => 'calendar',
                    'severity' => 'info',
                    'title' => "Calendar event {$event->event_number}",
                    'body' => $event->title,
                    'action_url' => '/collaboration/calendar-events?date_from='.$event->starts_at->toDateString(),
                    'payload' => [
                        'event_number' => $event->event_number,
                        'starts_at' => $event->starts_at->toISOString(),
                    ],
                ], $actor, $event);
            });
    }

    private function notifyTaskTransferApprovers(WorkTask $task, WorkTaskTransferRequest $transferRequest, User $actor): void
    {
        User::query()
            ->with('role')
            ->where('company_id', $task->company_id)
            ->where('status', 'active')
            ->get()
            ->filter(fn (User $recipient): bool => $recipient->id !== $actor->id && $recipient->hasPermission('collaboration.manage'))
            ->each(function (User $recipient) use ($task, $transferRequest, $actor): void {
                $this->notifications->sendToUser($recipient, [
                    'category' => 'collaboration',
                    'severity' => $task->priority === 'critical' ? 'critical' : 'info',
                    'title' => "Transfer approval needed for {$task->task_number}",
                    'body' => $task->title,
                    'action_url' => '/collaboration/tasks?transfer_request='.$transferRequest->id,
                    'payload' => [
                        'task_number' => $task->task_number,
                        'transfer_request_id' => $transferRequest->id,
                        'from_user_id' => $transferRequest->from_user_id,
                        'to_user_id' => $transferRequest->to_user_id,
                    ],
                ], $actor, $task);
            });
    }

    private function notifyTaskTransferResolution(WorkTask $task, WorkTaskTransferRequest $transferRequest, User $actor, string $action): void
    {
        collect([$transferRequest->requested_by_user_id, $transferRequest->from_user_id, $transferRequest->to_user_id])
            ->filter()
            ->unique()
            ->reject(fn (int $userId): bool => $userId === $actor->id)
            ->each(function (int $userId) use ($task, $transferRequest, $actor, $action): void {
                $recipient = User::query()->whereKey($userId)->first();

                if (! $recipient) {
                    return;
                }

                $this->notifications->sendToUser($recipient, [
                    'category' => 'collaboration',
                    'severity' => $action === 'approved' ? 'info' : 'warning',
                    'title' => "Task transfer {$action}: {$task->task_number}",
                    'body' => $task->title,
                    'action_url' => '/collaboration/tasks?q='.$task->task_number,
                    'payload' => [
                        'task_number' => $task->task_number,
                        'transfer_request_id' => $transferRequest->id,
                        'status' => $action,
                    ],
                ], $actor, $task);
            });
    }

    /**
     * @param EloquentCollection<int, User> $recipients
     */
    private function assertInternalRecipients(EloquentCollection $recipients, User $actor): void
    {
        foreach ($recipients as $recipient) {
            if ($recipient->status !== 'active') {
                throw ValidationException::withMessages(['recipient_user_ids' => 'All recipients must be active users.']);
            }

            if ($recipient->hasPermission('partner.portal') || $recipient->hasPermission('buyer.view')) {
                throw ValidationException::withMessages(['recipient_user_ids' => 'Mailbox messages can be sent only to internal users.']);
            }

            if (! $this->companyScope->allows($actor, $recipient->company_id)) {
                throw ValidationException::withMessages(['recipient_user_ids' => 'All recipients must belong to your company.']);
            }
        }
    }

    /**
     * @param array<int, array<string, mixed>> $attendees
     */
    private function companyIdForCreation(User $actor, ?User $assignee = null, ?Project $project = null, array $attendees = [], int|string|null $explicitCompanyId = null): int
    {
        if ($explicitCompanyId !== null) {
            if (! $this->companyScope->allows($actor, $explicitCompanyId)) {
                throw ValidationException::withMessages(['company_id' => 'The selected company is outside your company scope.']);
            }

            if ($project && (int) $project->company_id !== (int) $explicitCompanyId) {
                throw ValidationException::withMessages(['company_id' => 'The selected company must match the selected project company.']);
            }

            if ($assignee?->company_id !== null && (int) $assignee->company_id !== (int) $explicitCompanyId) {
                throw ValidationException::withMessages(['assigned_to_user_id' => 'The assignee must belong to the selected company.']);
            }

            $mismatchedAttendeeExists = collect($attendees)
                ->pluck('user_id')
                ->map(fn (int $userId): ?int => User::query()->whereKey($userId)->value('company_id'))
                ->filter()
                ->contains(fn (int $companyId): bool => (int) $companyId !== (int) $explicitCompanyId);

            if ($mismatchedAttendeeExists) {
                throw ValidationException::withMessages(['attendees' => 'All attendees must belong to the selected company.']);
            }

            return (int) $explicitCompanyId;
        }

        $attendeeCompanyId = collect($attendees)
            ->pluck('user_id')
            ->map(fn (int $userId): ?int => User::query()->whereKey($userId)->value('company_id'))
            ->filter()
            ->first();

        $companyId = $project?->company_id
            ?? $assignee?->company_id
            ?? $this->companyScope->companyIdFor($actor)
            ?? $attendeeCompanyId;

        if (! $companyId) {
            throw ValidationException::withMessages([
                'company_id' => 'A company assignment is required before creating collaboration records.',
            ]);
        }

        if (! $this->companyScope->allows($actor, $companyId)) {
            throw ValidationException::withMessages([
                'company_id' => 'The selected company is outside your company scope.',
            ]);
        }

        return (int) $companyId;
    }

    /**
     * @return array<string, string>
     */
    private function workflowEvent(string $status, User $actor, string $note): array
    {
        return [
            'status' => $status,
            'actor_user_id' => $actor->id,
            'actor' => $actor->name,
            'note' => $note,
            'at' => now()->toISOString(),
        ];
    }

    private function nextTaskNumber(): string
    {
        return sprintf('TSK-%05d', WorkTask::query()->withTrashed()->count() + 10001);
    }

    private function nextEventNumber(): string
    {
        return sprintf('CAL-%05d', CalendarEvent::query()->withTrashed()->count() + 10001);
    }

    private function nextMessageNumber(): string
    {
        return sprintf('MSG-%05d', CollaborationMessage::query()->withTrashed()->count() + 10001);
    }

    private function nextMessageThreadKey(): string
    {
        return sprintf('THR-%05d', CollaborationMessage::query()->withTrashed()->distinct('thread_key')->count('thread_key') + 10001);
    }

    private function notifyMailboxRecipient(CollaborationMessage $message, User $recipient, ?User $actor): void
    {
        $this->notifications->sendToUser($recipient, [
            'category' => 'mailbox',
            'severity' => $message->priority === 'critical' ? 'critical' : 'info',
            'title' => 'Mailbox message: '.$message->subject,
            'body' => Str::limit($message->body, 180),
            'action_url' => '/collaboration/messages?folder=inbox&thread_key='.$message->thread_key,
            'payload' => [
                'message_number' => $message->message_number,
                'thread_key' => $message->thread_key,
            ],
        ], $actor, $message);
    }

    /**
     * @return array<int, string>
     */
    public function taskRelations(): array
    {
        return ['company', 'project', 'createdBy', 'assignedTo', 'assignees', 'attachments.uploadedBy', 'comments.author', 'subtasks.assignedTo', 'subtasks.createdBy', 'timeLogs.user', 'transferRequests.requestedBy', 'transferRequests.fromUser', 'transferRequests.toUser', 'transferRequests.approvedBy', 'completionApprovals.requestedBy', 'completionApprovals.decidedBy', 'recurrenceRule'];
    }

    private function authorizeTaskAction(User $actor, string $ability, WorkTask $task): void
    {
        if (! $actor->can($ability, $task)) {
            throw new AuthorizationException('This task action is not available for your role.');
        }
    }

    /**
     * @return array<int, string>
     */
    public function transferRequestRelations(): array
    {
        return ['company', 'workTask', 'requestedBy', 'fromUser', 'toUser', 'approvedBy'];
    }

    /**
     * @return array<int, string>
     */
    public function eventRelations(): array
    {
        return ['company', 'project', 'organizer', 'attendeeRecords.user', 'recurrenceRule', 'reminderDeliveries', 'attachments.uploadedBy'];
    }

    /**
     * @return array<int, string>
     */
    public function messageRelations(): array
    {
        return ['company', 'project', 'sender', 'recipient', 'chatConversation'];
    }
}
