<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkTaskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'task_number' => $this->task_number,
            'lock_version' => (int) $this->lock_version,
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priority,
            'status' => $this->status,
            'due_at' => $this->due_at?->toISOString(),
            'started_at' => $this->started_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'module_context' => $this->module_context,
            'related_type' => $this->related_type,
            'related_id' => $this->related_id,
            'checklist' => $this->checklist ?? [],
            'attachments' => $this->whenLoaded('attachments', fn () => $this->attachments->map(fn ($attachment): array => [
                'id' => $attachment->id,
                'filename' => $attachment->original_filename,
                'mime_type' => $attachment->mime_type,
                'size_bytes' => $attachment->size_bytes,
                'scan_status' => $attachment->scan_status,
                'download_url' => route('collaboration.tasks.attachments.download', [$this->resource, $attachment]),
                'uploaded_at' => $attachment->created_at?->toISOString(),
            ])->values()->all()),
            'comments' => $this->whenLoaded('comments', fn () => $this->comments
                ->map(fn ($comment): array => [
                    'id' => $comment->id,
                    'body' => $comment->body,
                    'mentions' => $comment->mentions ?? [],
                    'author' => $comment->relationLoaded('author') && $comment->author ? [
                        'id' => $comment->author->id,
                        'name' => $comment->author->name,
                        'email' => $comment->author->email,
                    ] : null,
                    'created_at' => $comment->created_at?->toISOString(),
                    'updated_at' => $comment->updated_at?->toISOString(),
                ])
                ->values()
                ->all()),
            'subtasks' => $this->whenLoaded('subtasks', fn () => $this->subtasks
                ->map(fn ($subtask): array => [
                    'id' => $subtask->id,
                    'title' => $subtask->title,
                    'status' => $subtask->status,
                    'priority' => $subtask->priority,
                    'due_at' => $subtask->due_at?->toISOString(),
                    'completed_at' => $subtask->completed_at?->toISOString(),
                    'assigned_to' => $subtask->relationLoaded('assignedTo') && $subtask->assignedTo ? [
                        'id' => $subtask->assignedTo->id,
                        'name' => $subtask->assignedTo->name,
                        'email' => $subtask->assignedTo->email,
                    ] : null,
                    'created_by' => $subtask->relationLoaded('createdBy') && $subtask->createdBy ? [
                        'id' => $subtask->createdBy->id,
                        'name' => $subtask->createdBy->name,
                        'email' => $subtask->createdBy->email,
                    ] : null,
                    'metadata' => $subtask->metadata ?? [],
                    'created_at' => $subtask->created_at?->toISOString(),
                    'updated_at' => $subtask->updated_at?->toISOString(),
                ])
                ->values()
                ->all()),
            'time_logs' => $this->whenLoaded('timeLogs', fn () => $this->timeLogs
                ->map(fn ($timeLog): array => [
                    'id' => $timeLog->id,
                    'user' => $timeLog->relationLoaded('user') && $timeLog->user ? [
                        'id' => $timeLog->user->id,
                        'name' => $timeLog->user->name,
                        'email' => $timeLog->user->email,
                    ] : null,
                    'logged_on' => $timeLog->logged_on?->toDateString(),
                    'minutes' => $timeLog->minutes,
                    'hours' => round($timeLog->minutes / 60, 2),
                    'note' => $timeLog->note,
                    'source' => $timeLog->source,
                    'metadata' => $timeLog->metadata ?? [],
                    'created_at' => $timeLog->created_at?->toISOString(),
                    'updated_at' => $timeLog->updated_at?->toISOString(),
                ])
                ->values()
                ->all()),
            'transfer_requests' => $this->whenLoaded('transferRequests', fn () => $this->transferRequests
                ->map(fn ($transfer): array => [
                    'id' => $transfer->id,
                    'status' => $transfer->status,
                    'reason' => $transfer->reason,
                    'approval_note' => $transfer->approval_note,
                    'requested_at' => $transfer->requested_at?->toISOString(),
                    'resolved_at' => $transfer->resolved_at?->toISOString(),
                    'requested_by' => $transfer->relationLoaded('requestedBy') && $transfer->requestedBy ? [
                        'id' => $transfer->requestedBy->id,
                        'name' => $transfer->requestedBy->name,
                        'email' => $transfer->requestedBy->email,
                    ] : null,
                    'from_user' => $transfer->relationLoaded('fromUser') && $transfer->fromUser ? [
                        'id' => $transfer->fromUser->id,
                        'name' => $transfer->fromUser->name,
                        'email' => $transfer->fromUser->email,
                    ] : null,
                    'to_user' => $transfer->relationLoaded('toUser') && $transfer->toUser ? [
                        'id' => $transfer->toUser->id,
                        'name' => $transfer->toUser->name,
                        'email' => $transfer->toUser->email,
                    ] : null,
                    'approved_by' => $transfer->relationLoaded('approvedBy') && $transfer->approvedBy ? [
                        'id' => $transfer->approvedBy->id,
                        'name' => $transfer->approvedBy->name,
                        'email' => $transfer->approvedBy->email,
                    ] : null,
                    'workflow_history' => $transfer->workflow_history ?? [],
                    'metadata' => $transfer->metadata ?? [],
                ])
                ->values()
                ->all()),
            'completion_approvals' => $this->whenLoaded('completionApprovals', fn () => $this->completionApprovals
                ->map(fn ($approval): array => [
                    'id' => $approval->id,
                    'status' => $approval->status,
                    'request_note' => $approval->request_note,
                    'decision_note' => $approval->decision_note,
                    'requested_by' => $approval->requestedBy?->only(['id', 'name']),
                    'decided_by' => $approval->decidedBy?->only(['id', 'name']),
                    'decided_at' => $approval->decided_at?->toISOString(),
                ])->values()->all()),
            'workflow_history' => $this->workflow_history ?? [],
            'metadata' => $this->metadata ?? [],
            'company' => $this->whenLoaded('company', fn (): array => [
                'id' => $this->company->id,
                'name' => $this->company->name,
                'code' => $this->company->code,
            ]),
            'project' => $this->whenLoaded('project', fn (): ?array => $this->project ? [
                'id' => $this->project->id,
                'name' => $this->project->name,
                'code' => $this->project->code,
            ] : null),
            'created_by' => $this->whenLoaded('createdBy', fn (): array => [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
                'email' => $this->createdBy->email,
            ]),
            'assigned_to' => $this->whenLoaded('assignedTo', fn (): ?array => $this->assignedTo ? [
                'id' => $this->assignedTo->id,
                'name' => $this->assignedTo->name,
                'email' => $this->assignedTo->email,
            ] : null),
            'permissions' => [
                'can_view' => $user ? $user->can('view', $this->resource) : false,
                'can_update_status' => $user ? $user->can('updateStatus', $this->resource) : false,
                'can_update_details' => $user ? $user->can('updateDetails', $this->resource) : false,
                'can_assign' => $user ? $user->can('assign', $this->resource) : false,
                'can_archive' => $user ? $user->can('archive', $this->resource) : false,
                'can_comment' => $user ? $user->can('comment', $this->resource) : false,
                'can_log_time' => $user ? $user->can('logTime', $this->resource) : false,
                'can_request_transfer' => $user ? $user->can('requestTransfer', $this->resource) : false,
                'can_manage_checklist' => $user ? $user->can('updateChecklist', $this->resource) : false,
                'can_manage_subtasks' => $user ? $user->can('manageSubtasks', $this->resource) : false,
                'can_update_watcher' => $user ? $user->can('watch', $this->resource) : false,
                'can_update_dependencies' => $user ? $user->can('updateDetails', $this->resource) : false,
            ],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
