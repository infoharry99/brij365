<?php

namespace App\Application\Collaboration\Actions;

use App\Application\Collaboration\Data\CollaborationCommandData;
use App\Models\WorkTask;
use App\Services\Collaboration\CollaborationService;

final class ChangeWorkTaskDependencies
{
    public function __construct(private readonly CollaborationService $collaboration) {}

    public function execute(WorkTask $task, CollaborationCommandData $command): WorkTask
    {
        return $this->collaboration->updateTaskDependencies($task, $command->attributes, $command->actor, $command->request);
    }
}
