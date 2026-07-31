<?php

namespace App\Policies;

use App\Models\JobOffer;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

class JobOfferPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('recruitment.view')
            || $user->hasPermission('recruitment.manage')
            || $user->hasPermission('recruitment.approve');
    }

    public function view(User $user, JobOffer $jobOffer): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        return app(CompanyScopeService::class)->allows($user, $jobOffer->company_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('recruitment.manage');
    }

    public function release(User $user, JobOffer $jobOffer): bool
    {
        if (! $user->hasPermission('recruitment.approve')) {
            return false;
        }

        return $jobOffer->status === 'draft'
            && app(CompanyScopeService::class)->allows($user, $jobOffer->company_id)
            && $jobOffer->created_by_user_id !== $user->id;
    }
}
