<?php

namespace Tests\Feature;

use App\Domain\Hr\Services\AttendanceSourceEventRecorder;
use App\Models\AttendancePeriodLock;
use App\Models\AttendanceRecord;
use App\Models\AttendanceRoster;
use App\Models\AttendanceRosterEntry;
use App\Models\AttendanceShift;
use App\Models\Employee;
use App\Models\EmployeeShiftAssignment;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\Hr\AttendanceRosterManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class HrAttendanceIntegrityGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_source_events_use_authoritative_normal_and_overnight_work_dates_and_reject_out_of_window_evidence(): void
    {
        $this->seed();

        $actor = $this->user('deepa.rao@builder360.test');
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();
        EmployeeShiftAssignment::where('employee_id', $employee->id)->update(['is_active' => false]);
        $dayShift = AttendanceShift::where('code', 'GEN')->firstOrFail();
        $overnightShift = AttendanceShift::create([
            'company_id' => $employee->company_id,
            'code' => 'NIGHT-INTEGRITY',
            'name' => 'Night integrity shift',
            'starts_at' => '22:00',
            'ends_at' => '06:00',
            'is_overnight' => true,
            'late_grace_minutes' => 0,
            'early_leave_grace_minutes' => 0,
            'half_day_threshold_minutes' => 240,
            'full_day_threshold_minutes' => 480,
            'rules' => ['fixture' => 'attendance_integrity_guard'],
            'is_active' => true,
        ]);
        $day = now('Asia/Kolkata')->addYears(5)->startOfMonth();
        $night = $day->copy()->addDay();
        $roster = $this->publishedRoster($actor, $day, $night);
        $this->rosterEntry($roster, $employee, $dayShift, $day, 'shift');
        $this->rosterEntry($roster, $employee, $overnightShift, $night, 'shift');

        $recorder = app(AttendanceSourceEventRecorder::class);
        $normal = $recorder->append([
            'employee_id' => $employee->id,
            'work_date' => $day->toDateString(),
            'occurred_at' => $day->toDateString().' 10:00:00',
            'timezone' => 'Asia/Kolkata',
            'event_type' => 'check_in',
            'source' => 'attendance_terminal',
            'source_reference' => 'integrity-normal-in',
        ], $actor);
        $overnight = $recorder->append([
            'employee_id' => $employee->id,
            'work_date' => $night->toDateString(),
            'occurred_at' => $night->copy()->addDay()->toDateString().' 05:30:00',
            'timezone' => 'Asia/Kolkata',
            'event_type' => 'check_out',
            'source' => 'attendance_terminal',
            'source_reference' => 'integrity-night-out',
        ], $actor);

        $this->assertSame($day->toDateString(), $normal->work_date->toDateString());
        $this->assertSame($night->toDateString(), $overnight->work_date->toDateString());
        $this->assertSame('roster_entry', data_get($overnight->metadata, 'authoritative_schedule.type'));
        $this->assertSame($overnightShift->id, data_get($overnight->metadata, 'authoritative_schedule.attendance_shift_id'));

        $this->assertValidationError(
            fn () => $recorder->append([
                'employee_id' => $employee->id,
                'work_date' => $night->toDateString(),
                'occurred_at' => $night->copy()->addDay()->toDateString().' 06:30:00',
                'timezone' => 'Asia/Kolkata',
                'event_type' => 'check_out',
                'source' => 'attendance_terminal',
                'source_reference' => 'integrity-night-outside-window',
            ], $actor),
            'occurred_at',
            'outside',
        );
    }

    public function test_source_event_relabelling_cannot_bypass_a_finalized_authoritative_work_date(): void
    {
        $this->seed();

        $actor = $this->user('deepa.rao@builder360.test');
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();
        EmployeeShiftAssignment::where('employee_id', $employee->id)->update(['is_active' => false]);
        $shift = AttendanceShift::where('code', 'GEN')->firstOrFail();
        $date = now('Asia/Kolkata')->addYears(6)->startOfMonth();
        $roster = $this->publishedRoster($actor, $date, $date);
        $this->rosterEntry($roster, $employee, $shift, $date, 'shift');
        AttendancePeriodLock::create([
            'company_id' => $employee->company_id,
            'period_start' => $date->toDateString(),
            'period_end' => $date->toDateString(),
            'version' => 1,
            'status' => 'finalized',
            'finalized_by_user_id' => $actor->id,
            'finalized_at' => now(),
            'source_hash' => hash('sha256', 'finalized-authoritative-date'),
            'lock_version' => 1,
        ]);

        $this->assertValidationError(
            fn () => app(AttendanceSourceEventRecorder::class)->append([
                'employee_id' => $employee->id,
                'work_date' => $date->copy()->addDay()->toDateString(),
                'occurred_at' => $date->toDateString().' 10:00:00',
                'timezone' => 'Asia/Kolkata',
                'event_type' => 'check_in',
                'source' => 'attendance_terminal',
                'source_reference' => 'finalized-date-relabel-attempt',
            ], $actor),
            'work_date',
            'finalized',
        );
        $this->assertDatabaseMissing('attendance_source_events', ['source_reference' => 'finalized-date-relabel-attempt']);
    }

    public function test_period_finalization_rejects_an_unresolved_scheduled_day_and_accepts_explicit_absence(): void
    {
        $this->seed();

        $actor = $this->user('deepa.rao@builder360.test');
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();
        $this->isolateActiveEmployee($employee);
        $shift = AttendanceShift::where('code', 'GEN')->firstOrFail();
        $date = now('Asia/Kolkata')->addYears(7)->startOfMonth();
        $roster = $this->publishedRoster($actor, $date, $date);
        $this->rosterEntry($roster, $employee, $shift, $date, 'shift');
        $manager = app(AttendanceRosterManager::class);
        $payload = [
            'company_id' => $employee->company_id,
            'period_start' => $date->toDateString(),
            'period_end' => $date->toDateString(),
        ];

        $this->assertValidationError(
            fn () => $manager->finalizePeriod($payload, $actor),
            'period_start',
            'explicit absence',
        );
        $this->assertDatabaseMissing('attendance_period_locks', [
            'company_id' => $employee->company_id,
            'period_start' => $date->toDateString(),
            'status' => 'finalized',
        ]);

        AttendanceRecord::create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'attendance_shift_id' => $shift->id,
            'work_date' => $date->toDateString(),
            'source' => 'manual_authorized_absence',
            'status' => 'absent',
            'worked_minutes' => 0,
            'metadata' => ['fixture' => 'explicit_absence'],
        ]);

        $periodLock = $manager->finalizePeriod($payload, $actor);
        $snapshot = $periodLock->snapshots()->where('employee_id', $employee->id)->firstOrFail();
        $this->assertSame(1, $snapshot->scheduled_days);
        $this->assertSame(1, $snapshot->unpaid_days);
        $this->assertSame('0.00', $snapshot->payable_days);
    }

    public function test_period_finalization_materializes_approved_paid_leave_weekly_off_and_holiday(): void
    {
        $this->seed();

        $actor = $this->user('deepa.rao@builder360.test');
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();
        $this->isolateActiveEmployee($employee);
        $shift = AttendanceShift::where('code', 'GEN')->firstOrFail();
        $paidLeaveType = LeaveType::where('company_id', $employee->company_id)->where('is_paid', true)->firstOrFail();
        $start = now('Asia/Kolkata')->addYears(8)->startOfMonth();
        $off = $start->copy()->addDay();
        $holiday = $start->copy()->addDays(2);
        $roster = $this->publishedRoster($actor, $start, $holiday);
        $this->rosterEntry($roster, $employee, $shift, $start, 'shift');
        $this->rosterEntry($roster, $employee, null, $off, 'off');
        $this->rosterEntry($roster, $employee, null, $holiday, 'holiday');
        LeaveRequest::create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'leave_type_id' => $paidLeaveType->id,
            'requested_by_user_id' => $actor->id,
            'decided_by_user_id' => $actor->id,
            'request_number' => 'LR-INTEGRITY-FINALIZATION-1',
            'status' => 'approved',
            'starts_on' => $start->toDateString(),
            'ends_on' => $start->toDateString(),
            'duration_unit' => 'full_day',
            'requested_days' => 1,
            'reason' => 'Approved paid leave integrity fixture.',
            'decision_note' => 'Approved for deterministic finalization.',
            'workflow_history' => [],
            'decided_at' => now(),
        ]);

        $periodLock = app(AttendanceRosterManager::class)->finalizePeriod([
            'company_id' => $employee->company_id,
            'period_start' => $start->toDateString(),
            'period_end' => $holiday->toDateString(),
        ], $actor);

        $this->assertAttendanceStatus($employee, $start, 'on_leave');
        $this->assertAttendanceStatus($employee, $off, 'weekly_off');
        $this->assertAttendanceStatus($employee, $holiday, 'holiday');
        $snapshot = $periodLock->snapshots()->where('employee_id', $employee->id)->firstOrFail();
        $this->assertSame(3, $snapshot->paid_leave_days);
        $this->assertSame('3.00', $snapshot->payable_days);
    }

    public function test_period_finalization_fails_closed_when_an_active_employee_has_no_authoritative_schedule(): void
    {
        $this->seed();

        $actor = $this->user('deepa.rao@builder360.test');
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();
        $this->isolateActiveEmployee($employee);
        EmployeeShiftAssignment::where('employee_id', $employee->id)->update(['is_active' => false]);
        $date = now('Asia/Kolkata')->addYears(9)->startOfMonth();

        $this->assertValidationError(
            fn () => app(AttendanceRosterManager::class)->finalizePeriod([
                'company_id' => $employee->company_id,
                'period_start' => $date->toDateString(),
                'period_end' => $date->toDateString(),
            ], $actor),
            'period_start',
            'no authoritative schedule',
        );
        $this->assertDatabaseMissing('attendance_period_locks', [
            'company_id' => $employee->company_id,
            'period_start' => $date->toDateString(),
            'status' => 'finalized',
        ]);
    }

    public function test_period_finalization_resolves_an_effective_default_assignment_and_requires_attendance(): void
    {
        $this->seed();

        $actor = $this->user('deepa.rao@builder360.test');
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();
        $this->isolateActiveEmployee($employee);
        $assignment = EmployeeShiftAssignment::query()
            ->where('employee_id', $employee->id)
            ->where('is_active', true)
            ->firstOrFail();
        $date = now('Asia/Kolkata')->addYears(10)->startOfMonth();
        $payload = [
            'company_id' => $employee->company_id,
            'period_start' => $date->toDateString(),
            'period_end' => $date->toDateString(),
        ];

        $this->assertValidationError(
            fn () => app(AttendanceRosterManager::class)->finalizePeriod($payload, $actor),
            'period_start',
            'explicit absence',
        );

        AttendanceRecord::create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'attendance_shift_id' => $assignment->attendance_shift_id,
            'work_date' => $date->toDateString(),
            'source' => 'manual_authorized_absence',
            'status' => 'absent',
            'worked_minutes' => 0,
            'metadata' => ['fixture' => 'effective_assignment_absence'],
        ]);

        $periodLock = app(AttendanceRosterManager::class)->finalizePeriod($payload, $actor);
        $snapshot = $periodLock->snapshots()->where('employee_id', $employee->id)->firstOrFail();
        $this->assertSame(1, $snapshot->scheduled_days);
        $this->assertSame(1, $snapshot->unpaid_days);
        $this->assertSame('0.00', $snapshot->payable_days);
    }

    public function test_period_finalization_checks_only_dates_on_or_after_an_active_employees_join_date(): void
    {
        $this->seed();

        $actor = $this->user('deepa.rao@builder360.test');
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();
        $this->isolateActiveEmployee($employee);
        $assignment = EmployeeShiftAssignment::query()
            ->where('employee_id', $employee->id)
            ->where('is_active', true)
            ->firstOrFail();
        $periodStart = now('Asia/Kolkata')->addYears(11)->startOfMonth();
        $joinedOn = $periodStart->copy()->addDay();
        $employee->forceFill(['joined_on' => $joinedOn->toDateString()])->save();
        AttendanceRecord::create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'attendance_shift_id' => $assignment->attendance_shift_id,
            'work_date' => $joinedOn->toDateString(),
            'source' => 'manual_authorized_absence',
            'status' => 'absent',
            'worked_minutes' => 0,
            'metadata' => ['fixture' => 'join_boundary_absence'],
        ]);

        $periodLock = app(AttendanceRosterManager::class)->finalizePeriod([
            'company_id' => $employee->company_id,
            'period_start' => $periodStart->toDateString(),
            'period_end' => $joinedOn->toDateString(),
        ], $actor);

        $snapshot = $periodLock->snapshots()->where('employee_id', $employee->id)->firstOrFail();
        $this->assertSame(1, $snapshot->scheduled_days);
        $this->assertSame(1, $snapshot->unpaid_days);
        $this->assertSame('0.00', $snapshot->payable_days);
    }

    private function publishedRoster(User $actor, Carbon $start, Carbon $end): AttendanceRoster
    {
        return AttendanceRoster::create([
            'company_id' => $actor->company_id,
            'name' => 'Attendance integrity '.$start->format('Ymd'),
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'timezone' => 'Asia/Kolkata',
            'status' => 'published',
            'created_by_user_id' => $actor->id,
            'published_by_user_id' => $actor->id,
            'published_at' => now(),
            'lock_version' => 1,
        ]);
    }

    private function assertAttendanceStatus(Employee $employee, Carbon $workDate, string $status): void
    {
        $this->assertTrue(
            AttendanceRecord::query()
                ->where('employee_id', $employee->id)
                ->whereDate('work_date', $workDate->toDateString())
                ->where('status', $status)
                ->where('source', 'attendance_period_finalization')
                ->exists(),
            "Expected {$status} attendance for {$employee->employee_code} on {$workDate->toDateString()}.",
        );
    }

    private function rosterEntry(
        AttendanceRoster $roster,
        Employee $employee,
        ?AttendanceShift $shift,
        Carbon $date,
        string $entryType,
    ): AttendanceRosterEntry {
        $startsAt = null;
        $endsAt = null;
        if ($shift) {
            $startsAt = Carbon::parse($date->toDateString().' '.$shift->starts_at, $roster->timezone);
            $endsAt = Carbon::parse($date->toDateString().' '.$shift->ends_at, $roster->timezone);
            if ($shift->is_overnight || $endsAt->lte($startsAt)) {
                $endsAt->addDay();
            }
        }

        return AttendanceRosterEntry::create([
            'attendance_roster_id' => $roster->id,
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'attendance_shift_id' => $shift?->id,
            'work_date' => $date->toDateString(),
            'entry_type' => $entryType,
            'source' => 'manual',
            'occurrence_key' => "integrity:{$roster->id}:{$employee->id}:{$date->format('Ymd')}",
            'starts_at' => $startsAt?->utc(),
            'ends_at' => $endsAt?->utc(),
            'lock_version' => 1,
        ]);
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function isolateActiveEmployee(Employee $employee): void
    {
        Employee::query()
            ->where('company_id', $employee->company_id)
            ->where('id', '!=', $employee->id)
            ->update(['status' => 'separated']);
        $employee->forceFill(['status' => 'active'])->save();
    }

    private function assertValidationError(callable $callback, string $field, string $messageFragment): void
    {
        try {
            $callback();
            $this->fail('Expected attendance integrity validation to reject the operation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
            $this->assertStringContainsString($messageFragment, implode(' ', $exception->errors()[$field]));
        }
    }
}
