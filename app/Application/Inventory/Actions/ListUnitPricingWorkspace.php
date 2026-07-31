<?php

namespace App\Application\Inventory\Actions;

use App\Application\Inventory\Data\UnitPricingWorkspaceData;
use App\Domain\Inventory\Services\InventoryWorkspaceRegister;
use App\Models\UnitPriceVersion;
use App\Models\User;

final class ListUnitPricingWorkspace
{
    public function __construct(private readonly InventoryWorkspaceRegister $register) {}

    /** @param array<string, mixed> $filters */
    public function execute(User $user, array $filters): UnitPricingWorkspaceData
    {
        return new UnitPricingWorkspaceData(
            $this->register->priceVersions($user, $filters), $filters, $this->register->projects($user),
            $this->register->allUnits($user), ['draft' => 'Draft', 'active' => 'Active', 'archived' => 'Archived'],
            $user->can('create', UnitPriceVersion::class),
        );
    }
}
