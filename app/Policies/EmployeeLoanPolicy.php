<?php

namespace App\Policies;

use App\Models\EmployeeLoan;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

class EmployeeLoanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('loans.view') || $user->hasPermission('loans.manage')
            || $user->hasPermission('loans.approve') || $user->hasPermission('finance.approve')
            || $user->hasPermission('hr.manage')
            || $user->hasPermission('employee.self_service');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('employee.self_service') || $user->hasPermission('loans.manage');
    }

    public function approve(User $user, EmployeeLoan $loan): bool
    {
        return ($user->hasPermission('loans.approve') || $user->hasPermission('hr.manage'))
            && $loan->status === 'submitted'
            && $loan->requested_by_user_id !== $user->id
            && app(CompanyScopeService::class)->allows($user, $loan->company_id);
    }

    public function reject(User $user, EmployeeLoan $loan): bool
    {
        return $this->approve($user, $loan);
    }

    public function disburse(User $user, EmployeeLoan $loan): bool
    {
        return $user->hasPermission('finance.approve')
            && $loan->status === 'approved'
            && app(CompanyScopeService::class)->allows($user, $loan->company_id);
    }
}
