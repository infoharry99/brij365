<?php

namespace App\Policies;

use App\Models\PossessionHandover;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

class PossessionHandoverPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('possession.view')
            || $user->hasPermission('possession.manage')
            || $user->hasPermission('possession.approve');
    }

    public function view(User $user, PossessionHandover $possessionHandover): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        return app(CompanyScopeService::class)->allows($user, $possessionHandover->company_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('possession.manage');
    }

    public function update(User $user, PossessionHandover $possessionHandover): bool
    {
        return $user->hasPermission('possession.manage')
            && app(CompanyScopeService::class)->allows($user, $possessionHandover->company_id);
    }

    public function issueLetter(User $user, PossessionHandover $possessionHandover): bool
    {
        return $user->hasPermission('possession.manage')
            && in_array($possessionHandover->status, ['ready', 'blocked'], true)
            && app(CompanyScopeService::class)->allows($user, $possessionHandover->company_id);
    }

    public function complete(User $user, PossessionHandover $possessionHandover): bool
    {
        if (! $user->hasPermission('possession.approve')) {
            return false;
        }

        return in_array($possessionHandover->status, ['ready', 'blocked'], true)
            && app(CompanyScopeService::class)->allows($user, $possessionHandover->company_id);
    }
}
