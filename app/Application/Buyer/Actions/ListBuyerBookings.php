<?php

namespace App\Application\Buyer\Actions;

use App\Application\Buyer\Data\BuyerPortalWorkspaceData;
use App\Domain\Buyer\Services\BuyerPortalRegister;
use App\Models\User;

final class ListBuyerBookings
{
    public function __construct(private readonly BuyerPortalRegister $register) {}

    public function execute(User $actor, array $filters): BuyerPortalWorkspaceData
    {
        return new BuyerPortalWorkspaceData('bookings', $this->register->customer($actor), $this->register->bookings($actor, $filters), $filters,
            ['draft' => 'Draft', 'confirmed' => 'Confirmed', 'cancelled' => 'Cancelled']);
    }
}
