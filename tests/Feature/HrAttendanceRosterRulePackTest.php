<?php

namespace Tests\Feature;

use App\Domain\Hr\Services\AttendanceDailyMaterializer;
use App\Domain\Hr\Services\AttendanceRosterRulePackResolver;
use App\Domain\Hr\Services\AttendanceRosterRulePackValidator;
use App\Domain\Hr\Services\AttendanceSourceEventRecorder;
use App\Models\AttendancePeriodLock;
use App\Models\AttendanceRecord;
use App\Models\AttendanceRoster;
use App\Models\AttendanceRosterEntry;
use App\Models\AttendanceRotationRule;
use App\Models\AttendanceShift;
use App\Models\Employee;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Hr\AttendanceRosterManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class HrAttendanceRosterRulePackTest extends TestCase
{
    use RefreshDatabase;

    public function test_roster_publication_pins_effective_governed_rule_version_and_checksum(): void
    {
        $this->seed();

        $hr = $this->user('deepa.rao@builder360.test');
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();
        $shift = AttendanceShift::where('code', 'GEN')->firstOrFail();
        $date = now('Asia/Kolkata')->addMonthsNoOverflow(8)->startOfMonth();
        $setting = $this->activateRosterRules($hr, $date, [
            'block_shift_overlaps' => true,
            'minimum_rest_minutes' => 720,
            'maximum_consecutive_workdays' => 6,
            'require_complete_period_assignment' => false,
            'coverage_scope' => 'roster_employees',
        ]);
        $manager = app(AttendanceRosterManager::class);
        $roster = $this->draftRoster($manager, $hr, 'Pinned governed roster', $date, $date);
        $this->addShiftEntry($manager, $roster, $employee, $shift, $date);

        $published = $manager->publish($roster->refresh(), $roster->lock_version, $hr);

        $this->assertSame('published', $published->status);
        $this->assertSame($date->toDateString(), data_get($published->rule_context, 'packs.resolved_for'));
        $this->assertSame($setting->id, data_get($published->rule_context, 'packs.roster.setting_id'));
        $this->assertSame(1, data_get($published->rule_context, 'packs.roster.version'));
        $this->assertSame('active_system_setting', data_get($published->rule_context, 'packs.roster.source'));
        $this->assertSame(64, strlen((string) data_get($published->rule_context, 'packs.roster.checksum')));
        $this->assertSame(720, data_get($published->rule_context, 'effective_values.minimum_rest_minutes'));
    }

    public function test_publication_rejects_less_than_the_governed_minimum_rest(): void
    {
        $this->seed();

        $hr = $this->user('deepa.rao@builder360.test');
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();
        $shift = AttendanceShift::where('code', 'GEN')->firstOrFail();
        $start = now('Asia/Kolkata')->addMonthsNoOverflow(9)->startOfMonth();
        $this->activateRosterRules($hr, $start, ['minimum_rest_minutes' => 960]);
        $manager = app(AttendanceRosterManager::class);
        $roster = $this->draftRoster($manager, $hr, 'Minimum rest roster', $start, $start->copy()->addDay());
        $this->addShiftEntry($manager, $roster, $employee, $shift, $start);
        $this->addShiftEntry($manager, $roster, $employee, $shift, $start->copy()->addDay());

        $this->assertValidationError(
            fn () => $manager->publish($roster->refresh(), $roster->lock_version, $hr),
            'attendance_roster',
            'minimum rest',
        );
    }

    public function test_publication_rejects_governed_consecutive_day_and_missing_assignment_breaches(): void
    {
        $this->seed();

        $hr = $this->user('deepa.rao@builder360.test');
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();
        $shift = AttendanceShift::where('code', 'GEN')->firstOrFail();
        $start = now('Asia/Kolkata')->addMonthsNoOverflow(10)->startOfMonth();
        $manager = app(AttendanceRosterManager::class);

        $this->activateRosterRules($hr, $start, ['maximum_consecutive_workdays' => 1]);
        $consecutiveRoster = $this->draftRoster($manager, $hr, 'Consecutive workday roster', $start, $start->copy()->addDay());
        $this->addShiftEntry($manager, $consecutiveRoster, $employee, $shift, $start);
        $this->addShiftEntry($manager, $consecutiveRoster, $employee, $shift, $start->copy()->addDay());
        $this->assertValidationError(
            fn () => $manager->publish($consecutiveRoster->refresh(), $consecutiveRoster->lock_version, $hr),
            'attendance_roster',
            'consecutive workdays',
        );

        $later = $start->copy()->addMonthNoOverflow();
        $this->activateRosterRules($hr, $later, [
            'require_complete_period_assignment' => true,
            'coverage_scope' => 'roster_employees',
        ], 2);
        $coverageRoster = $this->draftRoster($manager, $hr, 'Complete coverage roster', $later, $later->copy()->addDay());
        $this->addShiftEntry($manager, $coverageRoster, $employee, $shift, $later);
        $this->assertValidationError(
            fn () => $manager->publish($coverageRoster->refresh(), $coverageRoster->lock_version, $hr),
            'attendance_roster',
            'Complete roster coverage',
        );
    }

    public function test_invalid_active_roster_pack_fails_closed_before_roster_persistence(): void
    {
        $this->seed();

        $hr = $this->user('deepa.rao@builder360.test');
        $date = now('Asia/Kolkata')->addMonthsNoOverflow(11)->startOfMonth();
        $this->activateSetting(
            $hr,
            AttendanceRosterRulePackValidator::ROSTER_KEY,
            ['minimum_rest_minutes' => -1],
            $date,
        );
        $manager = app(AttendanceRosterManager::class);

        $this->assertValidationError(
            fn () => $this->draftRoster($manager, $hr, 'Invalid governed pack roster', $date, $date),
            'value.minimum_rest_minutes',
            'between 0 and 2880',
        );
        $this->assertDatabaseMissing('attendance_rosters', ['name' => 'Invalid governed pack roster']);
    }

    public function test_publication_rejects_governed_shift_overlap_and_inactive_references(): void
    {
        $this->seed();

        $hr = $this->user('deepa.rao@builder360.test');
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();
        $general = AttendanceShift::where('code', 'GEN')->firstOrFail();
        $start = now('Asia/Kolkata')->addMonthsNoOverflow(6)->startOfMonth();
        $this->activateRosterRules($hr, $start, ['block_shift_overlaps' => true]);
        $overnight = AttendanceShift::create([
            'company_id' => $employee->company_id,
            'code' => 'OVERNIGHT-GOV',
            'name' => 'Governed overnight shift',
            'starts_at' => '20:00',
            'ends_at' => '10:00',
            'is_overnight' => true,
            'late_grace_minutes' => 0,
            'early_leave_grace_minutes' => 0,
            'half_day_threshold_minutes' => 240,
            'full_day_threshold_minutes' => 480,
            'rules' => ['fixture' => 'governed_overlap'],
            'is_active' => true,
        ]);
        $manager = app(AttendanceRosterManager::class);
        $overlapRoster = $this->draftRoster($manager, $hr, 'Governed overlap roster', $start, $start->copy()->addDay());
        $this->addShiftEntry($manager, $overlapRoster, $employee, $overnight, $start);
        $this->addShiftEntry($manager, $overlapRoster, $employee, $general, $start->copy()->addDay());

        $this->assertValidationError(
            fn () => $manager->publish($overlapRoster->refresh(), $overlapRoster->lock_version, $hr),
            'attendance_roster',
            'overlapping authoritative roster shifts',
        );

        $later = $start->copy()->addMonthNoOverflow();
        $inactiveRoster = $this->draftRoster($manager, $hr, 'Inactive reference roster', $later, $later);
        $this->addShiftEntry($manager, $inactiveRoster, $employee, $general, $later);
        $employee->forceFill(['status' => 'inactive'])->save();
        $this->assertValidationError(
            fn () => $manager->publish($inactiveRoster->refresh(), $inactiveRoster->lock_version, $hr),
            'attendance_roster',
            'inactive or unavailable employees',
        );

        $employee->forceFill(['status' => 'active'])->save();
        $general->forceFill(['is_active' => false])->save();
        $this->assertValidationError(
            fn () => $manager->publish($inactiveRoster->refresh(), $inactiveRoster->lock_version, $hr),
            'attendance_roster',
            'active shift',
        );
    }

    public function test_daily_materialization_uses_and_pins_the_effective_attendance_rule_pack(): void
    {
        $this->seed();

        $hr = $this->user('deepa.rao@builder360.test');
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();
        $shift = AttendanceShift::where('code', 'GEN')->firstOrFail();
        $date = now('Asia/Kolkata')->addMonthsNoOverflow(7)->startOfMonth();
        $attendanceSetting = $this->activateSetting($hr, AttendanceRosterRulePackValidator::ATTENDANCE_KEY, [
            'company_timezone' => 'Asia/Kolkata',
            'late_grace_minutes' => 0,
            'early_leave_grace_minutes' => 0,
            'half_day_threshold_minutes' => 200,
            'full_day_threshold_minutes' => 600,
            'rounding' => 'nearest_minute',
        ], $date, 2);
        $this->activateRosterRules($hr, $date, []);
        $manager = app(AttendanceRosterManager::class);
        $roster = $this->draftRoster($manager, $hr, 'Materialization rule context roster', $date, $date);
        $this->addShiftEntry($manager, $roster, $employee, $shift, $date);
        $manager->publish($roster->refresh(), $roster->lock_version, $hr);

        $recorder = app(AttendanceSourceEventRecorder::class);
        foreach ([['check_in', '09:00:00', 'governed-in'], ['check_out', '14:00:00', 'governed-out']] as [$type, $time, $reference]) {
            $recorder->append([
                'employee_id' => $employee->id,
                'work_date' => $date->toDateString(),
                'occurred_at' => $date->toDateString().' '.$time,
                'timezone' => 'Asia/Kolkata',
                'event_type' => $type,
                'source' => 'attendance_terminal',
                'source_reference' => $reference,
            ], $hr);
        }

        $record = app(AttendanceDailyMaterializer::class)->materialize($employee, $date, $hr);

        $this->assertSame('half_day', $record->status);
        $this->assertSame(300, $record->worked_minutes);
        $this->assertSame($attendanceSetting->id, data_get($record->calculation_trace, 'rule_context.attendance.setting_id'));
        $this->assertSame(2, data_get($record->calculation_trace, 'rule_context.attendance.version'));
        $this->assertSame(200, data_get($record->calculation_trace, 'effective_rules.half_day_threshold_minutes'));
        $this->assertSame(64, strlen((string) data_get($record->calculation_trace, 'rule_context.attendance.checksum')));
        $this->assertSame(1, data_get($record->calculation_trace, 'schedule.roster_rule_context.packs.roster.version'));
        $this->assertSame(64, strlen((string) data_get($record->calculation_trace, 'schedule.roster_rule_context.packs.roster.checksum')));
    }

    public function test_governed_operational_limits_are_normalized_and_pinned_on_publication(): void
    {
        $this->seed();

        $hr = $this->user('deepa.rao@builder360.test');
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();
        $shift = AttendanceShift::where('code', 'GEN')->firstOrFail();
        $date = now('Asia/Kolkata')->addMonthsNoOverflow(5)->startOfMonth();
        $this->activateRosterRules($hr, $date, [
            'publication_lead_days' => 7,
            'swap_request_cutoff_hours' => 24,
            'maximum_rotation_generation_horizon_days' => 120,
            'roster_reopen_limit_days' => 30,
            'attendance_reopen_limit_days' => 45,
        ]);
        $manager = app(AttendanceRosterManager::class);
        $roster = $this->draftRoster($manager, $hr, 'Operational limit context roster', $date, $date);
        $this->addShiftEntry($manager, $roster, $employee, $shift, $date);

        $published = $manager->publish($roster->refresh(), $roster->lock_version, $hr);

        $this->assertSame(7, data_get($published->rule_context, 'effective_values.publication_lead_days'));
        $this->assertSame(24, data_get($published->rule_context, 'effective_values.swap_request_cutoff_hours'));
        $this->assertSame(120, data_get($published->rule_context, 'effective_values.maximum_rotation_generation_horizon_days'));
        $this->assertSame(30, data_get($published->rule_context, 'effective_values.roster_reopen_limit_days'));
        $this->assertSame(45, data_get($published->rule_context, 'effective_values.attendance_reopen_limit_days'));
    }

    public function test_publication_and_rotation_obey_governed_lead_and_horizon_limits(): void
    {
        $this->seed();

        $hr = $this->user('deepa.rao@builder360.test');
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();
        $shift = AttendanceShift::where('code', 'GEN')->firstOrFail();
        $date = now('Asia/Kolkata')->addDays(2)->startOfDay();
        $this->activateRosterRules($hr, $date, [
            'publication_lead_days' => 5,
            'maximum_rotation_generation_horizon_days' => 14,
        ]);
        $manager = app(AttendanceRosterManager::class);
        $roster = $this->draftRoster($manager, $hr, 'Late publication roster', $date, $date);
        $this->addShiftEntry($manager, $roster, $employee, $shift, $date);

        $this->assertValidationError(
            fn () => $manager->publish($roster->refresh(), $roster->lock_version, $hr),
            'attendance_roster',
            'at least 5 day(s)',
        );
        $this->assertValidationError(
            fn () => $manager->createRotation([
                'employee_id' => $employee->id,
                'name' => 'Horizon constrained rotation',
                'anchor_date' => $date->toDateString(),
                'cycle_days' => 1,
                'pattern' => [['type' => 'shift', 'attendance_shift_id' => $shift->id]],
                'generation_horizon_days' => 15,
                'status' => 'active',
            ], $hr),
            'generation_horizon_days',
            'governed maximum of 14 days',
        );
    }

    public function test_swap_and_reopen_operations_obey_governed_time_windows(): void
    {
        $this->seed();

        $hr = $this->user('deepa.rao@builder360.test');
        $firstEmployee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();
        $secondEmployee = Employee::query()
            ->where('company_id', $firstEmployee->company_id)
            ->where('status', 'active')
            ->whereKeyNot($firstEmployee->id)
            ->firstOrFail();
        $shift = AttendanceShift::where('code', 'GEN')->firstOrFail();
        $soon = now('Asia/Kolkata')->addDays(2)->startOfDay();
        $this->activateRosterRules($hr, $soon, ['swap_request_cutoff_hours' => 72]);
        $manager = app(AttendanceRosterManager::class);
        $roster = $this->draftRoster($manager, $hr, 'Swap cutoff roster', $soon, $soon);
        $source = $this->addShiftEntry($manager, $roster, $firstEmployee, $shift, $soon);
        $target = $this->addShiftEntry($manager, $roster, $secondEmployee, $shift, $soon);
        $manager->publish($roster->refresh(), $roster->lock_version, $hr);

        $this->assertValidationError(
            fn () => $manager->requestSwap([
                'source_roster_entry_id' => $source->id,
                'target_roster_entry_id' => $target->id,
                'source_entry_lock_version' => $source->lock_version,
                'target_entry_lock_version' => $target->lock_version,
                'reason' => 'Governed cutoff regression fixture.',
            ], $hr),
            'source_roster_entry_id',
            'cutoff of 72 hour(s)',
        );

        $oldDate = now('Asia/Kolkata')->subDays(10)->startOfDay();
        $this->activateRosterRules($hr, $oldDate, [
            'roster_reopen_limit_days' => 1,
            'attendance_reopen_limit_days' => 1,
        ], 2);
        $oldRoster = $this->draftRoster($manager, $hr, 'Expired reopen roster', $oldDate, $oldDate);
        $this->addShiftEntry($manager, $oldRoster, $firstEmployee, $shift, $oldDate);
        $manager->publish($oldRoster->refresh(), $oldRoster->lock_version, $hr);
        $lockedRoster = $manager->lock($oldRoster->refresh(), $oldRoster->lock_version, $hr);
        $this->assertValidationError(
            fn () => $manager->reopenRoster($lockedRoster->refresh(), $lockedRoster->lock_version, $hr, 'Attempt outside governed reopen window.'),
            'attendance_roster',
            'reopen window has expired',
        );

        $periodLock = AttendancePeriodLock::query()->create([
            'company_id' => $hr->company_id,
            'period_start' => $oldDate->toDateString(),
            'period_end' => $oldDate->toDateString(),
            'version' => 1,
            'status' => 'finalized',
            'finalized_by_user_id' => $hr->id,
            'finalized_at' => $oldDate,
            'source_hash' => hash('sha256', 'expired-reopen-window'),
            'rule_context' => $this->pinnedRosterContext($firstEmployee->company_id, $oldDate),
            'lock_version' => 1,
        ]);
        $this->assertValidationError(
            fn () => $manager->reopenPeriod($periodLock, 1, $hr, 'Attempt outside governed attendance reopen window.'),
            'attendance_period_lock',
            'reopen window has expired',
        );
    }

    public function test_finalized_period_reopen_uses_archived_pinned_rules_instead_of_replacement_rules(): void
    {
        $this->seed();

        $hr = $this->user('deepa.rao@builder360.test');
        $period = now('Asia/Kolkata')->subDays(10)->startOfDay();
        $employee = $hr->employee()->firstOrFail();
        Employee::query()
            ->where('company_id', $hr->company_id)
            ->where('id', '!=', $employee->id)
            ->update(['status' => 'separated']);
        $employee->forceFill(['status' => 'active'])->save();
        $assignment = $employee->shiftAssignments()->where('is_active', true)->firstOrFail();
        AttendanceRecord::create([
            'company_id' => $hr->company_id,
            'employee_id' => $employee->id,
            'attendance_shift_id' => $assignment->attendance_shift_id,
            'work_date' => $period->toDateString(),
            'source' => 'manual_authorized_absence',
            'status' => 'absent',
            'worked_minutes' => 0,
            'metadata' => ['fixture' => 'pinned_reopen_rule_guard'],
        ]);
        $original = $this->activateRosterRules($hr, $period, [
            'attendance_reopen_limit_days' => 1,
        ]);
        $manager = app(AttendanceRosterManager::class);

        $lock = $manager->finalizePeriod([
            'company_id' => $hr->company_id,
            'period_start' => $period->toDateString(),
            'period_end' => $period->toDateString(),
        ], $hr);

        $this->assertSame($original->id, data_get($lock->rule_context, 'packs.roster.setting_id'));
        $this->assertSame(1, data_get($lock->rule_context, 'effective_values.attendance_reopen_limit_days'));
        $this->assertSame('Asia/Kolkata', data_get($lock->rule_context, 'effective_values.timezone'));
        $this->assertSame(64, strlen((string) data_get($lock->rule_context, 'packs.roster.checksum')));

        $original->forceFill(['status' => 'archived', 'effective_to' => now()->subDay()->toDateString()])->save();
        $replacement = $this->activateRosterRules($hr, $period, [
            'attendance_reopen_limit_days' => 100,
        ], 2);
        $this->assertSame(100, app(AttendanceRosterRulePackResolver::class)->resolve((int) $hr->company_id, $period)->attendanceReopenLimitDays);

        $this->assertValidationError(
            fn () => $manager->reopenPeriod($lock->refresh(), $lock->lock_version, $hr, 'Historical pinned-rule regression test.'),
            'attendance_period_lock',
            'reopen window has expired',
        );
        $this->assertSame('archived', $original->refresh()->status);
        $this->assertSame('active', $replacement->status);
        $this->assertSame('finalized', $lock->refresh()->status);
    }

    public function test_rotation_generation_keeps_creation_rule_context_after_new_rules_activate(): void
    {
        $this->seed();

        $hr = $this->user('deepa.rao@builder360.test');
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();
        $shift = AttendanceShift::where('code', 'GEN')->firstOrFail();
        $anchor = now('Asia/Kolkata')->addMonthsNoOverflow(4)->startOfMonth();
        $original = $this->activateRosterRules($hr, $anchor, [
            'maximum_rotation_generation_horizon_days' => 14,
        ]);
        $manager = app(AttendanceRosterManager::class);
        $rotation = $manager->createRotation([
            'employee_id' => $employee->id,
            'name' => 'Pinned context rotation',
            'anchor_date' => $anchor->toDateString(),
            'cycle_days' => 1,
            'pattern' => [['type' => 'shift', 'attendance_shift_id' => $shift->id]],
            'generation_horizon_days' => 2,
            'status' => 'active',
        ], $hr);

        $this->assertSame($original->id, data_get($rotation->rule_context, 'packs.roster.setting_id'));
        $this->assertSame(14, data_get($rotation->rule_context, 'effective_values.maximum_rotation_generation_horizon_days'));

        $original->forceFill(['status' => 'archived', 'effective_to' => $anchor->copy()->subDay()->toDateString()])->save();
        $replacement = $this->activateRosterRules($hr, $anchor, [
            'maximum_rotation_generation_horizon_days' => 1,
        ], 2);
        $roster = $this->draftRoster($manager, $hr, 'Rotation context target roster', $anchor, $anchor->copy()->addDay());

        $generated = $manager->generateRotation($rotation->refresh(), $roster->refresh(), $roster->lock_version, $hr);

        $this->assertSame(2, $generated);
        $entries = AttendanceRosterEntry::query()
            ->where('attendance_rotation_rule_id', $rotation->id)
            ->orderBy('work_date')
            ->get();
        $this->assertCount(2, $entries);
        $this->assertSame($original->id, data_get($entries->first()->metadata, 'rotation_rule_context.packs.roster.setting_id'));
        $this->assertSame(1, data_get($entries->first()->metadata, 'rotation_rule_context.packs.roster.version'));
        $this->assertSame(14, data_get($entries->first()->metadata, 'rotation_rule_context.effective_values.maximum_rotation_generation_horizon_days'));
        $this->assertSame('active', $replacement->status);
    }

    public function test_locked_roster_reopen_uses_its_pinned_timezone_after_timezone_rules_change(): void
    {
        Carbon::setTestNow(Carbon::parse('2027-01-21 01:30:00', 'Asia/Kolkata'));

        try {
            $this->seed();

            $hr = $this->user('deepa.rao@builder360.test');
            $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();
            $shift = AttendanceShift::where('code', 'GEN')->firstOrFail();
            $period = Carbon::parse('2027-01-19', 'Asia/Kolkata')->startOfDay();
            $original = $this->activateRosterRules($hr, $period, [
                'company_timezone' => 'Asia/Kolkata',
                'roster_reopen_limit_days' => 1,
            ]);
            $manager = app(AttendanceRosterManager::class);
            $roster = $this->draftRoster($manager, $hr, 'Pinned timezone reopen roster', $period, $period);
            $this->addShiftEntry($manager, $roster, $employee, $shift, $period);
            $manager->publish($roster->refresh(), $roster->lock_version, $hr);
            $locked = $manager->lock($roster->refresh(), $roster->lock_version, $hr);

            $original->forceFill(['status' => 'archived', 'effective_to' => '2027-01-20'])->save();
            $this->activateRosterRules($hr, $period, [
                'company_timezone' => 'America/Los_Angeles',
                'roster_reopen_limit_days' => 1,
            ], 2);
            $this->assertSame('America/Los_Angeles', app(AttendanceRosterRulePackResolver::class)->resolve((int) $hr->company_id, $period)->timezone);

            $this->assertValidationError(
                fn () => $manager->reopenRoster($locked->refresh(), $locked->lock_version, $hr, 'Pinned timezone boundary regression.'),
                'attendance_roster',
                'reopen window has expired',
            );
            $this->assertSame('locked', $locked->refresh()->status);
            $this->assertSame('Asia/Kolkata', data_get($locked->rule_context, 'effective_values.timezone'));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_roster_creation_uses_governed_timezone_before_roster_or_entries_persist(): void
    {
        $this->seed();

        $hr = $this->user('deepa.rao@builder360.test');
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();
        $shift = AttendanceShift::where('code', 'GEN')->firstOrFail();
        $date = now('Asia/Kolkata')->addMonthsNoOverflow(3)->startOfMonth();
        $this->activateRosterRules($hr, $date, ['company_timezone' => 'Asia/Kolkata']);
        $manager = app(AttendanceRosterManager::class);

        $roster = $manager->createRoster([
            'company_id' => $hr->company_id,
            'name' => 'Governed timezone override roster',
            'period_start' => $date->toDateString(),
            'period_end' => $date->toDateString(),
            'timezone' => 'UTC',
        ], $hr);

        $this->assertSame('Asia/Kolkata', $roster->timezone);
        $this->assertDatabaseMissing('attendance_rosters', [
            'name' => 'Governed timezone override roster',
            'timezone' => 'UTC',
        ]);

        $entry = $this->addShiftEntry($manager, $roster, $employee, $shift, $date);
        $expectedStart = Carbon::parse($date->toDateString().' '.$shift->starts_at, 'Asia/Kolkata')->utc();
        $this->assertTrue($entry->starts_at->equalTo($expectedStart));
        $this->assertSame('Asia/Kolkata', $entry->roster()->firstOrFail()->timezone);
    }

    public function test_legacy_period_and_rotation_without_pinned_context_fail_closed(): void
    {
        $this->seed();

        $hr = $this->user('deepa.rao@builder360.test');
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();
        $shift = AttendanceShift::where('code', 'GEN')->firstOrFail();
        $date = now('Asia/Kolkata')->addMonthsNoOverflow(2)->startOfMonth();
        $manager = app(AttendanceRosterManager::class);
        $periodLock = AttendancePeriodLock::query()->create([
            'company_id' => $hr->company_id,
            'period_start' => $date->toDateString(),
            'period_end' => $date->toDateString(),
            'version' => 1,
            'status' => 'finalized',
            'finalized_by_user_id' => $hr->id,
            'finalized_at' => now(),
            'source_hash' => hash('sha256', 'legacy-period-without-context'),
            'rule_context' => null,
            'lock_version' => 1,
        ]);
        $this->assertValidationError(
            fn () => $manager->reopenPeriod($periodLock, 1, $hr, 'Legacy context must fail closed.'),
            'attendance_period_lock',
            'no valid pinned governance context',
        );

        $roster = $this->draftRoster($manager, $hr, 'Legacy rotation target', $date, $date);
        $rotation = AttendanceRotationRule::query()->create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'name' => 'Legacy rotation without context',
            'anchor_date' => $date->toDateString(),
            'cycle_days' => 1,
            'pattern' => [['type' => 'shift', 'attendance_shift_id' => $shift->id]],
            'generation_horizon_days' => 1,
            'rule_context' => null,
            'status' => 'active',
            'created_by_user_id' => $hr->id,
            'lock_version' => 1,
        ]);
        $this->assertValidationError(
            fn () => $manager->generateRotation($rotation, $roster->refresh(), $roster->lock_version, $hr),
            'attendance_rotation_rule',
            'no valid pinned governance context',
        );
        $this->assertDatabaseMissing('attendance_roster_entries', [
            'attendance_rotation_rule_id' => $rotation->id,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function activateRosterRules(User $actor, Carbon $effectiveFrom, array $overrides, int $version = 1): SystemSetting
    {
        return $this->activateSetting($actor, AttendanceRosterRulePackValidator::ROSTER_KEY, array_replace([
            'company_timezone' => 'Asia/Kolkata',
            'block_shift_overlaps' => true,
            'minimum_rest_minutes' => 0,
            'maximum_consecutive_workdays' => 0,
            'require_complete_period_assignment' => false,
            'coverage_scope' => 'roster_employees',
        ], $overrides), $effectiveFrom, $version);
    }

    /** @param array<string, mixed> $value */
    private function activateSetting(User $actor, string $key, array $value, Carbon $effectiveFrom, int $version = 1): SystemSetting
    {
        return SystemSetting::create([
            'company_id' => $actor->company_id,
            'created_by_user_id' => $actor->id,
            'approved_by_user_id' => $this->user('nikhil.desai@builder360.test')->id,
            'scope_key' => 'company:'.$actor->company_id,
            'setting_group' => 'hr',
            'setting_key' => $key,
            'label' => 'Governed attendance roster test rules',
            'description' => 'Focused rule-pack fixture.',
            'value_type' => 'object',
            'value' => $value,
            'status' => 'active',
            'version' => $version,
            'effective_from' => $effectiveFrom->toDateString(),
            'approved_at' => now(),
            'workflow_history' => [['status' => 'active', 'actor' => 'System Administrator', 'at' => now()->toISOString()]],
            'metadata' => ['fixture' => 'attendance_roster_rule_pack'],
        ]);
    }

    private function draftRoster(AttendanceRosterManager $manager, User $actor, string $name, Carbon $start, Carbon $end): AttendanceRoster
    {
        return $manager->createRoster([
            'company_id' => $actor->company_id,
            'name' => $name,
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'timezone' => 'Asia/Kolkata',
        ], $actor);
    }

    /** @return array<string, mixed> */
    private function pinnedRosterContext(int $companyId, Carbon $effectiveOn): array
    {
        $rules = app(AttendanceRosterRulePackResolver::class)->resolve($companyId, $effectiveOn);

        return [
            'pinned_at' => now()->toISOString(),
            'packs' => $rules->ruleContext,
            'effective_values' => $rules->effectiveRosterValues(),
        ];
    }

    private function addShiftEntry(
        AttendanceRosterManager $manager,
        AttendanceRoster $roster,
        Employee $employee,
        AttendanceShift $shift,
        Carbon $date,
    ): AttendanceRosterEntry {
        $roster->refresh();

        return $manager->createEntry($roster, [
            'lock_version' => $roster->lock_version,
            'employee_id' => $employee->id,
            'attendance_shift_id' => $shift->id,
            'work_date' => $date->toDateString(),
            'entry_type' => 'shift',
        ], $this->user('deepa.rao@builder360.test'));
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function assertValidationError(callable $callback, string $field, string $messageFragment): void
    {
        try {
            $callback();
            $this->fail('Expected governed roster validation to reject the operation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
            $this->assertStringContainsString($messageFragment, implode(' ', $exception->errors()[$field]));
        }
    }
}
