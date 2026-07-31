<?php

namespace App\Domain\Hr\Services;

use App\Models\AttendanceRecord;
use App\Models\AttendanceRosterEntry;
use App\Models\Employee;
use App\Models\EmployeeShiftAssignment;
use App\Models\LeaveRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class AttendancePeriodFinalizationGuard
{
    private const RESOLVED_STATUSES = [
        'present',
        'late',
        'early_leave',
        'half_day',
        'absent',
        'on_leave',
        'weekly_off',
        'holiday',
    ];

    public function __construct(private readonly RosterResolutionEngine $rosterResolutionEngine) {}

    /**
     * Reconcile deterministic, authoritative non-punch outcomes and then
     * reject every employed employee-day that lacks a schedule or resolved
     * attendance. Effective default assignments are authoritative schedules;
     * published/locked dated roster entries take precedence over them.
     */
    public function reconcileAndAssert(int $companyId, Carbon $start, Carbon $end): void
    {
        $periodStart = $start->copy()->startOfDay();
        $periodEnd = $end->copy()->startOfDay();
        $entries = AttendanceRosterEntry::query()
            ->with(['roster:id,status,rule_context', 'shift:id'])
            ->where('company_id', $companyId)
            ->whereDate('work_date', '>=', $periodStart->toDateString())
            ->whereDate('work_date', '<=', $periodEnd->toDateString())
            ->whereHas('roster', fn ($query) => $query->whereIn('status', ['published', 'locked']))
            ->orderBy('employee_id')
            ->orderBy('work_date')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $duplicate = $entries
            ->groupBy(fn (AttendanceRosterEntry $entry): string => $this->key($entry->employee_id, $entry->work_date))
            ->first(fn (Collection $group): bool => $group->count() > 1);
        if ($duplicate !== null) {
            throw ValidationException::withMessages([
                'period_start' => 'Attendance cannot be finalized because multiple authoritative roster entries exist for an employee and work date.',
            ]);
        }

        $activeEmployees = Employee::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->where(function ($query) use ($periodEnd): void {
                $query->whereNull('joined_on')->orWhereDate('joined_on', '<=', $periodEnd->toDateString());
            })
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        $resolvedSchedules = $this->rosterResolutionEngine->resolveRange($activeEmployees, $periodStart, $periodEnd);

        /**
         * @var Collection<string, array{
         *     employee_id: int,
         *     work_date: Carbon,
         *     schedule: AttendanceRosterEntry|EmployeeShiftAssignment|null
         * }> $expectedDays
         */
        $expectedDays = collect();
        foreach ($activeEmployees as $employee) {
            $employeeStart = $periodStart->copy();
            if ($employee->joined_on && $employee->joined_on->gt($employeeStart)) {
                $employeeStart = $employee->joined_on->copy()->startOfDay();
            }

            for ($date = $employeeStart; $date->lte($periodEnd); $date->addDay()) {
                $dateString = $date->toDateString();
                $expectedDays->put($this->key($employee->id, $date), [
                    'employee_id' => (int) $employee->id,
                    'work_date' => $date->copy(),
                    'schedule' => $resolvedSchedules[$employee->id][$dateString] ?? null,
                ]);
            }
        }

        // Preserve the existing historical contract for non-active employees
        // who still have published/locked roster evidence in the period.
        foreach ($entries as $entry) {
            $key = $this->key($entry->employee_id, $entry->work_date);
            if (! $expectedDays->has($key)) {
                $expectedDays->put($key, [
                    'employee_id' => (int) $entry->employee_id,
                    'work_date' => $entry->work_date->copy(),
                    'schedule' => $entry,
                ]);
            }
        }

        if ($expectedDays->isEmpty()) {
            return;
        }

        $employeeIds = $expectedDays->pluck('employee_id')->unique()->values();
        $records = AttendanceRecord::withTrashed()
            ->where('company_id', $companyId)
            ->whereIn('employee_id', $employeeIds)
            ->whereDate('work_date', '>=', $periodStart->toDateString())
            ->whereDate('work_date', '<=', $periodEnd->toDateString())
            ->lockForUpdate()
            ->get()
            ->keyBy(fn (AttendanceRecord $record): string => $this->key($record->employee_id, $record->work_date));
        $leaveRequests = LeaveRequest::query()
            ->with('leaveType')
            ->where('company_id', $companyId)
            ->whereIn('employee_id', $employeeIds)
            ->where('status', 'approved')
            ->whereDate('starts_on', '<=', $periodEnd->toDateString())
            ->whereDate('ends_on', '>=', $periodStart->toDateString())
            ->orderBy('starts_on')
            ->orderBy('id')
            ->get()
            ->groupBy('employee_id');

        $unresolved = [];
        foreach ($expectedDays as $expected) {
            $employeeId = $expected['employee_id'];
            $workDate = $expected['work_date'];
            $workDateString = $workDate->toDateString();
            $key = $this->key($employeeId, $workDate);
            $schedule = $expected['schedule'];

            if (! $schedule instanceof AttendanceRosterEntry && ! $schedule instanceof EmployeeShiftAssignment) {
                $unresolved[] = "employee #{$employeeId} on {$workDateString} (no authoritative schedule)";

                continue;
            }

            $record = $records->get($key);
            if ($record && ! $record->trashed() && in_array($record->status, self::RESOLVED_STATUSES, true)) {
                continue;
            }

            $resolution = $this->deterministicResolution(
                $schedule,
                $workDate,
                $leaveRequests->get($employeeId, collect()),
            );
            if ($resolution === null || $record?->trashed()) {
                $unresolved[] = "employee #{$employeeId} on {$workDateString}";

                continue;
            }

            $created = $this->materialize(
                $schedule,
                $workDate,
                $resolution['status'],
                $resolution['reason'],
                $resolution['leave_request'],
            );
            $records->put($key, $created);
        }

        if ($unresolved !== []) {
            $examples = implode(', ', array_slice($unresolved, 0, 5));
            $remaining = count($unresolved) - min(count($unresolved), 5);
            $suffix = $remaining > 0 ? " and {$remaining} more" : '';

            throw ValidationException::withMessages([
                'period_start' => "Resolve an authoritative schedule and attendance, approved full-day leave, holiday, weekly off, or explicit absence for {$examples}{$suffix} before finalization.",
            ]);
        }
    }

    /**
     * @param  Collection<int, LeaveRequest>  $leaveRequests
     * @return array{status: string, reason: string, leave_request: ?LeaveRequest}|null
     */
    private function deterministicResolution(
        AttendanceRosterEntry|EmployeeShiftAssignment $schedule,
        Carbon $workDate,
        Collection $leaveRequests,
    ): ?array {
        if ($schedule instanceof AttendanceRosterEntry && $schedule->entry_type === 'off') {
            return ['status' => 'weekly_off', 'reason' => 'published_roster_off', 'leave_request' => null];
        }

        if ($schedule instanceof AttendanceRosterEntry && $schedule->entry_type === 'holiday') {
            return ['status' => 'holiday', 'reason' => 'published_roster_holiday', 'leave_request' => null];
        }

        if ($schedule instanceof AttendanceRosterEntry && $schedule->entry_type !== 'shift') {
            return null;
        }

        $date = $workDate->toDateString();
        $matchingLeaves = $leaveRequests
            ->filter(fn (LeaveRequest $leave): bool =>
                $leave->duration_unit === 'full_day'
                && $leave->starts_on->toDateString() <= $date
                && $leave->ends_on->toDateString() >= $date)
            ->values();

        if ($matchingLeaves->count() !== 1) {
            return null;
        }

        $leave = $matchingLeaves->first();
        if (! $leave->leaveType) {
            return null;
        }

        return [
            'status' => $leave->leaveType->is_paid ? 'on_leave' : 'absent',
            'reason' => $leave->leaveType->is_paid ? 'approved_paid_leave' : 'approved_unpaid_leave',
            'leave_request' => $leave,
        ];
    }

    private function materialize(
        AttendanceRosterEntry|EmployeeShiftAssignment $schedule,
        Carbon $workDate,
        string $status,
        string $reason,
        ?LeaveRequest $leaveRequest,
    ): AttendanceRecord {
        $scheduleTrace = $schedule instanceof AttendanceRosterEntry
            ? [
                'roster_entry' => [
                    'id' => $schedule->id,
                    'attendance_roster_id' => $schedule->attendance_roster_id,
                    'entry_type' => $schedule->entry_type,
                    'attendance_shift_id' => $schedule->attendance_shift_id,
                    'lock_version' => $schedule->lock_version,
                    'rule_context' => $schedule->roster?->rule_context,
                ],
                'shift_assignment' => null,
            ]
            : [
                'roster_entry' => null,
                'shift_assignment' => [
                    'id' => $schedule->id,
                    'attendance_shift_id' => $schedule->attendance_shift_id,
                    'effective_from' => $schedule->effective_from->toDateString(),
                    'effective_to' => $schedule->effective_to?->toDateString(),
                ],
            ];
        $trace = [
            'finalization_guard_version' => 2,
            'resolution' => $reason,
            'work_date' => $workDate->toDateString(),
            ...$scheduleTrace,
            'leave_request' => $leaveRequest ? [
                'id' => $leaveRequest->id,
                'leave_type_id' => $leaveRequest->leave_type_id,
                'duration_unit' => $leaveRequest->duration_unit,
                'is_paid' => (bool) $leaveRequest->leaveType?->is_paid,
            ] : null,
        ];
        $sourceHash = hash('sha256', json_encode([
            'company_id' => $schedule->company_id,
            'employee_id' => $schedule->employee_id,
            'work_date' => $workDate->toDateString(),
            'status' => $status,
            'trace' => $trace,
        ], JSON_THROW_ON_ERROR));

        return AttendanceRecord::create([
            'company_id' => $schedule->company_id,
            'employee_id' => $schedule->employee_id,
            'attendance_shift_id' => $schedule->attendance_shift_id,
            'work_date' => $workDate->toDateString(),
            'source' => 'attendance_period_finalization',
            'status' => $status,
            'late_minutes' => 0,
            'early_leave_minutes' => 0,
            'worked_minutes' => 0,
            'metadata' => ['resolution' => $reason],
            'source_hash' => $sourceHash,
            'calculation_trace' => $trace,
        ]);
    }

    private function key(int|string $employeeId, Carbon|string $workDate): string
    {
        $date = $workDate instanceof Carbon ? $workDate->toDateString() : Carbon::parse($workDate)->toDateString();

        return $employeeId.'|'.$date;
    }
}
