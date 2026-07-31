<?php

namespace Tests\Feature;

use App\Models\CommissionItem;
use App\Models\CommissionRule;
use App\Models\CommissionRun;
use App\Models\Company;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollCommissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_commission_workflows_have_server_rendered_people_workspace_without_breaking_json_contracts(): void
    {
        $this->seed();

        $payroll = User::where('email', 'kavita.shah@builder360.test')->firstOrFail();

        $this->actingAs($payroll)
            ->get(route('payroll.commission-rules.index'))
            ->assertOk()
            ->assertSee('Commission rules')
            ->assertSee('Create commission rule')
            ->assertSee('commissionRuleForm', false)
            ->assertSee(route('payroll.commission-rules.store'), false);

        $this->actingAs($payroll)
            ->get(route('payroll.commission-runs.index'))
            ->assertOk()
            ->assertSee('Commission runs')
            ->assertSee('Generate commission run')
            ->assertSee(route('payroll.commission-runs.store'), false);

        $this->actingAs($payroll)
            ->post(route('payroll.commission-rules.store'), [
                'rule_code' => 'COMM-BROWSER-1',
                'name' => 'Browser workflow rule',
                'rule_type' => 'fixed',
                'basis' => 'booking_value',
                'fixed_amount' => 1250,
                'effective_from' => now()->toDateString(),
                'status' => 'active',
            ])
            ->assertRedirect(route('payroll.commission-rules.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('commission_rules', [
            'rule_code' => 'COMM-BROWSER-1',
            'name' => 'Browser workflow rule',
        ]);

        $this->actingAs($payroll)
            ->getJson(route('payroll.commission-rules.index'))
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_payroll_can_generate_commission_run_finance_approves_and_payroll_includes_it_once(): void
    {
        $this->seed();

        $payroll = User::where('email', 'kavita.shah@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $period = now();
        $rule = CommissionRule::where('rule_code', 'COMM-SALES-BOOKING-1')->firstOrFail();

        $this->actingAs($payroll)
            ->getJson(route('payroll.commission-rules.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.rule_code', 'COMM-SALES-BOOKING-1')
            ->assertJsonPath('data.0.rule_type', 'percentage');

        $runId = $this->actingAs($payroll)
            ->postJson(route('payroll.commission-runs.store'), [
                'commission_rule_id' => $rule->id,
                'period_year' => $period->year,
                'period_month' => $period->month,
                'note' => 'Calculate current booking commissions.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'generated')
            ->assertJsonPath('data.item_count', 1)
            ->assertJsonPath('data.source_total', 14127000)
            ->assertJsonPath('data.commission_total', 176587.5)
            ->assertJsonPath('data.items.0.employee_code', 'EMP-0021')
            ->json('data.id');

        $run = CommissionRun::findOrFail($runId);

        $this->actingAs($payroll)
            ->patchJson(route('payroll.commission-runs.approve', $run), [
                'decision_note' => 'Creator cannot approve own commission run.',
            ])
            ->assertForbidden();

        $this->actingAs($finance)
            ->patchJson(route('payroll.commission-runs.approve', $run), [
                'decision_note' => 'Approved for payroll.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.approved_by.email', 'suresh.iyer@builder360.test');

        $this->assertDatabaseHas('commission_items', [
            'commission_run_id' => $run->id,
            'status' => 'approved',
            'commission_amount' => 176587.5,
        ]);

        $payrollRunNumber = $this->actingAs($payroll)
            ->postJson(route('payroll.runs.generate'), [
                'period_year' => $period->year,
                'period_month' => $period->month,
                'working_days' => 26,
            ])
            ->assertCreated()
            ->assertJsonPath('data.gross_earnings', 536587.5)
            ->assertJsonPath('data.total_deductions', 40000)
            ->assertJsonPath('data.net_payable', 496587.5)
            ->json('data.run_number');

        $payrollRun = PayrollRun::where('run_number', $payrollRunNumber)->firstOrFail();
        $commissionItem = CommissionItem::where('commission_run_id', $run->id)->firstOrFail();

        $this->assertSame('payroll_included', $commissionItem->status);
        $this->assertNotNull($commissionItem->payroll_run_item_id);

        $priyaPayrollItem = PayrollRunItem::with('employee')
            ->where('payroll_run_id', $payrollRun->id)
            ->whereHas('employee', fn ($query) => $query->where('employee_code', 'EMP-0021'))
            ->firstOrFail();

        $this->assertSame(266587.5, (float) $priyaPayrollItem->gross_earnings);
        $this->assertSame(256587.5, (float) $priyaPayrollItem->net_payable);
        $this->assertTrue(collect($priyaPayrollItem->component_breakup)->contains(fn (array $component): bool => $component['component_code'] === 'COMM'
            && (float) $component['amount'] === 176587.5));

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'payroll.commission_run.approved',
            'auditable_id' => $run->id,
            'user_id' => $finance->id,
        ]);
    }

    public function test_commission_run_generation_blocks_duplicate_rule_period(): void
    {
        $this->seed();

        $payroll = User::where('email', 'kavita.shah@builder360.test')->firstOrFail();
        $rule = CommissionRule::where('rule_code', 'COMM-SALES-BOOKING-1')->firstOrFail();
        $payload = [
            'commission_rule_id' => $rule->id,
            'period_year' => now()->year,
            'period_month' => now()->month,
        ];

        $this->actingAs($payroll)->postJson(route('payroll.commission-runs.store'), $payload)->assertCreated();

        $this->actingAs($payroll)
            ->postJson(route('payroll.commission-runs.store'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['period_month']);
    }

    public function test_non_global_commission_users_without_company_assignment_fail_closed(): void
    {
        $this->seed();

        $payroll = User::where('email', 'kavita.shah@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $rule = CommissionRule::where('rule_code', 'COMM-SALES-BOOKING-1')->firstOrFail();
        $externalRule = $this->createExternalCommissionRule();

        $run = CommissionRun::create([
            'company_id' => $rule->company_id,
            'commission_rule_id' => $rule->id,
            'generated_by_user_id' => $payroll->id,
            'run_number' => 'COM-SCOPE-0001',
            'period_year' => now()->year,
            'period_month' => now()->month,
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'status' => 'generated',
            'item_count' => 0,
            'source_total' => 0,
            'eligible_total' => 0,
            'commission_total' => 0,
            'calculation_summary' => [],
            'workflow_history' => [],
        ]);

        $payroll->forceFill(['company_id' => null])->save();
        $finance->forceFill(['company_id' => null])->save();

        $this->actingAs($payroll)
            ->getJson(route('payroll.commission-rules.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($payroll)
            ->getJson(route('payroll.commission-runs.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($payroll)
            ->getJson(route('payroll.commission-rules.index', ['project_id' => $externalRule->project_id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('project_id');

        $this->actingAs($payroll)
            ->getJson(route('payroll.commission-runs.index', ['commission_rule_id' => $rule->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('commission_rule_id');

        $this->actingAs($payroll)
            ->postJson(route('payroll.commission-rules.store'), [
                'rule_code' => 'COMM-NO-COMPANY',
                'name' => 'No Company Commission Rule',
                'rule_type' => 'fixed',
                'basis' => 'booking_value',
                'fixed_amount' => 5000,
                'effective_from' => now()->startOfYear()->toDateString(),
                'status' => 'active',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('rule_code');

        $this->actingAs($payroll)
            ->postJson(route('payroll.commission-runs.store'), [
                'commission_rule_id' => $rule->id,
                'period_year' => now()->year,
                'period_month' => now()->month,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('commission_rule_id');

        $this->actingAs($finance)
            ->patchJson(route('payroll.commission-runs.approve', $run), [
                'decision_note' => 'Should fail without company scope.',
            ])
            ->assertForbidden();

        $this->actingAs($finance)
            ->patchJson(route('payroll.commission-runs.reject', $run), [
                'decision_note' => 'Should fail without company scope.',
            ])
            ->assertForbidden();
    }

    public function test_commission_indexes_validate_filters_and_scope(): void
    {
        $this->seed();

        $payroll = User::where('email', 'kavita.shah@builder360.test')->firstOrFail();
        $rule = CommissionRule::where('rule_code', 'COMM-SALES-BOOKING-1')->firstOrFail();
        $externalRule = $this->createExternalCommissionRule();
        $period = now();

        $this->actingAs($payroll)
            ->getJson(route('payroll.commission-rules.index', ['status' => 'invalid']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->actingAs($payroll)
            ->getJson(route('payroll.commission-rules.index', ['page' => 1]))
            ->assertOk()
            ->assertJsonPath('meta.current_page', 1);

        $this->actingAs($payroll)
            ->getJson(route('payroll.commission-rules.index', ['commission_rule_id' => $rule->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['commission_rule_id'])
            ->assertJsonPath('errors.commission_rule_id.0', 'The selected filter is not available for this endpoint.');

        $this->actingAs($payroll)
            ->getJson(route('payroll.commission-rules.index', ['rule_type' => 'bonus']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('rule_type');

        $this->actingAs($payroll)
            ->getJson(route('payroll.commission-rules.index', ['basis' => 'cash']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('basis');

        $this->actingAs($payroll)
            ->getJson(route('payroll.commission-rules.index', ['per_page' => 500]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');

        $this->actingAs($payroll)
            ->getJson(route('payroll.commission-rules.index', ['project_id' => $externalRule->project_id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('project_id');

        $this->actingAs($payroll)
            ->getJson(route('payroll.commission-rules.index', [
                'status' => 'active',
                'rule_type' => 'percentage',
                'basis' => 'booking_value',
                'project_id' => $rule->project_id,
                'search' => 'COMM-SALES',
                'per_page' => 10,
            ]))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.rule_code', 'COMM-SALES-BOOKING-1');

        $runId = $this->actingAs($payroll)
            ->postJson(route('payroll.commission-runs.store'), [
                'commission_rule_id' => $rule->id,
                'period_year' => $period->year,
                'period_month' => $period->month,
            ])
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($payroll)
            ->getJson(route('payroll.commission-runs.index', ['status' => 'invalid']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->actingAs($payroll)
            ->getJson(route('payroll.commission-runs.index', ['page' => 1]))
            ->assertOk()
            ->assertJsonPath('meta.current_page', 1);

        $this->actingAs($payroll)
            ->getJson(route('payroll.commission-runs.index', ['project_id' => $rule->project_id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id'])
            ->assertJsonPath('errors.project_id.0', 'The selected filter is not available for this endpoint.');

        $this->actingAs($payroll)
            ->getJson(route('payroll.commission-runs.index', ['period_year' => 2019]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('period_year');

        $this->actingAs($payroll)
            ->getJson(route('payroll.commission-runs.index', ['period_month' => 13]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('period_month');

        $this->actingAs($payroll)
            ->getJson(route('payroll.commission-runs.index', ['per_page' => 500]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');

        $this->actingAs($payroll)
            ->getJson(route('payroll.commission-runs.index', ['commission_rule_id' => $externalRule->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('commission_rule_id');

        $this->actingAs($payroll)
            ->getJson(route('payroll.commission-runs.index', [
                'status' => 'generated',
                'commission_rule_id' => $rule->id,
                'period_year' => $period->year,
                'period_month' => $period->month,
                'per_page' => 10,
            ]))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $runId);
    }

    public function test_payroll_can_create_commission_rule_and_reject_generated_run(): void
    {
        $this->seed();

        $payroll = User::where('email', 'kavita.shah@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();

        $ruleId = $this->actingAs($payroll)
            ->postJson(route('payroll.commission-rules.store'), [
                'rule_code' => 'COMM-FIXED-DEMO',
                'name' => 'Fixed Demo Commission',
                'rule_type' => 'fixed',
                'basis' => 'booking_value',
                'fixed_amount' => 25000,
                'effective_from' => now()->startOfYear()->toDateString(),
                'status' => 'active',
            ])
            ->assertCreated()
            ->assertJsonPath('data.rule_code', 'COMM-FIXED-DEMO')
            ->json('data.id');

        $runId = $this->actingAs($payroll)
            ->postJson(route('payroll.commission-runs.store'), [
                'commission_rule_id' => $ruleId,
                'period_year' => now()->year,
                'period_month' => now()->month,
            ])
            ->assertCreated()
            ->assertJsonPath('data.commission_total', 25000)
            ->json('data.id');

        $run = CommissionRun::findOrFail($runId);

        $this->actingAs($finance)
            ->patchJson(route('payroll.commission-runs.reject', $run), [
                'decision_note' => 'Rejected during finance validation.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $this->assertDatabaseHas('commission_items', [
            'commission_run_id' => $run->id,
            'status' => 'rejected',
        ]);
    }

    public function test_partner_cannot_access_internal_commission_routes(): void
    {
        $this->seed();

        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();
        $rule = CommissionRule::where('rule_code', 'COMM-SALES-BOOKING-1')->firstOrFail();

        $this->actingAs($partner)
            ->getJson(route('payroll.commission-rules.index'))
            ->assertForbidden();

        $this->actingAs($partner)
            ->getJson(route('payroll.commission-runs.index'))
            ->assertForbidden();

        $this->actingAs($partner)
            ->postJson(route('payroll.commission-runs.store'), [
                'commission_rule_id' => $rule->id,
                'period_year' => now()->year,
                'period_month' => now()->month,
            ])
            ->assertForbidden();
    }

    private function createExternalCommissionRule(): CommissionRule
    {
        $company = Company::create([
            'code' => 'EXTCOMM',
            'name' => 'External Commission Company',
            'legal_name' => 'External Commission Company Pvt Ltd',
            'state' => 'MH',
            'status' => 'active',
        ]);

        $project = Project::create([
            'company_id' => $company->id,
            'branch_id' => null,
            'code' => 'EXT-COMM',
            'name' => 'External Commission Project',
            'project_type' => 'residential',
            'city' => 'Pune',
            'state' => 'MH',
            'status' => 'active',
            'budget_amount' => 10000000,
            'target_roi_percent' => 12,
            'starts_on' => now()->startOfYear()->toDateString(),
            'ends_on' => now()->addYear()->toDateString(),
        ]);

        return CommissionRule::create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'created_by_user_id' => null,
            'rule_code' => 'COMM-EXT-10001',
            'name' => 'External Commission Rule',
            'rule_type' => 'percentage',
            'basis' => 'booking_value',
            'rate_percent' => 1.5,
            'fixed_amount' => 0,
            'target_amount' => 0,
            'slab_rules' => null,
            'eligibility_rules' => [],
            'effective_from' => now()->startOfYear()->toDateString(),
            'effective_to' => null,
            'status' => 'active',
            'workflow_history' => [[
                'status' => 'active',
                'actor' => 'Scope Test',
                'note' => 'External rule',
                'at' => now()->toISOString(),
            ]],
            'metadata' => ['source' => 'scope-test'],
        ]);
    }
}
