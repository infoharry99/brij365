<?php

namespace App\Policies;

use App\Models\CommonAreaHandoverItem;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

class CommonAreaHandoverItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('after_sales.view')
            || $user->hasPermission('after_sales.manage')
            || $user->hasPermission('after_sales.approve')
            || $user->hasPermission('possession.view')
            || $user->hasPermission('possession.manage');
    }

    public function view(User $user, CommonAreaHandoverItem $item): bool
    {
        return $this->viewAny($user)
            && app(CompanyScopeService::class)->allows($user, $item->company_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('after_sales.manage') || $user->hasPermission('possession.manage');
    }

    public function update(User $user, CommonAreaHandoverItem $item): bool
    {
        return $this->create($user)
            && app(CompanyScopeService::class)->allows($user, $item->company_id);
    }

    public function signOff(User $user, CommonAreaHandoverItem $item): bool
    {
        return ($user->hasPermission('after_sales.approve') || $user->hasPermission('possession.approve'))
            && app(CompanyScopeService::class)->allows($user, $item->company_id)
            && $item->status !== 'complete';
    }
}
