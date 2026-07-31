<?php

namespace App\Policies;

use App\Models\ComplianceObligation;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

class ComplianceObligationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('legal.view')
            || $user->hasPermission('legal.manage')
            || $user->hasPermission('legal.approve');
    }

    public function view(User $user, ComplianceObligation $complianceObligation): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        return app(CompanyScopeService::class)->allows($user, $complianceObligation->company_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('legal.manage');
    }

    public function complete(User $user, ComplianceObligation $complianceObligation): bool
    {
        if (! ($user->hasPermission('legal.manage') || $user->hasPermission('legal.approve'))) {
            return false;
        }

        return $complianceObligation->status === 'open'
            && app(CompanyScopeService::class)->allows($user, $complianceObligation->company_id);
    }
}
