<?php

namespace App\Domain\Collaboration\Services;

use App\Models\User;
use App\Models\WorkTask;
use App\Models\WorkTaskCompletionApproval;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\NotificationCenterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TaskCompletionApprovalService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly NotificationCenterService $notifications,
        private readonly TaskRecurrenceService $recurrence,
    ) {}

    public function request(WorkTask $workTask, User $actor, string $note, ?Request $request = null): WorkTaskCompletionApproval
    {
        return DB::transaction(function () use ($workTask, $actor, $note, $request): WorkTaskCompletionApproval {
            $task = WorkTask::query()->whereKey($workTask->id)->lockForUpdate()->firstOrFail();
            $pending = WorkTaskCompletionApproval::query()->where('work_task_id', $task->id)->where('status', 'pending')->lockForUpdate()->first();
            if ($pending) { return $pending; }

            $approval = WorkTaskCompletionApproval::create([
                'company_id' => $task->company_id,
                'work_task_id' => $task->id,
                'requested_by_user_id' => $actor->id,
                'status' => 'pending',
                'request_note' => $note,
            ]);
            $history = $task->workflow_history ?? [];
            $history[] = ['status' => 'waiting_approval', 'actor_user_id' => $actor->id, 'actor' => $actor->name, 'note' => $note, 'at' => now()->toISOString()];
            $metadata = $task->metadata ?? [];
            $metadata['completion_approval'] = ['requested_by_user_id' => $actor->id, 'requested_at' => now()->toISOString(), 'note' => $note];
            $task->forceFill(['status' => 'waiting_approval', 'workflow_history' => $history, 'metadata' => $metadata])->save();

            User::query()->with('role')->where('company_id', $task->company_id)->where('status', 'active')->get()
                ->filter(fn (User $recipient): bool => $recipient->id !== $actor->id && $recipient->hasPermission('collaboration.manage'))
                ->each(fn (User $recipient) => $this->notifications->sendToUser($recipient, [
                    'category' => 'task', 'severity' => $task->priority === 'critical' ? 'critical' : 'warning',
                    'title' => "Completion approval needed for {$task->task_number}", 'body' => $task->title,
                    'action_url' => route('collaboration.tasks.index', ['task_id' => $task->id], false),
                    'payload' => ['task_id' => $task->id, 'approval_id' => $approval->id, 'approval_type' => 'completion'],
                ], $actor, $task));
            $this->audit->record($actor, 'collaboration.task.completion_requested', 'Requested task completion approval', $task, ['approval_id' => $approval->id], $request);

            return $approval;
        });
    }

    public function decide(WorkTaskCompletionApproval $approval, User $actor, string $decision, string $note, ?Request $request = null): WorkTask
    {
        return DB::transaction(function () use ($approval, $actor, $decision, $note, $request): WorkTask {
            $approval = WorkTaskCompletionApproval::query()->whereKey($approval->id)->lockForUpdate()->firstOrFail();
            if ($approval->status !== 'pending') {
                throw ValidationException::withMessages(['approval' => 'This completion request has already been decided.']);
            }
            $task = WorkTask::query()->whereKey($approval->work_task_id)->lockForUpdate()->firstOrFail();
            $status = $decision === 'approve' ? 'completed' : 'rejected';
            $approval->forceFill(['status' => $decision === 'approve' ? 'approved' : 'rejected', 'decided_by_user_id' => $actor->id, 'decision_note' => $note, 'decided_at' => now()])->save();
            $history = $task->workflow_history ?? [];
            $history[] = ['status' => $status, 'actor_user_id' => $actor->id, 'actor' => $actor->name, 'note' => $note, 'at' => now()->toISOString()];
            $task->forceFill(['status' => $status, 'completed_at' => $status === 'completed' ? now() : null, 'workflow_history' => $history])->save();
            if ($status === 'completed') {
                $this->recurrence->synchronize($task);
                $this->recurrence->generateNextForTask($task);
            }
            $requester = $approval->requestedBy()->first();
            if ($requester && $requester->id !== $actor->id) {
                $this->notifications->sendToUser($requester, [
                    'category' => 'task', 'severity' => $status === 'completed' ? 'success' : 'warning',
                    'title' => "Task {$task->task_number} completion {$approval->status}", 'body' => $note,
                    'action_url' => route('collaboration.tasks.index', ['task_id' => $task->id], false),
                    'payload' => ['task_id' => $task->id, 'approval_id' => $approval->id],
                ], $actor, $task);
            }
            $this->audit->record($actor, 'collaboration.task.completion_'.$approval->status, 'Decided task completion request', $task, ['approval_id' => $approval->id, 'decision' => $decision], $request);
            return $task->load(['completionApprovals.requestedBy', 'completionApprovals.decidedBy']);
        });
    }

    public function reopen(WorkTask $workTask, User $actor, string $note, ?Request $request = null): WorkTask
    {
        return DB::transaction(function () use ($workTask, $actor, $note, $request): WorkTask {
            $task = WorkTask::query()->whereKey($workTask->id)->lockForUpdate()->firstOrFail();
            if ($task->status !== 'rejected') {
                throw ValidationException::withMessages(['task' => 'Only a rejected task can be reopened.']);
            }
            $history = $task->workflow_history ?? [];
            $history[] = ['status' => 'in_progress', 'actor_user_id' => $actor->id, 'actor' => $actor->name, 'note' => $note, 'at' => now()->toISOString()];
            $task->forceFill(['status' => 'in_progress', 'workflow_history' => $history])->save();
            $this->audit->record($actor, 'collaboration.task.reopened', 'Reopened rejected task', $task, [], $request);
            return $task;
        });
    }
}
