<?php

namespace App\Policies;

use App\Models\CommissionRun;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

class CommissionRunPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('payroll.view')
            || $user->hasPermission('payroll.manage')
            || $user->hasPermission('payroll.approve')
            || $user->hasPermission('reports.view');
    }

    public function view(User $user, CommissionRun $run): bool
    {
        return $this->viewAny($user)
            && app(CompanyScopeService::class)->allows($user, $run->company_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('payroll.manage');
    }

    public function approve(User $user, CommissionRun $run): bool
    {
        return $user->hasPermission('payroll.approve')
            && $run->status === 'generated'
            && app(CompanyScopeService::class)->allows($user, $run->company_id)
            && $run->generated_by_user_id !== $user->id;
    }

    public function reject(User $user, CommissionRun $run): bool
    {
        return $this->approve($user, $run);
    }
}
