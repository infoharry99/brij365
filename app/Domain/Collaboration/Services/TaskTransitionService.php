<?php

namespace App\Domain\Collaboration\Services;

use App\Domain\Collaboration\TaskLifecycle;
use App\Models\User;
use App\Models\WorkTask;
use Illuminate\Validation\ValidationException;

final class TaskTransitionService
{
    public function __construct(private readonly TaskSettingsResolver $settings) {}

    public function assertAllowed(WorkTask $task, string $target, User $actor): void
    {
        if (! array_key_exists($target, TaskLifecycle::statuses())) {
            throw ValidationException::withMessages(['status' => 'The selected task status is not available.']);
        }

        if ($target === $task->status) {
            return;
        }

        if (! in_array($target, $this->allowedTargets($task, $actor), true)) {
            throw ValidationException::withMessages(['status' => "A task cannot move from {$task->status} to {$target}."]);
        }
    }

    /** @return array<int, string> */
    public function allowedTargets(WorkTask $task, User $actor): array
    {
        $configured = data_get($this->settings->forCompany($task->company_id), "transitions.{$task->status}");
        $allowed = is_array($configured) ? array_values($configured) : $this->defaults()[$task->status] ?? [];

        if ($actor->hasPermission('collaboration.manage')) {
            $allowed = [...$allowed, 'completed', 'rejected', 'cancelled', 'in_progress', 'open'];
        }

        return collect($allowed)
            ->filter(fn (mixed $status): bool => is_string($status) && array_key_exists($status, TaskLifecycle::statuses()))
            ->reject(fn (string $status): bool => $status === $task->status)
            ->reject(fn (string $status): bool => $status === 'assigned' && ! $task->assigned_to_user_id)
            ->unique()
            ->values()
            ->all();
    }

    /** @return array<string, array<int, string>> */
    public function defaults(): array
    {
        return [
            'draft' => ['open', 'assigned', 'cancelled'],
            'open' => ['assigned', 'accepted', 'in_progress', 'on_hold', 'waiting_info', 'waiting_dependency', 'under_review', 'waiting_approval', 'blocked', 'completed', 'cancelled'],
            'assigned' => ['accepted', 'in_progress', 'on_hold', 'waiting_info', 'waiting_dependency', 'cancelled'],
            'accepted' => ['in_progress', 'on_hold', 'waiting_info', 'waiting_dependency', 'cancelled'],
            'in_progress' => ['on_hold', 'waiting_info', 'waiting_dependency', 'under_review', 'waiting_approval', 'blocked', 'completed', 'cancelled'],
            'on_hold' => ['open', 'assigned', 'in_progress', 'cancelled'],
            'waiting_info' => ['in_progress', 'blocked', 'cancelled'],
            'waiting_dependency' => ['in_progress', 'blocked', 'cancelled'],
            'under_review' => ['in_progress', 'waiting_approval', 'completed', 'rejected'],
            'waiting_approval' => ['completed', 'rejected', 'in_progress'],
            'blocked' => ['open', 'in_progress', 'cancelled'],
            'rejected' => ['in_progress', 'cancelled'],
        ];
    }
}
