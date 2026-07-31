<?php

namespace App\Policies;

use App\Domain\Scoring\Support\LogicCenterPermissions;
use App\Models\AttendanceRoster;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

class AttendanceRosterPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('attendance.view')
            || $user->hasPermission('attendance.request')
            || $user->hasPermission('attendance.manage')
            || $user->hasPermission('attendance.approve')
            || $user->hasPermission(LogicCenterPermissions::ROSTER_MANAGE)
            || $user->hasPermission(LogicCenterPermissions::ROSTER_PUBLISH)
            || $user->hasPermission(LogicCenterPermissions::ROSTER_REOPEN)
            || $user->hasPermission(LogicCenterPermissions::SWAP_REQUEST)
            || $user->hasPermission('employee.self_service');
    }

    public function view(User $user, AttendanceRoster $attendanceRoster): bool
    {
        if (! $this->viewAny($user) || ! $this->inCompanyScope($user, $attendanceRoster)) {
            return false;
        }

        if ($user->hasPermission('attendance.view') || $this->canManage($user) || $this->canPublish($user) || $this->canReopen($user)) {
            return true;
        }

        return $attendanceRoster->entries()
            ->whereHas('employee', fn ($query) => $query->where('user_id', $user->id))
            ->exists();
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function manage(User $user, AttendanceRoster $attendanceRoster): bool
    {
        return $this->canManage($user)
            && $attendanceRoster->status === 'draft'
            && $this->inCompanyScope($user, $attendanceRoster);
    }

    public function publish(User $user, AttendanceRoster $attendanceRoster): bool
    {
        return $this->canPublish($user)
            && $attendanceRoster->status === 'draft'
            && $this->inCompanyScope($user, $attendanceRoster);
    }

    public function lock(User $user, AttendanceRoster $attendanceRoster): bool
    {
        return $this->canPublish($user)
            && $attendanceRoster->status === 'published'
            && $this->inCompanyScope($user, $attendanceRoster);
    }

    public function cancel(User $user, AttendanceRoster $attendanceRoster): bool
    {
        return $this->canPublish($user)
            && in_array($attendanceRoster->status, ['draft', 'published'], true)
            && $this->inCompanyScope($user, $attendanceRoster);
    }

    public function reopen(User $user, AttendanceRoster $attendanceRoster): bool
    {
        return $this->canReopen($user)
            && $attendanceRoster->status === 'locked'
            && $this->inCompanyScope($user, $attendanceRoster);
    }

    private function canManage(User $user): bool
    {
        return $user->hasPermission(LogicCenterPermissions::ROSTER_MANAGE)
            || $user->hasPermission('attendance.manage');
    }

    private function canPublish(User $user): bool
    {
        return $user->hasPermission(LogicCenterPermissions::ROSTER_PUBLISH)
            || $user->hasPermission('attendance.approve');
    }

    private function canReopen(User $user): bool
    {
        return $user->hasPermission(LogicCenterPermissions::ROSTER_REOPEN)
            || $user->hasPermission('attendance.approve');
    }

    private function inCompanyScope(User $user, AttendanceRoster $attendanceRoster): bool
    {
        return app(CompanyScopeService::class)->allows($user, $attendanceRoster->company_id);
    }
}
