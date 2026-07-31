<?php

namespace App\Application\Collaboration\Actions;

use App\Application\Collaboration\Data\CollaborationCommandData;
use App\Models\WorkTask;
use App\Models\WorkTaskSubtask;
use App\Services\Collaboration\CollaborationService;

final class ChangeWorkTaskSubtaskStatus
{
    public function __construct(private readonly CollaborationService $collaboration) {}

    public function execute(WorkTask $task, WorkTaskSubtask $subtask, CollaborationCommandData $command): WorkTask
    {
        return $this->collaboration->updateTaskSubtaskStatus($task, $subtask, $command->attributes, $command->actor, $command->request);
    }
}
