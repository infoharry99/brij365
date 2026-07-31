<?php

namespace Tests\Feature;

use App\Domain\Hr\Services\AttendanceDailyMaterializer;
use App\Domain\Hr\Services\AttendanceSourceEventRecorder;
use App\Models\AttendanceRoster;
use App\Models\AttendanceRosterEntry;
use App\Models\AttendanceShift;
use App\Models\AttendanceSourceEvent;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class HrAttendanceSourceIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_split_shift_segments_are_validated_persisted_and_returned_in_sequence(): void
    {
        $this->seed();
        $actor = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $payload = [
            'code' => 'SPLITQA',
            'name' => 'Governed Split Shift',
            'starts_at' => '09:00',
            'ends_at' => '18:00',
            'is_overnight' => false,
            'late_grace_minutes' => 5,
            'early_leave_grace_minutes' => 5,
            'half_day_threshold_minutes' => 240,
            'full_day_threshold_minutes' => 480,
            'rules' => ['shift_type' => 'split'],
            'segments' => [
                ['label' => 'Morning', 'starts_at' => '09:00', 'ends_at' => '13:00'],
                ['label' => 'Afternoon', 'starts_at' => '12:30', 'ends_at' => '18:00'],
            ],
        ];

        $this->actingAs($actor)
            ->postJson(route('hr.attendance-shifts.store'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('segments.1.starts_at');

        $payload['segments'][1]['starts_at'] = '14:00';
        $response = $this->actingAs($actor)
            ->postJson(route('hr.attendance-shifts.store'), $payload)
            ->assertCreated()
            ->assertJsonCount(2, 'data.segments')
            ->assertJsonPath('data.segments.0.label', 'Morning')
            ->assertJsonPath('data.segments.1.label', 'Afternoon');

        $shiftId = (int) $response->json('data.id');
        $this->assertDatabaseHas('attendance_shift_segments', [
            'attendance_shift_id' => $shiftId,
            'sequence' => 1,
            'starts_at' => '09:00',
            'ends_at' => '13:00',
        ]);
        $this->assertDatabaseHas('attendance_shift_segments', [
            'attendance_shift_id' => $shiftId,
            'sequence' => 2,
            'starts_at' => '14:00',
            'ends_at' => '18:00',
        ]);
    }

    public function test_source_events_are_idempotent_append_only_and_materialize_split_punches_deterministically(): void
    {
        $this->seed();

        $actor = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();
        $shift = AttendanceShift::where('code', 'GEN')->firstOrFail();
        $date = now('Asia/Kolkata')->addMonthsNoOverflow(7)->startOfMonth()->toDateString();
        $roster = AttendanceRoster::create([
            'company_id' => $employee->company_id,
            'name' => 'Source integrity roster',
            'period_start' => $date,
            'period_end' => $date,
            'timezone' => 'Asia/Kolkata',
            'status' => 'published',
            'created_by_user_id' => $actor->id,
            'published_by_user_id' => $actor->id,
            'published_at' => now(),
            'lock_version' => 1,
        ]);
        AttendanceRosterEntry::create([
            'attendance_roster_id' => $roster->id,
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'attendance_shift_id' => $shift->id,
            'work_date' => $date,
            'entry_type' => 'shift',
            'source' => 'manual',
            'occurrence_key' => "roster:{$roster->id}:employee:{$employee->id}:".str_replace('-', '', $date),
            'lock_version' => 1,
        ]);

        $recorder = app(AttendanceSourceEventRecorder::class);
        $events = [
            ['check_in', '09:00:00', 'terminal-1'],
            ['check_out', '13:00:00', 'terminal-2'],
            ['check_in', '14:00:00', 'terminal-3'],
            ['check_out', '18:00:00', 'terminal-4'],
        ];
        foreach ($events as [$type, $time, $reference]) {
            $recorder->append([
                'employee_id' => $employee->id,
                'work_date' => $date,
                'occurred_at' => "$date $time",
                'timezone' => 'Asia/Kolkata',
                'event_type' => $type,
                'source' => 'attendance_terminal',
                'source_reference' => $reference,
            ], $actor);
        }

        $duplicate = $recorder->append([
            'employee_id' => $employee->id,
            'work_date' => $date,
            'occurred_at' => "$date 09:00:00",
            'timezone' => 'Asia/Kolkata',
            'event_type' => 'check_in',
            'source' => 'attendance_terminal',
            'source_reference' => 'terminal-1',
        ], $actor);
        $this->assertSame(4, AttendanceSourceEvent::where('employee_id', $employee->id)->whereDate('work_date', $date)->count());
        $this->assertSame('terminal-1', $duplicate->source_reference);

        $record = app(AttendanceDailyMaterializer::class)->materialize($employee, $date, $actor);
        $firstUpdatedAt = $record->updated_at?->toISOString();
        $this->assertSame('source_events', $record->source);
        $this->assertSame(480, $record->worked_minutes);
        $this->assertSame(4, $record->metadata['source_event_count']);
        $this->assertCount(2, $record->calculation_trace['paired_event_ids']);
        $this->assertSame(64, strlen((string) $record->source_hash));

        $sameRecord = app(AttendanceDailyMaterializer::class)->materialize($employee, $date, $actor);
        $this->assertSame($record->id, $sameRecord->id);
        $this->assertSame($firstUpdatedAt, $sameRecord->updated_at?->toISOString());

        $event = AttendanceSourceEvent::where('source_reference', 'terminal-1')->firstOrFail();
        try {
            $event->forceFill(['source' => 'tampered'])->save();
            $this->fail('Immutable attendance source event was updated.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('immutable', $exception->getMessage());
        }
        try {
            $event->delete();
            $this->fail('Append-only attendance source event was deleted.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('append-only', $exception->getMessage());
        }
    }
}
