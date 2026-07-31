<?php

namespace App\Application\Construction\Actions;

use App\Application\Construction\Data\ConstructionCommandData;
use App\Models\ConstructionMilestone;
use App\Services\Construction\ConstructionService;

final class CreateConstructionMilestone
{
    public function __construct(private readonly ConstructionService $service) {}

    public function execute(ConstructionCommandData $command): ConstructionMilestone
    {
        return $this->service->createMilestone($command->attributes, $command->actor, $command->request);
    }
}
