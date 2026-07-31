<?php

namespace App\Events\Task;

use App\Models\WorkTask;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WorkTaskChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public WorkTask $task, public string $change = 'updated') {}

    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('tasks.company.'.$this->task->company_id)];
        $userIds = collect([$this->task->created_by_user_id, $this->task->assigned_to_user_id])
            ->merge(data_get($this->task->metadata, 'watcher_user_ids', []))->filter()->unique();
        foreach ($userIds as $userId) {
            $channels[] = new PrivateChannel('tasks.user.'.$userId);
        }

        return $channels;
    }

    public function broadcastAs(): string { return 'task.changed'; }

    public function broadcastWith(): array
    {
        return [
            'task_id' => $this->task->id,
            'task_number' => $this->task->task_number,
            'change' => $this->change,
            'lock_version' => $this->task->lock_version,
            'updated_at' => $this->task->updated_at?->toISOString(),
        ];
    }
}
