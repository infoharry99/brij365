<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vendor;
use App\Services\Security\CompanyScopeService;

class VendorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('procurement.view')
            || $user->hasPermission('procurement.manage')
            || $user->hasPermission('procurement.approve');
    }

    public function view(User $user, Vendor $vendor): bool
    {
        return $this->viewAny($user)
            && app(CompanyScopeService::class)->allows($user, $vendor->company_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('procurement.manage');
    }

    public function update(User $user, Vendor $vendor): bool
    {
        return $user->hasPermission('procurement.manage')
            && app(CompanyScopeService::class)->allows($user, $vendor->company_id);
    }

    public function updateStatus(User $user, Vendor $vendor): bool
    {
        return $this->update($user, $vendor);
    }
}
