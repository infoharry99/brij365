<?php

namespace App\Application\Collaboration\Actions;

use App\Application\Collaboration\Data\CollaborationCommandData;
use App\Models\WorkTask;
use App\Services\Collaboration\CollaborationService;

final class DuplicateWorkTask
{
    public function __construct(private readonly CollaborationService $service) {}

    public function execute(WorkTask $task, CollaborationCommandData $command): WorkTask
    {
        return $this->service->duplicateTask($task, $command->attributes, $command->actor, $command->request);
    }
}
