<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeLeaveBalance;
use App\Models\LeaveEncashment;
use App\Models\LeaveProcessingRun;
use App\Models\LeaveType;
use App\Models\Company;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrLeaveProcessingAndEncashmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_hr_can_create_and_director_can_post_leave_processing_from_blade_workspace(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $periodYear = (int) now()->year;

        $this->actingAs($hr)
            ->get(route('hr.leave-processing-runs.index'))
            ->assertOk()
            ->assertSee('Leave Workspace')
            ->assertSee('Leave processing run')
            ->assertSee('Leave processing runs');

        $this->actingAs($hr)
            ->post(route('hr.leave-processing-runs.store'), [
                'period_year' => $periodYear,
                'processing_type' => 'monthly_accrual',
                'is_dry_run' => '1',
                'note' => 'Native Blade monthly accrual preview.',
            ])
            ->assertRedirect(route('hr.leave-processing-runs.index'));

        $run = LeaveProcessingRun::query()
            ->where('created_by_user_id', $hr->id)
            ->where('period_year', $periodYear)
            ->where('processing_type', 'monthly_accrual')
            ->firstOrFail();

        $this->assertSame('preview', $run->status);

        $this->actingAs($director)
            ->patch(route('hr.leave-processing-runs.post', $run), [
                'note' => 'Posted from native Blade leave workspace.',
            ])
            ->assertRedirect(route('hr.leave-processing-runs.index'));

        $run->refresh();

        $this->assertSame('posted', $run->status);
        $this->assertSame($director->id, $run->posted_by_user_id);
    }

    public function test_leave_encashment_can_be_submitted_approved_and_marked_for_payroll_from_blade_workspace(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $payroll = User::where('email', 'kavita.shah@builder360.test')->firstOrFail();
        $employee = Employee::where('user_id', $sales->id)->firstOrFail();
        $earnedLeave = LeaveType::where('company_id', $employee->company_id)->where('code', 'EL')->firstOrFail();
        $periodYear = (int) now()->year;

        $this->actingAs($sales)
            ->post(route('hr.leave-encashments.store'), [
                'employee_id' => $employee->id,
                'leave_type_id' => $earnedLeave->id,
                'period_year' => $periodYear,
                'requested_days' => 1,
                'request_note' => 'Native Blade encashment request.',
            ])
            ->assertRedirect(route('hr.leave-encashments.index'));

        $encashment = LeaveEncashment::query()
            ->where('employee_id', $employee->id)
            ->where('period_year', $periodYear)
            ->where('request_note', 'Native Blade encashment request.')
            ->firstOrFail();

        $this->assertSame('submitted', $encashment->status);

        $this->actingAs($hr)
            ->patch(route('hr.leave-encashments.approve', $encashment), [
                'approved_days' => 1,
                'decision_note' => 'Approved from native Blade leave workspace.',
            ])
            ->assertRedirect(route('hr.leave-encashments.index'));

        $encashment->refresh();
        $this->assertSame('approved', $encashment->status);
        $this->assertSame('1.00', (string) $encashment->approved_days);

        $this->actingAs($payroll)
            ->patch(route('hr.leave-encashments.mark-payroll', $encashment), [
                'payroll_reference' => 'WEB-ENCASH-1001',
                'note' => 'Marked from native Blade leave workspace.',
            ])
            ->assertRedirect(route('hr.leave-encashments.index'));

        $encashment->refresh();

        $this->assertSame('payroll_marked', $encashment->status);
        $this->assertSame('WEB-ENCASH-1001', $encashment->payroll_reference);
    }
    public function test_leave_processing_preview_and_post_monthly_accrual_are_idempotent(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $employee = Employee::where('employee_code', 'EMP-0021')->firstOrFail();
        $earnedLeave = LeaveType::where('code', 'EL')->firstOrFail();
        $periodYear = (int) now()->year;

        $runId = $this->actingAs($hr)
            ->postJson(route('hr.leave-processing-runs.store'), [
                'period_year' => $periodYear,
                'processing_type' => 'monthly_accrual',
                'is_dry_run' => true,
                'note' => 'Preview monthly accrual.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'preview')
            ->assertJsonPath('data.processing_type', 'monthly_accrual')
            ->assertJsonPath('data.summary.employee_count', 4)
            ->assertJsonPath('data.summary.line_count', 12)
            ->assertJsonPath('data.summary.total_accrual_days', 8.32)
            ->json('data.id');

        $run = LeaveProcessingRun::findOrFail($runId);

        $this->actingAs($hr)
            ->patchJson(route('hr.leave-processing-runs.post', $run), [
                'note' => 'Creator cannot post own run.',
            ])
            ->assertForbidden();

        $this->actingAs($director)
            ->patchJson(route('hr.leave-processing-runs.post', $run), [
                'note' => 'Posted after HR preview verification.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'posted')
            ->assertJsonPath('data.posted_by.email', 'aditya.mehra@builder360.test');

        $this->assertDatabaseHas('employee_leave_balances', [
            'employee_id' => $employee->id,
            'leave_type_id' => $earnedLeave->id,
            'period_year' => $periodYear,
            'accrued_days' => 1.5,
            'available_days' => 19.5,
        ]);

        $this->actingAs($hr)
            ->postJson(route('hr.leave-processing-runs.store'), [
                'period_year' => $periodYear,
                'processing_type' => 'monthly_accrual',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['period_year']);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'hr.leave_processing.posted',
            'auditable_id' => $run->id,
            'user_id' => $director->id,
        ]);
    }

    public function test_year_end_processing_carries_forward_and_lapses_balance(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();
        $earnedLeave = LeaveType::where('code', 'EL')->firstOrFail();
        $sickLeave = LeaveType::where('code', 'SL')->firstOrFail();
        $periodYear = (int) now()->year;

        $runId = $this->actingAs($hr)
            ->postJson(route('hr.leave-processing-runs.store'), [
                'period_year' => $periodYear,
                'processing_type' => 'year_end',
            ])
            ->assertCreated()
            ->assertJsonPath('data.summary.total_carry_forward_days', 72)
            ->assertJsonPath('data.summary.total_lapse_days', 27)
            ->json('data.id');

        $run = LeaveProcessingRun::findOrFail($runId);

        $this->actingAs($director)
            ->patchJson(route('hr.leave-processing-runs.post', $run))
            ->assertOk()
            ->assertJsonPath('data.status', 'posted');

        $this->assertDatabaseHas('employee_leave_balances', [
            'employee_id' => $employee->id,
            'leave_type_id' => $earnedLeave->id,
            'period_year' => $periodYear + 1,
            'opening_balance_days' => 18,
            'available_days' => 18,
        ]);

        $this->assertDatabaseHas('employee_leave_balances', [
            'employee_id' => $employee->id,
            'leave_type_id' => $sickLeave->id,
            'period_year' => $periodYear,
            'available_days' => 0,
            'adjusted_days' => -7,
        ]);
    }

    public function test_leave_encashment_approval_reduces_balance_and_can_be_marked_for_payroll(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $payroll = User::where('email', 'kavita.shah@builder360.test')->firstOrFail();
        $employee = Employee::where('user_id', $sales->id)->firstOrFail();
        $earnedLeave = LeaveType::where('code', 'EL')->firstOrFail();
        $periodYear = (int) now()->year;

        $encashmentId = $this->actingAs($sales)
            ->postJson(route('hr.leave-encashments.store'), [
                'employee_id' => $employee->id,
                'leave_type_id' => $earnedLeave->id,
                'period_year' => $periodYear,
                'requested_days' => 2,
                'request_note' => 'Request earned leave encashment.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.requested_days', 2)
            ->assertJsonPath('data.gross_amount', 11200)
            ->assertJsonPath('data.tax_amount', 1120)
            ->assertJsonPath('data.net_amount', 10080)
            ->json('data.id');

        $encashment = LeaveEncashment::findOrFail($encashmentId);

        $this->actingAs($sales)
            ->patchJson(route('hr.leave-encashments.approve', $encashment), [
                'decision_note' => 'Invalid self approval.',
            ])
            ->assertForbidden();

        $this->actingAs($hr)
            ->patchJson(route('hr.leave-encashments.approve', $encashment), [
                'decision_note' => 'Approved within encashment policy.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.approved_days', 2)
            ->assertJsonPath('data.approved_by.email', 'deepa.rao@builder360.test');

        $this->assertDatabaseHas('employee_leave_balances', [
            'employee_id' => $employee->id,
            'leave_type_id' => $earnedLeave->id,
            'period_year' => $periodYear,
            'available_days' => 16,
            'adjusted_days' => -2,
        ]);

        $encashment->refresh();

        $this->actingAs($payroll)
            ->patchJson(route('hr.leave-encashments.mark-payroll', $encashment), [
                'payroll_reference' => 'PAY-ENCASH-1001',
                'note' => 'Include in next payroll run.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'payroll_marked')
            ->assertJsonPath('data.payroll_reference', 'PAY-ENCASH-1001')
            ->assertJsonPath('data.payroll_marked_by.email', 'kavita.shah@builder360.test');

        $this->assertTrue(UserNotification::where('recipient_user_id', $payroll->id)
            ->where('category', 'payroll')
            ->where('status', 'unread')
            ->exists());

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'hr.leave_encashment.payroll_marked',
            'auditable_id' => $encashment->id,
            'user_id' => $payroll->id,
        ]);
    }

    public function test_leave_encashment_scope_and_partner_denial_are_enforced(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $employeeUser = User::where('email', 'amit.verma@builder360.test')->firstOrFail();
        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();
        $otherEmployee = Employee::where('employee_code', 'EMP-0012')->firstOrFail();
        $earnedLeave = LeaveType::where('code', 'EL')->firstOrFail();

        $this->actingAs($sales)
            ->postJson(route('hr.leave-encashments.store'), [
                'employee_id' => $otherEmployee->id,
                'leave_type_id' => $earnedLeave->id,
                'period_year' => (int) now()->year,
                'requested_days' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['employee_id']);

        $ownEmployee = Employee::where('user_id', $employeeUser->id)->firstOrFail();

        $this->actingAs($employeeUser)
            ->postJson(route('hr.leave-encashments.store'), [
                'employee_id' => $ownEmployee->id,
                'leave_type_id' => $earnedLeave->id,
                'period_year' => (int) now()->year,
                'requested_days' => 1,
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.employee.employee_code', $ownEmployee->employee_code);

        $this->actingAs($employeeUser)
            ->getJson(route('hr.leave-encashments.index', ['employee_id' => $otherEmployee->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['employee_id']);

        $this->actingAs($partner)
            ->getJson(route('hr.leave-processing-runs.index'))
            ->assertForbidden();

        $this->actingAs($partner)
            ->getJson(route('hr.leave-encashments.index'))
            ->assertForbidden();
    }

    public function test_leave_processing_and_encashment_indexes_reject_unsupported_filters_and_accept_page(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();

        $this->actingAs($hr)
            ->getJson(route('hr.leave-processing-runs.index', ['page' => 1]))
            ->assertOk()
            ->assertJsonPath('meta.current_page', 1);

        $this->actingAs($hr)
            ->getJson(route('hr.leave-processing-runs.index', ['employee_id' => 1]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['employee_id'])
            ->assertJsonPath('errors.employee_id.0', 'The selected filter is not available for this endpoint.');

        $this->actingAs($hr)
            ->getJson(route('hr.leave-encashments.index', ['page' => 1]))
            ->assertOk()
            ->assertJsonPath('meta.current_page', 1);

        $this->actingAs($hr)
            ->getJson(route('hr.leave-encashments.index', ['processing_type' => 'monthly_accrual']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['processing_type'])
            ->assertJsonPath('errors.processing_type.0', 'The selected filter is not available for this endpoint.');
    }

    public function test_global_user_can_create_leave_processing_run_with_explicit_company_scope(): void
    {
        $this->seed();

        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $company = Company::where('code', 'B360D')->firstOrFail();
        $periodYear = (int) now()->year;

        $this->actingAs($director)
            ->postJson(route('hr.leave-processing-runs.store'), [
                'company_id' => $company->id,
                'period_year' => $periodYear,
                'processing_type' => 'monthly_accrual',
                'is_dry_run' => true,
                'note' => 'Global user preview with explicit company scope.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'preview')
            ->assertJsonPath('data.processing_type', 'monthly_accrual')
            ->assertJsonPath('data.summary.employee_count', 4);

        $this->assertDatabaseHas('leave_processing_runs', [
            'company_id' => $company->id,
            'created_by_user_id' => $director->id,
            'period_year' => $periodYear,
            'processing_type' => 'monthly_accrual',
            'status' => 'preview',
        ]);

        $this->actingAs($director)
            ->postJson(route('hr.leave-processing-runs.store'), [
                'period_year' => $periodYear,
                'processing_type' => 'year_end',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('leave_processing_runs', [
            'company_id' => $company->id,
            'created_by_user_id' => $director->id,
            'period_year' => $periodYear,
            'processing_type' => 'year_end',
        ]);
    }

    public function test_non_global_hr_user_without_company_assignment_fails_closed_for_leave_processing_and_encashments(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $employee = Employee::where('user_id', $sales->id)->firstOrFail();
        $earnedLeave = LeaveType::where('code', 'EL')->firstOrFail();

        $encashmentId = $this->actingAs($sales)
            ->postJson(route('hr.leave-encashments.store'), [
                'employee_id' => $employee->id,
                'leave_type_id' => $earnedLeave->id,
                'period_year' => (int) now()->year,
                'requested_days' => 1,
            ])
            ->assertCreated()
            ->json('data.id');

        $encashment = LeaveEncashment::findOrFail($encashmentId);
        $hr->forceFill(['company_id' => null])->save();

        $this->actingAs($hr)
            ->getJson(route('hr.leave-processing-runs.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($hr)
            ->getJson(route('hr.leave-encashments.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($hr)
            ->postJson(route('hr.leave-processing-runs.store'), [
                'period_year' => (int) now()->year,
                'processing_type' => 'monthly_accrual',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['company_id']);

        $this->actingAs($hr)
            ->patchJson(route('hr.leave-encashments.approve', $encashment), [
                'decision_note' => 'Invalid no-company approval.',
            ])
            ->assertForbidden();
    }
}
