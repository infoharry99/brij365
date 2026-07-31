<?php

namespace App\Application\Collaboration\Actions;

use App\Application\Collaboration\Data\CollaborationCommandData;
use App\Models\WorkTask;
use App\Services\Collaboration\CollaborationService;

final class CreateWorkTask
{
    public function __construct(private readonly CollaborationService $collaboration) {}

    public function execute(CollaborationCommandData $command): WorkTask
    {
        return $this->collaboration->createTask($command->attributes, $command->actor, $command->request);
    }
}
