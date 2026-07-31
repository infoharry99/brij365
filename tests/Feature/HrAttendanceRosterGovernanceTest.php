<?php

namespace Tests\Feature;

use App\Models\AttendancePeriodLock;
use App\Models\AttendanceRecord;
use App\Models\AttendanceRoster;
use App\Models\AttendanceRosterEntry;
use App\Models\AttendanceRotationRule;
use App\Models\AttendanceShift;
use App\Models\AttendanceShiftSwapRequest;
use App\Models\Employee;
use App\Models\EmployeeShiftAssignment;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\Hr\AttendanceRosterManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class HrAttendanceRosterGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_roster_workspace_and_mutations_are_policy_protected_and_company_scoped(): void
    {
        $this->seed();

        $hr = $this->user('deepa.rao@builder360.test');
        $employee = $this->user('amit.verma@builder360.test');
        $amit = $employee->employee()->firstOrFail();
        $shift = AttendanceShift::where('code', 'GEN')->firstOrFail();

        $this->actingAs($hr)
            ->get(route('hr.attendance-rosters.index'))
            ->assertOk()
            ->assertSee('Shifts &amp; Rosters', false)
            ->assertSee('Rosters')
            ->assertSee('Rotations')
            ->assertSee('Shift swaps')
            ->assertSee('Attendance periods')
            ->assertSee('Governed timezone')
            ->assertSee('aria-readonly="true"', false)
            ->assertDontSee('name="timezone"', false);

        $this->actingAs($employee)
            ->postJson(route('hr.attendance-shift-assignments.store'), [
                'employee_id' => $amit->id,
                'attendance_shift_id' => $shift->id,
                'effective_from' => now()->addYear()->startOfYear()->toDateString(),
            ])
            ->assertForbidden();

        $this->actingAs($hr)
            ->get(route('hr.attendance-rosters.index', ['view' => 'rotations']))
            ->assertOk()
            ->assertSee('x-data="rotationPatternEditor"', false)
            ->assertSee('max="31"', false)
            ->assertSee('x-ref="dayTemplate"', false);
    }

    public function test_effective_assignment_is_idempotent_and_rejects_overlapping_shift(): void
    {
        $this->seed();

        $hr = $this->user('deepa.rao@builder360.test');
        $amit = Employee::where('employee_code', 'EMP-0030')->firstOrFail();
        $general = AttendanceShift::where('code', 'GEN')->firstOrFail();
        $existing = EmployeeShiftAssignment::query()
            ->where('employee_id', $amit->id)
            ->where('attendance_shift_id', $general->id)
            ->firstOrFail();
        $before = EmployeeShiftAssignment::where('employee_id', $amit->id)->count();

        $payload = [
            'employee_id' => $amit->id,
            'attendance_shift_id' => $general->id,
            'effective_from' => $existing->effective_from->toDateString(),
            'effective_to' => null,
        ];

        $this->actingAs($hr)
            ->post(route('hr.attendance-shift-assignments.store'), $payload)
            ->assertRedirect();
        $this->assertSame($before, EmployeeShiftAssignment::where('employee_id', $amit->id)->count());

        $alternate = $this->alternateShift($hr);
        $this->actingAs($hr)
            ->postJson(route('hr.attendance-shift-assignments.store'), [
                'employee_id' => $amit->id,
                'attendance_shift_id' => $alternate->id,
                'effective_from' => now()->addMonths(2)->toDateString(),
                'effective_to' => now()->addMonths(3)->toDateString(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('effective_from');
    }

    public function test_roster_lifecycle_is_versioned_and_locked_roster_has_governed_reopen(): void
    {
        $this->seed();

        $hr = $this->user('deepa.rao@builder360.test');
        $amit = Employee::where('employee_code', 'EMP-0030')->firstOrFail();
        $shift = AttendanceShift::where('code', 'GEN')->firstOrFail();
        $start = now()->addMonthsNoOverflow(2)->startOfMonth();
        $end = $start->copy()->addDays(6);
        $roster = $this->createRoster($hr, 'Governed lifecycle roster', $start->toDateString(), $end->toDateString());

        $this->actingAs($hr)
            ->post(route('hr.attendance-rosters.entries.store', $roster), [
                'lock_version' => $roster->lock_version,
                'employee_id' => $amit->id,
                'attendance_shift_id' => $shift->id,
                'work_date' => $start->toDateString(),
                'entry_type' => 'shift',
            ])
            ->assertRedirect();

        $roster->refresh();
        $this->actingAs($hr)
            ->patch(route('hr.attendance-rosters.publish', $roster), ['lock_version' => $roster->lock_version])
            ->assertRedirect();
        $this->assertSame('published', $roster->refresh()->status);

        $this->actingAs($hr)
            ->patch(route('hr.attendance-rosters.lock', $roster), ['lock_version' => $roster->lock_version])
            ->assertRedirect();
        $this->assertSame('locked', $roster->refresh()->status);

        $staleVersion = $roster->lock_version - 1;
        $this->actingAs($hr)
            ->patchJson(route('hr.attendance-rosters.reopen', $roster), [
                'lock_version' => $staleVersion,
                'status_note' => 'Attempting stale governed correction.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lock_version');

        $this->actingAs($hr)
            ->patch(route('hr.attendance-rosters.reopen', $roster), [
                'lock_version' => $roster->lock_version,
                'status_note' => 'Approved correction before attendance finalization.',
            ])
            ->assertRedirect();

        $roster->refresh();
        $this->assertSame('published', $roster->status);
        $this->assertNull($roster->locked_at);
        $this->assertSame('Approved correction before attendance finalization.', $roster->status_note);
    }

    public function test_rotation_generation_is_deterministic_and_preserves_off_and_holiday_days(): void
    {
        $this->seed();

        $hr = $this->user('deepa.rao@builder360.test');
        $amit = Employee::where('employee_code', 'EMP-0030')->firstOrFail();
        $shift = AttendanceShift::where('code', 'GEN')->firstOrFail();
        $start = now()->addMonthsNoOverflow(3)->startOfMonth();
        $roster = $this->createRoster($hr, 'Rotation idempotency roster', $start->toDateString(), $start->copy()->addDays(2)->toDateString());

        $this->actingAs($hr)
            ->post(route('hr.attendance-rotation-rules.store'), [
                'employee_id' => $amit->id,
                'name' => 'Three day controlled rotation',
                'anchor_date' => $start->toDateString(),
                'cycle_days' => 3,
                'pattern' => [
                    ['type' => 'shift', 'attendance_shift_id' => $shift->id],
                    ['type' => 'off', 'attendance_shift_id' => null],
                    ['type' => 'holiday', 'attendance_shift_id' => null],
                ],
                'generation_horizon_days' => 3,
            ])
            ->assertRedirect();

        $rotation = AttendanceRotationRule::where('name', 'Three day controlled rotation')->firstOrFail();
        $this->actingAs($hr)
            ->post(route('hr.attendance-rotation-rules.generate', [$rotation, $roster]), [
                'lock_version' => $roster->lock_version,
                'start_date' => $start->toDateString(),
                'end_date' => $start->copy()->addDays(2)->toDateString(),
            ])
            ->assertRedirect();

        $this->assertSame(
            ['shift', 'off', 'holiday'],
            AttendanceRosterEntry::where('attendance_roster_id', $roster->id)->orderBy('work_date')->pluck('entry_type')->all(),
        );
        $this->assertSame(3, AttendanceRosterEntry::where('attendance_roster_id', $roster->id)->count());

        $roster->refresh();
        $this->actingAs($hr)
            ->post(route('hr.attendance-rotation-rules.generate', [$rotation, $roster]), [
                'lock_version' => $roster->lock_version,
                'start_date' => $start->toDateString(),
                'end_date' => $start->copy()->addDays(2)->toDateString(),
            ])
            ->assertRedirect();
        $this->assertSame(3, AttendanceRosterEntry::where('attendance_roster_id', $roster->id)->count());
    }

    public function test_rotation_generation_is_isolated_between_overlapping_draft_rosters(): void
    {
        $this->seed();

        $hr = $this->user('deepa.rao@builder360.test');
        $amit = Employee::where('employee_code', 'EMP-0030')->firstOrFail();
        $shift = AttendanceShift::where('code', 'GEN')->firstOrFail();
        $start = now()->addMonthsNoOverflow(5)->startOfMonth();
        $end = $start->copy()->addDays(1);
        $firstRoster = $this->createRoster($hr, 'Original rotation roster', $start->toDateString(), $end->toDateString());
        $replacementRoster = $this->createRoster($hr, 'Replacement rotation roster', $start->toDateString(), $end->toDateString());

        $this->actingAs($hr)
            ->post(route('hr.attendance-rotation-rules.store'), [
                'employee_id' => $amit->id,
                'name' => 'Replacement roster isolation rotation',
                'anchor_date' => $start->toDateString(),
                'cycle_days' => 2,
                'pattern' => [
                    ['type' => 'shift', 'attendance_shift_id' => $shift->id],
                    ['type' => 'off', 'attendance_shift_id' => null],
                ],
                'generation_horizon_days' => 2,
            ])
            ->assertRedirect();

        $rotation = AttendanceRotationRule::where('name', 'Replacement roster isolation rotation')->firstOrFail();
        $manager = app(AttendanceRosterManager::class);

        $this->assertSame(2, $manager->generateRotation(
            $rotation,
            $firstRoster,
            $firstRoster->lock_version,
            $hr,
            $start->toDateString(),
            $end->toDateString(),
        ));
        $this->assertSame(2, $manager->generateRotation(
            $rotation,
            $replacementRoster,
            $replacementRoster->lock_version,
            $hr,
            $start->toDateString(),
            $end->toDateString(),
        ));

        $firstEntries = AttendanceRosterEntry::where('attendance_roster_id', $firstRoster->id)->orderBy('work_date')->get();
        $replacementEntries = AttendanceRosterEntry::where('attendance_roster_id', $replacementRoster->id)->orderBy('work_date')->get();

        $this->assertCount(2, $firstEntries);
        $this->assertCount(2, $replacementEntries);
        $this->assertSame([$firstRoster->id], $firstEntries->pluck('attendance_roster_id')->unique()->values()->all());
        $this->assertSame([$replacementRoster->id], $replacementEntries->pluck('attendance_roster_id')->unique()->values()->all());
        $this->assertTrue($firstEntries->pluck('occurrence_key')->intersect($replacementEntries->pluck('occurrence_key'))->isEmpty());

        $replacementRoster->refresh();
        $this->assertSame(0, $manager->generateRotation(
            $rotation,
            $replacementRoster,
            $replacementRoster->lock_version,
            $hr,
            $start->toDateString(),
            $end->toDateString(),
        ));
        $this->assertSame(2, AttendanceRosterEntry::where('attendance_roster_id', $replacementRoster->id)->count());
    }

    public function test_published_swap_requires_independent_approval_and_updates_both_entries_atomically(): void
    {
        $this->seed();

        $hr = $this->user('deepa.rao@builder360.test');
        $amitUser = $this->user('amit.verma@builder360.test');
        $amit = $amitUser->employee()->firstOrFail();
        $priya = Employee::where('employee_code', 'EMP-0021')->firstOrFail();
        $shift = AttendanceShift::where('code', 'GEN')->firstOrFail();
        $start = now()->addMonthsNoOverflow(4)->startOfMonth();
        $roster = $this->createRoster($hr, 'Published swap roster', $start->toDateString(), $start->copy()->addDays(1)->toDateString());

        foreach ([[$amit, $start], [$priya, $start->copy()->addDay()]] as [$employee, $date]) {
            $roster->refresh();
            $this->actingAs($hr)
                ->post(route('hr.attendance-rosters.entries.store', $roster), [
                    'lock_version' => $roster->lock_version,
                    'employee_id' => $employee->id,
                    'attendance_shift_id' => $shift->id,
                    'work_date' => $date->toDateString(),
                    'entry_type' => 'shift',
                ])
                ->assertRedirect();
        }

        $roster->refresh();
        $this->actingAs($hr)
            ->patch(route('hr.attendance-rosters.publish', $roster), ['lock_version' => $roster->lock_version])
            ->assertRedirect();

        $entries = $roster->entries()->orderBy('work_date')->get();
        $source = $entries->first();
        $target = $entries->last();
        $this->actingAs($amitUser)
            ->post(route('hr.attendance-shift-swaps.store'), [
                'source_roster_entry_id' => $source->id,
                'target_roster_entry_id' => $target->id,
                'source_entry_lock_version' => $source->lock_version,
                'target_entry_lock_version' => $target->lock_version,
                'reason' => 'Controlled shift exchange for coverage.',
            ])
            ->assertRedirect();

        $swap = AttendanceShiftSwapRequest::where('status', 'submitted')->firstOrFail();
        $this->actingAs($amitUser)
            ->patchJson(route('hr.attendance-shift-swaps.approve', $swap), [
                'lock_version' => $swap->lock_version,
                'decision_note' => 'Self approval must not be possible.',
            ])
            ->assertForbidden();

        $this->actingAs($hr)
            ->patch(route('hr.attendance-shift-swaps.approve', $swap), [
                'lock_version' => $swap->lock_version,
                'decision_note' => 'Independent HR approval after conflict validation.',
            ])
            ->assertRedirect();

        $this->assertSame('approved', $swap->refresh()->status);
        $this->assertSame($priya->id, $source->refresh()->employee_id);
        $this->assertSame($amit->id, $target->refresh()->employee_id);
    }

    public function test_same_roster_same_date_swap_is_collision_safe_and_rekeys_both_occurrences(): void
    {
        $this->seed();

        $hr = $this->user('deepa.rao@builder360.test');
        $amitUser = $this->user('amit.verma@builder360.test');
        $amit = $amitUser->employee()->firstOrFail();
        $priya = Employee::where('employee_code', 'EMP-0021')->firstOrFail();
        $shift = AttendanceShift::where('code', 'GEN')->firstOrFail();
        $date = now()->addMonthsNoOverflow(6)->startOfMonth();
        $roster = $this->createRoster($hr, 'Same day swap roster', $date->toDateString(), $date->toDateString());

        foreach ([$amit, $priya] as $employee) {
            $roster->refresh();
            $this->actingAs($hr)
                ->post(route('hr.attendance-rosters.entries.store', $roster), [
                    'lock_version' => $roster->lock_version,
                    'employee_id' => $employee->id,
                    'attendance_shift_id' => $shift->id,
                    'work_date' => $date->toDateString(),
                    'entry_type' => 'shift',
                ])
                ->assertRedirect();
        }

        $roster->refresh();
        $this->actingAs($hr)
            ->patch(route('hr.attendance-rosters.publish', $roster), ['lock_version' => $roster->lock_version])
            ->assertRedirect();

        $source = $roster->entries()->where('employee_id', $amit->id)->firstOrFail();
        $target = $roster->entries()->where('employee_id', $priya->id)->firstOrFail();
        $this->actingAs($amitUser)
            ->post(route('hr.attendance-shift-swaps.store'), [
                'source_roster_entry_id' => $source->id,
                'target_roster_entry_id' => $target->id,
                'source_entry_lock_version' => $source->lock_version,
                'target_entry_lock_version' => $target->lock_version,
                'reason' => 'Same-date coverage exchange.',
            ])
            ->assertRedirect();

        $swap = AttendanceShiftSwapRequest::where('status', 'submitted')->firstOrFail();
        $this->actingAs($hr)
            ->patch(route('hr.attendance-shift-swaps.approve', $swap), [
                'lock_version' => $swap->lock_version,
                'decision_note' => 'Approved same-date exchange.',
            ])
            ->assertRedirect();

        $source->refresh();
        $target->refresh();
        $this->assertSame($priya->id, $source->employee_id);
        $this->assertSame($amit->id, $target->employee_id);
        $this->assertSame($date->toDateString(), $source->work_date->toDateString());
        $this->assertSame($date->toDateString(), $target->work_date->toDateString());
        $this->assertSame("roster:{$roster->id}:employee:{$priya->id}:{$date->format('Ymd')}", $source->occurrence_key);
        $this->assertSame("roster:{$roster->id}:employee:{$amit->id}:{$date->format('Ymd')}", $target->occurrence_key);
        $this->assertSame(2, $roster->entries()->count());
    }

    public function test_finalized_attendance_is_idempotent_and_payroll_approval_blocks_reopen(): void
    {
        $this->seed();

        $hr = $this->user('deepa.rao@builder360.test');
        $companyId = (int) $hr->company_id;
        $employee = $hr->employee()->firstOrFail();
        Employee::query()
            ->where('company_id', $companyId)
            ->where('id', '!=', $employee->id)
            ->update(['status' => 'separated']);
        $employee->forceFill(['status' => 'active'])->save();
        $assignment = EmployeeShiftAssignment::query()
            ->where('employee_id', $employee->id)
            ->where('is_active', true)
            ->firstOrFail();
        $start = now('Asia/Kolkata')->subDays(20)->startOfDay();
        $end = $start->copy();
        AttendanceRecord::create([
            'company_id' => $companyId,
            'employee_id' => $employee->id,
            'attendance_shift_id' => $assignment->attendance_shift_id,
            'work_date' => $start->toDateString(),
            'source' => 'manual_authorized_absence',
            'status' => 'absent',
            'worked_minutes' => 0,
            'metadata' => ['fixture' => 'attendance_roster_reopen_guard'],
        ]);
        $payload = [
            'company_id' => $companyId,
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
        ];

        $this->actingAs($hr)
            ->post(route('hr.attendance-periods.finalize'), $payload)
            ->assertRedirect();
        $first = AttendancePeriodLock::where('company_id', $companyId)->where('status', 'finalized')->firstOrFail();
        $snapshotCount = $first->snapshots()->count();
        $this->assertGreaterThan(0, $snapshotCount);

        $this->actingAs($hr)
            ->post(route('hr.attendance-periods.finalize'), $payload)
            ->assertRedirect();
        $this->assertSame(1, AttendancePeriodLock::where('company_id', $companyId)->where('status', 'finalized')->count());
        $this->assertSame($snapshotCount, $first->snapshots()->count());

        PayrollRun::create([
            'company_id' => $companyId,
            'approved_by_user_id' => $this->user('suresh.iyer@builder360.test')->id,
            'run_number' => 'PAY-ROSTER-GUARD-1',
            'period_year' => $start->year,
            'period_month' => $start->month,
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'working_days' => 26,
            'status' => 'approved',
            'gross_earnings' => 0,
            'total_deductions' => 0,
            'net_payable' => 0,
            'metadata' => ['fixture' => 'attendance_roster_reopen_guard'],
            'approved_at' => now(),
        ]);

        $this->actingAs($hr)
            ->patchJson(route('hr.attendance-periods.reopen', $first), [
                'lock_version' => $first->lock_version,
                'reopen_reason' => 'This must be rejected after payroll approval.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('attendance_period_lock');

        $this->assertSame('finalized', $first->refresh()->status);
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function alternateShift(User $actor): AttendanceShift
    {
        return AttendanceShift::create([
            'company_id' => $actor->company_id,
            'code' => 'ALT-GOV',
            'name' => 'Alternate governed shift',
            'starts_at' => '12:00',
            'ends_at' => '20:00',
            'is_overnight' => false,
            'late_grace_minutes' => 5,
            'early_leave_grace_minutes' => 5,
            'half_day_threshold_minutes' => 240,
            'full_day_threshold_minutes' => 480,
            'rules' => ['fixture' => 'roster_governance_test'],
            'is_active' => true,
        ]);
    }

    private function createRoster(User $actor, string $name, string $start, string $end): AttendanceRoster
    {
        $manager = app(AttendanceRosterManager::class);

        return $manager->createRoster([
            'company_id' => $actor->company_id,
            'name' => $name,
            'period_start' => $start,
            'period_end' => $end,
            'timezone' => 'Asia/Kolkata',
        ], $actor);
    }
}
