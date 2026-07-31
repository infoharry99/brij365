<?php

namespace App\Domain\Scoring\Services;

use App\Domain\Scoring\Support\LogicCenterPermissions;
use App\Models\User;

final class LogicCenterAccessService
{
    public const SECTIONS = [
        'overview',
        'business',
        'performance',
        'statutory',
        'roster',
        'simulation',
        'audit',
    ];

    public function canViewAny(User $user): bool
    {
        return collect(self::SECTIONS)->contains(fn (string $section): bool => $this->canViewSection($user, $section));
    }

    public function canViewSection(User $user, string $section): bool
    {
        return match ($section) {
            'overview' => $this->hasAny($user, [
                LogicCenterPermissions::VIEW, 'scoring.manage', 'scoring.approve',
                LogicCenterPermissions::PERFORMANCE_MANAGE,
                LogicCenterPermissions::PERFORMANCE_APPROVE,
                LogicCenterPermissions::STATUTORY_MANAGE,
                LogicCenterPermissions::STATUTORY_VERIFY,
                LogicCenterPermissions::STATUTORY_APPROVE,
                LogicCenterPermissions::STATUTORY_SIMULATE,
                LogicCenterPermissions::ROSTER_MANAGE,
                LogicCenterPermissions::ROSTER_PUBLISH,
                LogicCenterPermissions::ROSTER_REOPEN,
                LogicCenterPermissions::ATTENDANCE_FINALIZE,
                LogicCenterPermissions::ATTENDANCE_REOPEN,
                LogicCenterPermissions::AUDIT_VIEW,
            ]),
            'business' => $this->hasAny($user, [LogicCenterPermissions::VIEW, 'scoring.manage', 'scoring.approve']),
            'performance' => $this->hasAny($user, [
                'scoring.manage', 'scoring.approve',
                'performance.view', 'performance.manage', 'performance.approve',
                LogicCenterPermissions::PERFORMANCE_MANAGE,
                LogicCenterPermissions::PERFORMANCE_APPROVE,
                LogicCenterPermissions::PERFORMANCE_OVERRIDE_REQUEST,
                LogicCenterPermissions::PERFORMANCE_OVERRIDE_APPROVE,
            ]),
            'statutory' => $this->hasAny($user, [
                'compliance.view', 'compliance.manage', 'payroll.view', 'payroll.manage', 'payroll.approve',
                'settings.view', LogicCenterPermissions::STATUTORY_MANAGE,
                LogicCenterPermissions::STATUTORY_VERIFY,
                LogicCenterPermissions::STATUTORY_APPROVE,
                LogicCenterPermissions::STATUTORY_SIMULATE,
            ]),
            'roster' => $this->hasAny($user, [
                'attendance.manage', 'attendance.approve', 'settings.view',
                LogicCenterPermissions::ROSTER_MANAGE,
                LogicCenterPermissions::ROSTER_PUBLISH,
                LogicCenterPermissions::ROSTER_REOPEN,
                LogicCenterPermissions::SWAP_APPROVE,
                LogicCenterPermissions::ATTENDANCE_FINALIZE,
                LogicCenterPermissions::ATTENDANCE_REOPEN,
            ]),
            'simulation' => $this->hasAny($user, [
                LogicCenterPermissions::STATUTORY_SIMULATE,
                LogicCenterPermissions::PERFORMANCE_MANAGE,
                'payroll.manage', 'performance.manage', 'scoring.manage',
            ]),
            'audit' => $this->hasAny($user, [
                LogicCenterPermissions::AUDIT_VIEW, 'audit.view',
                'scoring.manage', 'scoring.approve', 'scoring.recalculate',
            ]),
            default => false,
        };
    }

    /** @return array<string, bool> */
    public function capabilities(User $user): array
    {
        return [
            'managePerformance' => $this->hasAny($user, [LogicCenterPermissions::PERFORMANCE_MANAGE, 'performance.manage', 'scoring.manage']),
            'approvePerformance' => $this->hasAny($user, [LogicCenterPermissions::PERFORMANCE_APPROVE, 'performance.approve', 'scoring.approve']),
            'requestPerformanceOverride' => $this->hasAny($user, [LogicCenterPermissions::PERFORMANCE_OVERRIDE_REQUEST, 'scoring.override']),
            'approvePerformanceOverride' => $this->hasAny($user, [LogicCenterPermissions::PERFORMANCE_OVERRIDE_APPROVE, 'performance.approve']),
            'manageStatutory' => $this->hasAny($user, [LogicCenterPermissions::STATUTORY_MANAGE, 'compliance.manage']),
            'verifyStatutory' => $this->hasAny($user, [LogicCenterPermissions::STATUTORY_VERIFY, 'compliance.manage']),
            'approveStatutory' => $this->hasAny($user, [LogicCenterPermissions::STATUTORY_APPROVE, 'settings.approve']),
            'simulateStatutory' => $this->hasAny($user, [LogicCenterPermissions::STATUTORY_SIMULATE, 'payroll.manage']),
            'manageRosters' => $this->hasAny($user, [LogicCenterPermissions::ROSTER_MANAGE, 'attendance.manage']),
            'publishRosters' => $this->hasAny($user, [LogicCenterPermissions::ROSTER_PUBLISH, 'attendance.approve']),
            'reopenRosters' => $this->hasAny($user, [LogicCenterPermissions::ROSTER_REOPEN, 'attendance.approve']),
            'requestSwap' => $this->hasAny($user, [LogicCenterPermissions::SWAP_REQUEST, 'employee.self_service']),
            'approveSwap' => $this->hasAny($user, [LogicCenterPermissions::SWAP_APPROVE, 'attendance.approve']),
            'finalizeAttendance' => $this->hasAny($user, [LogicCenterPermissions::ATTENDANCE_FINALIZE, 'attendance.approve']),
            'reopenAttendance' => $this->hasAny($user, [LogicCenterPermissions::ATTENDANCE_REOPEN, 'attendance.approve']),
            'viewAudit' => $this->hasAny($user, [LogicCenterPermissions::AUDIT_VIEW, 'audit.view']),
        ];
    }

    /** @param list<string> $permissions */
    private function hasAny(User $user, array $permissions): bool
    {
        return collect($permissions)->contains(fn (string $permission): bool => $user->hasPermission($permission));
    }
}
