<?php

namespace App\Policies;

use App\Models\ProjectUnit;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

class ProjectUnitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('inventory.view')
            || $user->hasPermission('booking.view')
            || $user->hasPermission('booking.manage');
    }

    public function view(User $user, ProjectUnit $projectUnit): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        return app(CompanyScopeService::class)->allows($user, $projectUnit->company_id);
    }
}
