<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\GstEntry;
use App\Models\GstReturnPeriod;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceGstComplianceTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_can_create_gst_entry_compliance_approves_and_return_period_locks(): void
    {
        $this->seed();

        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $compliance = User::where('email', 'meera.kapoor@builder360.test')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();
        $period = now();

        $this->actingAs($finance)
            ->getJson(route('finance.gst-entries.index', [
                'period_year' => $period->year,
                'period_month' => $period->month,
            ]))
            ->assertOk()
            ->assertJsonPath('data.0.entry_number', 'GST-10001')
            ->assertJsonPath('data.0.transaction_type', 'output')
            ->assertJsonPath('data.0.total_tax_amount', 76271.19);

        $entryNumber = $this->actingAs($finance)
            ->postJson(route('finance.gst-entries.store'), [
                'project_id' => $project->id,
                'document_date' => $period->toDateString(),
                'document_number' => 'VEND-GST-1001',
                'party_name' => 'Precision Civil Contractors',
                'party_gstin' => '27AABCP9876H1Z7',
                'place_of_supply_state' => 'MH',
                'transaction_type' => 'input',
                'hsn_sac' => '9954',
                'tax_rate' => 18,
                'taxable_amount' => 100000,
                'cgst_amount' => 9000,
                'sgst_amount' => 9000,
                'igst_amount' => 0,
                'cess_amount' => 0,
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.total_tax_amount', 18000)
            ->json('data.entry_number');

        $entry = GstEntry::where('entry_number', $entryNumber)->firstOrFail();

        $this->actingAs($finance)
            ->patchJson(route('finance.gst-entries.approve', $entry), ['note' => 'Invalid self approval.'])
            ->assertForbidden();

        $this->actingAs($compliance)
            ->patchJson(route('finance.gst-entries.approve', $entry), ['note' => 'Input credit checked.'])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.approved_by.email', 'meera.kapoor@builder360.test');

        $returnId = $this->actingAs($finance)
            ->postJson(route('finance.gst-return-periods.store'), [
                'period_year' => $period->year,
                'period_month' => $period->month,
                'note' => 'Prepare current month GST return.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'prepared')
            ->assertJsonPath('data.entry_count', 2)
            ->assertJsonPath('data.output_tax_total', 76271.19)
            ->assertJsonPath('data.input_tax_credit_total', 18000)
            ->assertJsonPath('data.net_tax_payable', 58271.19)
            ->json('data.id');

        $returnPeriod = GstReturnPeriod::findOrFail($returnId);

        $this->actingAs($finance)
            ->patchJson(route('finance.gst-return-periods.approve', $returnPeriod), ['note' => 'Invalid self approval.'])
            ->assertForbidden();

        $this->actingAs($compliance)
            ->patchJson(route('finance.gst-return-periods.approve', $returnPeriod), ['note' => 'Approved by compliance.'])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $returnPeriod->refresh();

        $this->actingAs($compliance)
            ->patchJson(route('finance.gst-return-periods.lock', $returnPeriod), ['note' => 'Locked after filing review.'])
            ->assertOk()
            ->assertJsonPath('data.status', 'locked')
            ->assertJsonPath('data.locked_by.email', 'meera.kapoor@builder360.test');

        $this->assertDatabaseHas('gst_entries', [
            'entry_number' => 'GST-10001',
            'status' => 'locked',
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'finance.gst_return.locked',
            'auditable_id' => $returnPeriod->id,
            'user_id' => $compliance->id,
        ]);
    }

    public function test_finance_can_use_native_blade_gst_entry_workspace(): void
    {
        $this->seed();

        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $compliance = User::where('email', 'meera.kapoor@builder360.test')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();
        $documentDate = now()->addMonthNoOverflow();

        $this->actingAs($finance)
            ->get(route('finance.gst-entries.index'))
            ->assertOk()
            ->assertSee('Workspace for GST input/output entries')
            ->assertSee('name="document_number"', false)
            ->assertSee('GST-10001')
            ->assertDontSee('window.Builder360Server', false)
            ->assertDontSee('id="root"', false);

        $this->actingAs($finance)
            ->post(route('finance.gst-entries.store'), [
                'project_id' => $project->id,
                'document_date' => $documentDate->toDateString(),
                'document_number' => 'BLADE-GST-1001',
                'party_name' => 'Blade GST Party',
                'party_gstin' => '27AABCP9876H1Z7',
                'place_of_supply_state' => 'MH',
                'transaction_type' => 'input',
                'hsn_sac' => '9954',
                'tax_rate' => 18,
                'taxable_amount' => 10000,
                'cgst_amount' => 900,
                'sgst_amount' => 900,
                'igst_amount' => 0,
                'cess_amount' => 0,
            ])
            ->assertRedirect(route('finance.gst-entries.index'))
            ->assertSessionHas('status');

        $entry = GstEntry::where('document_number', 'BLADE-GST-1001')->firstOrFail();

        $this->assertSame('submitted', $entry->status);
        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'finance.gst_entry.submitted',
            'auditable_id' => $entry->id,
            'user_id' => $finance->id,
        ]);

        $this->actingAs($compliance)
            ->patch(route('finance.gst-entries.approve', $entry), ['note' => 'Approved from Blade workspace.'])
            ->assertRedirect(route('finance.gst-entries.index'))
            ->assertSessionHas('status');

        $this->assertSame('approved', $entry->fresh()->status);
    }

    public function test_finance_can_use_native_blade_gst_return_period_workspace(): void
    {
        $this->seed();

        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $compliance = User::where('email', 'meera.kapoor@builder360.test')->firstOrFail();
        $period = now();

        $this->actingAs($finance)
            ->get(route('finance.gst-return-periods.index'))
            ->assertOk()
            ->assertSee('Workspace for preparing GST monthly return periods')
            ->assertSee('name="period_year"', false)
            ->assertDontSee('window.Builder360Server', false)
            ->assertDontSee('id="root"', false);

        $this->actingAs($finance)
            ->post(route('finance.gst-return-periods.store'), [
                'period_year' => $period->year,
                'period_month' => $period->month,
                'note' => 'Prepared from Blade workspace.',
            ])
            ->assertRedirect(route('finance.gst-return-periods.index'))
            ->assertSessionHas('status');

        $returnPeriod = GstReturnPeriod::where('period_year', $period->year)
            ->where('period_month', $period->month)
            ->firstOrFail();

        $this->assertSame('prepared', $returnPeriod->status);

        $this->actingAs($compliance)
            ->patch(route('finance.gst-return-periods.approve', $returnPeriod), ['note' => 'Approved from Blade workspace.'])
            ->assertRedirect(route('finance.gst-return-periods.index'))
            ->assertSessionHas('status');

        $returnPeriod->refresh();

        $this->actingAs($compliance)
            ->patch(route('finance.gst-return-periods.lock', $returnPeriod), ['note' => 'Locked from Blade workspace.'])
            ->assertRedirect(route('finance.gst-return-periods.index'))
            ->assertSessionHas('status');

        $this->assertSame('locked', $returnPeriod->fresh()->status);
    }

    public function test_non_global_finance_user_without_company_assignment_fails_closed_for_gst(): void
    {
        $this->seed();

        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();
        $entry = GstEntry::where('entry_number', 'GST-10001')->firstOrFail();
        $entry->forceFill([
            'status' => 'submitted',
            'created_by_user_id' => $director->id,
            'approved_by_user_id' => null,
            'approved_at' => null,
        ])->save();

        $periodStart = now()->addMonthNoOverflow()->startOfMonth();
        $returnPeriod = GstReturnPeriod::create([
            'company_id' => $project->company_id,
            'prepared_by_user_id' => $director->id,
            'return_number' => 'GSTR-FAIL-CLOSED',
            'period_year' => $periodStart->year,
            'period_month' => $periodStart->month,
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodStart->copy()->endOfMonth()->toDateString(),
            'status' => 'prepared',
            'entry_count' => 1,
            'output_taxable_total' => 1000,
            'output_tax_total' => 180,
            'input_taxable_total' => 0,
            'input_tax_credit_total' => 0,
            'net_tax_payable' => 180,
            'summary' => ['source' => 'test'],
            'workflow_history' => [],
        ]);

        $finance->forceFill(['company_id' => null])->save();

        $this->actingAs($finance)
            ->getJson(route('finance.gst-entries.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($finance)
            ->getJson(route('finance.gst-return-periods.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($finance)
            ->getJson(route('finance.gst-entries.index', ['project_id' => $project->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id']);

        $this->actingAs($finance)
            ->postJson(route('finance.gst-entries.store'), [
                'project_id' => $project->id,
                'document_date' => now()->toDateString(),
                'document_number' => 'FAIL-CLOSED-GST',
                'party_name' => 'Fail Closed GST Party',
                'place_of_supply_state' => 'MH',
                'transaction_type' => 'output',
                'tax_rate' => 18,
                'taxable_amount' => 1000,
                'cgst_amount' => 90,
                'sgst_amount' => 90,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id', 'document_date']);

        $this->actingAs($finance)
            ->postJson(route('finance.gst-return-periods.store'), [
                'period_year' => $periodStart->year,
                'period_month' => $periodStart->month,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['period_month']);

        $this->actingAs($finance)
            ->patchJson(route('finance.gst-entries.approve', $entry), ['note' => 'Scope denied.'])
            ->assertForbidden();

        $this->actingAs($finance)
            ->patchJson(route('finance.gst-return-periods.approve', $returnPeriod), ['note' => 'Scope denied.'])
            ->assertForbidden();
    }

    public function test_gst_indexes_validate_filters_and_project_scope(): void
    {
        $this->seed();

        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();
        $externalProject = $this->createExternalProject();
        $period = now();

        $this->actingAs($finance)
            ->getJson(route('finance.gst-entries.index', ['status' => 'invalid']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->actingAs($finance)
            ->getJson(route('finance.gst-entries.index', ['unexpected_filter' => 'ignored-before-hardening']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['unexpected_filter'])
            ->assertJsonPath('errors.unexpected_filter.0', 'The selected filter is not available for this endpoint.');

        $this->actingAs($finance)
            ->getJson(route('finance.gst-entries.index', ['transaction_type' => 'sale']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('transaction_type');

        $this->actingAs($finance)
            ->getJson(route('finance.gst-entries.index', ['period_year' => 2019]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('period_year');

        $this->actingAs($finance)
            ->getJson(route('finance.gst-entries.index', ['period_month' => 13]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('period_month');

        $this->actingAs($finance)
            ->getJson(route('finance.gst-entries.index', ['per_page' => 500]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');

        $this->actingAs($finance)
            ->getJson(route('finance.gst-entries.index', ['project_id' => $externalProject->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('project_id');

        $this->actingAs($finance)
            ->getJson(route('finance.gst-return-periods.index', ['status' => 'invalid']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->actingAs($finance)
            ->getJson(route('finance.gst-return-periods.index', ['unexpected_filter' => 'ignored-before-hardening']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['unexpected_filter'])
            ->assertJsonPath('errors.unexpected_filter.0', 'The selected filter is not available for this endpoint.');

        $this->actingAs($finance)
            ->getJson(route('finance.gst-return-periods.index', ['period_month' => 13]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('period_month');

        $this->actingAs($finance)
            ->getJson(route('finance.gst-entries.index', [
                'status' => 'approved',
                'transaction_type' => 'output',
                'period_year' => $period->year,
                'period_month' => $period->month,
                'project_id' => $project->id,
                'q' => 'RCPT-1001',
                'per_page' => 10,
            ]))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.entry_number', 'GST-10001');

        $returnId = $this->actingAs($finance)
            ->postJson(route('finance.gst-return-periods.store'), [
                'period_year' => $period->year,
                'period_month' => $period->month,
            ])
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($finance)
            ->getJson(route('finance.gst-return-periods.index', [
                'status' => 'prepared',
                'period_year' => $period->year,
                'period_month' => $period->month,
                'per_page' => 10,
            ]))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $returnId);
    }

    public function test_locked_gst_return_period_blocks_new_entries_for_same_month(): void
    {
        $this->seed();

        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $compliance = User::where('email', 'meera.kapoor@builder360.test')->firstOrFail();
        $period = now();

        $returnId = $this->actingAs($finance)
            ->postJson(route('finance.gst-return-periods.store'), [
                'period_year' => $period->year,
                'period_month' => $period->month,
            ])
            ->assertCreated()
            ->json('data.id');

        $returnPeriod = GstReturnPeriod::findOrFail($returnId);

        $this->actingAs($compliance)->patchJson(route('finance.gst-return-periods.approve', $returnPeriod))->assertOk();
        $returnPeriod->refresh();
        $this->actingAs($compliance)->patchJson(route('finance.gst-return-periods.lock', $returnPeriod))->assertOk();

        $this->actingAs($finance)
            ->postJson(route('finance.gst-entries.store'), [
                'document_date' => $period->toDateString(),
                'document_number' => 'LOCKED-GST-1001',
                'party_name' => 'Locked Period Party',
                'place_of_supply_state' => 'MH',
                'transaction_type' => 'output',
                'tax_rate' => 18,
                'taxable_amount' => 1000,
                'cgst_amount' => 90,
                'sgst_amount' => 90,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['document_date']);
    }

    public function test_gst_entry_duplicate_and_tax_total_validation(): void
    {
        $this->seed();

        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();

        $this->actingAs($finance)
            ->postJson(route('finance.gst-entries.store'), [
                'document_date' => now()->toDateString(),
                'document_number' => 'RCPT-1001',
                'party_name' => 'Duplicate Customer',
                'place_of_supply_state' => 'MH',
                'transaction_type' => 'output',
                'tax_rate' => 18,
                'taxable_amount' => 1000,
                'cgst_amount' => 90,
                'sgst_amount' => 90,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['document_number']);

        $this->actingAs($finance)
            ->postJson(route('finance.gst-entries.store'), [
                'document_date' => now()->addMonthNoOverflow()->toDateString(),
                'document_number' => 'BAD-TAX-1001',
                'party_name' => 'Bad Tax Party',
                'place_of_supply_state' => 'MH',
                'transaction_type' => 'output',
                'tax_rate' => 18,
                'taxable_amount' => 1000,
                'cgst_amount' => 20,
                'sgst_amount' => 20,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tax_rate']);
    }

    public function test_partner_cannot_access_gst_compliance_routes(): void
    {
        $this->seed();

        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();
        $entry = GstEntry::where('entry_number', 'GST-10001')->firstOrFail();

        $this->actingAs($partner)
            ->getJson(route('finance.gst-entries.index'))
            ->assertForbidden();

        $this->actingAs($partner)
            ->postJson(route('finance.gst-entries.store'), [])
            ->assertForbidden();

        $this->actingAs($partner)
            ->patchJson(route('finance.gst-entries.approve', $entry))
            ->assertForbidden();

        $this->actingAs($partner)
            ->getJson(route('finance.gst-return-periods.index'))
            ->assertForbidden();
    }

    private function createExternalProject(): Project
    {
        $company = Company::create([
            'code' => 'EXTGST',
            'name' => 'External GST Co',
            'legal_name' => 'External GST Co Private Limited',
            'state' => 'MH',
            'status' => 'active',
        ]);

        return Project::create([
            'company_id' => $company->id,
            'code' => 'EXT-GST',
            'name' => 'External GST Project',
            'project_type' => 'residential',
            'city' => 'Mumbai',
            'state' => 'MH',
            'status' => 'active',
            'budget_amount' => 1000000,
            'target_roi_percent' => 10,
        ]);
    }
}
