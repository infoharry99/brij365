<?php

namespace App\Policies;

use App\Models\GstReturnPeriod;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

class GstReturnPeriodPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('finance.view')
            || $user->hasPermission('finance.manage')
            || $user->hasPermission('finance.approve')
            || $user->hasPermission('compliance.view')
            || $user->hasPermission('compliance.manage');
    }

    public function view(User $user, GstReturnPeriod $period): bool
    {
        return $this->viewAny($user)
            && app(CompanyScopeService::class)->allows($user, $period->company_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('finance.manage') || $user->hasPermission('compliance.manage');
    }

    public function approve(User $user, GstReturnPeriod $period): bool
    {
        return ($user->hasPermission('finance.approve') || $user->hasPermission('compliance.manage'))
            && $period->status === 'prepared'
            && app(CompanyScopeService::class)->allows($user, $period->company_id)
            && $period->prepared_by_user_id !== $user->id;
    }

    public function lock(User $user, GstReturnPeriod $period): bool
    {
        return ($user->hasPermission('finance.approve') || $user->hasPermission('compliance.manage'))
            && $period->status === 'approved'
            && app(CompanyScopeService::class)->allows($user, $period->company_id);
    }
}
