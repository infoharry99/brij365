<?php

namespace App\Policies;

use App\Models\EmployeeConfirmationCase;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

class EmployeeConfirmationCasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('hr.view')
            || $user->hasPermission('hr.manage')
            || $user->hasPermission('performance.manage')
            || $user->hasPermission('employee.self_service');
    }

    public function view(User $user, EmployeeConfirmationCase $confirmationCase): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        if ($confirmationCase->employee?->user_id === $user->id) {
            return true;
        }

        if ($confirmationCase->managerEmployee?->user_id === $user->id && $user->hasPermission('performance.manage')) {
            return true;
        }

        $companyScope = app(CompanyScopeService::class);

        return ($companyScope->hasGlobalScope($user)
            || $user->hasPermission('hr.view')
            || $user->hasPermission('hr.manage'))
            && $companyScope->allows($user, $confirmationCase->company_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('hr.manage');
    }

    public function recommend(User $user, EmployeeConfirmationCase $confirmationCase): bool
    {
        if ($confirmationCase->employee?->user_id === $user->id) {
            return false;
        }

        return $confirmationCase->status === 'due'
            && $user->hasPermission('performance.manage')
            && $confirmationCase->managerEmployee?->user_id === $user->id;
    }

    public function decide(User $user, EmployeeConfirmationCase $confirmationCase): bool
    {
        if ($confirmationCase->employee?->user_id === $user->id || $confirmationCase->managerEmployee?->user_id === $user->id) {
            return false;
        }

        return $confirmationCase->status === 'manager_recommended'
            && $user->hasPermission('hr.manage')
            && app(CompanyScopeService::class)->allows($user, $confirmationCase->company_id);
    }
}
