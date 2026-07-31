<?php

namespace App\Policies;

use App\Models\UnitPriceVersion;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

class UnitPriceVersionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('inventory.view')
            || $user->hasPermission('booking.view')
            || $user->hasPermission('booking.manage');
    }

    public function view(User $user, UnitPriceVersion $unitPriceVersion): bool
    {
        return $this->viewAny($user)
            && app(CompanyScopeService::class)->allows($user, $unitPriceVersion->company_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('booking.manage');
    }

    public function approve(User $user, UnitPriceVersion $unitPriceVersion): bool
    {
        return ($user->hasPermission('booking.manage') || $user->hasPermission('finance.approve'))
            && app(CompanyScopeService::class)->allows($user, $unitPriceVersion->company_id);
    }
}
