<?php

namespace Tests\Feature;

use App\Application\Hr\Data\EmployeeMovementRowData;
use App\Domain\Hr\Services\EmployeeFieldVisibility;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeMovement;
use App\Models\PayrollRun;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrEmployeeCompensationVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_compensation_visibility_has_one_permission_authority(): void
    {
        $this->seed();

        $company = Company::where('code', 'B360D')->firstOrFail();
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();
        $otherEmployee = Employee::where('employee_code', 'EMP-0012')->firstOrFail();
        $selfServiceUser = User::where('email', 'amit.verma@builder360.test')->firstOrFail();
        $visibility = app(EmployeeFieldVisibility::class);

        foreach ([
            'visibility_wildcard' => ['*'],
            'visibility_payroll_view' => ['payroll.view'],
            'visibility_payroll_manage' => ['payroll.manage'],
            'visibility_payroll_approve' => ['payroll.approve'],
            'visibility_hr_manager' => ['hr.manage'],
        ] as $key => $permissions) {
            $actor = $this->createUserWithPermissions($company, $key, $permissions);

            $this->assertTrue(
                $visibility->canViewCompensation($actor, $employee),
                "The {$key} permission set should allow compensation visibility.",
            );
        }

        $this->assertTrue($visibility->canViewCompensation($selfServiceUser, $employee));
        $this->assertFalse($visibility->canViewCompensation($selfServiceUser, $otherEmployee));

        $unauthorized = $this->createUserWithPermissions($company, 'unauthorized_hr_viewer', ['hr.view']);
        $this->assertFalse($visibility->canViewCompensation($unauthorized, $employee));
    }

    public function test_payroll_summary_request_uses_the_shared_compensation_authority(): void
    {
        $this->seed();

        $company = Company::where('code', 'B360D')->firstOrFail();
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();
        $selfServiceUser = User::where('email', 'amit.verma@builder360.test')->firstOrFail();

        foreach ([
            'summary_wildcard' => ['*'],
            'summary_payroll_view' => ['payroll.view'],
            'summary_payroll_manage' => ['payroll.manage'],
            'summary_payroll_approve' => ['payroll.approve'],
            'summary_hr_manager' => ['hr.manage'],
        ] as $key => $permissions) {
            $actor = $this->createUserWithPermissions($company, $key, $permissions);

            $this->actingAs($actor)
                ->getJson(route('hr.employees.payroll-summary.show', $employee))
                ->assertOk();
        }

        $this->actingAs($selfServiceUser)
            ->getJson(route('hr.employees.payroll-summary.show', $employee))
            ->assertOk()
            ->assertJsonPath('data.access_mode', 'self_service');

        $unauthorized = $this->createUserWithPermissions($company, 'summary_unauthorized_hr_viewer', ['hr.view']);

        $this->actingAs($unauthorized)
            ->getJson(route('hr.employees.payroll-summary.show', $employee))
            ->assertForbidden();
    }

    public function test_employee_movement_resource_uses_the_shared_compensation_authority(): void
    {
        $this->seed();

        $company = Company::where('code', 'B360D')->firstOrFail();
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();
        $selfServiceUser = User::where('email', 'amit.verma@builder360.test')->firstOrFail();

        EmployeeMovement::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'movement_number' => 'MOV-COMPENSATION-VISIBILITY',
            'movement_type' => 'salary_change',
            'effective_on' => now()->toDateString(),
            'status' => 'approved',
            'previous_values' => ['monthly_ctc' => '62000.00'],
            'new_values' => ['monthly_ctc' => '73500.00'],
            'approved_at' => now(),
        ]);

        foreach ([
            'movement_wildcard' => ['*'],
            'movement_payroll_view' => ['payroll.view'],
            'movement_payroll_manage' => ['hr.view', 'payroll.manage'],
            'movement_payroll_approve' => ['hr.view', 'payroll.approve'],
            'movement_hr_manager' => ['hr.manage'],
        ] as $key => $permissions) {
            $actor = $this->createUserWithPermissions($company, $key, $permissions);

            $this->actingAs($actor)
                ->getJson(route('hr.employees.movements.index', [
                    $employee,
                    'movement_type' => 'salary_change',
                ]))
                ->assertOk()
                ->assertJsonPath('data.0.new_values.monthly_ctc', '73500.00');
        }

        $this->actingAs($selfServiceUser)
            ->getJson(route('hr.employees.movements.index', [
                $employee,
                'movement_type' => 'salary_change',
            ]))
            ->assertOk()
            ->assertJsonPath('data.0.new_values.monthly_ctc', '73500.00');

        $unauthorized = $this->createUserWithPermissions($company, 'movement_unauthorized_hr_viewer', ['hr.view']);

        $this->actingAs($unauthorized)
            ->getJson(route('hr.employees.movements.index', [
                $employee,
                'movement_type' => 'salary_change',
            ]))
            ->assertOk()
            ->assertJsonPath('data.0.previous_values.monthly_ctc', 'restricted')
            ->assertJsonPath('data.0.new_values.monthly_ctc', 'restricted');
    }

    public function test_employee_movement_html_uses_sanitized_rows_for_compensation_visibility(): void
    {
        $this->seed();

        $company = Company::where('code', 'B360D')->firstOrFail();
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();

        EmployeeMovement::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'movement_number' => 'MOV-HTML-COMPENSATION-VISIBILITY',
            'movement_type' => 'promotion',
            'effective_on' => now()->toDateString(),
            'status' => 'approved',
            'previous_values' => [
                'designation' => 'Site Engineer',
                'monthly_ctc' => '62000.00',
            ],
            'new_values' => [
                'designation' => 'Senior Site Engineer',
                'monthly_ctc' => '73500.00',
            ],
            'reason' => 'Approved promotion',
            'approved_at' => now(),
        ]);

        $restricted = $this->createUserWithPermissions($company, 'movement_html_hr_viewer', ['hr.view']);

        $this->actingAs($restricted)
            ->get(route('hr.employees.movements.index', $employee))
            ->assertOk()
            ->assertViewHas('movements', fn ($rows): bool => $rows->first() instanceof EmployeeMovementRowData)
            ->assertSee('Senior Site Engineer')
            ->assertSee('Compensation details: Restricted')
            ->assertDontSee('73500.00')
            ->assertDontSee('62000.00');

        $authorized = $this->createUserWithPermissions($company, 'movement_html_payroll_viewer', [
            'hr.view',
            'payroll.view',
        ]);

        $this->actingAs($authorized)
            ->get(route('hr.employees.movements.index', $employee))
            ->assertOk()
            ->assertViewHas('movements', fn ($rows): bool => $rows->first() instanceof EmployeeMovementRowData)
            ->assertSee('Monthly CTC: 73500.00')
            ->assertDontSee('Compensation details: Restricted');
    }

    public function test_command_center_uses_the_shared_compensation_authority(): void
    {
        $this->seed();

        $company = Company::where('code', 'B360D')->firstOrFail();
        $payroll = PayrollRun::create([
            'company_id' => $company->id,
            'run_number' => 'PAY-COMMAND-CENTER-VISIBILITY',
            'period_year' => 2099,
            'period_month' => 12,
            'period_start' => '2099-12-01',
            'period_end' => '2099-12-31',
            'working_days' => 26,
            'status' => 'approved',
            'gross_earnings' => 88000,
            'total_deductions' => 8000,
            'net_payable' => 80000,
            'approved_at' => now(),
        ]);

        foreach ([
            'dashboard_wildcard' => ['*'],
            'dashboard_payroll_view' => ['hr.view', 'payroll.view'],
            'dashboard_payroll_manage' => ['hr.view', 'payroll.manage'],
            'dashboard_payroll_approve' => ['hr.view', 'payroll.approve'],
            'dashboard_hr_manager' => ['hr.manage'],
        ] as $key => $permissions) {
            $actor = $this->createUserWithPermissions($company, $key, $permissions);

            $this->actingAs($actor)
                ->get(route('hr.dashboard'))
                ->assertOk()
                ->assertViewHas('dashboard', fn (array $dashboard): bool =>
                    data_get($dashboard, 'abilities.canViewPayroll') === true
                    && (float) data_get($dashboard, 'summary.latest_payroll_net_payable') === (float) $payroll->net_payable
                );
        }

        $unauthorized = $this->createUserWithPermissions($company, 'dashboard_unauthorized_hr_viewer', ['hr.view']);

        $this->actingAs($unauthorized)
            ->get(route('hr.dashboard'))
            ->assertOk()
            ->assertViewHas('dashboard', fn (array $dashboard): bool =>
                data_get($dashboard, 'abilities.canViewPayroll') === false
                && data_get($dashboard, 'summary.latest_payroll_net_payable') === null
            );

        $selfServiceUser = User::where('email', 'amit.verma@builder360.test')->firstOrFail();

        $this->actingAs($selfServiceUser)
            ->get(route('hr.dashboard'))
            ->assertForbidden();
    }

    /** @param array<int, string> $permissions */
    private function createUserWithPermissions(Company $company, string $key, array $permissions): User
    {
        $role = Role::create([
            'slug' => $key,
            'name' => str($key)->replace('_', ' ')->title()->toString(),
            'scope_level' => 'company',
            'permissions' => $permissions,
            'is_active' => true,
        ]);

        return User::factory()->create([
            'role_id' => $role->id,
            'company_id' => $company->id,
            'email' => $key.'@example.test',
            'status' => 'active',
        ]);
    }
}
