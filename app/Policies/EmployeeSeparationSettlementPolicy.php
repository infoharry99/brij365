<?php

namespace App\Policies;

use App\Models\EmployeeSeparationSettlement;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

class EmployeeSeparationSettlementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('hr.view')
            || $user->hasPermission('hr.manage')
            || $user->hasPermission('finance.view')
            || $user->hasPermission('finance.approve')
            || $user->hasPermission('employee.self_service');
    }

    public function view(User $user, EmployeeSeparationSettlement $settlement): bool
    {
        if ($settlement->employee?->user_id === $user->id) {
            return true;
        }

        return $this->viewAny($user)
            && app(CompanyScopeService::class)->allows($user, $settlement->company_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('hr.manage');
    }

    public function hrApprove(User $user, EmployeeSeparationSettlement $settlement): bool
    {
        return $settlement->status === 'initiated'
            && $user->hasPermission('hr.manage')
            && app(CompanyScopeService::class)->allows($user, $settlement->company_id);
    }

    public function financeApprove(User $user, EmployeeSeparationSettlement $settlement): bool
    {
        return $settlement->status === 'hr_approved'
            && $user->hasPermission('finance.approve')
            && app(CompanyScopeService::class)->allows($user, $settlement->company_id)
            && $settlement->hr_approved_by_user_id !== $user->id;
    }

    public function complete(User $user, EmployeeSeparationSettlement $settlement): bool
    {
        return $settlement->status === 'finance_approved'
            && $user->hasPermission('finance.approve')
            && app(CompanyScopeService::class)->allows($user, $settlement->company_id);
    }
}
