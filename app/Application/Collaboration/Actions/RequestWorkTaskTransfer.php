<?php

namespace App\Application\Collaboration\Actions;

use App\Application\Collaboration\Data\CollaborationCommandData;
use App\Models\WorkTask;
use App\Models\WorkTaskTransferRequest;
use App\Services\Collaboration\CollaborationService;

final class RequestWorkTaskTransfer
{
    public function __construct(private readonly CollaborationService $collaboration) {}

    public function execute(WorkTask $task, CollaborationCommandData $command): WorkTaskTransferRequest
    {
        return $this->collaboration->requestTaskTransferApproval($task, $command->attributes, $command->actor, $command->request);
    }
}
