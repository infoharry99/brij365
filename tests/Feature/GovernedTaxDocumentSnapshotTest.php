<?php

namespace Tests\Feature;

use App\Domain\Payroll\Data\StatutoryPayrollCutoverManifest;
use App\Domain\Payroll\Services\CanonicalPayrollHasher;
use App\Models\AttendancePeriodLock;
use App\Models\Employee;
use App\Models\EmployeeTaxDocument;
use App\Models\PayrollAttendanceSnapshot;
use App\Models\PayrollCalculationSnapshot;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\SalaryAssignment;
use App\Models\StatutoryRuleVerification;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GovernedTaxDocumentSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_governed_required_form_16_uses_canonically_verified_snapshots_and_exact_tax_rule_provenance(): void
    {
        $this->seed();

        $payroll = User::where('email', 'kavita.shah@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();
        $taxSetting = $this->governedTaxSetting('2026-2027', true, true);
        [$run, $snapshot] = $this->governedApprovedRun($employee, $payroll, $taxSetting);

        $firstId = $this->actingAs($payroll)
            ->postJson(route('payroll.tax-documents.store'), [
                'employee_id' => $employee->id,
                'financial_year' => '2026-2027',
            ])
            ->assertCreated()
            ->assertJsonPath('data.gross_salary', 1000.01)
            ->assertJsonPath('data.taxable_income', 900.01)
            ->assertJsonPath('data.tds_deducted', 123.45)
            ->assertJsonPath('data.net_salary_paid', 876.56)
            ->assertJsonPath('data.payroll_run_ids.0', $run->id)
            ->assertJsonPath('data.tax_configuration_snapshot.calculation_mode', 'governed_required')
            ->assertJsonPath('data.tax_configuration_snapshot.form16_template_status', 'approved')
            ->assertJsonPath('data.tax_configuration_snapshot.legal_template_approved', true)
            ->assertJsonPath('data.tax_configuration_snapshot.payroll_calculation_provenance.taxable_income_method', 'pinned_tax_line_basis')
            ->assertJsonPath('data.tax_configuration_snapshot.payroll_calculation_provenance.snapshots.0.snapshot_id', $snapshot->id)
            ->assertJsonPath('data.tax_configuration_snapshot.payroll_calculation_provenance.snapshots.0.input_hash', $snapshot->input_hash)
            ->assertJsonPath('data.tax_configuration_snapshot.payroll_calculation_provenance.snapshots.0.settings.0.setting_key', 'payroll.tax_rules')
            ->json('data.id');

        $first = EmployeeTaxDocument::findOrFail($firstId);
        $firstPayload = $first->document_payload;

        $secondId = $this->actingAs($payroll)
            ->postJson(route('payroll.tax-documents.store'), [
                'employee_id' => $employee->id,
                'financial_year' => '2026-2027',
                'force_new_version' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.version', 2)
            ->json('data.id');

        $second = EmployeeTaxDocument::findOrFail($secondId);
        $this->assertSame($first->gross_salary, $second->gross_salary);
        $this->assertSame($first->taxable_income, $second->taxable_income);
        $this->assertSame($first->tds_deducted, $second->tds_deducted);
        $this->assertSame($first->net_salary_paid, $second->net_salary_paid);
        $this->assertSame(
            data_get($firstPayload, 'tax_setting.payroll_calculation_provenance'),
            data_get($second->document_payload, 'tax_setting.payroll_calculation_provenance'),
        );

        $this->actingAs($finance)
            ->patchJson(route('payroll.tax-documents.issue', $first), [
                'issue_reference' => 'GOVERNED-FORM16-APPROVED',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'issued');
    }

    public function test_governed_required_form_16_fails_closed_when_the_active_tax_rule_lacks_official_evidence(): void
    {
        $this->seed();

        $payroll = User::where('email', 'kavita.shah@builder360.test')->firstOrFail();
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();
        $taxSetting = $this->governedTaxSetting('2026-2027', false, true);
        [, $snapshot] = $this->governedApprovedRun($employee, $payroll, $taxSetting);

        $this->actingAs($payroll)
            ->postJson(route('payroll.tax-documents.store'), [
                'employee_id' => $employee->id,
                'financial_year' => '2026-2027',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('financial_year')
            ->assertJsonPath(
                'errors.financial_year.0',
                'Governed-required Form 16 generation requires a complete, independently verifiable payroll tax rule definition.',
            );

        $this->assertDatabaseMissing('employee_tax_documents', [
            'employee_id' => $employee->id,
            'financial_year' => '2026-2027',
        ]);
        $this->assertDatabaseHas('payroll_calculation_snapshots', ['id' => $snapshot->id]);
    }

    public function test_governed_required_form_16_rejects_database_tampering_in_a_calculation_line(): void
    {
        $this->seed();

        $payroll = User::where('email', 'kavita.shah@builder360.test')->firstOrFail();
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();
        $taxSetting = $this->governedTaxSetting('2026-2027', true, true);
        [, $snapshot] = $this->governedApprovedRun($employee, $payroll, $taxSetting);

        DB::table('payroll_calculation_lines')
            ->where('payroll_calculation_snapshot_id', $snapshot->id)
            ->where('system_setting_id', $taxSetting->id)
            ->update(['amount_minor' => 12_346]);

        $this->actingAs($payroll)
            ->postJson(route('payroll.tax-documents.store'), [
                'employee_id' => $employee->id,
                'financial_year' => '2026-2027',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('financial_year');

        $this->assertDatabaseMissing('employee_tax_documents', [
            'employee_id' => $employee->id,
            'financial_year' => '2026-2027',
        ]);
    }

    public function test_governed_required_form_16_rejects_an_active_tax_rule_that_differs_from_the_snapshot_pin(): void
    {
        $this->seed();

        $payroll = User::where('email', 'kavita.shah@builder360.test')->firstOrFail();
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();
        $pinnedSetting = $this->governedTaxSetting('2026-2027', true, true);
        $this->governedApprovedRun($employee, $payroll, $pinnedSetting);
        $this->governedTaxSetting('2026-2027', true, true, 2);

        $this->actingAs($payroll)
            ->postJson(route('payroll.tax-documents.store'), [
                'employee_id' => $employee->id,
                'financial_year' => '2026-2027',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('financial_year')
            ->assertJsonPath(
                'errors.financial_year.0',
                'Every governed payroll snapshot must pin the exact active payroll tax rule version used for Form 16 generation.',
            );
    }

    public function test_governed_required_form_16_with_a_prototype_template_can_be_generated_but_not_issued(): void
    {
        $this->seed();

        $payroll = User::where('email', 'kavita.shah@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();
        $taxSetting = $this->governedTaxSetting('2026-2027', true, false);
        $this->governedApprovedRun($employee, $payroll, $taxSetting);

        $documentId = $this->actingAs($payroll)
            ->postJson(route('payroll.tax-documents.store'), [
                'employee_id' => $employee->id,
                'financial_year' => '2026-2027',
            ])
            ->assertCreated()
            ->assertJsonPath('data.tax_configuration_snapshot.form16_template_status', 'legal_review_pending')
            ->assertJsonPath('data.tax_configuration_snapshot.is_prototype', true)
            ->json('data.id');

        $this->actingAs($finance)
            ->patchJson(route('payroll.tax-documents.issue', EmployeeTaxDocument::findOrFail($documentId)), [
                'issue_reference' => 'MUST-NOT-ISSUE',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tax_document');

        $this->assertDatabaseHas('employee_tax_documents', [
            'id' => $documentId,
            'status' => 'generated',
        ]);
    }

    private function governedTaxSetting(
        string $financialYear,
        bool $withEvidence,
        bool $templateApproved,
        int $version = 1,
    ): SystemSetting {
        $base = SystemSetting::where('setting_key', 'payroll.tax_rules')->orderBy('version')->firstOrFail();
        $evidence = $withEvidence ? [[
            'authority' => 'Controlled Government Source Test Fixture',
            'title' => 'Synthetic source descriptor for governance regression testing',
            'document_reference' => 'TEST-ONLY-NOT-A-STATUTORY-CITATION',
            'source_type' => 'official_government',
            'url' => 'https://incometax.gov.in/test-only-governance-fixture',
            'source_checksum' => hash('sha256', 'governed-tax-document-source-v'.$version),
            'published_or_accessed_on' => '2026-07-01',
        ]] : [];
        $value = [
            'governed_statutory_pack_version' => 1,
            'statutory_validation_required' => true,
            'approval_chain' => ['independent_source_verifier', 'independent_rule_approver'],
            'source_evidence' => $evidence,
            'attendance_proration' => ['enabled' => false, 'component_codes' => []],
            'jurisdictions' => [[
                'type' => 'central',
                'code' => 'INDIA',
                'state_resolution' => 'allow_no_match',
                'effective_from' => '2026-04-01',
                'effective_to' => '2027-03-31',
                'applicability' => [],
                'lines' => [[
                    'code' => 'INCOME_TAX',
                    'name' => 'Controlled income tax fixture',
                    'line_type' => 'tax_adjustment',
                    'method' => 'fixed_minor',
                    'fixed_minor' => 12_345,
                ]],
            ]],
            'financial_year' => $financialYear,
            'verified' => true,
            'payroll_year_locked' => true,
            'form16_template_version' => 'governed-v'.$version,
            'form16_template_status' => $templateApproved ? 'approved' : 'legal_review_pending',
            'legal_template_approved' => $templateApproved,
            'is_prototype' => ! $templateApproved,
            'tds_component_codes' => ['INCOME_TAX'],
            'standard_deduction_minor' => 5_000_000,
        ];

        if ($version === 1) {
            $setting = $base;
            $setting->forceFill(['value' => $value])->save();
        } else {
            $setting = SystemSetting::create([
                'company_id' => $base->company_id,
                'created_by_user_id' => $base->created_by_user_id,
                'approved_by_user_id' => $base->approved_by_user_id,
                'scope_key' => $base->scope_key,
                'setting_group' => $base->setting_group,
                'setting_key' => $base->setting_key,
                'label' => $base->label,
                'description' => $base->description,
                'value_type' => 'object',
                'value' => $value,
                'status' => 'active',
                'version' => $version,
                'effective_from' => '2026-04-01',
                'approved_at' => now(),
                'workflow_history' => [],
                'metadata' => ['fixture' => 'governed_tax_document_test'],
            ]);
        }

        StatutoryRuleVerification::updateOrCreate(
            ['system_setting_id' => $setting->id],
            [
                'company_id' => $setting->company_id,
                'verified_by_user_id' => User::where('email', 'meera.kapoor@builder360.test')->firstOrFail()->id,
                'configuration_checksum' => app(CanonicalPayrollHasher::class)->hash($value),
                'attestation' => 'Controlled independent verification fixture.',
                'verified_at' => now(),
            ],
        );

        return $setting->refresh();
    }

    /** @return array{0: PayrollRun, 1: PayrollCalculationSnapshot} */
    private function governedApprovedRun(Employee $employee, User $payroll, SystemSetting $taxSetting): array
    {
        $hasher = app(CanonicalPayrollHasher::class);
        $assignment = SalaryAssignment::query()
            ->where('employee_id', $employee->id)
            ->where('status', 'active')
            ->firstOrFail();
        $run = PayrollRun::create([
            'company_id' => $employee->company_id,
            'generated_by_user_id' => $payroll->id,
            'approved_by_user_id' => $payroll->id,
            'run_number' => 'GOV-TAX-2026-07',
            'period_year' => 2026,
            'period_month' => 7,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'working_days' => 26,
            'status' => 'approved',
            'gross_earnings' => '1000.01',
            'total_deductions' => '123.45',
            'net_payable' => '876.56',
            'metadata' => ['statutory_cutover_mode' => 'governed_required'],
            'approved_at' => now(),
        ]);
        $item = PayrollRunItem::create([
            'payroll_run_id' => $run->id,
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'salary_structure_id' => $assignment->salary_structure_id,
            'monthly_ctc' => '1000.01',
            'payable_days' => '26.00',
            'gross_earnings' => '1000.01',
            'total_deductions' => '123.45',
            'net_payable' => '876.56',
            'component_breakup' => [],
            'status' => 'approved',
        ]);

        $lock = AttendancePeriodLock::create([
            'company_id' => $employee->company_id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'version' => 1,
            'status' => 'finalized',
            'finalized_by_user_id' => $payroll->id,
            'finalized_at' => now(),
            'source_hash' => hash('sha256', 'governed-form16-attendance-period'),
            'lock_version' => 1,
        ]);
        $attendance = PayrollAttendanceSnapshot::create([
            'attendance_period_lock_id' => $lock->id,
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'scheduled_days' => 26,
            'present_days' => 26,
            'paid_leave_days' => 0,
            'unpaid_days' => 0,
            'worked_minutes' => 12_480,
            'payable_days' => '26.00',
            'source_hash' => hash('sha256', 'governed-form16-attendance-snapshot'),
            'calculation_trace' => ['fixture' => 'governed Form 16 canonical evidence'],
        ]);
        $manifestValue = [
            'schema_version' => 1,
            'mode' => 'governed_required',
            'required_packs' => [[
                'setting_key' => 'payroll.tax_rules',
                'state_codes' => [],
                'replaces_component_codes' => ['INCOME_TAX'],
            ]],
        ];
        $manifest = SystemSetting::create([
            'company_id' => $employee->company_id,
            'created_by_user_id' => $taxSetting->created_by_user_id,
            'approved_by_user_id' => $taxSetting->approved_by_user_id,
            'scope_key' => 'company:'.$employee->company_id,
            'setting_group' => 'payroll',
            'setting_key' => StatutoryPayrollCutoverManifest::SETTING_KEY,
            'label' => 'Governed Form 16 cutover fixture',
            'value_type' => 'object',
            'value' => $manifestValue,
            'status' => 'active',
            'version' => 1,
            'effective_from' => '2026-04-01',
            'approved_at' => now(),
            'workflow_history' => [],
            'metadata' => ['fixture' => 'governed_tax_document_test'],
        ]);
        $settingChecksum = $hasher->hash((array) $taxSetting->value);
        $ruleContext = [
            'mode' => 'governed_verified',
            'cutover_mode' => 'governed_required',
            'calculation_version' => 1,
            'setting_ids' => [$taxSetting->id],
            'replaced_legacy_component_codes' => ['INCOME_TAX'],
            'cutover_manifest' => [
                'setting_id' => $manifest->id,
                'version' => $manifest->version,
                'checksum' => $hasher->hash($manifestValue),
            ],
            'settings' => [[
                'setting_id' => $taxSetting->id,
                'setting_key' => 'payroll.tax_rules',
                'version' => $taxSetting->version,
                'checksum' => $settingChecksum,
                'jurisdiction_type' => 'central',
                'jurisdiction_code' => 'INDIA',
                'source_evidence' => data_get($taxSetting->value, 'source_evidence', []),
            ]],
        ];
        $input = [
            'employee_id' => $employee->id,
            'employee_context' => [
                'employment_type' => $employee->employment_type,
                'department' => $employee->department,
                'statutory_state' => $employee->statutory_state,
            ],
            'salary_assignment_id' => $assignment->id,
            'salary_structure_id' => $assignment->salary_structure_id,
            'component_minor' => ['BASIC' => 100_001],
            'commission_item_ids' => [],
            'attendance_snapshot' => [
                'id' => $attendance->id,
                'source_hash' => $attendance->source_hash,
                'payable_days' => (string) $attendance->payable_days,
                'scheduled_days' => $attendance->scheduled_days,
            ],
            'rule_context' => $ruleContext,
        ];
        $lines = [
            [
                'system_setting_id' => null,
                'component_code' => 'BASIC',
                'component_name' => 'Basic salary',
                'line_type' => 'earning',
                'amount_minor' => 100_001,
                'basis_minor' => 100_001,
                'rate_ppm' => null,
                'sort_order' => 0,
                'trace' => ['source' => 'salary_structure'],
            ],
            [
                'system_setting_id' => $taxSetting->id,
                'component_code' => 'INCOME_TAX',
                'component_name' => 'Controlled income tax fixture',
                'line_type' => 'tax_adjustment',
                'amount_minor' => 12_345,
                'basis_minor' => 90_001,
                'rate_ppm' => null,
                'sort_order' => 1,
                'trace' => [
                    'source' => 'verified_governed_statutory_pack',
                    'setting_id' => $taxSetting->id,
                    'setting_key' => 'payroll.tax_rules',
                    'setting_version' => $taxSetting->version,
                    'setting_checksum' => $settingChecksum,
                ],
            ],
        ];
        $inputHash = $hasher->hash($input);
        $resultHash = $hasher->hash([
            'gross_minor' => 100_001,
            'deduction_minor' => 12_345,
            'employer_contribution_minor' => 0,
            'net_minor' => 87_656,
            'lines' => $lines,
            'input_hash' => $inputHash,
        ]);
        $snapshot = PayrollCalculationSnapshot::create([
            'payroll_run_item_id' => $item->id,
            'payroll_attendance_snapshot_id' => $attendance->id,
            'salary_assignment_id' => $assignment->id,
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'created_by_user_id' => $payroll->id,
            'currency' => 'INR',
            'calculation_version' => 1,
            'gross_minor' => 100_001,
            'deduction_minor' => 12_345,
            'employer_contribution_minor' => 0,
            'net_minor' => 87_656,
            'input_hash' => $inputHash,
            'result_hash' => $resultHash,
            'rule_context' => $ruleContext,
            'input_snapshot' => $input,
            'calculation_trace' => [],
        ]);
        $snapshot->lines()->createMany($lines);

        return [$run->load('items'), $snapshot->load('lines')];
    }
}
