<?php

namespace App\Application\Construction\Actions;

use App\Application\Construction\Data\ConstructionCommandData;
use App\Models\BoqItem;
use App\Services\Construction\ConstructionService;

final class CreateBoqItem
{
    public function __construct(private readonly ConstructionService $service) {}

    public function execute(ConstructionCommandData $command): BoqItem
    {
        return $this->service->createBoqItem($command->attributes, $command->actor, $command->request);
    }
}
