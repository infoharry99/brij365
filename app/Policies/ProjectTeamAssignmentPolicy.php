<?php

namespace App\Policies;

use App\Models\ProjectTeamAssignment;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

class ProjectTeamAssignmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('*')
            || $user->hasPermission('settings.manage')
            || $user->hasPermission('construction.manage')
            || $user->hasPermission('inventory.view')
            || $user->hasPermission('reports.view');
    }

    public function view(User $user, ProjectTeamAssignment $assignment): bool
    {
        return $this->viewAny($user)
            && app(CompanyScopeService::class)->allows($user, $assignment->company_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('*')
            || $user->hasPermission('settings.manage')
            || $user->hasPermission('construction.manage');
    }

    public function delete(User $user, ProjectTeamAssignment $assignment): bool
    {
        return $this->create($user)
            && app(CompanyScopeService::class)->allows($user, $assignment->company_id);
    }
}
