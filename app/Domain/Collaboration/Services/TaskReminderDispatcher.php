<?php

namespace App\Domain\Collaboration\Services;

use App\Domain\Collaboration\TaskLifecycle;
use App\Models\User;
use App\Models\WorkTask;
use App\Models\WorkTaskReminderDelivery;
use App\Services\Notifications\NotificationCenterService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

final class TaskReminderDispatcher
{
    public function __construct(
        private readonly NotificationCenterService $notifications,
        private readonly TaskSettingsResolver $settings,
    ) {}

    public function dispatchDue(CarbonInterface $now): int
    {
        $sent = 0;

        WorkTask::query()
            ->whereNotIn('status', TaskLifecycle::terminalStatuses())
            ->whereNotNull('due_at')
            ->where('due_at', '<=', $now->copy()->addDays(7))
            ->orderBy('id')
            ->chunkById(100, function ($tasks) use ($now, &$sent): void {
                foreach ($tasks as $task) {
                    $sent += $this->dispatchForTask((int) $task->id, $now);
                }
            });

        return $sent;
    }

    private function dispatchForTask(int $taskId, CarbonInterface $now): int
    {
        return DB::transaction(function () use ($taskId, $now): int {
            $task = WorkTask::query()->whereKey($taskId)->lockForUpdate()->first();
            if (! $task || in_array($task->status, TaskLifecycle::terminalStatuses(), true) || ! $task->due_at) {
                return 0;
            }

            $metadata = $task->metadata ?? [];
            $settings = $this->settings->forCompany($task->company_id);
            $minutes = collect($metadata['reminder_minutes_before'] ?? [1440, 60])
                ->map(fn ($value): int => (int) $value)
                ->filter(fn (int $value): bool => $value >= 0)
                ->unique()
                ->sortDesc();
            $recipientIds = collect([$task->assigned_to_user_id])
                ->merge($metadata['watcher_user_ids'] ?? [])
                ->filter()
                ->map(fn ($id): int => (int) $id)
                ->unique();
            $sentCount = 0;

            foreach ($minutes as $minutesBefore) {
                $reminderAt = $task->due_at->copy()->subMinutes($minutesBefore);
                if ($now->lt($reminderAt)) {
                    continue;
                }

                $isOverdue = $task->due_at->isPast();
                if (($isOverdue && ! data_get($settings, 'notify_overdue', true)) || (! $isOverdue && ! data_get($settings, 'notify_due_soon', true))) {
                    continue;
                }

                User::query()->whereIn('id', $recipientIds)->where('status', 'active')->get()->each(function (User $recipient) use ($task, $minutesBefore, $reminderAt, $isOverdue, &$sentCount): void {
                    $key = hash('sha256', implode('|', [$task->id, $recipient->id, $task->due_at->utc()->format('Y-m-d H:i:s'), $minutesBefore]));
                    $delivery = WorkTaskReminderDelivery::query()->firstOrCreate(
                        ['idempotency_key' => $key],
                        [
                            'work_task_id' => $task->id,
                            'recipient_user_id' => $recipient->id,
                            'reminder_at' => $reminderAt,
                            'minutes_before' => $minutesBefore,
                            'status' => 'pending',
                        ],
                    );

                    if ($delivery->status === 'sent' || $delivery->attempts >= 3) {
                        return;
                    }

                    try {
                        $delivery->forceFill(['status' => 'processing', 'attempts' => $delivery->attempts + 1, 'error_code' => null])->save();
                        $this->notifications->sendToUser($recipient, [
                            'category' => 'task',
                            'severity' => $isOverdue || $task->priority === 'critical' ? 'critical' : 'warning',
                            'title' => $isOverdue ? "Task {$task->task_number} is overdue" : "Task {$task->task_number} is due soon",
                            'body' => $task->title,
                            'action_url' => route('collaboration.tasks.index', ['task_id' => $task->id], false),
                            'payload' => ['task_id' => $task->id, 'task_number' => $task->task_number, 'minutes_before' => $minutesBefore],
                        ], null, $task);
                        $delivery->forceFill(['status' => 'sent', 'sent_at' => now(), 'failed_at' => null])->save();
                        $sentCount++;
                    } catch (\Throwable $exception) {
                        $delivery->forceFill([
                            'status' => $delivery->attempts >= 3 ? 'failed' : 'pending',
                            'failed_at' => now(),
                            'error_code' => class_basename($exception),
                        ])->save();
                        report($exception);
                    }
                });
            }

            return $sentCount;
        });
    }
}
