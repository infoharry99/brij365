<?php

namespace App\Application\Collaboration\Actions;

use App\Application\Collaboration\Data\CollaborationCommandData;
use App\Models\WorkTask;
use App\Services\Collaboration\CollaborationService;

final class CreateWorkTaskSubtask
{
    public function __construct(private readonly CollaborationService $collaboration) {}

    public function execute(WorkTask $task, CollaborationCommandData $command): WorkTask
    {
        return $this->collaboration->createTaskSubtask($task, $command->attributes, $command->actor, $command->request);
    }
}
