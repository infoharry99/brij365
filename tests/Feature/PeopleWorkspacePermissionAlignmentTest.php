<?php

namespace Tests\Feature;

use App\Domain\Scoring\Support\LogicCenterPermissions;
use App\Domain\Hr\Services\EmployeeOperationsRegister;
use App\Domain\Hr\Services\PeopleWorkspaceNavigation;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeAsset;
use App\Models\EmployeeLoan;
use App\Models\ExpenseClaim;
use App\Models\HrHelpdeskTicket;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PeopleWorkspacePermissionAlignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_self_service_navigation_uses_route_policy_authority(): void
    {
        $this->seed();

        $actor = User::where('email', 'amit.verma@builder360.test')->firstOrFail();
        $this->givePermissions($actor, ['employee.self_service']);

        $keys = collect(app(PeopleWorkspaceNavigation::class)->links($actor))->pluck('key');

        $this->assertTrue($keys->contains('performance'));
        $this->assertTrue($keys->contains('assets'));
        $this->assertTrue($keys->contains('claims'));
        $this->assertTrue($keys->contains('loans'));
        $this->assertTrue($keys->contains('helpdesk'));
        $this->assertFalse($keys->contains('employees'));
        $this->assertFalse($keys->contains('documents'));
        $this->assertFalse($keys->contains('compliance'));

        $this->actingAs($actor)
            ->get(route('hr.assets.index'))
            ->assertOk()
            ->assertSee(route('hr.performance-dashboard.index'), false)
            ->assertSee(route('hr.assets.index'), false)
            ->assertSee(route('hr.helpdesk-tickets.index'), false)
            ->assertDontSee(route('hr.compliance-rule-settings.index'), false);
    }

    public function test_self_service_combined_with_reviewer_permissions_keeps_company_register_scope(): void
    {
        $this->seed();

        $actor = User::where('email', 'amit.verma@builder360.test')->firstOrFail();
        $this->givePermissions($actor, [
            'employee.self_service',
            'assets.view',
            'claims.view',
            'loans.view',
            'helpdesk.view',
        ]);

        $companyId = (int) $actor->company_id;

        $this->actingAs($actor)
            ->getJson(route('hr.assets.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', EmployeeAsset::where('company_id', $companyId)->count());

        $this->actingAs($actor)
            ->getJson(route('hr.expense-claims.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', ExpenseClaim::where('company_id', $companyId)->count());

        $this->actingAs($actor)
            ->getJson(route('hr.loans.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', EmployeeLoan::where('company_id', $companyId)->count());

        $this->actingAs($actor)
            ->getJson(route('hr.helpdesk-tickets.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', HrHelpdeskTicket::where('company_id', $companyId)->count());

        $employeeCount = Employee::where('company_id', $companyId)->where('status', 'active')->count();
        $this->assertCount($employeeCount, app(EmployeeOperationsRegister::class)->employees($actor, 'claims'));

        $otherEmployee = Employee::where('company_id', $companyId)
            ->where('user_id', '!=', $actor->id)
            ->firstOrFail();
        $otherClaim = ExpenseClaim::create([
            'company_id' => $companyId,
            'employee_id' => $otherEmployee->id,
            'requested_by_user_id' => $actor->id,
            'claim_number' => 'EXP-SCOPE-001',
            'claim_type' => 'office',
            'status' => 'submitted',
            'claim_date' => now()->toDateString(),
            'amount' => 500,
            'currency' => 'INR',
            'description' => 'Permission-scope policy fixture.',
        ]);
        $otherAsset = EmployeeAsset::create([
            'company_id' => $companyId,
            'employee_id' => $otherEmployee->id,
            'assigned_by_user_id' => $actor->id,
            'asset_code' => 'AST-SCOPE-001',
            'category' => 'equipment',
            'name' => 'Permission scope fixture',
            'status' => 'assigned',
            'condition' => 'good',
            'assigned_on' => now()->toDateString(),
        ]);

        $this->assertTrue($actor->can('view', $otherClaim));
        $this->assertTrue($actor->can('view', $otherAsset));
    }

    public function test_true_self_service_user_remains_limited_to_own_operations_and_employee_choice(): void
    {
        $this->seed();

        $actor = User::where('email', 'amit.verma@builder360.test')->firstOrFail();
        $this->givePermissions($actor, ['employee.self_service']);

        $ownEmployee = Employee::where('user_id', $actor->id)->firstOrFail();

        foreach (['assets', 'claims', 'loans', 'helpdesk'] as $operation) {
            $employees = app(EmployeeOperationsRegister::class)->employees($actor, $operation);
            $this->assertCount(1, $employees);
            $this->assertSame($ownEmployee->id, $employees->first()->id);
        }
    }

    public function test_self_service_manager_can_submit_claim_for_another_company_employee(): void
    {
        $this->seed();

        $actor = User::where('email', 'amit.verma@builder360.test')->firstOrFail();
        $this->givePermissions($actor, ['employee.self_service', 'claims.manage']);
        $otherEmployee = Employee::where('company_id', $actor->company_id)
            ->where('user_id', '!=', $actor->id)
            ->firstOrFail();

        $this->actingAs($actor)
            ->postJson(route('hr.expense-claims.store'), [
                'employee_id' => $otherEmployee->id,
                'claim_type' => 'office',
                'claim_date' => now()->toDateString(),
                'amount' => 1500,
                'currency' => 'INR',
                'description' => 'Office supplies purchased for an authorized employee.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.employee.id', $otherEmployee->id);
    }

    public function test_hr_manager_can_reach_claim_and_loan_registers_that_its_policy_can_approve(): void
    {
        $this->seed();

        $actor = User::where('email', 'amit.verma@builder360.test')->firstOrFail();
        $this->givePermissions($actor, ['hr.manage']);

        $keys = collect(app(PeopleWorkspaceNavigation::class)->links($actor))->pluck('key');

        $this->assertTrue($keys->contains('claims'));
        $this->assertTrue($keys->contains('loans'));

        $otherEmployee = Employee::where('company_id', $actor->company_id)
            ->where('user_id', '!=', $actor->id)
            ->firstOrFail();

        $this->actingAs($actor)
            ->get(route('hr.expense-claims.index', ['employee_id' => $otherEmployee->id]))
            ->assertOk();

        $this->actingAs($actor)
            ->get(route('hr.loans.index', ['employee_id' => $otherEmployee->id]))
            ->assertOk();
    }

    public function test_roster_only_role_receives_the_governed_roster_destination_without_legacy_attendance_access(): void
    {
        $this->seed();

        $actor = User::where('email', 'amit.verma@builder360.test')->firstOrFail();
        $this->givePermissions($actor, [LogicCenterPermissions::ROSTER_MANAGE]);

        $links = collect(app(PeopleWorkspaceNavigation::class)->links($actor));
        $rosterLink = $links->firstWhere('key', 'shifts');

        $this->assertNotNull($rosterLink);
        $this->assertSame('hr.attendance-rosters.index', $rosterLink->route);
        $this->assertFalse($links->contains('key', 'attendance'));

        $this->actingAs($actor)
            ->get(route('hr.attendance-rosters.index'))
            ->assertOk()
            ->assertSee(route('hr.attendance-rosters.index'), false)
            ->assertDontSee(route('hr.attendance-records.index'), false);
    }

    /** @param array<int, string> $permissions */
    private function givePermissions(User $actor, array $permissions): void
    {
        $company = Company::findOrFail($actor->company_id);
        $role = Role::create([
            'slug' => 'people-permission-alignment-'.str()->lower(str()->random(8)),
            'name' => 'People Permission Alignment',
            'scope_level' => 'company',
            'permissions' => $permissions,
            'is_active' => true,
        ]);

        $actor->forceFill([
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ])->save();
        $actor->unsetRelation('role');
    }
}
