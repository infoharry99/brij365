<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('hr.view')
            || $user->hasPermission('hr.manage')
            || $user->hasPermission('payroll.view');
    }

    public function view(User $user, Employee $employee): bool
    {
        if ($employee->user_id === $user->id && $user->hasPermission('employee.self_service')) {
            return true;
        }

        if (! $this->viewAny($user)) {
            return false;
        }

        return app(CompanyScopeService::class)->allows($user, $employee->company_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('hr.manage');
    }

    public function update(User $user, Employee $employee): bool
    {
        return $user->hasPermission('hr.manage')
            && app(CompanyScopeService::class)->allows($user, $employee->company_id);
    }
}
