<?php

namespace App\Policies;

use App\Models\MaintenanceDue;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

class MaintenanceDuePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('after_sales.view')
            || $user->hasPermission('after_sales.manage')
            || $user->hasPermission('finance.view')
            || $user->hasPermission('finance.manage')
            || $user->hasPermission('collections.view')
            || $user->hasPermission('collections.manage')
            || $user->hasPermission('buyer.view');
    }

    public function view(User $user, MaintenanceDue $due): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        if ($this->isBuyerPortalUser($user)) {
            return $due->customer()->where('portal_user_id', $user->id)->exists();
        }

        return app(CompanyScopeService::class)->allows($user, $due->company_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('after_sales.manage')
            || $user->hasPermission('finance.manage')
            || $user->hasPermission('collections.manage');
    }

    public function markPaid(User $user, MaintenanceDue $due): bool
    {
        return ($user->hasPermission('finance.manage') || $user->hasPermission('collections.manage'))
            && $due->status !== 'paid'
            && app(CompanyScopeService::class)->allows($user, $due->company_id);
    }

    public function remind(User $user, MaintenanceDue $due): bool
    {
        return ($user->hasPermission('after_sales.manage') || $user->hasPermission('collections.manage'))
            && $due->status !== 'paid'
            && app(CompanyScopeService::class)->allows($user, $due->company_id);
    }

    private function isBuyerPortalUser(User $user): bool
    {
        return $user->role?->slug === 'buyer' && $user->hasPermission('buyer.view');
    }
}
