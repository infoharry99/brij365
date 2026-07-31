<?php

namespace App\Application\Inventory\Actions;

use App\Application\Inventory\Data\UnitInventoryWorkspaceData;
use App\Domain\Inventory\Services\InventoryWorkspaceRegister;
use App\Models\User;

final class ListUnitInventoryWorkspace
{
    public function __construct(private readonly InventoryWorkspaceRegister $register) {}

    /** @param array<string, mixed> $filters */
    public function execute(User $user, array $filters): UnitInventoryWorkspaceData
    {
        return new UnitInventoryWorkspaceData(
            $this->register->units($user, $filters), $filters, $this->register->projects($user),
            $this->register->unitTypes($user),
            ['available' => 'Available', 'reserved' => 'Reserved', 'booked' => 'Booked', 'registered' => 'Registered', 'handed_over' => 'Handed Over', 'blocked' => 'Blocked', 'on_hold' => 'On Hold'],
            $this->register->unitSummary($user),
        );
    }
}
