<?php

namespace App\Policies;

use App\Models\HandoverSnag;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

class HandoverSnagPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('possession.view')
            || $user->hasPermission('possession.manage')
            || $user->hasPermission('possession.approve');
    }

    public function view(User $user, HandoverSnag $handoverSnag): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        return app(CompanyScopeService::class)->allows($user, $handoverSnag->company_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('possession.manage');
    }

    public function resolve(User $user, HandoverSnag $handoverSnag): bool
    {
        return $user->hasPermission('possession.manage')
            && $handoverSnag->status === 'open'
            && app(CompanyScopeService::class)->allows($user, $handoverSnag->company_id);
    }
}
