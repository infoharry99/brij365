<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Employee;
use App\Models\EmployeeLeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrLeaveTest extends TestCase
{
    use RefreshDatabase;

    public function test_leave_users_can_open_native_blade_workspace(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();

        $this->actingAs($hr)
            ->get(route('hr.leave-requests.index'))
            ->assertOk()
            ->assertSee('Leave Workspace')
            ->assertSee('Submit leave request')
            ->assertSee('Leave requests')
            ->assertSee('Leave balances')
            ->assertSee('Leave types');
    }

    public function test_employee_can_submit_and_hr_can_approve_leave_from_blade_workspace(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $employee = Employee::where('user_id', $sales->id)->firstOrFail();
        $earnedLeave = LeaveType::where('company_id', $employee->company_id)->where('code', 'EL')->firstOrFail();
        $startsOn = now()->addDays(75)->toDateString();
        $endsOn = now()->addDays(76)->toDateString();

        $this->actingAs($sales)
            ->post(route('hr.leave-requests.store'), [
                'employee_id' => $employee->id,
                'leave_type_id' => $earnedLeave->id,
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
                'duration_unit' => 'full_day',
                'reason' => 'Submitted from native Blade leave workspace.',
            ])
            ->assertRedirect(route('hr.leave-requests.index'));

        $leaveRequest = LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->whereDate('starts_on', $startsOn)
            ->firstOrFail();

        $this->assertSame('submitted', $leaveRequest->status);
        $this->assertSame('2.00', (string) $leaveRequest->requested_days);

        $this->actingAs($hr)
            ->patch(route('hr.leave-requests.approve', $leaveRequest), [
                'decision_note' => 'Approved from native Blade leave workspace.',
            ])
            ->assertRedirect(route('hr.leave-requests.index'));

        $leaveRequest->refresh();

        $this->assertSame('approved', $leaveRequest->status);
        $this->assertSame('Approved from native Blade leave workspace.', $leaveRequest->decision_note);

        $this->assertDatabaseHas('employee_leave_balances', [
            'employee_id' => $employee->id,
            'leave_type_id' => $earnedLeave->id,
            'pending_days' => 0,
            'used_days' => 2,
            'available_days' => 16,
        ]);
    }
    public function test_employee_can_list_own_leave_types_balances_and_requests(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $employee = Employee::where('user_id', $sales->id)->firstOrFail();

        $this->actingAs($sales)
            ->getJson(route('hr.leave-types.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 3);

        $this->actingAs($sales)
            ->getJson(route('hr.leave-balances.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('data.0.employee.employee_code', $employee->employee_code);

        $this->actingAs($sales)
            ->getJson(route('hr.leave-requests.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_employee_self_service_user_can_submit_own_leave_request(): void
    {
        $this->seed();

        $employeeUser = User::where('email', 'amit.verma@builder360.test')->firstOrFail();
        $employee = Employee::where('user_id', $employeeUser->id)->firstOrFail();
        $earnedLeave = LeaveType::where('company_id', $employee->company_id)->where('code', 'EL')->firstOrFail();
        $startsOn = now()->addDays(21)->toDateString();

        $this->actingAs($employeeUser)
            ->getJson(route('hr.leave-types.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 3);

        $response = $this->actingAs($employeeUser)->postJson(route('hr.leave-requests.store'), [
            'employee_id' => $employee->id,
            'leave_type_id' => $earnedLeave->id,
            'starts_on' => $startsOn,
            'ends_on' => $startsOn,
            'duration_unit' => 'full_day',
            'reason' => 'Self-service leave request from ESS quick action.',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.employee.employee_code', 'EMP-0030')
            ->assertJsonPath('data.leave_type.code', 'EL');

        $this->assertDatabaseHas('leave_requests', [
            'request_number' => $response->json('data.request_number'),
            'employee_id' => $employee->id,
            'requested_by_user_id' => $employeeUser->id,
            'status' => 'submitted',
        ]);

        $this->actingAs($employeeUser)
            ->getJson(route('hr.leave-requests.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.employee.employee_code', 'EMP-0030');
    }

    public function test_leave_indexes_validate_filters_and_employee_scope(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $employee = Employee::where('user_id', $sales->id)->firstOrFail();
        $otherEmployee = Employee::where('employee_code', 'EMP-0012')->firstOrFail();

        $this->actingAs($sales)
            ->getJson(route('hr.leave-types.index', ['per_page' => 500]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');

        $this->actingAs($sales)
            ->getJson(route('hr.leave-types.index', ['page' => 1]))
            ->assertOk()
            ->assertJsonPath('meta.current_page', 1);

        $this->actingAs($sales)
            ->getJson(route('hr.leave-types.index', ['status' => 'active']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status')
            ->assertJsonPath('errors.status.0', 'The selected filter is not available for this endpoint.');

        $this->actingAs($sales)
            ->getJson(route('hr.leave-balances.index', ['period_year' => 1999]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('period_year');

        $this->actingAs($sales)
            ->getJson(route('hr.leave-balances.index', ['status' => 'active']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status')
            ->assertJsonPath('errors.status.0', 'The selected filter is not available for this endpoint.');

        $this->actingAs($sales)
            ->getJson(route('hr.leave-balances.index', ['employee_id' => $otherEmployee->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('employee_id');

        $this->actingAs($sales)
            ->getJson(route('hr.leave-requests.index', ['status' => 'invalid']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->actingAs($sales)
            ->getJson(route('hr.leave-requests.index', ['period_year' => now()->year]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('period_year')
            ->assertJsonPath('errors.period_year.0', 'The selected filter is not available for this endpoint.');

        $this->actingAs($sales)
            ->getJson(route('hr.leave-requests.index', ['employee_id' => $otherEmployee->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('employee_id');

        $this->actingAs($sales)
            ->getJson(route('hr.leave-balances.index', [
                'employee_id' => $employee->id,
                'period_year' => now()->year,
                'per_page' => 10,
                'page' => 1,
            ]))
            ->assertOk()
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('data.0.employee.employee_code', $employee->employee_code);

        $this->actingAs($sales)
            ->getJson(route('hr.leave-requests.index', ['page' => 1]))
            ->assertOk()
            ->assertJsonPath('meta.current_page', 1);
    }

    public function test_non_global_hr_user_without_company_assignment_fails_closed_for_leave(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $employee = Employee::where('user_id', $sales->id)->firstOrFail();
        $earnedLeave = LeaveType::where('code', 'EL')->firstOrFail();

        $requestNumber = $this->actingAs($sales)->postJson(route('hr.leave-requests.store'), [
            'employee_id' => $employee->id,
            'leave_type_id' => $earnedLeave->id,
            'starts_on' => now()->addDays(25)->toDateString(),
            'ends_on' => now()->addDays(25)->toDateString(),
            'duration_unit' => 'full_day',
            'reason' => 'Create pending leave request for fail-closed approval test.',
        ])->assertCreated()->json('data.request_number');

        $leaveRequest = LeaveRequest::where('request_number', $requestNumber)->firstOrFail();

        $hr->forceFill(['company_id' => null])->save();

        $this->actingAs($hr)
            ->getJson(route('hr.leave-types.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($hr)
            ->getJson(route('hr.leave-balances.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($hr)
            ->getJson(route('hr.leave-requests.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($hr)
            ->getJson(route('hr.leave-balances.index', ['employee_id' => $employee->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('employee_id');

        $this->actingAs($hr)
            ->postJson(route('hr.leave-requests.store'), [
                'employee_id' => $employee->id,
                'leave_type_id' => $earnedLeave->id,
                'starts_on' => now()->addDays(26)->toDateString(),
                'ends_on' => now()->addDays(26)->toDateString(),
                'duration_unit' => 'full_day',
                'reason' => 'Invalid company-scope request.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('employee_id');

        $this->actingAs($hr)
            ->patchJson(route('hr.leave-requests.approve', $leaveRequest), [
                'decision_note' => 'Should fail closed.',
            ])
            ->assertForbidden();
    }

    public function test_employee_can_submit_leave_and_hr_can_approve_with_balance_deduction(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $employee = Employee::where('user_id', $sales->id)->firstOrFail();
        $earnedLeave = LeaveType::where('code', 'EL')->firstOrFail();
        $startsOn = now()->addDays(30)->toDateString();
        $endsOn = now()->addDays(31)->toDateString();

        $submitResponse = $this->actingAs($sales)->postJson(route('hr.leave-requests.store'), [
            'employee_id' => $employee->id,
            'leave_type_id' => $earnedLeave->id,
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
            'duration_unit' => 'full_day',
            'reason' => 'Family travel.',
        ]);

        $submitResponse
            ->assertCreated()
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.requested_days', 2)
            ->assertJsonPath('data.employee.employee_code', $employee->employee_code);

        $leaveRequest = LeaveRequest::where('request_number', $submitResponse->json('data.request_number'))->firstOrFail();

        $this->assertDatabaseHas('employee_leave_balances', [
            'employee_id' => $employee->id,
            'leave_type_id' => $earnedLeave->id,
            'pending_days' => 2,
            'available_days' => 16,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'hr.leave.submitted',
            'action' => 'Submitted leave request',
            'user_id' => $sales->id,
        ]);

        $this->actingAs($hr)
            ->patchJson(route('hr.leave-requests.approve', $leaveRequest), [
                'decision_note' => str_repeat('x', 2001),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('decision_note');

        $this->actingAs($hr)
            ->patchJson(route('hr.leave-requests.approve', $leaveRequest), [
                'decision_note' => 'Approved by HR.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.decided_by.email', 'deepa.rao@builder360.test');

        $this->assertDatabaseHas('leave_requests', [
            'id' => $leaveRequest->id,
            'status' => 'approved',
            'decided_by_user_id' => $hr->id,
        ]);

        $this->assertDatabaseHas('employee_leave_balances', [
            'employee_id' => $employee->id,
            'leave_type_id' => $earnedLeave->id,
            'pending_days' => 0,
            'used_days' => 2,
            'available_days' => 16,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'hr.leave.approved',
            'action' => 'Approved leave request',
            'user_id' => $hr->id,
        ]);

        $leaveRequest->refresh();
        $this->assertSame('Approved by HR.', $leaveRequest->decision_note);
        $this->assertSame('Approved by HR.', collect($leaveRequest->workflow_history)->last()['note']);

        $audit = AuditEvent::query()
            ->where('event_type', 'hr.leave.approved')
            ->latest()
            ->firstOrFail();
        $this->assertSame('Approved by HR.', $audit->metadata['decision_note']);
    }

    public function test_hr_rejection_releases_reserved_leave_balance(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $employee = Employee::where('user_id', $sales->id)->firstOrFail();
        $earnedLeave = LeaveType::where('code', 'EL')->firstOrFail();

        $requestNumber = $this->actingAs($sales)->postJson(route('hr.leave-requests.store'), [
            'employee_id' => $employee->id,
            'leave_type_id' => $earnedLeave->id,
            'starts_on' => now()->addDays(40)->toDateString(),
            'ends_on' => now()->addDays(40)->toDateString(),
            'duration_unit' => 'half_day',
            'reason' => 'Personal work.',
        ])->assertCreated()->json('data.request_number');

        $leaveRequest = LeaveRequest::where('request_number', $requestNumber)->firstOrFail();

        $this->actingAs($hr)
            ->patchJson(route('hr.leave-requests.reject', $leaveRequest), [
                'decision_note' => 'Business critical day.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $this->assertDatabaseHas('employee_leave_balances', [
            'employee_id' => $employee->id,
            'leave_type_id' => $earnedLeave->id,
            'pending_days' => 0,
            'used_days' => 0,
            'available_days' => 18,
        ]);
    }

    public function test_employee_cannot_submit_leave_for_another_employee(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $otherEmployee = Employee::where('employee_code', 'EMP-0012')->firstOrFail();
        $earnedLeave = LeaveType::where('code', 'EL')->firstOrFail();

        $this->actingAs($sales)->postJson(route('hr.leave-requests.store'), [
            'employee_id' => $otherEmployee->id,
            'leave_type_id' => $earnedLeave->id,
            'starts_on' => now()->addDays(50)->toDateString(),
            'ends_on' => now()->addDays(50)->toDateString(),
            'duration_unit' => 'full_day',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('employee_id');
    }

    public function test_leave_request_rejects_insufficient_balance(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $employee = Employee::where('user_id', $sales->id)->firstOrFail();
        $earnedLeave = LeaveType::where('code', 'EL')->firstOrFail();

        $this->actingAs($sales)->postJson(route('hr.leave-requests.store'), [
            'employee_id' => $employee->id,
            'leave_type_id' => $earnedLeave->id,
            'starts_on' => now()->addDays(60)->toDateString(),
            'ends_on' => now()->addDays(90)->toDateString(),
            'duration_unit' => 'full_day',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('leave_type_id');
    }

    public function test_partner_cannot_access_internal_hr_leave_routes(): void
    {
        $this->seed();

        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();

        $this->actingAs($partner)
            ->getJson(route('hr.leave-requests.index'))
            ->assertForbidden();

        $this->actingAs($partner)
            ->getJson(route('hr.leave-balances.index'))
            ->assertForbidden();
    }
}
