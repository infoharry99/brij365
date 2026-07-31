<?php

namespace App\Domain\Hr\Services;

use App\Application\Hr\Data\AttendanceRosterWorkspaceData;
use App\Domain\Scoring\Support\LogicCenterPermissions;
use App\Models\AttendancePeriodLock;
use App\Models\AttendanceRoster;
use App\Models\AttendanceRosterEntry;
use App\Models\AttendanceRotationRule;
use App\Models\AttendanceShift;
use App\Models\AttendanceShiftSwapRequest;
use App\Models\Employee;
use App\Models\User;
use App\Services\Security\CompanyScopeService;
use App\Support\PaginationPolicy;

final class AttendanceRosterRegister
{
    public function __construct(
        private readonly CompanyScopeService $scope,
        private readonly PaginationPolicy $pagination,
        private readonly AttendanceRosterRulePackResolver $rulePacks,
    ) {}

    /** @param array<string, mixed> $filters */
    public function workspace(User $actor, array $filters = []): AttendanceRosterWorkspaceData
    {
        $activeView = $filters['view'] ?? 'rosters';
        $perPage = $this->pagination->defaultPerPage(isset($filters['per_page']) ? (int) $filters['per_page'] : null);
        $canViewAll = $actor->hasPermission('attendance.view')
            || $actor->hasPermission('attendance.manage')
            || $actor->hasPermission('attendance.approve')
            || $actor->hasPermission(LogicCenterPermissions::ROSTER_MANAGE)
            || $actor->hasPermission(LogicCenterPermissions::ROSTER_PUBLISH)
            || $actor->hasPermission(LogicCenterPermissions::ROSTER_REOPEN)
            || $actor->hasPermission(LogicCenterPermissions::SWAP_APPROVE)
            || $actor->hasPermission(LogicCenterPermissions::ATTENDANCE_FINALIZE)
            || $actor->hasPermission(LogicCenterPermissions::ATTENDANCE_REOPEN);
        $employeeId = $actor->employee?->id;

        $rosterQuery = $this->scope->apply(
            AttendanceRoster::query()->with(['createdBy', 'entries.employee', 'entries.shift'])->withCount('entries'),
            $actor,
        );
        if (! $canViewAll) {
            $rosterQuery
                ->whereIn('status', ['published', 'locked'])
                ->when(
                    $employeeId,
                    fn ($query) => $query->whereHas('entries', fn ($entry) => $entry->where('employee_id', $employeeId)),
                    fn ($query) => $query->whereRaw('1 = 0'),
                );
        }
        $rosters = $rosterQuery
            ->when(
                $activeView === 'rosters' && in_array($filters['status'] ?? null, ['draft', 'published', 'locked', 'cancelled'], true),
                fn ($query) => $query->where('status', $filters['status']),
            )
            ->when($filters['employee_id'] ?? null, fn ($query, $id) => $query->whereHas('entries', fn ($entry) => $entry->where('employee_id', $id)))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('period_end', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('period_start', '<=', $date))
            ->latest('period_start')
            ->latest('id')
            ->paginate($perPage, ['*'], 'roster_page')
            ->withQueryString();

        $rotationQuery = $this->scope->apply(
            AttendanceRotationRule::query()->with(['employee', 'createdBy'])->withCount('entries'),
            $actor,
        );
        if (! $canViewAll) {
            $rotationQuery->when(
                $employeeId,
                fn ($query) => $query->where('employee_id', $employeeId),
                fn ($query) => $query->whereRaw('1 = 0'),
            );
        }
        $rotations = $rotationQuery
            ->when(
                $activeView === 'rotations' && in_array($filters['status'] ?? null, ['active', 'paused'], true),
                fn ($query) => $query->where('status', $filters['status']),
            )
            ->when($filters['employee_id'] ?? null, fn ($query, $id) => $query->where('employee_id', $id))
            ->latest('id')
            ->paginate($perPage, ['*'], 'rotation_page')
            ->withQueryString();

        $swapQuery = $this->scope->apply(
            AttendanceShiftSwapRequest::query()->with([
                'requesterEmployee',
                'sourceEntry.employee',
                'sourceEntry.shift',
                'targetEntry.employee',
                'targetEntry.shift',
                'requestedBy',
                'decidedBy',
            ]),
            $actor,
        );
        if (! $canViewAll) {
            $swapQuery->where(function ($query) use ($actor, $employeeId): void {
                $query->where('requested_by_user_id', $actor->id)
                    ->when($employeeId, fn ($nested) => $nested
                        ->orWhereHas('sourceEntry', fn ($entry) => $entry->where('employee_id', $employeeId))
                        ->orWhereHas('targetEntry', fn ($entry) => $entry->where('employee_id', $employeeId)));
            });
        }
        $swaps = $swapQuery
            ->when(
                $activeView === 'swaps' && in_array($filters['status'] ?? null, ['submitted', 'approved', 'rejected', 'cancelled'], true),
                fn ($query) => $query->where('status', $filters['status']),
            )
            ->latest('id')
            ->paginate($perPage, ['*'], 'swap_page')
            ->withQueryString();

        $periodLockQuery = $this->scope->apply(
            AttendancePeriodLock::query()->with(['finalizedBy'])->withCount('snapshots'),
            $actor,
        );
        if (! $actor->can('viewAny', AttendancePeriodLock::class)) {
            $periodLockQuery->whereRaw('1 = 0');
        }
        $periodLocks = $periodLockQuery
            ->when(
                $activeView === 'periods' && in_array($filters['status'] ?? null, ['finalized', 'reopened'], true),
                fn ($query) => $query->where('status', $filters['status']),
            )
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('period_end', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('period_start', '<=', $date))
            ->latest('period_start')
            ->latest('version')
            ->paginate($perPage, ['*'], 'lock_page')
            ->withQueryString();

        $employees = $this->scope->apply(Employee::query(), $actor)
            ->where('status', 'active')
            ->when(! $canViewAll, fn ($query) => $employeeId ? $query->whereKey($employeeId) : $query->whereRaw('1 = 0'))
            ->orderBy('name')
            ->get(['id', 'company_id', 'user_id', 'employee_code', 'name', 'department', 'designation']);
        $shifts = $this->scope->apply(AttendanceShift::query(), $actor)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        $availableEntries = $this->scope->apply(
            AttendanceRosterEntry::query()->with(['roster', 'employee', 'shift'])
                ->where('entry_type', 'shift')
                ->whereHas('roster', fn ($query) => $query->where('status', 'published')),
            $actor,
        )
            ->whereDate('work_date', '>=', now()->subDay()->toDateString())
            ->orderBy('work_date')
            ->limit(250)
            ->get();
        $draftRosters = $this->scope->apply(
            AttendanceRoster::query()->where('status', 'draft'),
            $actor,
        )
            ->when(
                ! $actor->hasPermission('attendance.manage') && ! $actor->hasPermission(LogicCenterPermissions::ROSTER_MANAGE),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->orderBy('period_start')
            ->orderBy('id')
            ->limit(100)
            ->get();
        $companyId = $this->scope->companyIdFor($actor) ?? (int) $actor->company_id;
        $governedTimezone = $companyId > 0
            ? $this->rulePacks->resolve($companyId)->timezone
            : 'Asia/Kolkata';

        return new AttendanceRosterWorkspaceData(
            rosters: $rosters,
            rotations: $rotations,
            swaps: $swaps,
            periodLocks: $periodLocks,
            employees: $employees,
            shifts: $shifts,
            availableEntries: $availableEntries,
            draftRosters: $draftRosters,
            governedTimezone: $governedTimezone,
            abilities: [
                'canManage' => $actor->hasPermission('attendance.manage') || $actor->hasPermission(LogicCenterPermissions::ROSTER_MANAGE),
                'canApprove' => $actor->hasPermission('attendance.approve')
                    || $actor->hasPermission(LogicCenterPermissions::ROSTER_PUBLISH)
                    || $actor->hasPermission(LogicCenterPermissions::ROSTER_REOPEN)
                    || $actor->hasPermission(LogicCenterPermissions::SWAP_APPROVE),
                'canRequestSwap' => $actor->can('create', AttendanceShiftSwapRequest::class),
                'canFinalize' => $actor->can('create', AttendancePeriodLock::class),
                'canViewPeriods' => $actor->can('viewAny', AttendancePeriodLock::class),
            ],
            filters: $filters,
        );
    }
}
