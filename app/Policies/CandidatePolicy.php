<?php

namespace App\Policies;

use App\Models\Candidate;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

class CandidatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('recruitment.view')
            || $user->hasPermission('recruitment.manage')
            || $user->hasPermission('recruitment.approve');
    }

    public function view(User $user, Candidate $candidate): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        return app(CompanyScopeService::class)->allows($user, $candidate->company_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('recruitment.manage');
    }

    public function update(User $user, Candidate $candidate): bool
    {
        return $user->hasPermission('recruitment.manage')
            && app(CompanyScopeService::class)->allows($user, $candidate->company_id);
    }

    public function convert(User $user, Candidate $candidate): bool
    {
        return $user->hasPermission('recruitment.approve')
            && app(CompanyScopeService::class)->allows($user, $candidate->company_id);
    }
}
