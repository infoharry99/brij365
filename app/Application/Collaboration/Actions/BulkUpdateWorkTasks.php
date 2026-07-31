<?php

namespace App\Application\Collaboration\Actions;

use App\Application\Collaboration\Data\CollaborationCommandData;
use App\Services\Collaboration\CollaborationService;
use Illuminate\Database\Eloquent\Collection;

final class BulkUpdateWorkTasks
{
    public function __construct(private readonly CollaborationService $collaboration) {}

    /** @return Collection<int,\App\Models\WorkTask> */
    public function execute(CollaborationCommandData $command): Collection
    {
        return $this->collaboration->bulkUpdateTasks($command->attributes, $command->actor, $command->request);
    }
}
