<?php

namespace App\Policies;

use App\Models\JobOpening;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

class JobOpeningPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('recruitment.view')
            || $user->hasPermission('recruitment.manage')
            || $user->hasPermission('recruitment.approve');
    }

    public function view(User $user, JobOpening $jobOpening): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        return app(CompanyScopeService::class)->allows($user, $jobOpening->company_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('recruitment.manage');
    }

    public function approve(User $user, JobOpening $jobOpening): bool
    {
        return $user->hasPermission('recruitment.approve')
            && $jobOpening->status === 'pending_approval'
            && $jobOpening->created_by_user_id !== $user->id
            && app(CompanyScopeService::class)->allows($user, $jobOpening->company_id);
    }

    public function reject(User $user, JobOpening $jobOpening): bool
    {
        return $this->approve($user, $jobOpening);
    }
}
