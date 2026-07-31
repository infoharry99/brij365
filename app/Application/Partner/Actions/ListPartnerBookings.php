<?php

namespace App\Application\Partner\Actions;

use App\Application\Partner\Data\PartnerPortalWorkspaceData;
use App\Domain\Partner\Services\PartnerPortalRegister;
use App\Models\User;

final class ListPartnerBookings
{
    public function __construct(private readonly PartnerPortalRegister $register) {}
    public function execute(User $actor, array $filters): PartnerPortalWorkspaceData
    {
        return new PartnerPortalWorkspaceData('bookings', $this->register->bookings($actor, $filters), $filters,
            ['draft' => 'Draft', 'confirmed' => 'Confirmed', 'cancelled' => 'Cancelled']);
    }
}
