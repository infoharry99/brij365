<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeLeaveBalance;
use App\Models\EmployeePolicyAcknowledgement;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\Role;
use App\Models\User;
use App\Services\Hr\EmployeePolicyAcknowledgementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class HrPeopleDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_authorized_hr_user_can_open_the_people_command_center(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();

        $this->actingAs($hr)
            ->get(route('hr.dashboard'))
            ->assertOk()
            ->assertViewIs('hr.dashboard.index')
            ->assertViewHas('dashboard')
            ->assertSee('HR Command Center')
            ->assertSee('Total Employees')
            ->assertSee('Attendance Today')
            ->assertSee('Approval Inbox')
            ->assertSee('Department Headcount')
            ->assertSee('people-workspace', false);
    }

    public function test_people_command_center_rejects_unsupported_query_parameters(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();

        $this->actingAs($hr)
            ->getJson(route('hr.dashboard', ['unsupported' => 'value']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('unsupported');
    }

    public function test_ordinary_employee_and_partner_cannot_open_the_people_command_center(): void
    {
        $this->seed();

        $employee = User::where('email', 'amit.verma@builder360.test')->firstOrFail();
        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();

        $this->actingAs($employee)
            ->get(route('hr.dashboard'))
            ->assertForbidden();

        $this->actingAs($partner)
            ->get(route('hr.dashboard'))
            ->assertForbidden();
    }

    public function test_command_center_aggregates_are_restricted_to_the_active_company(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $visibleCompany = Company::where('code', 'B360D')->firstOrFail();
        $outsideCompany = Company::where('code', 'B360P')->firstOrFail();

        Employee::create([
            'company_id' => $outsideCompany->id,
            'employee_code' => 'EMP-CROSS-COMPANY-DASHBOARD',
            'name' => 'Cross Company Dashboard Sentinel',
            'designation' => 'Sentinel',
            'department' => 'Cross Company Sentinel Department',
            'employment_type' => 'full_time',
            'status' => 'active',
            'joined_on' => now()->subYear(),
            'statutory_state' => 'MH',
        ]);

        $expectedHeadcount = Employee::query()
            ->where('company_id', $visibleCompany->id)
            ->count();

        $this->actingAs($hr)
            ->get(route('hr.dashboard'))
            ->assertOk()
            ->assertDontSee('Cross Company Sentinel Department')
            ->assertDontSee('Cross Company Dashboard Sentinel')
            ->assertViewHas('dashboard', function (array $dashboard) use ($expectedHeadcount): bool {
                $departments = collect($dashboard['departmentHeadcount'] ?? []);

                return (int) data_get($dashboard, 'summary.total_headcount') === $expectedHeadcount
                    && ! $departments->contains(
                        fn (array $row): bool => ($row['department'] ?? null) === 'Cross Company Sentinel Department',
                    );
            });
    }

    public function test_command_center_never_exposes_payroll_totals_without_payroll_permission(): void
    {
        $this->seed();

        $company = Company::where('code', 'B360D')->firstOrFail();
        $payroll = PayrollRun::create([
            'company_id' => $company->id,
            'run_number' => 'PAY-DASHBOARD-SENSITIVE-01',
            'period_year' => 2035,
            'period_month' => 11,
            'period_start' => '2035-11-01',
            'period_end' => '2035-11-30',
            'working_days' => 25,
            'status' => 'approved',
            'gross_earnings' => 10000000.00,
            'total_deductions' => 123456.79,
            'net_payable' => 9876543.21,
            'approved_at' => now(),
        ]);

        $hrViewer = $this->createUserWithPermissions(
            company: $company,
            slug: 'hr_dashboard_read_only',
            email: 'hr.dashboard.viewer@example.test',
            permissions: ['hr.view'],
        );
        $payrollOnlyViewer = $this->createUserWithPermissions(
            company: $company,
            slug: 'payroll_only_dashboard_viewer',
            email: 'payroll.only.dashboard.viewer@example.test',
            permissions: ['payroll.view'],
        );
        $hrPayrollViewer = $this->createUserWithPermissions(
            company: $company,
            slug: 'hr_payroll_dashboard_viewer',
            email: 'hr.payroll.dashboard.viewer@example.test',
            permissions: ['hr.view', 'payroll.view'],
        );

        $this->actingAs($hrViewer)
            ->get(route('hr.dashboard'))
            ->assertOk()
            ->assertDontSee('9,876,543')
            ->assertViewHas('dashboard', function (array $dashboard): bool {
                $summary = $dashboard['summary'] ?? [];

                return ! isset($summary['latest_payroll_net_payable']);
            });

        $this->actingAs($payrollOnlyViewer)
            ->get(route('hr.dashboard'))
            ->assertForbidden();

        $this->actingAs($hrPayrollViewer)
            ->get(route('hr.dashboard'))
            ->assertOk()
            ->assertViewHas('dashboard', fn (array $dashboard): bool =>
                (float) data_get($dashboard, 'summary.latest_payroll_net_payable') === (float) $payroll->net_payable
            );
    }

    public function test_employee_self_service_dashboard_uses_the_current_employees_real_records(): void
    {
        Carbon::setTestNow('2026-07-16 10:00:00');
        $this->seed();

        $user = User::where('email', 'amit.verma@builder360.test')->firstOrFail();
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();
        $leaveType = LeaveType::query()->where('company_id', $employee->company_id)->firstOrFail();

        AttendanceRecord::withTrashed()
            ->where('employee_id', $employee->id)
            ->whereBetween('work_date', ['2026-07-01', '2026-07-31'])
            ->forceDelete();

        AttendanceRecord::create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'work_date' => '2026-07-15',
            'source' => 'manual',
            'status' => 'present',
            'worked_minutes' => 480,
        ]);
        AttendanceRecord::create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'work_date' => '2026-07-16',
            'source' => 'manual',
            'status' => 'absent',
            'worked_minutes' => 0,
        ]);

        EmployeeLeaveBalance::query()
            ->where('employee_id', $employee->id)
            ->where('period_year', 2026)
            ->update(['available_days' => 0]);
        EmployeeLeaveBalance::updateOrCreate(
            [
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
                'period_year' => 2026,
            ],
            [
                'company_id' => $employee->company_id,
                'opening_balance_days' => 12.5,
                'accrued_days' => 0,
                'used_days' => 0,
                'pending_days' => 0,
                'adjusted_days' => 0,
                'available_days' => 12.5,
                'ledger' => [],
            ],
        );

        LeaveRequest::create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'requested_by_user_id' => $user->id,
            'request_number' => 'LR-ESS-DASHBOARD-01',
            'status' => 'submitted',
            'starts_on' => '2026-07-20',
            'ends_on' => '2026-07-20',
            'duration_unit' => 'full_day',
            'requested_days' => 1,
            'reason' => 'Self-service dashboard fixture',
            'workflow_history' => [],
        ]);

        $payroll = PayrollRun::create([
            'company_id' => $employee->company_id,
            'run_number' => 'PAY-ESS-DASHBOARD-01',
            'period_year' => 2035,
            'period_month' => 10,
            'period_start' => '2035-10-01',
            'period_end' => '2035-10-31',
            'working_days' => 26,
            'status' => 'approved',
            'gross_earnings' => 62000,
            'total_deductions' => 7000,
            'net_payable' => 55000,
            'approved_at' => now(),
        ]);
        PayrollRunItem::create([
            'payroll_run_id' => $payroll->id,
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'monthly_ctc' => 62000,
            'payable_days' => 26,
            'gross_earnings' => 62000,
            'total_deductions' => 7000,
            'net_payable' => 55000,
            'component_breakup' => [],
            'status' => 'approved',
        ]);

        $response = $this->actingAs($user)
            ->get(route('hr.employees.me'));

        $response
            ->assertOk()
            ->assertViewIs('hr.employees.self-service')
            ->assertViewHas('employee', fn (Employee $viewEmployee): bool => $viewEmployee->is($employee))
            ->assertSee('Employee Self Service')
            ->assertSee('My Attendance')
            ->assertSee('My Actions')
            ->assertSee('10/2035');

        $selfService = $response->viewData('selfService');

        $this->assertIsArray($selfService);
        $this->assertSame(50.0, (float) data_get($selfService, 'summary.attendance_percent'));
        $this->assertSame(2, (int) data_get($selfService, 'summary.attendance_marked_days'));
        $this->assertSame(12.5, (float) data_get($selfService, 'summary.leave_available_days'));
        $this->assertGreaterThanOrEqual(1, (int) data_get($selfService, 'summary.open_requests'));
        $this->assertSame('10/2035', data_get($selfService, 'summary.latest_payslip_period'));
        $recentAttendance = collect($selfService['recentAttendance'] ?? []);
        $this->assertCount(2, $recentAttendance);
        $this->assertSame(['2026-07-16', '2026-07-15'], $recentAttendance->pluck('work_date')->all());
        $this->assertTrue($recentAttendance->contains(
            fn (array $row): bool => ($row['status'] ?? null) === 'present'
                && ($row['status_label'] ?? null) === 'Present'
                && ($row['tone'] ?? null) === 'success',
        ));
    }

    public function test_self_service_dashboard_is_self_only_and_has_honest_empty_states(): void
    {
        Carbon::setTestNow('2026-07-16 10:00:00');
        $this->seed();

        $company = Company::where('code', 'B360D')->firstOrFail();
        $user = $this->createUserWithPermissions(
            company: $company,
            slug: 'ess_dashboard_empty',
            email: 'empty.ess.dashboard@example.test',
            permissions: ['employee.self_service', 'leave.view', 'leave.request', 'attendance.view', 'attendance.regularize'],
        );
        $employee = Employee::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'employee_code' => 'EMP-ESS-EMPTY',
            'name' => 'Empty ESS Dashboard User',
            'designation' => 'Employee',
            'department' => 'Operations',
            'employment_type' => 'full_time',
            'status' => 'active',
            'joined_on' => '2026-07-01',
            'statutory_state' => 'MH',
        ]);

        $policy = app(EmployeePolicyAcknowledgementService::class)->policyCatalogue($employee)[0];
        EmployeePolicyAcknowledgement::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'acknowledged_by_user_id' => $user->id,
            'policy_key' => $policy['policy_key'],
            'policy_title' => $policy['policy_title'],
            'policy_version' => $policy['policy_version'],
            'status' => 'acknowledged',
            'policy_snapshot' => $policy,
            'workflow_history' => [],
            'acknowledged_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->get(route('hr.employees.me'));

        $response
            ->assertOk()
            ->assertViewIs('hr.employees.self-service')
            ->assertViewHas('employee', fn (Employee $viewEmployee): bool => $viewEmployee->is($employee))
            ->assertSee('No attendance records yet')
            ->assertSee('No pending actions')
            ->assertDontSee('Amit Verma');

        $selfService = $response->viewData('selfService');

        $this->assertIsArray($selfService);
        $this->assertNull(data_get($selfService, 'summary.attendance_percent'));
        $this->assertSame(0, (int) data_get($selfService, 'summary.open_requests'));
        $this->assertNull(data_get($selfService, 'summary.latest_payslip_period'));
        $this->assertSame([], $selfService['recentAttendance'] ?? []);
        $this->assertContains(
            route('hr.employees.documents.index', $employee),
            collect($selfService['quickActions'] ?? [])->pluck('url')->all(),
        );
        $this->assertContains(
            route('hr.employees.payroll-summary.show', $employee),
            collect($selfService['quickActions'] ?? [])->pluck('url')->all(),
        );

        $otherEmployee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();

        $this->actingAs($user)
            ->get(route('hr.employees.show', $otherEmployee))
            ->assertForbidden();
    }

    public function test_self_service_user_can_still_open_their_employee_360_profile(): void
    {
        $this->seed();

        $user = User::where('email', 'amit.verma@builder360.test')->firstOrFail();
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();

        $this->actingAs($user)
            ->get(route('hr.employees.me.profile'))
            ->assertOk()
            ->assertViewIs('hr.employees.show')
            ->assertViewHas('employee', fn (Employee $viewEmployee): bool => $viewEmployee->is($employee))
            ->assertSee('Employee 360')
            ->assertSee('Amit Verma');
    }

    /**
     * @param array<int, string> $permissions
     */
    private function createUserWithPermissions(Company $company, string $slug, string $email, array $permissions): User
    {
        $role = Role::create([
            'slug' => $slug,
            'name' => str($slug)->replace('_', ' ')->title()->toString(),
            'scope_level' => 'company',
            'permissions' => $permissions,
            'is_active' => true,
        ]);

        return User::factory()->create([
            'role_id' => $role->id,
            'company_id' => $company->id,
            'email' => $email,
            'status' => 'active',
        ]);
    }
}
