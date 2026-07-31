<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('*')
            || $user->hasPermission('inventory.view')
            || $user->hasPermission('reports.view')
            || $user->hasPermission('settings.manage');
    }

    public function view(User $user, Project $project): bool
    {
        return $this->viewAny($user)
            && app(CompanyScopeService::class)->allows($user, $project->company_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('*') || $user->hasPermission('settings.manage');
    }

    public function update(User $user, Project $project): bool
    {
        return $this->create($user)
            && app(CompanyScopeService::class)->allows($user, $project->company_id);
    }
}
