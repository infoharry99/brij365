<?php

namespace App\Application\Collaboration\Actions;

use App\Application\Collaboration\Data\CollaborationCommandData;
use App\Services\Collaboration\CollaborationService;

final class BulkArchiveWorkTasks
{
    public function __construct(private readonly CollaborationService $collaboration) {}

    /** @return array<int,array<string,mixed>> */
    public function execute(CollaborationCommandData $command): array
    {
        return $this->collaboration->bulkArchiveTasks($command->attributes, $command->actor, $command->request);
    }
}
