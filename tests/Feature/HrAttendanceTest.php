<?php

namespace Tests\Feature;

use App\Application\Hr\Data\AttendanceCalculationTraceData;
use App\Models\AuditEvent;
use App\Models\AttendanceRecord;
use App\Models\AttendanceRegularizationRequest;
use App\Models\AttendanceShift;
use App\Models\Employee;
use App\Models\EmployeeShiftAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrAttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_attendance_users_can_open_native_blade_workspace(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();

        $this->actingAs($hr)
            ->get(route('hr.attendance-records.index'))
            ->assertOk()
            ->assertSee('Attendance Management')
            ->assertSee('Attendance register')
            ->assertSee('Exceptions &amp; basis', false)
            ->assertSee('Regularizations')
            ->assertSee('Shift definitions')
            ->assertSee('Early leaving')
            ->assertDontSee('Create attendance shift')
            ->assertDontSee('Submit attendance regularization');
    }

    public function test_hr_manager_can_create_attendance_shift_from_blade_workspace(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();

        $this->actingAs($hr)
            ->post(route('hr.attendance-shifts.store'), [
                'code' => 'WEBATT',
                'name' => 'Web Attendance Shift',
                'starts_at' => '08:30',
                'ends_at' => '17:30',
                'is_overnight' => '0',
                'late_grace_minutes' => 10,
                'early_leave_grace_minutes' => 5,
                'half_day_threshold_minutes' => 240,
                'full_day_threshold_minutes' => 480,
                'rules' => [
                    'shift_type' => 'fixed',
                    'weekly_off_policy' => 'Office roster',
                    'overtime_enabled' => '1',
                    'geofence_required' => '0',
                ],
            ])
            ->assertRedirect(route('hr.attendance-shifts.index'));

        $this->assertDatabaseHas('attendance_shifts', [
            'company_id' => $hr->company_id,
            'code' => 'WEBATT',
            'name' => 'Web Attendance Shift',
            'early_leave_grace_minutes' => 5,
        ]);
    }

    public function test_employee_can_submit_and_hr_can_approve_regularization_from_blade_workspace(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $employee = Employee::where('user_id', $sales->id)->firstOrFail();
        $workDate = now()->subDays(8);

        $this->actingAs($sales)
            ->post(route('hr.attendance-regularizations.store'), [
                'employee_id' => $employee->id,
                'work_date' => $workDate->toDateString(),
                'requested_check_in_at' => $workDate->copy()->setTime(9, 30)->format('Y-m-d\TH:i'),
                'requested_check_out_at' => $workDate->copy()->setTime(18, 30)->format('Y-m-d\TH:i'),
                'reason' => 'Submitted from native Blade attendance workspace.',
            ])
            ->assertRedirect(route('hr.attendance-regularizations.index'));

        $regularization = AttendanceRegularizationRequest::query()
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', $workDate->toDateString())
            ->firstOrFail();

        $this->assertSame('submitted', $regularization->status);

        $this->actingAs($hr)
            ->patch(route('hr.attendance-regularizations.approve', $regularization), [
                'decision_note' => 'Approved from native Blade workspace.',
            ])
            ->assertRedirect(route('hr.attendance-regularizations.index'));

        $regularization->refresh();

        $this->assertSame('approved', $regularization->status);
        $this->assertSame('Approved from native Blade workspace.', $regularization->decision_note);

        $this->assertTrue(
            AttendanceRecord::query()
                ->where('employee_id', $employee->id)
                ->whereDate('work_date', $workDate->toDateString())
                ->where('source', 'regularized')
                ->where('status', 'present')
                ->exists(),
        );
    }

    public function test_attendance_records_accept_early_leave_filter(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $employee = Employee::where('user_id', $sales->id)->firstOrFail();
        $shift = AttendanceShift::where('code', 'GEN')->firstOrFail();

        AttendanceRecord::create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'attendance_shift_id' => $shift->id,
            'work_date' => now()->subDays(9)->toDateString(),
            'check_in_at' => now()->subDays(9)->setTime(9, 30),
            'check_out_at' => now()->subDays(9)->setTime(17, 0),
            'source' => 'web',
            'status' => 'early_leave',
            'late_minutes' => 0,
            'early_leave_minutes' => 80,
            'worked_minutes' => 450,
        ]);

        $this->actingAs($sales)
            ->getJson(route('hr.attendance-records.index', ['status' => 'early_leave']))
            ->assertOk()
            ->assertJsonPath('data.0.status', 'early_leave')
            ->assertJsonPath('data.0.early_leave_minutes', 80);
    }

    public function test_employee_can_list_own_shifts_and_attendance_records(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $employee = Employee::where('user_id', $sales->id)->firstOrFail();

        $this->actingAs($sales)
            ->getJson(route('hr.attendance-shifts.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.code', 'GEN');

        $this->actingAs($sales)
            ->getJson(route('hr.attendance-records.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.employee.employee_code', $employee->employee_code)
            ->assertJsonPath('data.0.status', 'late')
            ->assertJsonPath('data.0.late_minutes', 6)
            ->assertJsonPath('data.0.early_leave_minutes', 8);
    }

    public function test_employee_self_service_user_can_submit_own_attendance_regularization(): void
    {
        $this->seed();

        $employeeUser = User::where('email', 'amit.verma@builder360.test')->firstOrFail();
        $employee = Employee::where('user_id', $employeeUser->id)->firstOrFail();
        $workDate = now()->subDays(6)->toDateString();

        $this->actingAs($employeeUser)
            ->getJson(route('hr.attendance-shifts.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $response = $this->actingAs($employeeUser)->postJson(route('hr.attendance-regularizations.store'), [
            'employee_id' => $employee->id,
            'work_date' => $workDate,
            'requested_check_in_at' => now()->subDays(6)->setTime(9, 30)->toDateTimeString(),
            'requested_check_out_at' => now()->subDays(6)->setTime(18, 30)->toDateTimeString(),
            'reason' => 'Self-service attendance regularization from ESS quick action.',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.employee.employee_code', 'EMP-0030');

        $this->assertDatabaseHas('attendance_regularization_requests', [
            'request_number' => $response->json('data.request_number'),
            'employee_id' => $employee->id,
            'requested_by_user_id' => $employeeUser->id,
            'status' => 'submitted',
        ]);

        $this->actingAs($employeeUser)
            ->getJson(route('hr.attendance-regularizations.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.employee.employee_code', 'EMP-0030');
    }

    public function test_attendance_indexes_validate_filters_and_employee_scope(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $employee = Employee::where('user_id', $sales->id)->firstOrFail();
        $otherEmployee = Employee::where('employee_code', 'EMP-0012')->firstOrFail();

        $this->actingAs($sales)
            ->getJson(route('hr.attendance-shifts.index', ['per_page' => 500]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');

        $this->actingAs($sales)
            ->getJson(route('hr.attendance-shifts.index', ['page' => 1]))
            ->assertOk()
            ->assertJsonPath('meta.current_page', 1);

        $this->actingAs($sales)
            ->getJson(route('hr.attendance-shifts.index', ['employee_id' => $employee->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('employee_id')
            ->assertJsonPath('errors.employee_id.0', 'The selected filter is not available for this endpoint.');

        $this->actingAs($sales)
            ->getJson(route('hr.attendance-records.index', ['status' => 'invalid']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->actingAs($sales)
            ->getJson(route('hr.attendance-records.index', ['view' => 'invalid']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('view');

        $this->actingAs($sales)
            ->getJson(route('hr.attendance-shifts.index', ['view' => 'invalid']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('view');

        $this->actingAs($sales)
            ->getJson(route('hr.attendance-records.index', ['shift_id' => 1]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('shift_id')
            ->assertJsonPath('errors.shift_id.0', 'The selected filter is not available for this endpoint.');

        $this->actingAs($sales)
            ->getJson(route('hr.attendance-records.index', [
                'date_from' => now()->toDateString(),
                'date_to' => now()->subDay()->toDateString(),
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('date_to');

        $this->actingAs($sales)
            ->getJson(route('hr.attendance-records.index', ['employee_id' => $otherEmployee->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('employee_id');

        $this->actingAs($sales)
            ->getJson(route('hr.attendance-regularizations.index', ['status' => 'invalid']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->actingAs($sales)
            ->getJson(route('hr.attendance-regularizations.index', ['date_from' => now()->toDateString()]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('date_from')
            ->assertJsonPath('errors.date_from.0', 'The selected filter is not available for this endpoint.');

        $this->actingAs($sales)
            ->getJson(route('hr.attendance-regularizations.index', ['employee_id' => $otherEmployee->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('employee_id');

        $this->actingAs($sales)
            ->getJson(route('hr.attendance-records.index', [
                'employee_id' => $employee->id,
                'status' => 'late',
                'date_from' => now()->subWeek()->toDateString(),
                'date_to' => now()->toDateString(),
                'per_page' => 10,
                'page' => 1,
            ]))
            ->assertOk()
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.employee.employee_code', $employee->employee_code);

        $this->actingAs($sales)
            ->getJson(route('hr.attendance-regularizations.index', ['page' => 1]))
            ->assertOk()
            ->assertJsonPath('meta.current_page', 1);
    }

    public function test_hr_manager_can_create_attendance_shift_with_rules_and_audit(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();

        $response = $this->actingAs($hr)->postJson(route('hr.attendance-shifts.store'), [
            'code' => 'SITE2',
            'name' => 'Site Second Shift',
            'starts_at' => '14:00',
            'ends_at' => '22:30',
            'is_overnight' => false,
            'late_grace_minutes' => 10,
            'early_leave_grace_minutes' => 5,
            'half_day_threshold_minutes' => 240,
            'full_day_threshold_minutes' => 510,
            'rules' => [
                'shift_type' => 'fixed',
                'weekly_off_policy' => 'Site roster',
                'overtime_enabled' => true,
                'geofence_required' => true,
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.code', 'SITE2')
            ->assertJsonPath('data.name', 'Site Second Shift')
            ->assertJsonPath('data.late_grace_minutes', 10)
            ->assertJsonPath('data.early_leave_grace_minutes', 5)
            ->assertJsonPath('data.rules.shift_type', 'fixed')
            ->assertJsonPath('data.rules.geofence_required', true);

        $shift = AttendanceShift::where('code', 'SITE2')->firstOrFail();

        $this->assertSame($hr->company_id, $shift->company_id);
        $this->assertTrue($shift->is_active);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'hr.attendance_shift.created',
            'action' => 'Created attendance shift',
            'user_id' => $hr->id,
            'auditable_type' => AttendanceShift::class,
            'auditable_id' => $shift->id,
        ]);

        $this->actingAs($hr)
            ->getJson(route('hr.attendance-shifts.index'))
            ->assertOk()
            ->assertJsonFragment(['code' => 'SITE2']);
    }

    public function test_attendance_shift_creation_validates_scope_duplicates_and_permissions(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();

        $payload = [
            'code' => 'BADNIGHT',
            'name' => 'Invalid Night Shift',
            'starts_at' => '22:00',
            'ends_at' => '06:00',
            'is_overnight' => false,
            'late_grace_minutes' => 10,
            'early_leave_grace_minutes' => 10,
            'half_day_threshold_minutes' => 540,
            'full_day_threshold_minutes' => 480,
            'rules' => ['shift_type' => 'night'],
        ];

        $this->actingAs($sales)
            ->postJson(route('hr.attendance-shifts.store'), array_merge($payload, [
                'code' => 'SALES',
                'name' => 'Sales unauthorized shift',
                'starts_at' => '09:30',
                'ends_at' => '18:30',
                'half_day_threshold_minutes' => 240,
            ]))
            ->assertForbidden();

        $this->actingAs($hr)
            ->postJson(route('hr.attendance-shifts.store'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['is_overnight', 'half_day_threshold_minutes']);

        $this->actingAs($hr)
            ->postJson(route('hr.attendance-shifts.store'), array_merge($payload, [
                'code' => 'GEN',
                'name' => 'Duplicate general shift',
                'starts_at' => '09:30',
                'ends_at' => '18:30',
                'is_overnight' => false,
                'half_day_threshold_minutes' => 240,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        $hr->forceFill(['company_id' => null])->save();

        $this->actingAs($hr)
            ->postJson(route('hr.attendance-shifts.store'), array_merge($payload, [
                'code' => 'NOCOMP',
                'name' => 'No Company Shift',
                'starts_at' => '09:30',
                'ends_at' => '18:30',
                'is_overnight' => false,
                'half_day_threshold_minutes' => 240,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('company_id');
    }

    public function test_non_global_hr_user_without_company_assignment_fails_closed_for_attendance(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $employee = Employee::where('user_id', $sales->id)->firstOrFail();
        $workDate = now()->subDays(4)->toDateString();

        $requestNumber = $this->actingAs($sales)->postJson(route('hr.attendance-regularizations.store'), [
            'employee_id' => $employee->id,
            'work_date' => $workDate,
            'requested_check_in_at' => now()->subDays(4)->setTime(9, 30)->toDateTimeString(),
            'requested_check_out_at' => now()->subDays(4)->setTime(18, 30)->toDateTimeString(),
            'reason' => 'Create pending request for fail-closed approval test.',
        ])->assertCreated()->json('data.request_number');

        $regularization = AttendanceRegularizationRequest::where('request_number', $requestNumber)->firstOrFail();

        $hr->forceFill(['company_id' => null])->save();

        $this->actingAs($hr)
            ->getJson(route('hr.attendance-shifts.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($hr)
            ->getJson(route('hr.attendance-records.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($hr)
            ->getJson(route('hr.attendance-regularizations.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($hr)
            ->getJson(route('hr.attendance-records.index', ['employee_id' => $employee->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('employee_id');

        $this->actingAs($hr)
            ->postJson(route('hr.attendance-regularizations.store'), [
                'employee_id' => $employee->id,
                'work_date' => now()->subDays(5)->toDateString(),
                'requested_check_in_at' => now()->subDays(5)->setTime(9, 30)->toDateTimeString(),
                'requested_check_out_at' => now()->subDays(5)->setTime(18, 30)->toDateTimeString(),
                'reason' => 'Invalid company-scope request.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('employee_id');

        $this->actingAs($hr)
            ->patchJson(route('hr.attendance-regularizations.approve', $regularization), [
                'decision_note' => 'Should fail closed.',
            ])
            ->assertForbidden();
    }

    public function test_employee_can_submit_regularization_and_hr_approval_updates_attendance_record(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $employee = Employee::where('user_id', $sales->id)->firstOrFail();
        $workDate = now()->subDay()->toDateString();

        $submitResponse = $this->actingAs($sales)->postJson(route('hr.attendance-regularizations.store'), [
            'employee_id' => $employee->id,
            'work_date' => $workDate,
            'requested_check_in_at' => now()->subDay()->setTime(9, 25)->toDateTimeString(),
            'requested_check_out_at' => now()->subDay()->setTime(18, 35)->toDateTimeString(),
            'reason' => 'Biometric machine did not capture corrected punch.',
        ]);

        $submitResponse
            ->assertCreated()
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.employee.employee_code', $employee->employee_code);

        $regularization = AttendanceRegularizationRequest::where('request_number', $submitResponse->json('data.request_number'))->firstOrFail();

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'hr.attendance_regularization.submitted',
            'action' => 'Submitted attendance regularization',
            'user_id' => $sales->id,
        ]);

        $this->actingAs($hr)
            ->patchJson(route('hr.attendance-regularizations.approve', $regularization), [
                'decision_note' => str_repeat('x', 2001),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('decision_note');

        $this->actingAs($hr)
            ->patchJson(route('hr.attendance-regularizations.approve', $regularization), [
                'decision_note' => 'Verified with site supervisor.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.attendance_record.status', 'present')
            ->assertJsonPath('data.attendance_record.late_minutes', 0)
            ->assertJsonPath('data.attendance_record.early_leave_minutes', 0);

        $this->assertTrue(
            AttendanceRecord::query()
                ->where('employee_id', $employee->id)
                ->whereDate('work_date', $workDate)
                ->where('source', 'regularized')
                ->where('status', 'present')
                ->where('late_minutes', 0)
                ->where('early_leave_minutes', 0)
                ->exists(),
        );

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'hr.attendance_regularization.approved',
            'action' => 'Approved attendance regularization',
            'user_id' => $hr->id,
        ]);

        $regularization->refresh();
        $this->assertSame('Verified with site supervisor.', $regularization->decision_note);
        $this->assertSame('Verified with site supervisor.', collect($regularization->workflow_history)->last()['note']);

        $audit = AuditEvent::query()
            ->where('event_type', 'hr.attendance_regularization.approved')
            ->latest()
            ->firstOrFail();
        $this->assertSame('Verified with site supervisor.', $audit->metadata['decision_note']);
    }

    public function test_regularization_rejection_does_not_change_existing_attendance_record(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $employee = Employee::where('user_id', $sales->id)->firstOrFail();
        $workDate = now()->subDays(2)->toDateString();

        $requestNumber = $this->actingAs($sales)->postJson(route('hr.attendance-regularizations.store'), [
            'employee_id' => $employee->id,
            'work_date' => $workDate,
            'requested_check_in_at' => now()->subDays(2)->setTime(10, 30)->toDateTimeString(),
            'requested_check_out_at' => now()->subDays(2)->setTime(18, 30)->toDateTimeString(),
            'reason' => 'Forgot check-in regularization request.',
        ])->assertCreated()->json('data.request_number');

        $regularization = AttendanceRegularizationRequest::where('request_number', $requestNumber)->firstOrFail();

        $this->actingAs($hr)
            ->patchJson(route('hr.attendance-regularizations.reject', $regularization), [
                'decision_note' => 'No supervisor confirmation.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $this->assertDatabaseMissing('attendance_records', [
            'employee_id' => $employee->id,
            'work_date' => $workDate,
        ]);
    }

    public function test_employee_cannot_submit_attendance_regularization_for_another_employee(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $otherEmployee = Employee::where('employee_code', 'EMP-0012')->firstOrFail();

        $this->actingAs($sales)->postJson(route('hr.attendance-regularizations.store'), [
            'employee_id' => $otherEmployee->id,
            'work_date' => now()->subDays(3)->toDateString(),
            'requested_check_in_at' => now()->subDays(3)->setTime(9, 30)->toDateTimeString(),
            'requested_check_out_at' => now()->subDays(3)->setTime(18, 30)->toDateTimeString(),
            'reason' => 'Invalid request.',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('employee_id');
    }

    public function test_partner_cannot_access_internal_attendance_routes(): void
    {
        $this->seed();

        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();

        $this->actingAs($partner)
            ->getJson(route('hr.attendance-records.index'))
            ->assertForbidden();

        $this->actingAs($partner)
            ->getJson(route('hr.attendance-regularizations.index'))
            ->assertForbidden();
    }

    public function test_attendance_operational_subviews_render_authoritative_data(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $employee = Employee::where('employee_code', 'EMP-0012')->firstOrFail();
        $shift = AttendanceShift::where('code', 'GEN')->firstOrFail();

        EmployeeShiftAssignment::query()->firstOrCreate(
            [
                'company_id' => $employee->company_id,
                'employee_id' => $employee->id,
                'attendance_shift_id' => $shift->id,
            ],
            [
                'effective_from' => now()->subMonth()->toDateString(),
                'effective_to' => null,
                'is_active' => true,
            ],
        );

        $this->actingAs($hr)
            ->get(route('hr.attendance-records.index', ['view' => 'exceptions']))
            ->assertOk()
            ->assertSee('Attendance exceptions')
            ->assertSee('Explainable stored result.')
            ->assertSee('does not recalculate or override attendance policy');

        $this->actingAs($hr)
            ->get(route('hr.attendance-shifts.index', ['view' => 'assignments']))
            ->assertOk()
            ->assertSee('Effective shift assignments')
            ->assertSee('Effective assignment history.')
            ->assertSee('Create overlap-protected assignments and manage dated rosters, rotations, and swaps')
            ->assertSee(route('hr.attendance-rosters.index'))
            ->assertSee($employee->name);
    }

    public function test_calculation_trace_renders_persisted_inputs_current_shift_and_stored_result_without_recalculation(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $employee = Employee::where('employee_code', 'EMP-0012')->firstOrFail();
        $shift = AttendanceShift::where('code', 'GEN')->firstOrFail();
        $workDate = now()->subDays(35)->startOfDay();

        AttendanceRecord::create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'attendance_shift_id' => $shift->id,
            'work_date' => $workDate->toDateString(),
            'check_in_at' => $workDate->copy()->setTime(9, 0),
            'check_out_at' => $workDate->copy()->setTime(18, 0),
            'source' => 'imported',
            'status' => 'absent',
            'late_minutes' => 17,
            'early_leave_minutes' => 9,
            'worked_minutes' => 540,
            'metadata' => ['regularization_request_number' => 'AR-TRACE-1001'],
        ]);

        $this->actingAs($hr)
            ->get(route('hr.attendance-records.index', [
                'view' => 'trace',
                'employee_id' => $employee->id,
                'date_from' => $workDate->toDateString(),
                'date_to' => $workDate->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Attendance calculation trace')
            ->assertSee('Recorded inputs')
            ->assertSee('Current linked shift')
            ->assertSee('Persisted result')
            ->assertSee('Imported')
            ->assertSee('AR-TRACE-1001')
            ->assertSee('Absent')
            ->assertSee('540 min')
            ->assertSee('No calculation-time rule snapshot is stored')
            ->assertSee('does not claim that the current definition produced the persisted result');
    }

    public function test_attendance_calculation_trace_is_an_immutable_application_contract(): void
    {
        $this->assertTrue((new \ReflectionClass(AttendanceCalculationTraceData::class))->isReadOnly());
    }

    public function test_attendance_summary_uses_complete_filtered_query_not_current_page(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $employee = Employee::where('employee_code', 'EMP-0012')->firstOrFail();
        $shift = AttendanceShift::where('code', 'GEN')->firstOrFail();

        foreach ([11, 12] as $daysAgo) {
            AttendanceRecord::create([
                'company_id' => $employee->company_id,
                'employee_id' => $employee->id,
                'attendance_shift_id' => $shift->id,
                'work_date' => now()->subDays($daysAgo)->toDateString(),
                'check_in_at' => now()->subDays($daysAgo)->setTime(9, 30),
                'check_out_at' => now()->subDays($daysAgo)->setTime(18, 30),
                'source' => 'web',
                'status' => 'present',
                'late_minutes' => 0,
                'early_leave_minutes' => 0,
                'worked_minutes' => 540,
            ]);
        }

        $expected = AttendanceRecord::query()->where('company_id', $hr->company_id)->count();

        $this->actingAs($hr)
            ->get(route('hr.attendance-records.index', ['per_page' => 1]))
            ->assertOk()
            ->assertSee('data-summary-total="'.$expected.'"', false)
            ->assertSee('Showing 1 to 1 of '.$expected);
    }

    public function test_regularization_actions_are_derived_from_each_row_policy(): void
    {
        $this->seed();

        $requester = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $approver = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $employee = Employee::where('user_id', $requester->id)->firstOrFail();
        $workDate = now()->subDays(20);

        $number = $this->actingAs($requester)->postJson(route('hr.attendance-regularizations.store'), [
            'employee_id' => $employee->id,
            'work_date' => $workDate->toDateString(),
            'requested_check_in_at' => $workDate->copy()->setTime(9, 30)->toDateTimeString(),
            'requested_check_out_at' => $workDate->copy()->setTime(18, 30)->toDateTimeString(),
            'reason' => 'Policy-derived action visibility regression.',
        ])->assertCreated()->json('data.request_number');

        $regularization = AttendanceRegularizationRequest::where('request_number', $number)->firstOrFail();
        $approvalUrl = route('hr.attendance-regularizations.approve', $regularization);

        $this->actingAs($approver)
            ->get(route('hr.attendance-regularizations.index'))
            ->assertOk()
            ->assertSee($approvalUrl);

        $this->actingAs($requester)
            ->get(route('hr.attendance-regularizations.index'))
            ->assertOk()
            ->assertDontSee($approvalUrl);
    }

    public function test_attendance_presentation_sources_have_no_encoding_artifacts(): void
    {
        $sources = [
            app_path('Domain/Hr/Services/AttendanceWorkspaceRegister.php'),
            resource_path('views/hr/attendance/partials/records.blade.php'),
            resource_path('views/hr/attendance/partials/exceptions.blade.php'),
            resource_path('views/hr/attendance/partials/regularizations.blade.php'),
            resource_path('views/hr/attendance/partials/shifts.blade.php'),
            resource_path('views/hr/attendance/partials/assignments.blade.php'),
            resource_path('views/hr/attendance/partials/trace.blade.php'),
        ];

        foreach ($sources as $source) {
            $contents = file_get_contents($source);

            $this->assertIsString($contents);
            $this->assertStringNotContainsString("\u{00E2}", $contents, $source);
            $this->assertStringNotContainsString("\u{00C2}", $contents, $source);
        }

        $trace = file_get_contents(resource_path('views/hr/attendance/partials/trace.blade.php'));

        $this->assertIsString($trace);
        $this->assertStringNotContainsString('::query(', $trace);
        $this->assertStringNotContainsString('Carbon::', $trace);
        $this->assertStringNotContainsString('@php', $trace);
    }
}
