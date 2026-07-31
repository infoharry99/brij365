<?php

namespace Tests\Feature;

use App\Domain\Payroll\Data\StatutoryPayrollCutoverManifest;
use App\Domain\Payroll\Services\StatutoryRulePackDefinitionValidator;
use App\Domain\Payroll\Services\StatutoryRulePackResolver;
use App\Models\AttendancePeriodLock;
use App\Models\Company;
use App\Models\Employee;
use App\Models\PayrollAttendanceSnapshot;
use App\Models\PayrollCalculationLine;
use App\Models\PayrollCalculationSnapshot;
use App\Models\PayrollRun;
use App\Models\Role;
use App\Models\SalaryAssignment;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class GovernedStatutoryPayrollTest extends TestCase
{
    use RefreshDatabase;

    public function test_governed_pack_accepts_the_explicit_maharashtra_labour_welfare_board_host(): void
    {
        $definition = $this->definition(now());
        $definition['source_evidence'][0]['url'] = 'https://www.mlwb.in/official-controlled-source-fixture';

        app(StatutoryRulePackDefinitionValidator::class)->assertValid($definition);

        $this->addToAssertionCount(1);
    }

    public function test_governed_pack_rejects_lookalike_labour_welfare_board_hosts(): void
    {
        $definition = $this->definition(now());
        $definition['source_evidence'][0]['url'] = 'https://fake-mlwb.in/official-controlled-source-fixture';

        try {
            app(StatutoryRulePackDefinitionValidator::class)->assertValid($definition);
            $this->fail('A lookalike statutory authority host was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('value.source_evidence.0.url', $exception->errors());
        }
    }

    public function test_governed_pack_requires_independent_source_verification_and_approval(): void
    {
        $this->seed();

        $creator = User::where('email', 'meera.kapoor@builder360.test')->firstOrFail();
        $company = Company::where('code', 'B360D')->firstOrFail();
        $verifier = $this->createVerifier($company);
        $approver = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $effective = now()->addMonthNoOverflow()->startOfMonth();
        $draft = $this->createDraft($creator, $company, $effective);

        $this->actingAs($creator)
            ->patchJson(route('hr.compliance-rule-settings.verify', $draft), [
                'attestation' => 'I independently checked the captured official-source evidence and its SHA-256 checksum.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('setting');

        $this->actingAs($verifier)
            ->patchJson(route('hr.compliance-rule-settings.verify', $draft), [
                'attestation' => 'I independently checked the captured official-source evidence and its SHA-256 checksum.',
            ])
            ->assertOk()
            ->assertJsonPath('data.statutory_verification.verified_by.id', $verifier->id)
            ->assertJsonPath('data.statutory_verification.configuration_checksum', fn (string $checksum): bool => strlen($checksum) === 64);

        $this->actingAs($verifier)
            ->patchJson(route('hr.compliance-rule-settings.approve', $draft), ['note' => 'A verifier cannot approve the same version.'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('setting');

        $this->actingAs($approver)
            ->patchJson(route('hr.compliance-rule-settings.approve', $draft), ['note' => 'Independent maker-checker approval for the controlled test pack.'])
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.approved_by.id', $approver->id)
            ->assertJsonPath('data.statutory_verification.verified_by.id', $verifier->id);

        $this->assertDatabaseHas('statutory_rule_verifications', [
            'system_setting_id' => $draft->id,
            'verified_by_user_id' => $verifier->id,
        ]);
    }

    public function test_simulation_is_deterministic_and_does_not_create_payroll_records(): void
    {
        $this->seed();

        $creator = User::where('email', 'meera.kapoor@builder360.test')->firstOrFail();
        $payroll = User::where('email', 'kavita.shah@builder360.test')->firstOrFail();
        $company = Company::where('code', 'B360D')->firstOrFail();
        $draft = $this->createDraft($creator, $company, now()->startOfMonth());
        $beforeRuns = PayrollRun::count();
        $beforeSnapshots = PayrollCalculationSnapshot::count();
        $payload = [
            'statutory_state' => 'MH',
            'components' => ['BASIC' => 100_005],
        ];

        $first = $this->actingAs($payroll)
            ->postJson(route('hr.compliance-rule-settings.simulate', $draft), $payload)
            ->assertOk()
            ->assertJsonPath('data.authoritative', false)
            ->assertJsonPath('data.mutated_records', 0)
            ->assertJsonPath('data.gross_minor', 100_005)
            ->assertJsonPath('data.deduction_minor', 10_001)
            ->assertJsonPath('data.net_minor', 90_004)
            ->json('data');

        $second = $this->actingAs($payroll)
            ->postJson(route('hr.compliance-rule-settings.simulate', $draft), $payload)
            ->assertOk()
            ->json('data');

        $this->assertSame($first['input_hash'], $second['input_hash']);
        $this->assertSame($first['result_hash'], $second['result_hash']);
        $this->assertSame($beforeRuns, PayrollRun::count());
        $this->assertSame($beforeSnapshots, PayrollCalculationSnapshot::count());
    }

    public function test_unverified_active_pack_is_rejected_by_the_authoritative_payroll_resolver(): void
    {
        $this->seed();

        $creator = User::where('email', 'meera.kapoor@builder360.test')->firstOrFail();
        $approver = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $company = Company::where('code', 'B360D')->firstOrFail();
        $effective = now()->startOfMonth();
        $pack = $this->createDraft($creator, $company, $effective);
        $pack->forceFill([
            'status' => 'active',
            'approved_by_user_id' => $approver->id,
            'approved_at' => now(),
        ])->save();
        $beforeRuns = PayrollRun::count();

        try {
            $this->app->make(StatutoryRulePackResolver::class)
                ->resolve($company->id, 'MH', $effective);
            $this->fail('An unverified statutory pack was accepted by the authoritative resolver.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['Governed statutory pack hr.statutory.pf is active without a current independent verification.'],
                $exception->errors()['statutory_rules'] ?? [],
            );
        }

        $this->assertSame($beforeRuns, PayrollRun::count());
    }

    public function test_payroll_user_can_run_non_authoritative_simulation_from_logic_center(): void
    {
        $this->seed();

        $creator = User::where('email', 'meera.kapoor@builder360.test')->firstOrFail();
        $payroll = User::where('email', 'kavita.shah@builder360.test')->firstOrFail();
        $company = Company::where('code', 'B360D')->firstOrFail();
        $draft = $this->createDraft($creator, $company, now()->startOfMonth());
        $beforeRuns = PayrollRun::count();

        $this->followingRedirects()
            ->actingAs($payroll)
            ->post(route('hr.compliance-rule-settings.simulate', $draft), [
                'return_to' => 'logic_center',
                'statutory_state' => 'MH',
                'component_codes' => ['BASIC', null],
                'component_amounts' => ['1000.05', null],
            ])
            ->assertOk()
            ->assertSee('Non-authoritative statutory simulation')
            ->assertSee('This result cannot affect payroll.')
            ->assertSee('900.04')
            ->assertSee('Controlled governed payroll test pack');

        $this->assertSame($beforeRuns, PayrollRun::count());
    }

    public function test_html_simulation_rejects_duplicate_component_codes(): void
    {
        $this->seed();

        $creator = User::where('email', 'meera.kapoor@builder360.test')->firstOrFail();
        $payroll = User::where('email', 'kavita.shah@builder360.test')->firstOrFail();
        $company = Company::where('code', 'B360D')->firstOrFail();
        $draft = $this->createDraft($creator, $company, now()->startOfMonth());

        $this->from(route('scoring.index', ['view' => 'simulation']))
            ->actingAs($payroll)
            ->post(route('hr.compliance-rule-settings.simulate', $draft), [
                'return_to' => 'logic_center',
                'statutory_state' => 'MH',
                'component_codes' => ['BASIC', 'basic'],
                'component_amounts' => ['1000.00', '500.00'],
            ])
            ->assertRedirect(route('scoring.index', ['view' => 'simulation']))
            ->assertSessionHasErrors('component_codes');
    }

    public function test_population_scoped_simulation_requires_context_and_applies_only_to_matching_employees(): void
    {
        $this->seed();

        $creator = User::where('email', 'meera.kapoor@builder360.test')->firstOrFail();
        $payroll = User::where('email', 'kavita.shah@builder360.test')->firstOrFail();
        $company = Company::where('code', 'B360D')->firstOrFail();
        $draft = $this->createDraft($creator, $company, now()->startOfMonth());
        $definition = $draft->value;
        data_set($definition, 'jurisdictions.0.applicability.employment_types', ['permanent']);
        $draft->forceFill(['value' => $definition])->save();
        $payload = [
            'statutory_state' => 'MH',
            'components' => ['BASIC' => 100_000],
        ];

        $this->actingAs($payroll)
            ->postJson(route('hr.compliance-rule-settings.simulate', $draft), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('employee_context.employment_type');

        $this->actingAs($payroll)
            ->postJson(route('hr.compliance-rule-settings.simulate', $draft), $payload + [
                'employee_context' => ['employment_type' => 'contract'],
            ])
            ->assertOk()
            ->assertJsonPath('data.deduction_minor', 0)
            ->assertJsonPath('data.net_minor', 100_000);

        $this->actingAs($payroll)
            ->postJson(route('hr.compliance-rule-settings.simulate', $draft), $payload + [
                'employee_context' => ['employment_type' => 'Permanent'],
            ])
            ->assertOk()
            ->assertJsonPath('data.deduction_minor', 10_000)
            ->assertJsonPath('data.net_minor', 90_000);
    }

    public function test_governed_generation_requires_finalized_attendance_and_persists_version_pinned_trace(): void
    {
        $this->seed();

        $creator = User::where('email', 'meera.kapoor@builder360.test')->firstOrFail();
        $company = Company::where('code', 'B360D')->firstOrFail();
        $verifier = $this->createVerifier($company);
        $approver = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $payroll = User::where('email', 'kavita.shah@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $period = now()->addMonthsNoOverflow(14)->startOfMonth();
        $draft = $this->createDraft($creator, $company, $period);

        $this->actingAs($verifier)->patchJson(route('hr.compliance-rule-settings.verify', $draft), [
            'attestation' => 'I independently checked the captured official-source evidence and its SHA-256 checksum.',
        ])->assertOk();
        $this->actingAs($approver)->patchJson(route('hr.compliance-rule-settings.approve', $draft), [
            'note' => 'Independent maker-checker approval for the controlled test pack.',
        ])->assertOk();

        $assignments = SalaryAssignment::query()
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->get();
        Employee::query()->whereIn('id', $assignments->pluck('employee_id'))->update(['statutory_state' => 'MH']);

        $payload = ['period_year' => $period->year, 'period_month' => $period->month, 'working_days' => 26];
        $this->actingAs($payroll)
            ->postJson(route('payroll.runs.generate'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('attendance');

        $periodEnd = $period->copy()->endOfMonth();
        $lock = AttendancePeriodLock::create([
            'company_id' => $company->id,
            'period_start' => $period->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'version' => 1,
            'status' => 'finalized',
            'finalized_by_user_id' => $creator->id,
            'finalized_at' => now(),
            'source_hash' => hash('sha256', 'controlled-finalized-attendance-period'),
            'lock_version' => 1,
        ]);

        foreach ($assignments as $assignment) {
            PayrollAttendanceSnapshot::create([
                'attendance_period_lock_id' => $lock->id,
                'company_id' => $company->id,
                'employee_id' => $assignment->employee_id,
                'period_start' => $period->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'scheduled_days' => 26,
                'present_days' => 25,
                'paid_leave_days' => 0,
                'unpaid_days' => 1,
                'worked_minutes' => 12_000,
                'payable_days' => '25.50',
                'source_hash' => hash('sha256', 'attendance-'.$assignment->employee_id),
                'calculation_trace' => ['fixture' => 'controlled governed payroll test'],
            ]);
        }

        $this->actingAs($payroll)
            ->postJson(route('payroll.runs.generate'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('statutory_rules');

        $this->assertDatabaseMissing('payroll_runs', [
            'company_id' => $company->id,
            'period_year' => $period->year,
            'period_month' => $period->month,
        ]);

        SystemSetting::create([
            'company_id' => $company->id,
            'created_by_user_id' => $creator->id,
            'approved_by_user_id' => $approver->id,
            'scope_key' => 'company:'.$company->id,
            'setting_group' => 'payroll',
            'setting_key' => StatutoryPayrollCutoverManifest::SETTING_KEY,
            'label' => 'Controlled hybrid payroll cutover',
            'value_type' => 'object',
            'value' => [
                'schema_version' => 1,
                'mode' => 'hybrid',
                'required_packs' => [[
                    'setting_key' => 'hr.statutory.pf',
                    'state_codes' => [],
                    'replaces_component_codes' => ['PF'],
                ]],
            ],
            'status' => 'active',
            'version' => 1,
            'effective_from' => $period->toDateString(),
            'approved_at' => now(),
            'workflow_history' => [],
            'metadata' => ['fixture' => 'controlled_hybrid_overlap_replacement'],
        ]);

        $this->actingAs($payroll)
            ->postJson(route('payroll.runs.generate'), $payload)
            ->assertCreated()
            ->assertJsonPath('data.status', 'generated');

        $run = PayrollRun::query()
            ->with(['items.calculationSnapshot.lines'])
            ->where('company_id', $company->id)
            ->where('period_year', $period->year)
            ->where('period_month', $period->month)
            ->firstOrFail();

        $this->assertSame('governed_verified', $run->metadata['calculation_mode']);
        $this->assertSame($lock->id, $run->metadata['attendance_period_lock_id']);
        $this->assertCount($assignments->count(), $run->items);
        $this->assertSame($run->items->count(), PayrollCalculationSnapshot::count());

        foreach ($run->items as $item) {
            $snapshot = $item->calculationSnapshot;
            $this->assertNotNull($snapshot);
            $this->assertFalse(collect($item->component_breakup)->contains(
                fn (array $component): bool => ($component['component_code'] ?? null) === 'PF'
                    && ($component['source'] ?? null) === 'salary_structure',
            ), 'An explicitly replaced legacy statutory component must not be calculated beside its governed pack.');
            $this->assertSame('25.50', (string) $item->payable_days);
            $this->assertSame('governed_verified', $snapshot->rule_context['mode']);
            $this->assertContains('PF', data_get($snapshot->rule_context, 'replaced_legacy_component_codes', []));
            $this->assertSame(
                hash('sha256', 'controlled-source-evidence-fixture'),
                data_get($snapshot->rule_context, 'settings.0.source_evidence.0.source_checksum'),
            );
            $this->assertSame(64, strlen($snapshot->input_hash));
            $this->assertSame(64, strlen($snapshot->result_hash));
            $this->assertTrue($snapshot->lines->contains(fn (PayrollCalculationLine $line): bool => $line->system_setting_id === $draft->id));
        }

        $lock->forceFill(['status' => 'reopened', 'reopened_by_user_id' => $creator->id, 'reopened_at' => now(), 'reopen_reason' => 'Controlled approval guard test.'])->save();
        $this->actingAs($finance)
            ->patchJson(route('payroll.runs.approve', $run), ['note' => 'Must fail while attendance is reopened.'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payroll_run');

        $lock->forceFill(['status' => 'finalized', 'reopened_by_user_id' => null, 'reopened_at' => null, 'reopen_reason' => null])->save();

        $governedLine = PayrollCalculationLine::query()
            ->whereNotNull('system_setting_id')
            ->firstOrFail();
        $originalAmount = $governedLine->amount_minor;
        DB::table($governedLine->getTable())
            ->where('id', $governedLine->id)
            ->update(['amount_minor' => $originalAmount + 1]);

        $this->actingAs($finance)
            ->patchJson(route('payroll.runs.approve', $run), ['note' => 'Must reject a tampered calculation line.'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payroll_run');

        DB::table($governedLine->getTable())
            ->where('id', $governedLine->id)
            ->update(['amount_minor' => $originalAmount]);

        $this->actingAs($finance)
            ->patchJson(route('payroll.runs.approve', $run), ['note' => 'Approved after the finalized attendance guard passed.'])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');
    }

    public function test_governed_required_cutover_fails_closed_when_a_required_verified_pack_is_missing(): void
    {
        $this->seed();

        $company = Company::where('code', 'B360D')->firstOrFail();
        $creator = User::where('email', 'meera.kapoor@builder360.test')->firstOrFail();
        $approver = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $payroll = User::where('email', 'kavita.shah@builder360.test')->firstOrFail();
        $period = now()->addMonthsNoOverflow(19)->startOfMonth();

        SystemSetting::create([
            'company_id' => $company->id,
            'created_by_user_id' => $creator->id,
            'approved_by_user_id' => $approver->id,
            'scope_key' => 'company:'.$company->id,
            'setting_group' => 'payroll',
            'setting_key' => StatutoryPayrollCutoverManifest::SETTING_KEY,
            'label' => 'Controlled governed-required payroll cutover',
            'value_type' => 'object',
            'value' => [
                'schema_version' => 1,
                'mode' => 'governed_required',
                'required_packs' => [[
                    'setting_key' => 'hr.statutory.pf',
                    'state_codes' => [],
                    'replaces_component_codes' => ['PF'],
                ]],
            ],
            'status' => 'active',
            'version' => 1,
            'effective_from' => $period->toDateString(),
            'approved_at' => now(),
            'workflow_history' => [],
            'metadata' => ['fixture' => 'governed_required_missing_pack'],
        ]);

        $assignments = SalaryAssignment::query()
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->get();
        Employee::query()->whereIn('id', $assignments->pluck('employee_id'))->update(['statutory_state' => 'MH']);

        $this->actingAs($payroll)
            ->postJson(route('payroll.runs.generate'), [
                'period_year' => $period->year,
                'period_month' => $period->month,
                'working_days' => 26,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('statutory_rules');

        $this->assertDatabaseMissing('payroll_runs', [
            'company_id' => $company->id,
            'period_year' => $period->year,
            'period_month' => $period->month,
        ]);
    }

    public function test_finalized_payroll_attendance_snapshots_cannot_be_updated_or_deleted(): void
    {
        $this->seed();

        $company = Company::where('code', 'B360D')->firstOrFail();
        $employee = Employee::where('company_id', $company->id)->firstOrFail();
        $actor = User::where('email', 'meera.kapoor@builder360.test')->firstOrFail();
        $period = now()->addYears(3)->startOfMonth();
        $lock = AttendancePeriodLock::create([
            'company_id' => $company->id,
            'period_start' => $period->toDateString(),
            'period_end' => $period->copy()->endOfMonth()->toDateString(),
            'version' => 1,
            'status' => 'finalized',
            'finalized_by_user_id' => $actor->id,
            'finalized_at' => now(),
            'source_hash' => hash('sha256', 'immutable-attendance-period'),
            'lock_version' => 1,
        ]);
        $snapshot = PayrollAttendanceSnapshot::create([
            'attendance_period_lock_id' => $lock->id,
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'period_start' => $period->toDateString(),
            'period_end' => $period->copy()->endOfMonth()->toDateString(),
            'scheduled_days' => 26,
            'present_days' => 26,
            'paid_leave_days' => 0,
            'unpaid_days' => 0,
            'worked_minutes' => 12_480,
            'payable_days' => '26.00',
            'source_hash' => hash('sha256', 'immutable-attendance-snapshot'),
            'calculation_trace' => ['fixture' => 'immutable snapshot regression'],
        ]);

        try {
            $snapshot->forceFill(['payable_days' => '25.00'])->save();
            $this->fail('A finalized payroll attendance snapshot was updated.');
        } catch (\LogicException $exception) {
            $this->assertSame('Payroll attendance snapshots are immutable.', $exception->getMessage());
        }

        try {
            $snapshot->delete();
            $this->fail('A finalized payroll attendance snapshot was deleted.');
        } catch (\LogicException $exception) {
            $this->assertSame('Payroll attendance snapshots are immutable.', $exception->getMessage());
        }

        $this->assertDatabaseHas('payroll_attendance_snapshots', [
            'id' => $snapshot->id,
            'payable_days' => 26,
        ]);
    }

    private function createDraft(User $creator, Company $company, Carbon $effective): SystemSetting
    {
        $id = $this->actingAs($creator)
            ->postJson(route('hr.compliance-rule-settings.store'), [
                'company_id' => $company->id,
                'setting_key' => 'hr.statutory.pf',
                'label' => 'Controlled governed payroll test pack',
                'description' => 'Test-only deterministic rule definition; it is not an asserted statutory rate.',
                'effective_from' => $effective->toDateString(),
                'value' => $this->definition($effective),
                'metadata' => ['fixture' => 'governed_statutory_payroll_test'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->json('data.id');

        return SystemSetting::findOrFail($id);
    }

    private function createVerifier(Company $company): User
    {
        $role = Role::create([
            'slug' => 'independent_statutory_verifier',
            'name' => 'Independent Statutory Verifier',
            'scope_level' => 'company',
            'permissions' => ['scoring.statutory.verify', 'scoring.statutory.approve'],
            'is_active' => true,
        ]);

        return User::factory()->create([
            'role_id' => $role->id,
            'company_id' => $company->id,
            'email' => fake()->unique()->safeEmail(),
            'status' => 'active',
        ]);
    }

    /** @return array<string, mixed> */
    private function definition(Carbon $effective): array
    {
        return [
            'governed_statutory_pack_version' => 1,
            'statutory_validation_required' => true,
            'approval_chain' => ['independent_source_verifier', 'independent_rule_approver'],
            'source_evidence' => [[
                'authority' => 'Controlled Government Source Test Fixture',
                'title' => 'Synthetic source descriptor for governance regression testing',
                'document_reference' => 'TEST-ONLY-NOT-A-STATUTORY-CITATION',
                'source_type' => 'official_government',
                'url' => 'https://labour.gov.in/test-only-governance-fixture',
                'source_checksum' => hash('sha256', 'controlled-source-evidence-fixture'),
                'published_or_accessed_on' => now()->toDateString(),
            ]],
            'attendance_proration' => ['enabled' => true, 'component_codes' => ['BASIC']],
            'jurisdictions' => [[
                'type' => 'central',
                'code' => 'INDIA',
                'state_resolution' => 'allow_no_match',
                'effective_from' => $effective->toDateString(),
                'effective_to' => null,
                'applicability' => [],
                'lines' => [[
                    'code' => 'CONTROLLED_TEST_DEDUCTION',
                    'name' => 'Controlled deterministic test deduction',
                    'line_type' => 'deduction',
                    'method' => 'rate_ppm',
                    'basis_codes' => ['BASIC'],
                    'rate_ppm' => 100_000,
                ]],
            ]],
        ];
    }
}
