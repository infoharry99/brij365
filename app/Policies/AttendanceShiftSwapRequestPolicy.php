<?php

namespace App\Policies;

use App\Domain\Scoring\Support\LogicCenterPermissions;
use App\Models\AttendanceShiftSwapRequest;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

class AttendanceShiftSwapRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('attendance.view')
            || $user->hasPermission('attendance.request')
            || $user->hasPermission('attendance.manage')
            || $user->hasPermission('attendance.approve')
            || $user->hasPermission(LogicCenterPermissions::SWAP_REQUEST)
            || $user->hasPermission(LogicCenterPermissions::SWAP_APPROVE)
            || $user->hasPermission('employee.self_service');
    }

    public function view(User $user, AttendanceShiftSwapRequest $attendanceShiftSwapRequest): bool
    {
        if (! $this->viewAny($user) || ! $this->inCompanyScope($user, $attendanceShiftSwapRequest)) {
            return false;
        }

        if ($user->hasPermission('attendance.view') || $user->hasPermission('attendance.manage') || $user->hasPermission('attendance.approve') || $user->hasPermission(LogicCenterPermissions::SWAP_APPROVE)) {
            return true;
        }

        if ((int) $attendanceShiftSwapRequest->requested_by_user_id === (int) $user->id) {
            return true;
        }

        return $attendanceShiftSwapRequest->sourceEntry()
            ->whereHas('employee', fn ($query) => $query->where('user_id', $user->id))
            ->exists()
            || $attendanceShiftSwapRequest->targetEntry()
                ->whereHas('employee', fn ($query) => $query->where('user_id', $user->id))
                ->exists();
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('attendance.request')
            || $user->hasPermission(LogicCenterPermissions::SWAP_REQUEST)
            || $user->hasPermission('attendance.manage')
            || $user->hasPermission('employee.self_service');
    }

    public function approve(User $user, AttendanceShiftSwapRequest $attendanceShiftSwapRequest): bool
    {
        return $this->canDecide($user, $attendanceShiftSwapRequest);
    }

    public function reject(User $user, AttendanceShiftSwapRequest $attendanceShiftSwapRequest): bool
    {
        return $this->canDecide($user, $attendanceShiftSwapRequest);
    }

    public function cancel(User $user, AttendanceShiftSwapRequest $attendanceShiftSwapRequest): bool
    {
        if ($attendanceShiftSwapRequest->status !== 'submitted' || ! $this->inCompanyScope($user, $attendanceShiftSwapRequest)) {
            return false;
        }

        return (int) $attendanceShiftSwapRequest->requested_by_user_id === (int) $user->id
            || $user->hasPermission('attendance.manage')
            || $user->hasPermission(LogicCenterPermissions::ROSTER_MANAGE);
    }

    private function canDecide(User $user, AttendanceShiftSwapRequest $attendanceShiftSwapRequest): bool
    {
        return ($user->hasPermission('attendance.approve') || $user->hasPermission(LogicCenterPermissions::SWAP_APPROVE))
            && $attendanceShiftSwapRequest->status === 'submitted'
            && (int) $attendanceShiftSwapRequest->requested_by_user_id !== (int) $user->id
            && $this->inCompanyScope($user, $attendanceShiftSwapRequest);
    }

    private function inCompanyScope(User $user, AttendanceShiftSwapRequest $attendanceShiftSwapRequest): bool
    {
        return app(CompanyScopeService::class)->allows($user, $attendanceShiftSwapRequest->company_id);
    }
}
