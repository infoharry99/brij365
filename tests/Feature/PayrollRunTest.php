<?php

namespace Tests\Feature;

use App\Domain\Payroll\Services\PayrollWorkspaceRegister;
use App\Models\Company;
use App\Models\PayrollRun;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class PayrollRunTest extends TestCase
{
    use RefreshDatabase;

    public function test_payroll_users_can_open_native_blade_workspace(): void
    {
        $this->seed();

        $payroll = User::where('email', 'kavita.shah@builder360.test')->firstOrFail();

        $this->actingAs($payroll)
            ->get(route('payroll.runs.index'))
            ->assertOk()
            ->assertSee('Payroll Workspace')
            ->assertSee('Generate payroll run')
            ->assertSee('Payroll runs')
            ->assertSee('Salary structures')
            ->assertSee('Payroll components');
    }

    public function test_payroll_run_trace_projects_persisted_items_only_for_compensation_authorized_users(): void
    {
        $this->seed();

        $payroll = User::where('email', 'kavita.shah@builder360.test')->firstOrFail();
        $period = now()->addMonthsNoOverflow(11);

        $this->actingAs($payroll)
            ->post(route('payroll.runs.generate'), [
                'period_year' => $period->year,
                'period_month' => $period->month,
                'working_days' => 26,
            ])
            ->assertRedirect(route('payroll.runs.index'));

        $run = PayrollRun::query()
            ->with('items.employee')
            ->where('period_year', $period->year)
            ->where('period_month', $period->month)
            ->firstOrFail();
        $persistedItem = $run->items->firstOrFail();
        $register = app(PayrollWorkspaceRegister::class);
        $filters = [
            'period_year' => $period->year,
            'period_month' => $period->month,
            'status' => 'generated',
            'per_page' => 10,
        ];

        $authorizedRuns = $register->presentRuns($payroll, $register->runs($payroll, $filters));
        $authorizedRow = collect($authorizedRuns->items())->firstWhere('id', $run->id);

        $this->assertNotNull($authorizedRow);
        $this->assertTrue($authorizedRow->canViewCompensation);
        $this->assertSame($run->items->count(), count($authorizedRow->items));
        $this->assertSame($persistedItem->employee?->name, $authorizedRow->items[0]->employeeName);
        $this->assertSame('INR '.number_format((float) $persistedItem->net_payable, 2), $authorizedRow->items[0]->netPayable);

        $this->actingAs($payroll)
            ->get(route('payroll.runs.index', $filters))
            ->assertOk()
            ->assertSee('Employee line trace')
            ->assertSee($persistedItem->employee?->name)
            ->assertSee($authorizedRow->items[0]->netPayable)
            ->assertSee('people-payroll-trace-table', false);

        $company = Company::findOrFail($payroll->company_id);
        $unauthorized = $this->createUserWithPermissions($company, 'payroll_trace_hr_viewer', ['hr.view']);
        $restrictedRuns = $register->presentRuns($unauthorized, $register->runs($unauthorized, $filters));
        $restrictedRow = collect($restrictedRuns->items())->firstWhere('id', $run->id);

        $this->assertNotNull($restrictedRow);
        $this->assertFalse($restrictedRow->canViewCompensation);
        $this->assertSame([], $restrictedRow->items);
        $this->assertSame('Restricted', $restrictedRow->grossEarnings);
        $this->assertSame('Restricted', $restrictedRow->deductions);
        $this->assertSame('Restricted', $restrictedRow->netPayable);
    }

    public function test_payroll_workspace_renders_only_the_requested_register_and_rejects_unsupported_run_states(): void
    {
        $this->seed();

        $payroll = User::where('email', 'kavita.shah@builder360.test')->firstOrFail();

        $this->actingAs($payroll)
            ->get(route('payroll.runs.index'))
            ->assertOk()
            ->assertSee('Generate payroll run')
            ->assertDontSee('Salary master summary');

        $this->actingAs($payroll)
            ->get(route('payroll.salary-structures.index'))
            ->assertOk()
            ->assertSee('Salary master summary')
            ->assertDontSee('Generate payroll run');

        foreach (['draft', 'rejected'] as $unsupportedStatus) {
            $this->actingAs($payroll)
                ->getJson(route('payroll.runs.index', ['status' => $unsupportedStatus]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('status');
        }
    }

    public function test_payroll_user_can_generate_and_finance_can_approve_run_from_blade_workspace(): void
    {
        $this->seed();

        $payroll = User::where('email', 'kavita.shah@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $period = now()->addMonthsNoOverflow(10);

        $this->actingAs($payroll)
            ->post(route('payroll.runs.generate'), [
                'period_year' => $period->year,
                'period_month' => $period->month,
                'working_days' => 26,
            ])
            ->assertRedirect(route('payroll.runs.index'));

        $run = PayrollRun::query()
            ->where('period_year', $period->year)
            ->where('period_month', $period->month)
            ->where('generated_by_user_id', $payroll->id)
            ->firstOrFail();

        $this->assertSame('generated', $run->status);

        $this->actingAs($finance)
            ->patch(route('payroll.runs.approve', $run), [
                'note' => 'Approved through the native Blade payroll workspace.',
            ])
            ->assertRedirect(route('payroll.runs.index'));

        $run->refresh();

        $this->assertSame('approved', $run->status);
        $this->assertSame($finance->id, $run->approved_by_user_id);
        $this->assertSame('Approved through the native Blade payroll workspace.', $run->metadata['approval_note']);
    }

    public function test_payroll_approval_rejects_an_explicitly_stale_attendance_snapshot_for_a_legacy_run(): void
    {
        $this->seed();

        $payroll = User::where('email', 'kavita.shah@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $period = now()->addMonthsNoOverflow(18);

        $this->actingAs($payroll)
            ->postJson(route('payroll.runs.generate'), [
                'period_year' => $period->year,
                'period_month' => $period->month,
                'working_days' => 26,
            ])
            ->assertCreated();

        $run = PayrollRun::query()
            ->where('period_year', $period->year)
            ->where('period_month', $period->month)
            ->firstOrFail();
        $run->forceFill([
            'metadata' => array_merge($run->metadata ?? [], ['attendance_snapshot_stale' => true]),
        ])->save();

        $this->actingAs($finance)
            ->patchJson(route('payroll.runs.approve', $run), ['note' => 'This stale run must not be approved.'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payroll_run');

        $this->assertSame('generated', $run->fresh()->status);
    }

    public function test_payroll_user_can_list_components_and_salary_structures(): void
    {
        $this->seed();

        $payroll = User::where('email', 'kavita.shah@builder360.test')->firstOrFail();

        $this->actingAs($payroll)
            ->getJson(route('payroll.components.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 4)
            ->assertJsonPath('data.0.component_type', 'deduction');

        $this->actingAs($payroll)
            ->getJson(route('payroll.salary-structures.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.code', 'B360-M1')
            ->assertJsonCount(4, 'data.0.components');
    }

    public function test_payroll_indexes_validate_filters(): void
    {
        $this->seed();

        $payroll = User::where('email', 'kavita.shah@builder360.test')->firstOrFail();
        $period = now()->addMonthsNoOverflow(4);

        $this->actingAs($payroll)->postJson(route('payroll.runs.generate'), [
            'period_year' => $period->year,
            'period_month' => $period->month,
            'working_days' => 26,
        ])->assertCreated();

        $this->actingAs($payroll)
            ->getJson(route('payroll.components.index', ['component_type' => 'invalid']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['component_type']);

        $this->actingAs($payroll)
            ->getJson(route('payroll.components.index', ['page' => 1]))
            ->assertOk()
            ->assertJsonPath('meta.current_page', 1);

        $this->actingAs($payroll)
            ->getJson(route('payroll.components.index', ['status' => 'active']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status'])
            ->assertJsonPath('errors.status.0', 'The selected filter is not available for this endpoint.');

        $this->actingAs($payroll)
            ->getJson(route('payroll.components.index', ['per_page' => 500]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);

        $this->actingAs($payroll)
            ->getJson(route('payroll.salary-structures.index', ['page' => 1]))
            ->assertOk()
            ->assertJsonPath('meta.current_page', 1);

        $this->actingAs($payroll)
            ->getJson(route('payroll.salary-structures.index', ['status' => 'active']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status'])
            ->assertJsonPath('errors.status.0', 'The selected filter is not available for this endpoint.');

        $this->actingAs($payroll)
            ->getJson(route('payroll.salary-structures.index', ['per_page' => 500]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);

        $this->actingAs($payroll)
            ->getJson(route('payroll.runs.index', ['page' => 1]))
            ->assertOk()
            ->assertJsonPath('meta.current_page', 1);

        $this->actingAs($payroll)
            ->getJson(route('payroll.runs.index', ['employee_id' => 1]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['employee_id'])
            ->assertJsonPath('errors.employee_id.0', 'The selected filter is not available for this endpoint.');

        $this->actingAs($payroll)
            ->getJson(route('payroll.runs.index', ['period_month' => 13]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['period_month']);

        $this->actingAs($payroll)
            ->getJson(route('payroll.runs.index', ['period_year' => 1999]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['period_year']);

        $this->actingAs($payroll)
            ->getJson(route('payroll.runs.index', ['status' => 'invalid']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->actingAs($payroll)
            ->getJson(route('payroll.runs.index', [
                'period_year' => $period->year,
                'period_month' => $period->month,
                'status' => 'generated',
                'per_page' => 10,
            ]))
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->actingAs($payroll)
            ->getJson(route('payroll.components.index', [
                'component_type' => 'earning',
                'per_page' => 10,
            ]))
            ->assertOk()
            ->assertJsonPath('meta.total', 3);
    }

    public function test_payroll_generation_uses_configured_period_and_working_day_bounds(): void
    {
        $this->seed();

        Config::set('builder360.payroll.period_year_min', 2025);
        Config::set('builder360.payroll.period_year_max', 2026);
        Config::set('builder360.payroll.working_days_min', 20);
        Config::set('builder360.payroll.working_days_max', 26);

        $payroll = User::where('email', 'kavita.shah@builder360.test')->firstOrFail();

        $this->actingAs($payroll)->postJson(route('payroll.runs.generate'), [
            'period_year' => 2024,
            'period_month' => 1,
            'working_days' => 26,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('period_year');

        $this->actingAs($payroll)->postJson(route('payroll.runs.generate'), [
            'period_year' => 2026,
            'period_month' => 1,
            'working_days' => 27,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('working_days');
    }

    public function test_payroll_component_index_uses_configured_large_pagination_limit(): void
    {
        $this->seed();

        Config::set('builder360.pagination.large_max_per_page', 3);

        $payroll = User::where('email', 'kavita.shah@builder360.test')->firstOrFail();

        $this->actingAs($payroll)
            ->getJson(route('payroll.components.index', ['per_page' => 4]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);

        $this->actingAs($payroll)
            ->getJson(route('payroll.components.index', ['per_page' => 3]))
            ->assertOk()
            ->assertJsonPath('meta.per_page', 3);
    }

    public function test_non_global_payroll_users_without_company_assignment_fail_closed_for_runs(): void
    {
        $this->seed();

        $payroll = User::where('email', 'kavita.shah@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $period = now()->addMonthsNoOverflow(9);

        $runNumber = $this->actingAs($payroll)
            ->postJson(route('payroll.runs.generate'), [
                'period_year' => $period->year,
                'period_month' => $period->month,
                'working_days' => 26,
            ])
            ->assertCreated()
            ->json('data.run_number');

        $run = PayrollRun::where('run_number', $runNumber)->firstOrFail();

        $payroll->forceFill(['company_id' => null])->save();
        $finance->forceFill(['company_id' => null])->save();

        $this->actingAs($payroll)
            ->getJson(route('payroll.components.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($payroll)
            ->getJson(route('payroll.salary-structures.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($payroll)
            ->getJson(route('payroll.runs.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($payroll)
            ->postJson(route('payroll.runs.generate'), [
                'period_year' => now()->addMonthsNoOverflow(10)->year,
                'period_month' => now()->addMonthsNoOverflow(10)->month,
                'working_days' => 26,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('period_month');

        $this->actingAs($finance)
            ->patchJson(route('payroll.runs.approve', $run), [
                'note' => 'Should fail closed.',
            ])
            ->assertForbidden();
    }

    public function test_payroll_user_can_generate_run_and_finance_can_approve_it(): void
    {
        $this->seed();

        $payroll = User::where('email', 'kavita.shah@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $period = now()->addMonthNoOverflow();

        $response = $this->actingAs($payroll)->postJson(route('payroll.runs.generate'), [
            'period_year' => $period->year,
            'period_month' => $period->month,
            'working_days' => 26,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.status', 'generated')
            ->assertJsonPath('data.gross_earnings', 360000)
            ->assertJsonPath('data.total_deductions', 40000)
            ->assertJsonPath('data.net_payable', 320000)
            ->assertJsonCount(4, 'data.items');

        $run = PayrollRun::where('run_number', $response->json('data.run_number'))->firstOrFail();

        $this->assertDatabaseHas('payroll_run_items', [
            'payroll_run_id' => $run->id,
            'gross_earnings' => 90000,
            'total_deductions' => 10000,
            'net_payable' => 80000,
            'status' => 'generated',
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'payroll.run.generated',
            'action' => 'Generated payroll run',
            'user_id' => $payroll->id,
        ]);

        $this->actingAs($finance)
            ->patchJson(route('payroll.runs.approve', $run), [
                'note' => str_repeat('x', 1001),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('note');

        $this->actingAs($finance)
            ->patchJson(route('payroll.runs.approve', $run), [
                'note' => 'Approved after payroll variance review.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.approved_by.email', 'suresh.iyer@builder360.test');

        $this->assertDatabaseHas('payroll_runs', [
            'id' => $run->id,
            'status' => 'approved',
            'approved_by_user_id' => $finance->id,
        ]);

        $run->refresh();

        $this->assertSame('Approved after payroll variance review.', $run->metadata['approval_note']);

        $this->assertDatabaseHas('payroll_run_items', [
            'payroll_run_id' => $run->id,
            'status' => 'approved',
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'payroll.run.approved',
            'action' => 'Approved payroll run',
            'user_id' => $finance->id,
            'metadata->note' => 'Approved after payroll variance review.',
        ]);
    }

    public function test_payroll_generation_rejects_duplicate_period(): void
    {
        $this->seed();

        $payroll = User::where('email', 'kavita.shah@builder360.test')->firstOrFail();
        $period = now()->addMonthsNoOverflow(2);

        $payload = [
            'period_year' => $period->year,
            'period_month' => $period->month,
            'working_days' => 26,
        ];

        $this->actingAs($payroll)->postJson(route('payroll.runs.generate'), $payload)->assertCreated();

        $this->actingAs($payroll)->postJson(route('payroll.runs.generate'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('period_month');
    }

    public function test_payroll_generator_cannot_approve_own_run(): void
    {
        $this->seed();

        $payroll = User::where('email', 'kavita.shah@builder360.test')->firstOrFail();
        $period = now()->addMonthsNoOverflow(3);

        $runNumber = $this->actingAs($payroll)->postJson(route('payroll.runs.generate'), [
            'period_year' => $period->year,
            'period_month' => $period->month,
            'working_days' => 26,
        ])->assertCreated()->json('data.run_number');

        $run = PayrollRun::where('run_number', $runNumber)->firstOrFail();

        $this->actingAs($payroll)
            ->patchJson(route('payroll.runs.approve', $run))
            ->assertForbidden();
    }

    public function test_partner_cannot_access_internal_payroll_routes(): void
    {
        $this->seed();

        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();

        $this->actingAs($partner)
            ->getJson(route('payroll.runs.index'))
            ->assertForbidden();

        $this->actingAs($partner)->postJson(route('payroll.runs.generate'), [
            'period_year' => now()->year,
            'period_month' => now()->month,
            'working_days' => 26,
        ])->assertForbidden();
    }

    /** @param list<string> $permissions */
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
