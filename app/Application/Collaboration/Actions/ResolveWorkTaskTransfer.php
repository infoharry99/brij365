<?php

namespace App\Application\Collaboration\Actions;

use App\Application\Collaboration\Data\CollaborationCommandData;
use App\Models\WorkTask;
use App\Models\WorkTaskTransferRequest;
use App\Services\Collaboration\CollaborationService;

final class ResolveWorkTaskTransfer
{
    public function __construct(private readonly CollaborationService $collaboration) {}

    public function execute(WorkTaskTransferRequest $transfer, CollaborationCommandData $command): WorkTask
    {
        return $this->collaboration->resolveTaskTransferApproval($transfer, $command->attributes, $command->actor, $command->request);
    }
}
