<?php

namespace App\Policies;

use App\Models\LeaveEncashment;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

class LeaveEncashmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('leave.view')
            || $user->hasPermission('leave.manage')
            || $user->hasPermission('leave.approve')
            || $user->hasPermission('payroll.view')
            || $user->hasPermission('payroll.manage')
            || $user->hasPermission('employee.self_service');
    }

    public function view(User $user, LeaveEncashment $encashment): bool
    {
        if ($encashment->employee?->user_id === $user->id) {
            return true;
        }

        return $this->viewAny($user)
            && app(CompanyScopeService::class)->allows($user, $encashment->company_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('leave.request')
            || $user->hasPermission('leave.manage')
            || $user->hasPermission('employee.self_service');
    }

    public function approve(User $user, LeaveEncashment $encashment): bool
    {
        return $encashment->status === 'submitted'
            && ($user->hasPermission('leave.approve') || $user->hasPermission('leave.manage'))
            && app(CompanyScopeService::class)->allows($user, $encashment->company_id)
            && $encashment->requested_by_user_id !== $user->id;
    }

    public function reject(User $user, LeaveEncashment $encashment): bool
    {
        return $this->approve($user, $encashment);
    }

    public function markPayroll(User $user, LeaveEncashment $encashment): bool
    {
        return $encashment->status === 'approved'
            && $user->hasPermission('payroll.manage')
            && app(CompanyScopeService::class)->allows($user, $encashment->company_id);
    }
}
