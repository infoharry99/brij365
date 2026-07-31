<?php

namespace App\Domain\Hr\Services;

use App\Models\Employee;
use App\Models\User;

final class EmployeeFieldVisibility
{
    public function canExportRegister(User $actor): bool
    {
        return $actor->hasPermission('*')
            || $actor->hasPermission('hr.view')
            || $actor->hasPermission('hr.manage');
    }

    public function canViewCompensation(User $actor, ?Employee $employee = null): bool
    {
        if ($employee?->user_id === $actor->id && $actor->hasPermission('employee.self_service')) {
            return true;
        }

        return $actor->hasPermission('*')
            || $actor->hasPermission('hr.manage')
            || $actor->hasPermission('payroll.view')
            || $actor->hasPermission('payroll.manage')
            || $actor->hasPermission('payroll.approve');
    }

    public function canViewSensitiveProfile(User $actor, ?Employee $employee = null): bool
    {
        if ($employee?->user_id === $actor->id && $actor->hasPermission('employee.self_service')) {
            return true;
        }

        return $actor->hasPermission('*') || $actor->hasPermission('hr.manage');
    }
}
