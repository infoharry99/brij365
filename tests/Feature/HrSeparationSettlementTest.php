<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeAsset;
use App\Models\EmployeeExitInterview;
use App\Models\EmployeeLoan;
use App\Models\EmployeeSeparationSettlement;
use App\Models\ExpenseClaim;
use App\Models\Role;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrSeparationSettlementTest extends TestCase
{
    public function test_separation_register_renders_as_blade_workspace_for_browser_requests(): void
    {
        $this->seed();
        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $this->actingAs($hr)->get(route('hr.separation-settlements.index'))->assertOk()->assertViewIs('hr.separation.index')->assertSee('Separation &amp; Final Settlement', false);
    }

    use RefreshDatabase;

    public function test_hr_can_initiate_full_and_final_settlement_with_calculation_and_clearance_blockers(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();

        $settlementId = $this->actingAs($hr)
            ->postJson(route('hr.separation-settlements.store'), [
                'employee_id' => $employee->id,
                'separation_type' => 'resignation',
                'resignation_date' => '2026-07-01',
                'last_working_date' => '2026-07-15',
                'settlement_date' => '2026-07-31',
                'reason' => 'Employee resignation after project handover.',
                'bonus_amount' => 5000,
                'gratuity_amount' => 10000,
                'notice_recovery_amount' => 2000,
                'tax_recovery_amount' => 1500,
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'initiated')
            ->assertJsonPath('data.employee.employee_code', 'EMP-0030')
            ->assertJsonPath('data.last_salary_amount', 31000.05)
            ->assertJsonPath('data.leave_encashment_amount', 37200.06)
            ->assertJsonPath('data.bonus_amount', 5000)
            ->assertJsonPath('data.gratuity_amount', 10000)
            ->assertJsonPath('data.notice_recovery_amount', 2000)
            ->assertJsonPath('data.tax_recovery_amount', 1500)
            ->assertJsonPath('data.net_payable', 79700.11)
            ->assertJsonCount(3, 'data.clearance_blockers')
            ->json('data.id');

        $settlement = EmployeeSeparationSettlement::findOrFail($settlementId);

        $this->assertSame('on_notice', $employee->refresh()->status);
        $this->assertSame(['assets', 'loans', 'claims'], collect($settlement->clearance_blockers)->pluck('type')->all());

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'hr.separation_settlement.initiated',
            'auditable_id' => $settlement->id,
            'user_id' => $hr->id,
        ]);

        $this->actingAs($hr)
            ->postJson(route('hr.separation-settlements.store'), [
                'employee_id' => $employee->id,
                'separation_type' => 'resignation',
                'last_working_date' => '2026-07-20',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['employee_id']);
    }

    public function test_hr_and_finance_approval_order_and_segregation_are_enforced(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $employee = Employee::where('employee_code', 'EMP-0021')->firstOrFail();
        $settlement = $this->initiateSettlement($hr, $employee);

        $this->actingAs($finance)
            ->patchJson(route('hr.separation-settlements.finance-approve', $settlement), [
                'note' => 'Invalid approval before HR approval.',
            ])
            ->assertForbidden();

        $this->actingAs($hr)
            ->patchJson(route('hr.separation-settlements.hr-approve', $settlement), [
                'note' => 'HR verified resignation, leave and recoveries.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'hr_approved')
            ->assertJsonPath('data.hr_approved_by.email', 'deepa.rao@builder360.test');

        $settlement->refresh();

        $this->actingAs($hr)
            ->patchJson(route('hr.separation-settlements.finance-approve', $settlement), [
                'note' => 'Invalid same-user finance approval.',
            ])
            ->assertForbidden();

        $this->actingAs($finance)
            ->patchJson(route('hr.separation-settlements.finance-approve', $settlement), [
                'note' => 'Finance verified settlement control totals.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'finance_approved')
            ->assertJsonPath('data.finance_approved_by.email', 'suresh.iyer@builder360.test');

        $this->assertTrue(UserNotification::where('recipient_user_id', $finance->id)
            ->where('category', 'hr')
            ->where('status', 'unread')
            ->exists());

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'hr.separation_settlement.hr_approved',
            'auditable_id' => $settlement->id,
            'user_id' => $hr->id,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'hr.separation_settlement.finance_approved',
            'auditable_id' => $settlement->id,
            'user_id' => $finance->id,
        ]);
    }

    public function test_full_and_final_completion_is_blocked_until_clearances_are_resolved(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();
        $settlement = $this->initiateSettlement($hr, $employee);

        $this->actingAs($hr)->patchJson(route('hr.separation-settlements.hr-approve', $settlement))->assertOk();
        $settlement->refresh();
        $this->actingAs($finance)->patchJson(route('hr.separation-settlements.finance-approve', $settlement))->assertOk();
        $settlement->refresh();

        $this->actingAs($finance)
            ->patchJson(route('hr.separation-settlements.complete', $settlement), [
                'payment_reference' => 'NEFT-FNF-BLOCKED',
                'note' => 'Attempt completion before clearance.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['clearance_blockers']);

        $this->assertSame('finance_approved', $settlement->refresh()->status);

        EmployeeAsset::where('employee_id', $employee->id)->update(['status' => 'recovered', 'recovered_on' => '2026-07-20']);
        EmployeeLoan::where('employee_id', $employee->id)->update(['status' => 'recovered']);
        ExpenseClaim::where('employee_id', $employee->id)->update(['status' => 'paid']);

        $this->actingAs($finance)
            ->patchJson(route('hr.separation-settlements.complete', $settlement), [
                'payment_reference' => 'NEFT-FNF-1001',
                'note' => 'All clearance blockers resolved.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.payment_reference', 'NEFT-FNF-1001')
            ->assertJsonPath('data.completed_by.email', 'suresh.iyer@builder360.test')
            ->assertJsonCount(0, 'data.clearance_blockers');

        $this->assertSame('separated', $employee->refresh()->status);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'hr.separation_settlement.completed',
            'auditable_id' => $settlement->id,
            'user_id' => $finance->id,
        ]);
    }

    public function test_employee_self_scope_and_partner_denial_are_enforced(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $employeeUser = User::where('email', 'amit.verma@builder360.test')->firstOrFail();
        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();
        $amit = Employee::where('employee_code', 'EMP-0030')->firstOrFail();
        $priya = Employee::where('employee_code', 'EMP-0021')->firstOrFail();

        $this->initiateSettlement($hr, $amit);
        $this->initiateSettlement($hr, $priya, 'termination');

        $this->actingAs($employeeUser)
            ->getJson(route('hr.separation-settlements.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.employee.employee_code', 'EMP-0030');

        $this->actingAs($employeeUser)
            ->getJson(route('hr.separation-settlements.index', ['employee_id' => $priya->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['employee_id']);

        $this->actingAs($employeeUser)
            ->postJson(route('hr.separation-settlements.store'), [
                'employee_id' => $amit->id,
                'separation_type' => 'resignation',
                'last_working_date' => '2026-07-31',
            ])
            ->assertForbidden();

        $this->actingAs($partner)
            ->getJson(route('hr.separation-settlements.index'))
            ->assertForbidden();

        $this->actingAs($partner)
            ->postJson(route('hr.separation-settlements.store'), [
                'employee_id' => $amit->id,
                'separation_type' => 'resignation',
                'last_working_date' => '2026-07-31',
            ])
            ->assertForbidden();
    }

    public function test_settlement_compensation_visibility_is_shared_by_json_blade_and_nested_exit_interviews(): void
    {
        $this->seed();

        $company = Company::where('code', 'B360D')->firstOrFail();
        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $employeeUser = User::where('email', 'amit.verma@builder360.test')->firstOrFail();
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();
        $settlement = $this->initiateSettlement($hr, $employee);

        $readOnlyRole = Role::create([
            'slug' => 'settlement-read-only-hr',
            'name' => 'Settlement Read-only HR',
            'scope_level' => 'department',
            'permissions' => ['hr.view'],
            'is_active' => true,
        ]);
        $readOnlyHr = User::factory()->create([
            'role_id' => $readOnlyRole->id,
            'company_id' => $company->id,
            'email' => 'settlement.readonly.hr@example.test',
            'status' => 'active',
        ]);

        $restrictedFields = [
            'calculation_breakdown',
            'last_salary_amount',
            'leave_encashment_amount',
            'bonus_amount',
            'gratuity_amount',
            'claim_payable_amount',
            'notice_recovery_amount',
            'loan_recovery_amount',
            'asset_recovery_amount',
            'tax_recovery_amount',
            'gross_payable',
            'total_recoveries',
            'net_payable',
            'payment_reference',
        ];

        $restrictedJson = $this->actingAs($readOnlyHr)
            ->getJson(route('hr.separation-settlements.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $settlement->id);

        foreach ($restrictedFields as $field) {
            $restrictedJson->assertJsonMissingPath('data.0.'.$field);
        }

        $this->actingAs($readOnlyHr)
            ->get(route('hr.separation-settlements.index'))
            ->assertOk()
            ->assertSee('Compensation details restricted')
            ->assertDontSee('Gross INR '.number_format((float) $settlement->gross_payable, 2), false)
            ->assertDontSee('Recovery INR '.number_format((float) $settlement->total_recoveries, 2), false)
            ->assertDontSee('Net INR '.number_format((float) $settlement->net_payable, 2), false)
            ->assertDontSee('INR '.number_format((float) $settlement->net_payable, 2), false);

        foreach ([$hr, $finance, $employeeUser] as $authorizedActor) {
            $this->actingAs($authorizedActor)
                ->getJson(route('hr.separation-settlements.index'))
                ->assertOk()
                ->assertJsonPath('data.0.last_salary_amount', (float) $settlement->last_salary_amount)
                ->assertJsonPath('data.0.gross_payable', (float) $settlement->gross_payable)
                ->assertJsonPath('data.0.total_recoveries', fn (mixed $value): bool => (float) $value === (float) $settlement->total_recoveries)
                ->assertJsonPath('data.0.net_payable', (float) $settlement->net_payable);
        }

        EmployeeExitInterview::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'employee_separation_settlement_id' => $settlement->id,
            'scheduled_by_user_id' => $hr->id,
            'interview_number' => 'EXI-SETTLEMENT-VISIBILITY',
            'status' => 'archived',
            'interview_due_on' => now()->toDateString(),
        ]);

        $nestedRestricted = $this->actingAs($readOnlyHr)
            ->getJson(route('hr.exit-interviews.index', ['status' => 'archived']))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.separation_settlement.id', $settlement->id);

        foreach ($restrictedFields as $field) {
            $nestedRestricted->assertJsonMissingPath('data.0.separation_settlement.'.$field);
        }

        $this->actingAs($hr)
            ->getJson(route('hr.exit-interviews.index', ['status' => 'archived']))
            ->assertOk()
            ->assertJsonPath('data.0.separation_settlement.net_payable', (float) $settlement->net_payable);
    }

    public function test_separation_settlement_index_rejects_unsupported_filters_and_accepts_page(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();

        $this->actingAs($hr)
            ->getJson(route('hr.separation-settlements.index', ['page' => 1]))
            ->assertOk()
            ->assertJsonPath('meta.current_page', 1);

        $this->actingAs($hr)
            ->getJson(route('hr.separation-settlements.index', ['rehire_recommendation' => 'yes']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rehire_recommendation'])
            ->assertJsonPath('errors.rehire_recommendation.0', 'The selected filter is not available for this endpoint.');
    }

    public function test_non_global_hr_user_without_company_assignment_fails_closed_for_separation_settlements(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();
        $settlement = $this->initiateSettlement($hr, $employee);

        $hr->forceFill(['company_id' => null])->save();

        $this->actingAs($hr)
            ->getJson(route('hr.separation-settlements.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($hr)
            ->postJson(route('hr.separation-settlements.store'), [
                'employee_id' => $employee->id,
                'separation_type' => 'resignation',
                'last_working_date' => '2026-07-31',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['employee_id']);

        $this->actingAs($hr)
            ->patchJson(route('hr.separation-settlements.hr-approve', $settlement), [
                'note' => 'Invalid no-company HR approval.',
            ])
            ->assertForbidden();
    }

    private function initiateSettlement(User $hr, Employee $employee, string $separationType = 'resignation'): EmployeeSeparationSettlement
    {
        $id = $this->actingAs($hr)
            ->postJson(route('hr.separation-settlements.store'), [
                'employee_id' => $employee->id,
                'separation_type' => $separationType,
                'resignation_date' => '2026-07-01',
                'last_working_date' => '2026-07-15',
                'settlement_date' => '2026-07-31',
                'reason' => 'Test lifecycle settlement.',
            ])
            ->assertCreated()
            ->json('data.id');

        return EmployeeSeparationSettlement::findOrFail($id);
    }
}
