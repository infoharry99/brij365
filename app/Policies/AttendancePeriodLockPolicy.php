<?php

namespace App\Policies;

use App\Domain\Scoring\Support\LogicCenterPermissions;
use App\Models\AttendancePeriodLock;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

class AttendancePeriodLockPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('attendance.view')
            || $user->hasPermission('attendance.manage')
            || $user->hasPermission('attendance.approve')
            || $user->hasPermission('payroll.view')
            || $user->hasPermission('payroll.manage')
            || $user->hasPermission(LogicCenterPermissions::ATTENDANCE_FINALIZE)
            || $user->hasPermission(LogicCenterPermissions::ATTENDANCE_REOPEN);
    }

    public function view(User $user, AttendancePeriodLock $attendancePeriodLock): bool
    {
        return $this->viewAny($user)
            && app(CompanyScopeService::class)->allows($user, $attendancePeriodLock->company_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('attendance.approve')
            || $user->hasPermission(LogicCenterPermissions::ATTENDANCE_FINALIZE);
    }

    public function reopen(User $user, AttendancePeriodLock $attendancePeriodLock): bool
    {
        return ($user->hasPermission('attendance.approve') || $user->hasPermission(LogicCenterPermissions::ATTENDANCE_REOPEN))
            && $attendancePeriodLock->status === 'finalized'
            && app(CompanyScopeService::class)->allows($user, $attendancePeriodLock->company_id);
    }
}
