<?php

namespace App\Domain\Hr\Services;

use App\Models\AttendancePeriodLock;
use App\Models\AttendanceRecord;
use App\Models\AttendanceRosterEntry;
use App\Models\AttendanceSourceEvent;
use App\Models\AttendanceShift;
use App\Models\Employee;
use App\Models\EmployeeShiftAssignment;
use App\Models\User;
use App\Services\Security\CompanyScopeService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AttendanceDailyMaterializer
{
    public function __construct(
        private readonly RosterResolutionEngine $rosterResolution,
        private readonly CompanyScopeService $companyScope,
        private readonly AttendanceRosterRulePackResolver $rulePacks,
    ) {}

    public function materialize(Employee $employee, Carbon|string $workDate, ?User $actor = null): AttendanceRecord
    {
        return DB::transaction(function () use ($employee, $workDate, $actor): AttendanceRecord {
            $employee = Employee::query()->whereKey($employee->id)->lockForUpdate()->firstOrFail();
            if ($actor && ! $this->companyScope->allows($actor, (int) $employee->company_id)) {
                throw ValidationException::withMessages(['employee_id' => 'The selected employee is outside your company scope.']);
            }

            $date = Carbon::parse($workDate)->toDateString();
            $this->assertPeriodEditable((int) $employee->company_id, $date);
            $rules = $this->rulePacks->resolve((int) $employee->company_id, $date);
            $schedule = $this->rosterResolution->resolve($employee, $date);
            if ($schedule instanceof AttendanceRosterEntry && $schedule->entry_type !== 'shift') {
                throw ValidationException::withMessages(['work_date' => 'The authoritative roster marks this date as non-working.']);
            }

            $shift = $schedule?->shift;
            if (! $shift instanceof AttendanceShift || ! $shift->is_active) {
                throw ValidationException::withMessages(['employee_id' => 'No active authoritative shift is assigned for this employee and date.']);
            }
            $shift->loadMissing('segments');

            $events = AttendanceSourceEvent::query()
                ->where('company_id', $employee->company_id)
                ->where('employee_id', $employee->id)
                ->whereDate('work_date', $date)
                ->orderBy('occurred_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            [$pairs, $unpairedEventIds] = $this->pairEvents($events);
            if ($pairs === []) {
                throw ValidationException::withMessages([
                    'source_events' => 'At least one ordered check-in and check-out pair is required to materialize attendance.',
                ]);
            }

            $timezone = $schedule instanceof AttendanceRosterEntry
                ? (string) ($schedule->roster?->timezone ?? $rules->timezone)
                : $rules->timezone;
            [$scheduledStart, $scheduledEnd] = $this->scheduledInstants($schedule, $shift, $date, $timezone);
            $checkIn = $pairs[0][0];
            $checkOut = $pairs[array_key_last($pairs)][1];
            $workedMinutes = array_sum(array_map(
                fn (array $pair): int => max($this->roundedMinutes($pair[0], $pair[1], $rules->rounding), 0),
                $pairs,
            ));
            $lateGrace = $rules->lateGraceMinutes ?? (int) $shift->late_grace_minutes;
            $earlyGrace = $rules->earlyLeaveGraceMinutes ?? (int) $shift->early_leave_grace_minutes;
            $halfDayThreshold = $rules->halfDayThresholdMinutes ?? (int) $shift->half_day_threshold_minutes;
            $fullDayThreshold = $rules->fullDayThresholdMinutes ?? (int) $shift->full_day_threshold_minutes;
            $lateMinutes = max($this->roundedMinutes($scheduledStart, $checkIn, $rules->rounding) - $lateGrace, 0);
            $earlyLeaveMinutes = max($this->roundedMinutes($checkOut, $scheduledEnd, $rules->rounding) - $earlyGrace, 0);
            $status = match (true) {
                $workedMinutes < $halfDayThreshold => 'absent',
                $workedMinutes < $fullDayThreshold => 'half_day',
                $lateMinutes > 0 => 'late',
                $earlyLeaveMinutes > 0 => 'early_leave',
                default => 'present',
            };

            $trace = [
                'materializer_version' => 1,
                'work_date' => $date,
                'timezone' => $timezone,
                'rule_context' => $rules->ruleContext,
                'effective_rules' => $rules->effectiveAttendanceValues(),
                'schedule' => [
                    'type' => $schedule instanceof AttendanceRosterEntry ? 'roster_entry' : 'shift_assignment',
                    'id' => $schedule?->id,
                    'roster_entry_lock_version' => $schedule instanceof AttendanceRosterEntry ? $schedule->lock_version : null,
                    'roster_rule_context' => $schedule instanceof AttendanceRosterEntry ? $schedule->roster?->rule_context : null,
                    'attendance_shift_id' => $shift->id,
                    'shift_segments' => $shift->segments->map(fn ($segment): array => [
                        'id' => $segment->id,
                        'sequence' => $segment->sequence,
                        'starts_at' => $segment->starts_at,
                        'ends_at' => $segment->ends_at,
                    ])->all(),
                    'scheduled_start_utc' => $scheduledStart->toISOString(),
                    'scheduled_end_utc' => $scheduledEnd->toISOString(),
                ],
                'source_event_ids' => $events->pluck('id')->all(),
                'paired_event_ids' => array_map(static fn (array $pair): array => [$pair[2], $pair[3]], $pairs),
                'unpaired_event_ids' => $unpairedEventIds,
                'worked_minutes' => $workedMinutes,
                'late_minutes' => $lateMinutes,
                'early_leave_minutes' => $earlyLeaveMinutes,
                'status' => $status,
            ];
            $sourceHash = hash('sha256', json_encode([
                'employee_id' => $employee->id,
                'events' => $events->map(fn (AttendanceSourceEvent $event): array => [
                    $event->id,
                    $event->event_key,
                    $event->payload_hash,
                ])->all(),
                'schedule' => $trace['schedule'],
                'rule_context' => $rules->ruleContext,
                'effective_rules' => $rules->effectiveAttendanceValues(),
            ], JSON_THROW_ON_ERROR));

            $record = AttendanceRecord::query()
                ->where('employee_id', $employee->id)
                ->whereDate('work_date', $date)
                ->lockForUpdate()
                ->first();
            if ($record?->source === 'regularized') {
                throw ValidationException::withMessages([
                    'source_events' => 'Approved regularized attendance is authoritative for this date and cannot be overwritten by raw-event materialization.',
                ]);
            }
            if ($record && hash_equals((string) $record->source_hash, $sourceHash)) {
                return $record;
            }

            $record ??= new AttendanceRecord(['employee_id' => $employee->id, 'work_date' => $date]);
            $record->fill([
                'company_id' => $employee->company_id,
                'attendance_shift_id' => $shift->id,
                'check_in_at' => $checkIn,
                'check_out_at' => $checkOut,
                'source' => 'source_events',
                'status' => $status,
                'late_minutes' => $lateMinutes,
                'early_leave_minutes' => $earlyLeaveMinutes,
                'worked_minutes' => $workedMinutes,
                'metadata' => array_merge($record->metadata ?? [], [
                    'materialized_from_source_events' => true,
                    'source_event_count' => $events->count(),
                ]),
                'source_hash' => $sourceHash,
                'calculation_trace' => $trace,
            ])->save();

            return $record;
        });
    }

    /** @return array{0: array<int, array{0: Carbon, 1: Carbon, 2: int, 3: int}>, 1: array<int, int>} */
    private function pairEvents(Collection $events): array
    {
        $pairs = [];
        $unpaired = [];
        $open = null;
        foreach ($events as $event) {
            if ($event->event_type === 'check_in') {
                if ($open !== null) {
                    $unpaired[] = $open->id;
                }
                $open = $event;
                continue;
            }
            if ($event->event_type !== 'check_out') {
                continue;
            }
            if ($open === null || $event->occurred_at->lte($open->occurred_at)) {
                $unpaired[] = $event->id;
                continue;
            }
            $pairs[] = [$open->occurred_at, $event->occurred_at, $open->id, $event->id];
            $open = null;
        }
        if ($open !== null) {
            $unpaired[] = $open->id;
        }

        return [$pairs, $unpaired];
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function scheduledInstants(AttendanceRosterEntry|EmployeeShiftAssignment|null $schedule, AttendanceShift $shift, string $date, string $timezone): array
    {
        if ($schedule instanceof AttendanceRosterEntry && $schedule->starts_at && $schedule->ends_at) {
            return [$schedule->starts_at->copy()->utc(), $schedule->ends_at->copy()->utc()];
        }

        $start = Carbon::parse($date.' '.$shift->starts_at, $timezone);
        $end = Carbon::parse($date.' '.$shift->ends_at, $timezone);
        if ($shift->is_overnight || $end->lte($start)) {
            $end->addDay();
        }

        return [$start->utc(), $end->utc()];
    }

    private function assertPeriodEditable(int $companyId, string $workDate): void
    {
        if (AttendancePeriodLock::query()
            ->where('company_id', $companyId)
            ->where('status', 'finalized')
            ->whereDate('period_start', '<=', $workDate)
            ->whereDate('period_end', '>=', $workDate)
            ->exists()) {
            throw ValidationException::withMessages([
                'work_date' => 'Attendance for this date is finalized. Reopen the period before materializing source evidence.',
            ]);
        }
    }

    private function roundedMinutes(Carbon $from, Carbon $to, string $rounding): int
    {
        $minutes = ($to->getTimestamp() - $from->getTimestamp()) / 60;

        return match ($rounding) {
            'floor_minute' => (int) floor($minutes),
            'ceil_minute' => (int) ceil($minutes),
            default => (int) round($minutes, 0, PHP_ROUND_HALF_UP),
        };
    }
}
