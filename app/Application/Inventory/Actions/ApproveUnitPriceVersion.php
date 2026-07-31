<?php

namespace App\Application\Inventory\Actions;

use App\Application\Inventory\Data\InventoryCommandData;
use App\Models\UnitPriceVersion;
use App\Services\Inventory\UnitPricingService;

final class ApproveUnitPriceVersion
{
    public function __construct(private readonly UnitPricingService $pricing) {}

    public function execute(UnitPriceVersion $version, InventoryCommandData $command): UnitPriceVersion
    {
        return $this->pricing->approve($version, $command->attributes, $command->actor, $command->request);
    }
}
