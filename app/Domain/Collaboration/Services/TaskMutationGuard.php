<?php

namespace App\Domain\Collaboration\Services;

use App\Models\WorkTask;
use Illuminate\Validation\ValidationException;

final class TaskMutationGuard
{
    public function assertVersion(WorkTask $task, mixed $submittedVersion): void
    {
        if ($submittedVersion === null || $submittedVersion === '') {
            return; // Backward compatibility for existing integrations.
        }

        if ((int) $submittedVersion !== (int) $task->lock_version) {
            throw ValidationException::withMessages([
                'lock_version' => 'This task was updated by another user. Refresh the task before saving your changes.',
            ]);
        }
    }
}
