<?php

namespace App\Policies;

use App\Models\ConstructionMilestone;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

class ConstructionMilestonePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('construction.view')
            || $user->hasPermission('construction.manage')
            || $user->hasPermission('construction.approve');
    }

    public function view(User $user, ConstructionMilestone $constructionMilestone): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        return app(CompanyScopeService::class)->allows($user, $constructionMilestone->company_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('construction.manage');
    }
}
