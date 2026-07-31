<?php

namespace App\Policies;

use App\Models\ProjectApproval;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

class ProjectApprovalPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('legal.view')
            || $user->hasPermission('legal.manage')
            || $user->hasPermission('legal.approve');
    }

    public function view(User $user, ProjectApproval $projectApproval): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        return app(CompanyScopeService::class)->allows($user, $projectApproval->company_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('legal.manage');
    }

    public function verify(User $user, ProjectApproval $projectApproval): bool
    {
        if (! $user->hasPermission('legal.approve')) {
            return false;
        }

        return in_array($projectApproval->status, ['applied', 'approved'], true)
            && app(CompanyScopeService::class)->allows($user, $projectApproval->company_id)
            && $projectApproval->responsible_user_id !== $user->id;
    }
}
