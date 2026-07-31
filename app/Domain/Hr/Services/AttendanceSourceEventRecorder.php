<?php

namespace App\Domain\Hr\Services;

use App\Models\AttendancePeriodLock;
use App\Models\AttendanceRosterEntry;
use App\Models\AttendanceShift;
use App\Models\AttendanceSourceEvent;
use App\Models\Employee;
use App\Models\EmployeeShiftAssignment;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Security\CompanyScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AttendanceSourceEventRecorder
{
    private const EVENT_TYPES = ['check_in', 'check_out', 'break_start', 'break_end'];

    public function __construct(
        private readonly CompanyScopeService $companyScope,
        private readonly AuditLogger $audit,
        private readonly RosterResolutionEngine $rosterResolution,
        private readonly AttendanceRosterRulePackResolver $rulePacks,
    ) {}

    /** @param array<string, mixed> $data */
    public function append(array $data, User $actor, ?Request $request = null): AttendanceSourceEvent
    {
        return DB::transaction(function () use ($data, $actor, $request): AttendanceSourceEvent {
            $employee = Employee::query()->whereKey($data['employee_id'] ?? null)->firstOrFail();
            if (! $this->companyScope->allows($actor, (int) $employee->company_id)) {
                throw ValidationException::withMessages(['employee_id' => 'The selected employee is outside your company scope.']);
            }

            $eventType = trim((string) ($data['event_type'] ?? ''));
            if (! in_array($eventType, self::EVENT_TYPES, true)) {
                throw ValidationException::withMessages(['event_type' => 'Select a supported immutable attendance source event type.']);
            }

            $source = trim((string) ($data['source'] ?? ''));
            if ($source === '' || mb_strlen($source) > 48) {
                throw ValidationException::withMessages(['source' => 'A source name of at most 48 characters is required.']);
            }

            $timezone = trim((string) ($data['timezone'] ?? 'Asia/Kolkata'));
            if (! in_array($timezone, timezone_identifiers_list(), true)) {
                throw ValidationException::withMessages(['timezone' => 'Select a valid IANA timezone.']);
            }

            if (! isset($data['occurred_at']) || trim((string) $data['occurred_at']) === '') {
                throw ValidationException::withMessages(['occurred_at' => 'Enter the source-event date and time.']);
            }

            try {
                $occurredAt = Carbon::parse((string) $data['occurred_at'], $timezone)->utc();
            } catch (\Throwable) {
                throw ValidationException::withMessages(['occurred_at' => 'Enter a valid source-event date and time.']);
            }

            $schedule = $this->authoritativeSchedule($employee, $occurredAt, $eventType);
            $workDate = $schedule['work_date'];

            // The authoritative date is checked before the caller-provided label. This
            // prevents relabelling evidence onto an open date to bypass a finalized day.
            $this->assertPeriodEditable((int) $employee->company_id, $workDate);

            if (isset($data['work_date']) && trim((string) $data['work_date']) !== '') {
                try {
                    $providedWorkDate = Carbon::parse((string) $data['work_date'])->toDateString();
                } catch (\Throwable) {
                    throw ValidationException::withMessages(['work_date' => 'Enter a valid attendance work date.']);
                }

                if ($providedWorkDate !== $workDate) {
                    throw ValidationException::withMessages([
                        'work_date' => "The source timestamp belongs to the authoritative work date {$workDate}.",
                    ]);
                }
            }

            $sourceReference = trim((string) ($data['source_reference'] ?? '')) ?: null;
            $metadata = $this->canonicalize((array) ($data['metadata'] ?? []));
            $metadata['authoritative_schedule'] = [
                'type' => $schedule['type'],
                'id' => $schedule['id'],
                'attendance_shift_id' => $schedule['attendance_shift_id'],
                'timezone' => $schedule['timezone'],
                'scheduled_start_utc' => $schedule['scheduled_start']->toISOString(),
                'scheduled_end_utc' => $schedule['scheduled_end']->toISOString(),
            ];
            $metadata = $this->canonicalize($metadata);
            $payload = [
                'company_id' => (int) $employee->company_id,
                'employee_id' => (int) $employee->id,
                'work_date' => $workDate,
                'occurred_at' => $occurredAt->toISOString(),
                'timezone' => $timezone,
                'event_type' => $eventType,
                'source' => $source,
                'source_reference' => $sourceReference,
                'metadata' => $metadata,
            ];
            $payloadHash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
            $eventKey = hash('sha256', $sourceReference !== null
                ? implode('|', [(string) $employee->company_id, $source, $sourceReference])
                : json_encode($payload, JSON_THROW_ON_ERROR));

            $event = AttendanceSourceEvent::query()->firstOrCreate(
                ['event_key' => $eventKey],
                [
                    ...$payload,
                    'recorded_by_user_id' => $actor->id,
                    'payload_hash' => $payloadHash,
                ],
            );

            if (! hash_equals((string) $event->payload_hash, $payloadHash)) {
                throw ValidationException::withMessages([
                    'source_reference' => 'This source reference already exists with different immutable attendance data.',
                ]);
            }

            if ($event->wasRecentlyCreated) {
                $this->audit->record($actor, 'hr.attendance_source_event.recorded', 'Recorded immutable attendance source event', $event, [
                    'employee_id' => $employee->id,
                    'work_date' => $workDate,
                    'event_type' => $eventType,
                    'source' => $source,
                    'payload_hash' => $payloadHash,
                ], $request);
            }

            return $event;
        });
    }

    /** @return array<string|int, mixed> */
    private function canonicalize(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->canonicalize($item);
            }
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
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
                'work_date' => 'Attendance for this date is finalized. Reopen the attendance period before appending source evidence.',
            ]);
        }
    }

    /**
     * @return array{
     *     type: string,
     *     id: int,
     *     attendance_shift_id: int,
     *     work_date: string,
     *     timezone: string,
     *     scheduled_start: Carbon,
     *     scheduled_end: Carbon
     * }
     */
    private function authoritativeSchedule(Employee $employee, Carbon $occurredAt, string $eventType): array
    {
        $companyRules = $this->rulePacks->resolve((int) $employee->company_id, $occurredAt->toDateString());
        $companyLocalDate = $occurredAt->copy()->setTimezone($companyRules->timezone)->startOfDay();
        $candidateDates = collect([
            $companyLocalDate->toDateString(),
            $companyLocalDate->copy()->subDay()->toDateString(),
            $companyLocalDate->copy()->addDay()->toDateString(),
        ])->unique()->values();

        $matches = [];
        foreach ($candidateDates as $candidateDate) {
            $schedule = $this->rosterResolution->resolve($employee, $candidateDate);
            if ($schedule instanceof AttendanceRosterEntry && $schedule->entry_type !== 'shift') {
                continue;
            }

            $shift = $schedule?->shift;
            if (! $schedule || ! ($shift instanceof AttendanceShift) || ! $shift->is_active) {
                continue;
            }

            $rules = $this->rulePacks->resolve((int) $employee->company_id, $candidateDate);
            $scheduleTimezone = $schedule instanceof AttendanceRosterEntry
                ? (string) ($schedule->roster?->timezone ?? $rules->timezone)
                : $rules->timezone;
            [$scheduledStart, $scheduledEnd] = $this->scheduledInstants(
                $schedule,
                $shift,
                $candidateDate,
                $scheduleTimezone,
            );

            if (! $this->belongsToScheduleWindow(
                $occurredAt,
                $eventType,
                $candidateDate,
                $scheduleTimezone,
                $scheduledStart,
                $scheduledEnd,
            )) {
                continue;
            }

            $matches[$candidateDate] = [
                'type' => $schedule instanceof AttendanceRosterEntry ? 'roster_entry' : 'shift_assignment',
                'id' => (int) $schedule->id,
                'attendance_shift_id' => (int) $shift->id,
                'work_date' => $candidateDate,
                'timezone' => $scheduleTimezone,
                'scheduled_start' => $scheduledStart,
                'scheduled_end' => $scheduledEnd,
            ];
        }

        if (count($matches) > 1) {
            throw ValidationException::withMessages([
                'occurred_at' => 'The source timestamp overlaps multiple authoritative schedules. Resolve the roster conflict before recording evidence.',
            ]);
        }

        if ($matches === []) {
            throw ValidationException::withMessages([
                'occurred_at' => 'The source timestamp falls outside the employee\'s authoritative scheduled shift window.',
            ]);
        }

        return array_values($matches)[0];
    }

    private function belongsToScheduleWindow(
        Carbon $occurredAt,
        string $eventType,
        string $workDate,
        string $timezone,
        Carbon $scheduledStart,
        Carbon $scheduledEnd,
    ): bool {
        if ($occurredAt->betweenIncluded($scheduledStart, $scheduledEnd)) {
            return true;
        }

        // A terminal check-in can legitimately precede the scheduled start (for
        // example the existing 09:00 evidence for a 09:30 shift). It still belongs
        // to this schedule only when it occurred on the schedule's local start date;
        // later evidence and all other event types remain bounded by the shift.
        return $eventType === 'check_in'
            && $occurredAt->lt($scheduledStart)
            && $occurredAt->copy()->setTimezone($timezone)->toDateString() === $workDate;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function scheduledInstants(
        AttendanceRosterEntry|EmployeeShiftAssignment $schedule,
        AttendanceShift $shift,
        string $workDate,
        string $timezone,
    ): array {
        if ($schedule instanceof AttendanceRosterEntry && $schedule->starts_at && $schedule->ends_at) {
            return [$schedule->starts_at->copy()->utc(), $schedule->ends_at->copy()->utc()];
        }

        $start = Carbon::parse($workDate.' '.$shift->starts_at, $timezone);
        $end = Carbon::parse($workDate.' '.$shift->ends_at, $timezone);
        if ($shift->is_overnight || $end->lte($start)) {
            $end->addDay();
        }

        return [$start->utc(), $end->utc()];
    }
}
