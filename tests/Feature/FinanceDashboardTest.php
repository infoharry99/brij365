<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\FinancialVoucher;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_user_can_open_native_blade_finance_dashboard(): void
    {
        $this->seed();

        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();

        $this->actingAs($finance)
            ->get(route('finance.dashboard'))
            ->assertOk()
            ->assertSee('Finance Dashboard')
            ->assertSee('Dashboard')
            ->assertSee('Finance filters')
            ->assertSee('Net cash position')
            ->assertSee('Schedule outstanding')
            ->assertSee('Recent vouchers')
            ->assertSee('name="company_id"', false)
            ->assertSee('name="forecast_days"', false)
            ->assertDontSee('window.Builder360Server', false)
            ->assertDontSee('id="root"', false);
    }

    public function test_finance_dashboard_reports_real_cash_position_receivables_payables_and_gst(): void
    {
        $this->seed();

        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();

        FinancialVoucher::create([
            'company_id' => $project->company_id,
            'project_id' => $project->id,
            'created_by_user_id' => $director->id,
            'approved_by_user_id' => $finance->id,
            'voucher_number' => 'PV-DASH-APPROVED',
            'voucher_type' => 'payment',
            'status' => 'approved',
            'voucher_date' => now()->toDateString(),
            'reference_number' => 'DASHBOARD-APPROVED-PAYMENT',
            'narration' => 'Approved project payment included in current cash position.',
            'currency' => 'INR',
            'total_debit' => 100000,
            'total_credit' => 100000,
            'tax_summary' => ['total_tax_amount' => 0],
            'workflow_history' => [],
            'metadata' => ['source' => 'finance_dashboard_test'],
            'approved_at' => now(),
        ]);

        FinancialVoucher::create([
            'company_id' => $project->company_id,
            'project_id' => $project->id,
            'created_by_user_id' => $director->id,
            'approved_by_user_id' => null,
            'voucher_number' => 'PV-DASH-SUBMITTED',
            'voucher_type' => 'payment',
            'status' => 'submitted',
            'voucher_date' => now()->addDays(10)->toDateString(),
            'reference_number' => 'DASHBOARD-SUBMITTED-PAYMENT',
            'narration' => 'Submitted project payment included in forecast and approval counts.',
            'currency' => 'INR',
            'total_debit' => 118000,
            'total_credit' => 118000,
            'tax_summary' => ['total_tax_amount' => 18000],
            'workflow_history' => [],
            'metadata' => ['source' => 'finance_dashboard_test'],
            'approved_at' => null,
        ]);

        $this->actingAs($finance)
            ->getJson(route('finance.dashboard', [
                'project_id' => $project->id,
                'date_from' => now()->subDay()->toDateString(),
                'date_to' => now()->toDateString(),
                'forecast_days' => 30,
            ]))
            ->assertOk()
            ->assertJsonPath('data.source', 'laravel-sqlite')
            ->assertJsonPath('data.cash_position.approved_collection_cash_in', 500000)
            ->assertJsonPath('data.cash_position.approved_receipt_voucher_cash_in', 0)
            ->assertJsonPath('data.cash_position.approved_payment_voucher_cash_out', 100000)
            ->assertJsonPath('data.cash_position.net_cash_position', 400000)
            ->assertJsonPath('data.period_summary.approved_collections', 500000)
            ->assertJsonPath('data.period_summary.approved_payment_vouchers', 100000)
            ->assertJsonPath('data.receivables.schedule_outstanding', 13627000)
            ->assertJsonPath('data.receivables.due_next_30_days', 3738100)
            ->assertJsonPath('data.receivables.requested_payment_links', 2825400)
            ->assertJsonPath('data.receivables.forecast_inflow', 2825400)
            ->assertJsonPath('data.payables.submitted_payment_vouchers', 118000)
            ->assertJsonPath('data.payables.forecast_outflow', 118000)
            ->assertJsonPath('data.gst.approved_entry_count', 1)
            ->assertJsonPath('data.gst.total_tax_amount', 76271.19)
            ->assertJsonPath('data.approvals.submitted_payment_vouchers', 1)
            ->assertJsonPath('data.approvals.requested_payment_links', 1)
            ->assertJsonStructure([
                'data' => [
                    'period',
                    'cash_position',
                    'period_summary',
                    'receivables' => ['aging_buckets'],
                    'payables',
                    'gst' => ['by_transaction_type'],
                    'approvals',
                    'recent_activity' => ['collections', 'vouchers', 'payment_requests'],
                ],
            ]);
    }

    public function test_finance_dashboard_rejects_unsupported_filters_and_cross_company_scope(): void
    {
        $this->seed();

        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $company = Company::where('code', 'B360D')->firstOrFail();
        $otherProject = Project::where('code', 'MTO-PUN')->firstOrFail();

        $this->actingAs($finance)
            ->getJson(route('finance.dashboard', ['unexpected_filter' => 'not allowed']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['unexpected_filter'])
            ->assertJsonPath('errors.unexpected_filter.0', 'The selected filter is not available for this endpoint.');

        $this->actingAs($finance)
            ->getJson(route('finance.dashboard', ['forecast_days' => 181]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['forecast_days']);

        $this->actingAs($finance)
            ->getJson(route('finance.dashboard', ['project_id' => $otherProject->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id']);

        $this->actingAs($finance)
            ->getJson(route('finance.dashboard', [
                'company_id' => $company->id,
                'project_id' => $otherProject->id,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id']);
    }

    public function test_finance_dashboard_fails_closed_for_non_global_user_without_company_assignment(): void
    {
        $this->seed();

        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();

        $finance->forceFill(['company_id' => null])->save();

        $this->actingAs($finance)
            ->getJson(route('finance.dashboard'))
            ->assertOk()
            ->assertJsonPath('data.cash_position.approved_collection_cash_in', 0)
            ->assertJsonPath('data.receivables.schedule_outstanding', 0)
            ->assertJsonPath('data.approvals.requested_payment_links', 0);

        $this->actingAs($finance)
            ->getJson(route('finance.dashboard', ['project_id' => $project->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id']);
    }

    public function test_partner_cannot_access_internal_finance_dashboard(): void
    {
        $this->seed();

        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();

        $this->actingAs($partner)
            ->getJson(route('finance.dashboard'))
            ->assertForbidden();
    }
}
