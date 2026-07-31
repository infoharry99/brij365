<?php

namespace App\Application\Inventory\Actions;

use App\Application\Inventory\Data\InventoryCommandData;
use App\Models\UnitPriceVersion;
use App\Services\Inventory\UnitPricingService;

final class CreateUnitPriceVersion
{
    public function __construct(private readonly UnitPricingService $pricing) {}

    public function execute(InventoryCommandData $command): UnitPriceVersion
    {
        return $this->pricing->createVersion($command->attributes, $command->actor, $command->request);
    }
}
