<?php

namespace App\Policies;

use App\Models\BoqItem;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

class BoqItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('construction.view')
            || $user->hasPermission('construction.manage')
            || $user->hasPermission('construction.approve')
            || $user->hasPermission('procurement.view');
    }

    public function view(User $user, BoqItem $boqItem): bool
    {
        return $this->viewAny($user)
            && app(CompanyScopeService::class)->allows($user, $boqItem->company_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('construction.manage');
    }
}
