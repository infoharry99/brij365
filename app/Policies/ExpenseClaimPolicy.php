<?php

namespace App\Policies;

use App\Models\ExpenseClaim;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

class ExpenseClaimPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('claims.view')
            || $user->hasPermission('claims.manage')
            || $user->hasPermission('claims.approve')
            || $user->hasPermission('finance.approve')
            || $user->hasPermission('hr.manage')
            || $user->hasPermission('employee.self_service');
    }

    public function view(User $user, ExpenseClaim $expenseClaim): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        if ($this->hasRegisterScope($user)) {
            return app(CompanyScopeService::class)->allows($user, $expenseClaim->company_id);
        }

        return $user->hasPermission('employee.self_service')
            && $expenseClaim->employee()->where('user_id', $user->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('employee.self_service')
            || $user->hasPermission('claims.manage')
            || $user->hasPermission('hr.manage');
    }

    public function approve(User $user, ExpenseClaim $expenseClaim): bool
    {
        return ($user->hasPermission('claims.approve') || $user->hasPermission('hr.manage'))
            && $expenseClaim->status === 'submitted'
            && $expenseClaim->requested_by_user_id !== $user->id
            && app(CompanyScopeService::class)->allows($user, $expenseClaim->company_id);
    }

    public function reject(User $user, ExpenseClaim $expenseClaim): bool
    {
        return $this->approve($user, $expenseClaim);
    }

    public function pay(User $user, ExpenseClaim $expenseClaim): bool
    {
        return $user->hasPermission('finance.approve')
            && $expenseClaim->status === 'approved'
            && app(CompanyScopeService::class)->allows($user, $expenseClaim->company_id);
    }

    private function hasRegisterScope(User $user): bool
    {
        return $user->hasPermission('claims.view')
            || $user->hasPermission('claims.manage')
            || $user->hasPermission('claims.approve')
            || $user->hasPermission('finance.approve')
            || $user->hasPermission('hr.manage');
    }
}
