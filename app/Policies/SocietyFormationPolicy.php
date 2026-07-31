<?php

namespace App\Policies;

use App\Models\SocietyFormation;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

class SocietyFormationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('after_sales.view')
            || $user->hasPermission('after_sales.manage')
            || $user->hasPermission('after_sales.approve')
            || $user->hasPermission('possession.view')
            || $user->hasPermission('possession.manage');
    }

    public function view(User $user, SocietyFormation $formation): bool
    {
        return $this->viewAny($user)
            && app(CompanyScopeService::class)->allows($user, $formation->company_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('after_sales.manage') || $user->hasPermission('possession.manage');
    }

    public function update(User $user, SocietyFormation $formation): bool
    {
        return $this->create($user)
            && app(CompanyScopeService::class)->allows($user, $formation->company_id);
    }
}
