<?php

namespace App\Domain\Hr\Services;

use App\Application\Hr\Data\AttendanceAssignmentRowData;
use App\Application\Hr\Data\AttendanceCalculationTraceData;
use App\Application\Hr\Data\AttendanceRecordRowData;
use App\Application\Hr\Data\AttendanceRegularizationRowData;
use App\Application\Hr\Data\AttendanceShiftRowData;
use App\Application\Hr\Data\AttendanceSummaryData;
use App\Models\AttendanceRecord;
use App\Models\AttendanceRegularizationRequest;
use App\Models\AttendanceShift;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeShiftAssignment;
use App\Models\User;
use App\Services\Security\CompanyScopeService;
use App\Support\PaginationPolicy;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class AttendanceWorkspaceRegister
{
    public function __construct(private readonly CompanyScopeService $scope, private readonly PaginationPolicy $pagination) {}

    public function shifts(User $u, array $f = [], string $page = 'page'): LengthAwarePaginator
    {
        return $this->scope->apply(
            AttendanceShift::query()
                ->with('segments')
                ->withCount([
                    'assignments as active_assignments_count' => fn (Builder $query) => $query->where('is_active', true),
                ]),
            $u,
        )->where('is_active', true)
            ->orderBy('name')
            ->paginate($this->pagination->largePerPage($f['per_page'] ?? null), ['*'], $page);
    }

    public function records(User $u, array $f = [], string $page = 'page'): LengthAwarePaginator
    {
        $q = $this->recordsQuery($u, $f, includeStatus: true)
            ->with(['employee.branch', 'shift'])
            ->when(
                ($f['view'] ?? 'records') === 'exceptions',
                fn (Builder $query) => $query->where(function (Builder $exceptions): void {
                    $exceptions
                        ->whereIn('status', ['late', 'early_leave', 'half_day', 'absent'])
                        ->orWhereNull('check_in_at')
                        ->orWhereNull('check_out_at');
                }),
            )
            ->latest('work_date')
            ->latest('id');

        return $q->paginate($this->pagination->defaultPerPage($f['per_page'] ?? null), ['*'], $page);
    }

    public function assignments(User $u, array $f = [], string $page = 'page'): LengthAwarePaginator
    {
        $query = $this->scope->apply(
            EmployeeShiftAssignment::query()->with(['employee.branch', 'shift']),
            $u,
        )
            ->when(
                ! $this->canManage($u),
                fn (Builder $builder) => $builder->whereHas(
                    'employee',
                    fn (Builder $employee) => $employee->where('user_id', $u->id),
                ),
            )
            ->where('is_active', true)
            ->orderByDesc('effective_from')
            ->orderByDesc('id');

        return $query->paginate(
            $this->pagination->defaultPerPage($f['per_page'] ?? null),
            ['*'],
            $page,
        );
    }

    /**
     * Complete-query attendance counts for the selected employee/date scope.
     * The explicit status filter is intentionally excluded so the KPI
     * distribution remains meaningful while a register status is selected.
     */
    public function summary(User $u, array $f = []): AttendanceSummaryData
    {
        $counts = $this->recordsQuery($u, $f, includeStatus: false)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn (mixed $count): int => (int) $count);

        $total = (int) $counts->sum();
        $present = (int) $counts->get('present', 0);
        $late = (int) $counts->get('late', 0);
        $early = (int) $counts->get('early_leave', 0);
        $halfDay = (int) $counts->get('half_day', 0);
        $absent = (int) $counts->get('absent', 0);

        return new AttendanceSummaryData(
            total: $total,
            present: $present,
            late: $late,
            earlyLeave: $early,
            halfDay: $halfDay,
            absent: $absent,
            attendanceRate: $total > 0
                ? round((($present + $late + $early + $halfDay) / $total) * 100, 1)
                : 0.0,
            pendingRegularizations: $this->regularizationScope($u)
                ->when(
                    $f['employee_id'] ?? null,
                    fn (Builder $query, mixed $employeeId) => $query->where('employee_id', $employeeId),
                )
                ->where('status', 'submitted')
                ->count(),
        );
    }

    /**
     * @return Collection<int, array{location:string,strength:int,marked:int,coverage:float}>
     */
    public function siteAttendance(User $u, array $f = []): Collection
    {
        $employeeQuery = $this->scope->apply(
            Employee::query()
                ->leftJoin('branches', 'branches.id', '=', 'employees.branch_id')
                ->where('employees.status', 'active'),
            $u,
            'employees.company_id',
        )
            ->when(
                $f['employee_id'] ?? null,
                fn (Builder $query, mixed $employeeId) => $query->where('employees.id', $employeeId),
            )
            ->selectRaw("COALESCE(branches.name, 'No branch') as location, COUNT(employees.id) as strength")
            ->groupBy('branches.id', 'branches.name')
            ->orderBy('location');

        $presentByLocation = $this->recordsQuery($u, $f, includeStatus: false)
            ->join('employees', 'employees.id', '=', 'attendance_records.employee_id')
            ->leftJoin('branches', 'branches.id', '=', 'employees.branch_id')
            ->whereIn('attendance_records.status', ['present', 'late', 'early_leave', 'half_day'])
            ->selectRaw("COALESCE(branches.name, 'No branch') as location, COUNT(DISTINCT attendance_records.employee_id) as marked")
            ->groupBy('branches.id', 'branches.name')
            ->pluck('marked', 'location');

        return $employeeQuery->get()->map(function (object $row) use ($presentByLocation): array {
            $strength = (int) $row->strength;
            $marked = (int) ($presentByLocation[$row->location] ?? 0);

            return [
                'location' => (string) $row->location,
                'strength' => $strength,
                'marked' => $marked,
                'coverage' => $strength > 0 ? round(($marked / $strength) * 100, 1) : 0.0,
            ];
        })->values();
    }

    public function regularizations(User $u, array $f = [], string $page = 'page'): LengthAwarePaginator
    {
        $q = $this->regularizationScope($u)
            ->with(['employee', 'attendanceRecord', 'requestedBy', 'decidedBy'])
            ->when($f['employee_id'] ?? null, fn (Builder $query, mixed $value) => $query->where('employee_id', $value))
            ->when($f['status'] ?? null, fn (Builder $query, mixed $value) => $query->where('status', $value))
            ->latest();

        return $q->paginate($this->pagination->defaultPerPage($f['per_page'] ?? null), ['*'], $page);
    }

    public function companies(User $u): Collection
    {
        $q = Company::query();
        $this->scope->apply($q, $u, 'id');

        return $q->orderBy('name')->get(['id', 'code', 'name']);
    }

    public function employees(User $u): Collection
    {
        return $this->scope->apply(Employee::query(), $u)->when(! $this->canManage($u), fn ($q) => $q->where('user_id', $u->id))->orderBy('employee_code')->get(['id', 'employee_code', 'name', 'department', 'company_id', 'user_id']);
    }

    public function canManage(User $u): bool
    {
        return $u->hasPermission('attendance.manage') || $u->hasPermission('attendance.approve');
    }

    public function presentRecords(LengthAwarePaginator $paginator): LengthAwarePaginator
    {
        $timezone = (string) config('app.timezone', 'Asia/Kolkata');

        return $paginator->through(function (AttendanceRecord $record) use ($timezone): AttendanceRecordRowData {
            $shiftTiming = $record->shift
                ? $this->clock($record->shift->starts_at).' - '.$this->clock($record->shift->ends_at)
                : 'No shift assigned';

            return new AttendanceRecordRowData(
                id: $record->id,
                workDate: $record->work_date?->format('d M Y') ?? 'Date unavailable',
                employeeCode: $record->employee?->employee_code ?? '-',
                employeeName: $record->employee?->name ?? 'Unknown employee',
                employeeInitial: Str::upper(Str::substr($record->employee?->name ?? '?', 0, 1)),
                branch: $record->employee?->branch?->name ?? 'No branch',
                shiftCode: $record->shift?->code ?? '-',
                shiftName: $record->shift?->name ?? 'No shift assigned',
                shiftTiming: $shiftTiming,
                checkIn: $record->check_in_at?->timezone($timezone)->format('d M Y, h:i A') ?? 'Not recorded',
                checkOut: $record->check_out_at?->timezone($timezone)->format('d M Y, h:i A') ?? 'Not recorded',
                status: (string) $record->status,
                statusLabel: Str::headline((string) $record->status),
                lateMinutes: (int) ($record->late_minutes ?? 0),
                earlyLeaveMinutes: (int) ($record->early_leave_minutes ?? 0),
                workedMinutes: (int) ($record->worked_minutes ?? 0),
                sourceLabel: Str::headline((string) $record->source),
                calculationBasis: $this->calculationBasis($record),
            );
        });
    }

    public function presentCalculationTraces(LengthAwarePaginator $paginator): LengthAwarePaginator
    {
        $timezone = (string) config('app.timezone', 'Asia/Kolkata');

        return $paginator->through(function (AttendanceRecord $record) use ($timezone): AttendanceCalculationTraceData {
            $shift = $record->shift;
            $metadata = is_array($record->metadata) ? $record->metadata : [];
            $regularizationNumber = $metadata['regularization_request_number'] ?? null;

            return new AttendanceCalculationTraceData(
                recordId: $record->id,
                employeeCode: $record->employee?->employee_code ?? '-',
                employeeName: $record->employee?->name ?? 'Unknown employee',
                employeeInitial: Str::upper(Str::substr($record->employee?->name ?? '?', 0, 1)),
                branch: $record->employee?->branch?->name ?? 'No branch',
                workDate: $record->work_date?->format('d M Y') ?? 'Date unavailable',
                sourceLabel: Str::headline((string) $record->source),
                checkIn: $record->check_in_at?->timezone($timezone)->format('d M Y, h:i A') ?? 'Not recorded',
                checkOut: $record->check_out_at?->timezone($timezone)->format('d M Y, h:i A') ?? 'Not recorded',
                regularizationRequestNumber: is_scalar($regularizationNumber) && (string) $regularizationNumber !== ''
                    ? (string) $regularizationNumber
                    : null,
                hasLinkedShift: $shift !== null,
                shiftCode: $shift?->code ?? '-',
                shiftName: $shift?->name ?? 'No shift linked',
                shiftTiming: $shift
                    ? $this->clock($shift->starts_at).' - '.$this->clock($shift->ends_at)
                    : 'Timing unavailable',
                overnight: (bool) ($shift?->is_overnight ?? false),
                lateGraceMinutes: (int) ($shift?->late_grace_minutes ?? 0),
                earlyLeaveGraceMinutes: (int) ($shift?->early_leave_grace_minutes ?? 0),
                halfDayThresholdMinutes: (int) ($shift?->half_day_threshold_minutes ?? 0),
                fullDayThresholdMinutes: (int) ($shift?->full_day_threshold_minutes ?? 0),
                status: (string) $record->status,
                statusLabel: Str::headline((string) $record->status),
                lateMinutes: (int) ($record->late_minutes ?? 0),
                earlyLeaveMinutes: (int) ($record->early_leave_minutes ?? 0),
                workedMinutes: (int) ($record->worked_minutes ?? 0),
                provenanceNote: $shift
                    ? 'The shift values shown are the current linked definition. No calculation-time rule snapshot is stored for this record, so this trace does not claim that the current definition produced the persisted result.'
                    : 'No linked shift definition or calculation-time rule snapshot is stored for this record. Only the recorded inputs and persisted result can be verified.',
            );
        });
    }

    public function presentRegularizations(LengthAwarePaginator $paginator, User $actor): LengthAwarePaginator
    {
        $timezone = (string) config('app.timezone', 'Asia/Kolkata');

        return $paginator->through(fn (AttendanceRegularizationRequest $request) => new AttendanceRegularizationRowData(
            id: $request->id,
            requestNumber: $request->request_number,
            employeeCode: $request->employee?->employee_code ?? '-',
            employeeName: $request->employee?->name ?? 'Unknown employee',
            workDate: $request->work_date?->format('d M Y') ?? 'Date unavailable',
            requestedCheckIn: $request->requested_check_in_at?->timezone($timezone)->format('d M Y, h:i A') ?? 'Not recorded',
            requestedCheckOut: $request->requested_check_out_at?->timezone($timezone)->format('d M Y, h:i A') ?? 'Not recorded',
            status: (string) $request->status,
            statusLabel: Str::headline((string) $request->status),
            reason: $request->reason ?: 'No reason provided',
            decisionNote: $request->decision_note,
            canApprove: $actor->can('approve', $request),
            canReject: $actor->can('reject', $request),
        ));
    }

    public function presentShifts(LengthAwarePaginator $paginator): LengthAwarePaginator
    {
        return $paginator->through(fn (AttendanceShift $shift) => new AttendanceShiftRowData(
            id: $shift->id,
            code: $shift->code,
            name: $shift->name,
            timing: $this->clock($shift->starts_at).' - '.$this->clock($shift->ends_at),
            overnight: (bool) $shift->is_overnight,
            lateGraceMinutes: (int) $shift->late_grace_minutes,
            earlyLeaveGraceMinutes: (int) $shift->early_leave_grace_minutes,
            halfDayThresholdMinutes: (int) $shift->half_day_threshold_minutes,
            fullDayThresholdMinutes: (int) $shift->full_day_threshold_minutes,
            rules: collect($shift->rules ?? [])->map(
                fn (mixed $value, mixed $key): string => Str::headline((string) $key).': '.(
                    is_bool($value) ? ($value ? 'Yes' : 'No') : (string) $value
                ),
            )->values()->all(),
            segments: $shift->segments->map(fn ($segment): array => [
                'sequence' => (int) $segment->sequence,
                'label' => $segment->label,
                'timing' => $this->clock($segment->starts_at).' - '.$this->clock($segment->ends_at),
            ])->all(),
            activeAssignments: (int) ($shift->active_assignments_count ?? 0),
        ));
    }

    public function presentAssignments(LengthAwarePaginator $paginator): LengthAwarePaginator
    {
        return $paginator->through(fn (EmployeeShiftAssignment $assignment) => new AttendanceAssignmentRowData(
            id: $assignment->id,
            employeeCode: $assignment->employee?->employee_code ?? '-',
            employeeName: $assignment->employee?->name ?? 'Unknown employee',
            employeeInitial: Str::upper(Str::substr($assignment->employee?->name ?? '?', 0, 1)),
            department: $assignment->employee?->department ?: 'No department',
            branch: $assignment->employee?->branch?->name ?? 'No branch',
            shiftCode: $assignment->shift?->code ?? '-',
            shiftName: $assignment->shift?->name ?? 'Unknown shift',
            shiftTiming: $assignment->shift
                ? $this->clock($assignment->shift->starts_at).' - '.$this->clock($assignment->shift->ends_at)
                : 'Timing unavailable',
            effectiveFrom: $assignment->effective_from?->format('d M Y') ?? 'Not recorded',
            effectiveTo: $assignment->effective_to?->format('d M Y') ?? 'No end date',
            statusLabel: $assignment->is_active ? 'Active' : 'Inactive',
        ));
    }

    /** @return Builder<AttendanceRecord> */
    private function recordsQuery(User $u, array $f, bool $includeStatus): Builder
    {
        return $this->scope->apply(AttendanceRecord::query(), $u, 'attendance_records.company_id')
            ->when(
                ! $this->canManage($u),
                fn (Builder $query) => $query->whereHas(
                    'employee',
                    fn (Builder $employee) => $employee->where('user_id', $u->id),
                ),
            )
            ->when($f['employee_id'] ?? null, fn (Builder $query, mixed $value) => $query->where('employee_id', $value))
            ->when(
                $includeStatus && ($f['status'] ?? null),
                fn (Builder $query) => $query->where('status', $f['status']),
            )
            ->when($f['date_from'] ?? null, fn (Builder $query, mixed $value) => $query->whereDate('work_date', '>=', $value))
            ->when($f['date_to'] ?? null, fn (Builder $query, mixed $value) => $query->whereDate('work_date', '<=', $value));
    }

    /** @return Builder<AttendanceRegularizationRequest> */
    private function regularizationScope(User $u): Builder
    {
        return $this->scope->apply(AttendanceRegularizationRequest::query(), $u)
            ->when(
                ! $this->canManage($u),
                fn (Builder $query) => $query->whereHas(
                    'employee',
                    fn (Builder $employee) => $employee->where('user_id', $u->id),
                ),
            );
    }

    private function clock(?string $value): string
    {
        if (! $value) {
            return 'Not set';
        }

        $time = substr($value, 0, 5);

        return date('g:i A', strtotime($time));
    }

    private function calculationBasis(AttendanceRecord $record): string
    {
        $parts = [];

        if ($record->shift) {
            $parts[] = 'Shift '.$record->shift->code.' ('.$this->clock($record->shift->starts_at).' - '.$this->clock($record->shift->ends_at).')';
            $parts[] = 'late grace '.$record->shift->late_grace_minutes.' min';
            $parts[] = 'early-leave grace '.$record->shift->early_leave_grace_minutes.' min';
        } else {
            $parts[] = 'No shift rule linked';
        }

        $parts[] = 'stored result '.Str::lower(Str::headline((string) $record->status));

        return implode(' / ', $parts);
    }
}
