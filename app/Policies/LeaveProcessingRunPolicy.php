<?php

namespace App\Policies;

use App\Models\LeaveProcessingRun;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

class LeaveProcessingRunPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('leave.view') || $user->hasPermission('leave.manage') || $user->hasPermission('leave.approve');
    }

    public function view(User $user, LeaveProcessingRun $run): bool
    {
        return $this->viewAny($user)
            && app(CompanyScopeService::class)->allows($user, $run->company_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('leave.manage');
    }

    public function post(User $user, LeaveProcessingRun $run): bool
    {
        return $run->status === 'preview'
            && $user->hasPermission('leave.approve')
            && app(CompanyScopeService::class)->allows($user, $run->company_id)
            && $run->created_by_user_id !== $user->id;
    }
}
