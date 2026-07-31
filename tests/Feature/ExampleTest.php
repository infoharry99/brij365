<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Booking;
use App\Models\BookingPaymentSchedule;
use App\Models\BoqItem;
use App\Models\CollectionReceipt;
use App\Models\CommonAreaHandoverItem;
use App\Models\ComplianceObligation;
use App\Models\ContractorBill;
use App\Models\ContractorMeasurement;
use App\Models\ConstructionMilestone;
use App\Models\Customer;
use App\Models\DataImportBatch;
use App\Models\DailyProgressReport;
use App\Models\DocumentCategory;
use App\Models\HandoverSnag;
use App\Models\Lead;
use App\Models\MarketingCampaign;
use App\Models\MaintenanceDue;
use App\Models\ManagedDocument;
use App\Models\Partner;
use App\Models\PossessionHandover;
use App\Models\Project;
use App\Models\ProjectApproval;
use App\Models\ProjectUnit;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequisition;
use App\Models\ReraRegistration;
use App\Models\Role;
use App\Models\ServiceTicket;
use App\Models\SocietyFormation;
use App\Models\StockItem;
use App\Models\SystemSetting;
use App\Models\UnitPriceVersion;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Builder360\Builder360Bootstrap;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use RuntimeException;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_builder360_dashboard_route_mounts_the_server_rendered_blade_workspace(): void
    {
        $this->seed();
        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();

        $response = $this->actingAs($director)->get('/');

        $response
            ->assertStatus(200)
            ->assertSee('Builder360')
            ->assertSee('Management Dashboard')
            ->assertSee('class="b360-shell"', false)
            ->assertDontSee('id="builder360-bootstrap"', false)
            ->assertDontSee('id="root"', false)
            ->assertSee('/build/assets/app-', false)
            ->assertSee('/build/assets/enterprise-', false)
            ->assertDontSee('resources/js/app.jsx', false)
            ->assertDontSee('window.Builder360Server =', false);
    }

    public function test_builder360_legacy_app_route_redirects_to_classic_dashboard(): void
    {
        $this->seed();
        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();

        $this->actingAs($director)
            ->get(route('builder360.legacy-app'))
            ->assertRedirect(route('builder360.dashboard'));
    }

    public function test_dashboard_bootstrap_contains_server_backed_global_metrics(): void
    {
        $this->seed();

        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $payload = app(Builder360Bootstrap::class)->forUser($director);

        $this->assertSame('laravel-sqlite', $payload['dashboard']['source']);
        $activeCompanyId = Company::where('code', 'B360D')->value('id');

        $this->assertSame(Project::where('company_id', $activeCompanyId)->count(), $payload['dashboard']['kpis']['projects']);
        $this->assertSame(ProjectUnit::where('company_id', $activeCompanyId)->count(), $payload['dashboard']['kpis']['totalUnits']);
        $this->assertGreaterThanOrEqual(1, $payload['dashboard']['kpis']['leads']);
        $this->assertCount(Project::where('company_id', $activeCompanyId)->count(), $payload['dashboard']['projects']);
        $this->assertNotEmpty($payload['dashboard']['funnel']);
        $this->assertArrayHasKey('generated_at', $payload['dashboard']);
        $this->assertGreaterThan(0, collect($payload['projects'])->first()['budget_amount']);
        $this->assertGreaterThan(0, collect($payload['projects'])->first()['target_roi_percent']);
    }

    public function test_dashboard_bootstrap_exposes_crm_leads_only_to_crm_users(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();

        $salesPayload = app(Builder360Bootstrap::class)->forUser($sales);
        $adminPayload = app(Builder360Bootstrap::class)->forUser($admin);
        $partnerPayload = app(Builder360Bootstrap::class)->forUser($partner);

        $this->assertNotEmpty($salesPayload['crm_leads']);
        $this->assertContains('LD-1001', collect($salesPayload['crm_leads'])->pluck('lead_code')->all());
        $this->assertSame('laravel-sqlite', $salesPayload['crm_lead_metrics']['source']);
        $this->assertSame(
            Lead::where('company_id', $sales->company_id)->count(),
            $salesPayload['crm_lead_metrics']['summary']['total_leads'],
        );
        $this->assertSame(
            Lead::where('company_id', $sales->company_id)->whereNotIn('status', ['won', 'lost'])->count(),
            $salesPayload['crm_lead_metrics']['summary']['open_leads'],
        );
        $this->assertSame(
            Lead::where('company_id', $sales->company_id)
                ->whereNotIn('status', ['won', 'lost'])
                ->where(function ($query): void {
                    $query->whereIn('stage', ['Negotiation', 'Site Visit Done'])
                        ->orWhereHas('qualifications', fn ($qualificationQuery) => $qualificationQuery->where('score', '>=', 75));
                })
                ->count(),
            $salesPayload['crm_lead_metrics']['summary']['hot_leads'],
        );
        $this->assertCount(6, $salesPayload['crm_lead_metrics']['kanban_columns']);
        $this->assertSame('New', $salesPayload['crm_lead_metrics']['kanban_columns'][0]['label']);
        $this->assertArrayHasKey('avg_response_hours', $salesPayload['crm_lead_metrics']['summary']);
        $lead1001 = collect($salesPayload['crm_leads'])->firstWhere('lead_code', 'LD-1001');
        $this->assertIsArray($lead1001['activities']);
        $this->assertNotEmpty($lead1001['activities']);
        $this->assertArrayHasKey('activity_number', $lead1001['activities'][0]);
        $this->assertArrayHasKey('who', $lead1001['activities'][0]);
        $this->assertArrayHasKey('act', $lead1001['activities'][0]);
        $this->assertSame('/crm/lead-activities', $lead1001['activity_create_url']);
        $this->assertTrue($lead1001['can_log_activity']);
        $this->assertSame('/crm/site-visits', $lead1001['site_visit_store_url']);
        $this->assertTrue($lead1001['can_schedule_site_visit']);
        $this->assertIsInt($lead1001['record_id']);
        $convertibleLead = collect($salesPayload['crm_leads'])->firstWhere('can_convert_booking', true);
        $this->assertNotNull($convertibleLead);
        $this->assertSame('/sales/bookings', $convertibleLead['booking_store_url']);
        $this->assertIsInt($convertibleLead['customer_id']);
        $this->assertArrayHasKey('disposition_url', $salesPayload['crm_leads'][0]);
        $this->assertArrayHasKey('can_disposition', $salesPayload['crm_leads'][0]);
        $this->assertTrue($salesPayload['crm_lead_create_options']['can_create']);
        $this->assertSame('/crm/leads', $salesPayload['crm_lead_create_options']['store_url']);
        $this->assertNotEmpty($salesPayload['crm_lead_create_options']['companies']);
        $this->assertNotEmpty($salesPayload['crm_lead_create_options']['projects']);
        $this->assertNotEmpty($salesPayload['crm_lead_create_options']['partners']);
        $this->assertContains('New', $salesPayload['crm_lead_create_options']['stages']);
        $this->assertTrue($salesPayload['crm_site_visit_options']['can_schedule']);
        $this->assertSame('/crm/site-visits', $salesPayload['crm_site_visit_options']['index_url']);
        $this->assertSame('/crm/site-visits', $salesPayload['crm_site_visit_options']['store_url']);
        $this->assertSame('/crm/site-visits/__VISIT__', $salesPayload['crm_site_visit_options']['update_url_template']);
        $this->assertSame('/crm/site-visits/__VISIT__/complete', $salesPayload['crm_site_visit_options']['complete_url_template']);
        $this->assertSame('/crm/site-visits/__VISIT__/cancel', $salesPayload['crm_site_visit_options']['cancel_url_template']);
        $this->assertContains('scheduled', $salesPayload['crm_site_visit_options']['statuses']);
        $this->assertContains('follow_up_required', $salesPayload['crm_site_visit_options']['outcomes']);
        $this->assertNotEmpty($salesPayload['crm_site_visit_options']['leads']);
        $this->assertArrayHasKey('customer_name', $salesPayload['crm_site_visit_options']['leads'][0]);
        $this->assertArrayHasKey('project_name', $salesPayload['crm_site_visit_options']['leads'][0]);
        $this->assertNotEmpty($salesPayload['crm_site_visit_options']['assignees']);
        $this->assertContains('site', collect($salesPayload['crm_site_visit_options']['visit_modes'])->pluck('value')->all());
        $this->assertTrue($salesPayload['crm_booking_options']['can_create']);
        $this->assertSame('/sales/bookings', $salesPayload['crm_booking_options']['store_url']);
        $this->assertSame('/sales/booking-quotes', $salesPayload['crm_booking_options']['quote_url']);
        $this->assertNotEmpty($salesPayload['crm_booking_options']['units']);
        $this->assertContains('Booking Amount', collect($salesPayload['crm_booking_options']['default_payment_schedule'])->pluck('milestone')->all());
        $this->assertSame('laravel-sqlite', $salesPayload['sales_booking_options']['source']);
        $this->assertSame('/sales/bookings', $salesPayload['sales_booking_options']['index_url']);
        $this->assertSame('/sales/bookings', $salesPayload['sales_booking_options']['store_url']);
        $this->assertSame('/sales/booking-quotes', $salesPayload['sales_booking_options']['quote_url']);
        $this->assertTrue($salesPayload['sales_booking_options']['can_view']);
        $this->assertTrue($salesPayload['sales_booking_options']['can_create']);
        $this->assertNotEmpty($salesPayload['sales_booking_options']['bookings']);
        $this->assertNotEmpty($salesPayload['sales_booking_options']['stage_distribution']);
        $this->assertArrayHasKey('booking_value_crore', $salesPayload['sales_booking_options']['summary']);
        $this->assertEqualsWithDelta(
            (float) Booking::where('company_id', $sales->company_id)
                ->whereIn('status', ['confirmed', 'agreement_pending', 'registered'])
                ->sum('net_receivable'),
            $salesPayload['sales_booking_options']['summary']['booking_value'],
            0.01,
        );
        $firstSalesBooking = $salesPayload['sales_booking_options']['bookings'][0];
        $this->assertArrayHasKey('booking_code', $firstSalesBooking);
        $this->assertArrayHasKey('status_label', $firstSalesBooking);
        $this->assertArrayHasKey('payment_percent', $firstSalesBooking);
        $scheduleTotal = (float) BookingPaymentSchedule::whereHas('booking', fn ($query) => $query
            ->where('company_id', $sales->company_id)
            ->whereIn('status', ['confirmed', 'agreement_pending', 'registered']))
            ->sum('amount');
        $approvedScheduleReceipts = (float) CollectionReceipt::where('company_id', $sales->company_id)
            ->where('status', 'approved')
            ->whereNotNull('booking_payment_schedule_id')
            ->sum('amount');
        $this->assertSame('laravel-sqlite', $salesPayload['collection_metrics']['source']);
        $this->assertEqualsWithDelta(
            round(max($scheduleTotal - $approvedScheduleReceipts, 0), 2),
            $salesPayload['collection_metrics']['summary']['outstanding'],
            0.01,
        );
        $this->assertCount(5, $salesPayload['collection_metrics']['ageing_buckets']);
        $this->assertNotEmpty($salesPayload['collection_metrics']['ledger_rows']);
        $this->assertSame('laravel-sqlite', $salesPayload['collection_receipt_options']['source']);
        $this->assertSame('/finance/collections', $salesPayload['collection_receipt_options']['index_url']);
        $this->assertSame('/finance/collections/export', $salesPayload['collection_receipt_options']['export_url']);
        $this->assertSame('/finance/collections', $salesPayload['collection_receipt_options']['store_url']);
        $this->assertSame('/finance/collections/__RECEIPT__/approve', $salesPayload['collection_receipt_options']['approve_url_template']);
        $this->assertTrue($salesPayload['collection_receipt_options']['can_export']);
        $this->assertTrue($salesPayload['collection_receipt_options']['can_create']);
        $this->assertNotEmpty($salesPayload['collection_receipt_options']['receipts']);
        $this->assertNotEmpty($salesPayload['collection_receipt_options']['bookings']);
        $fyStart = now()->month >= 4
            ? now()->startOfYear()->addMonths(3)->toDateString()
            : now()->subYear()->startOfYear()->addMonths(3)->toDateString();
        $expectedMarketingSpend = (float) MarketingCampaign::where('company_id', $sales->company_id)
            ->where('start_on', '>=', $fyStart)
            ->sum('budget_amount');
        $this->assertSame('laravel-sqlite', $salesPayload['marketing_metrics']['source']);
        $this->assertSame('/crm/campaigns', $salesPayload['marketing_metrics']['index_url']);
        $this->assertSame('/crm/campaigns', $salesPayload['marketing_metrics']['store_url']);
        $this->assertSame('/crm/campaigns/__CAMPAIGN__/status', $salesPayload['marketing_metrics']['status_url_template']);
        $this->assertTrue($salesPayload['marketing_metrics']['can_create']);
        $this->assertTrue($salesPayload['marketing_metrics']['can_update_status']);
        $this->assertContains('digital', collect($salesPayload['marketing_metrics']['channels'])->pluck('value')->all());
        $this->assertContains('draft', collect($salesPayload['marketing_metrics']['statuses'])->pluck('value')->all());
        $this->assertNotEmpty($salesPayload['marketing_metrics']['companies']);
        $this->assertNotEmpty($salesPayload['marketing_metrics']['projects']);
        $this->assertSame(['B360D'], collect($salesPayload['marketing_metrics']['companies'])->pluck('code')->all());
        $this->assertNotContains('MTO-PUN', collect($salesPayload['marketing_metrics']['projects'])->pluck('code')->all());
        $this->assertEqualsWithDelta(
            round($expectedMarketingSpend, 2),
            $salesPayload['marketing_metrics']['summary']['marketing_spend'],
            0.01,
        );
        $this->assertNotEmpty($salesPayload['marketing_metrics']['campaigns']);
        $this->assertContains('MC-10001', collect($salesPayload['marketing_metrics']['campaigns'])->pluck('campaign_code')->all());
        $skylineCampaign = collect($salesPayload['marketing_metrics']['campaigns'])->firstWhere('campaign_code', 'MC-10001');
        $this->assertSame(1, $skylineCampaign['leads']);
        $this->assertSame(1, $skylineCampaign['bookings']);
        $this->assertSame('laravel-sqlite', $salesPayload['sales_funnel_metrics']['source']);
        $this->assertSame(
            Lead::where('company_id', $sales->company_id)->count(),
            $salesPayload['sales_funnel_metrics']['summary']['total_leads'],
        );
        $this->assertArrayHasKey('booking_conversion_percent', $salesPayload['sales_funnel_metrics']['summary']);
        $this->assertNotEmpty($salesPayload['sales_funnel_metrics']['funnel']);
        $this->assertNotEmpty($salesPayload['sales_funnel_metrics']['source_conversion']);
        $this->assertContains('Channel walk-in', collect($salesPayload['sales_funnel_metrics']['source_conversion'])->pluck('label')->all());
        $this->assertNotEmpty($salesPayload['sales_funnel_metrics']['project_booking_rates']);
        $this->assertSame('laravel-sqlite', $salesPayload['sales_performance_metrics']['source']);
        $this->assertSame('crm', $salesPayload['sales_performance_metrics']['scope']['type']);
        $this->assertNotEmpty($salesPayload['sales_performance_metrics']['sales_rows']);
        $this->assertContains('Priya Nair', collect($salesPayload['sales_performance_metrics']['sales_rows'])->pluck('name')->all());
        $this->assertArrayHasKey('avg_conversion', $salesPayload['sales_performance_metrics']['summary']);
        $this->assertNotEmpty($salesPayload['sales_performance_metrics']['revenue_leaderboard']);
        $this->assertNotEmpty($salesPayload['sales_performance_metrics']['target_chart']);
        $this->assertNull($salesPayload['crm_import_options']);
        $this->assertTrue($adminPayload['crm_import_options']['can_import']);
        $this->assertSame(DataImportBatch::TYPE_CRM_PROSPECT_INQUIRIES, $adminPayload['crm_import_options']['import_type']);
        $this->assertSame('/settings/data-imports/preview', $adminPayload['crm_import_options']['preview_url']);
        $this->assertSame('/settings/data-imports/__BATCH__/post', $adminPayload['crm_import_options']['post_url_template']);
        $this->assertContains('project_code', $adminPayload['crm_import_options']['required_headers']);
        $this->assertStringContainsString('project_code,name,email,phone', $adminPayload['crm_import_options']['sample_csv']);
        $this->assertNotEmpty($adminPayload['crm_import_options']['companies']);
        $this->assertSame([], $partnerPayload['crm_leads']);
        $this->assertNull($partnerPayload['crm_lead_metrics']);
        $this->assertNull($partnerPayload['collection_metrics']);
        $this->assertNull($partnerPayload['collection_receipt_options']);
        $this->assertNull($partnerPayload['marketing_metrics']);
        $this->assertSame('laravel-sqlite', $partnerPayload['sales_funnel_metrics']['source']);
        $this->assertNull($partnerPayload['crm_lead_create_options']);
        $this->assertNull($partnerPayload['crm_import_options']);
        $this->assertNull($partnerPayload['crm_site_visit_options']);
        $this->assertNull($partnerPayload['crm_booking_options']);
        $this->assertSame('laravel-sqlite', $partnerPayload['sales_booking_options']['source']);
        $this->assertSame('partner', $partnerPayload['sales_booking_options']['scope']['level']);
        $this->assertFalse($partnerPayload['sales_booking_options']['can_create']);
        $this->assertNull($partnerPayload['sales_booking_options']['store_url']);
    }

    public function test_dashboard_bootstrap_is_company_scoped_for_non_global_users(): void
    {
        $this->seed();

        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $payload = app(Builder360Bootstrap::class)->forUser($finance);

        $expectedProjectCount = Project::where('company_id', $finance->company_id)->count();
        $expectedUnitCount = ProjectUnit::where('company_id', $finance->company_id)->count();

        $this->assertSame($finance->company_id, $payload['dashboard']['scope']['company_id']);
        $this->assertSame('B360D', $payload['dashboard']['scope']['company_code']);
        $this->assertSame($expectedProjectCount, $payload['dashboard']['kpis']['projects']);
        $this->assertSame($expectedUnitCount, $payload['dashboard']['kpis']['totalUnits']);
        $this->assertCount($expectedProjectCount, $payload['dashboard']['projects']);
        $this->assertNotContains('MTO-PUN', collect($payload['dashboard']['projects'])->pluck('code')->all());
        $this->assertSame('laravel-sqlite', $payload['finance_dashboard']['source']);
        $this->assertSame('/finance/dashboard', $payload['finance_dashboard']['index_url']);
        $this->assertArrayHasKey('cash_position', $payload['finance_dashboard']);
        $this->assertArrayHasKey('receivables', $payload['finance_dashboard']);
        $this->assertArrayHasKey('payables', $payload['finance_dashboard']);
        $this->assertArrayHasKey('gst', $payload['finance_dashboard']);
        $this->assertArrayHasKey('recent_activity', $payload['finance_dashboard']);
        $this->assertSame('laravel-sqlite', $payload['finance_payment_request_options']['source']);
        $this->assertSame('/finance/payment-requests', $payload['finance_payment_request_options']['index_url']);
        $this->assertSame('/finance/payment-requests', $payload['finance_payment_request_options']['store_url']);
        $this->assertSame('/finance/payment-requests/__PAYMENT_REQUEST__/cancel', $payload['finance_payment_request_options']['cancel_url_template']);
        $this->assertTrue($payload['finance_payment_request_options']['can_create']);
        $this->assertTrue($payload['finance_payment_request_options']['can_cancel']);
        $this->assertNotEmpty($payload['finance_payment_request_options']['requests']);
        $this->assertNotEmpty($payload['finance_payment_request_options']['bookings']);
        $this->assertSame('laravel-sqlite', $payload['finance_voucher_options']['source']);
        $this->assertSame('/finance/vouchers', $payload['finance_voucher_options']['index_url']);
        $this->assertSame('/finance/vouchers', $payload['finance_voucher_options']['store_url']);
        $this->assertTrue($payload['finance_voucher_options']['can_create']);
        $this->assertTrue($payload['finance_voucher_options']['can_approve']);
        $this->assertNotEmpty($payload['finance_voucher_options']['companies']);
        $this->assertSame(['B360D'], collect($payload['finance_voucher_options']['companies'])->pluck('code')->all());
        $this->assertNotEmpty($payload['finance_voucher_options']['projects']);
        $this->assertNotContains('MTO-PUN', collect($payload['finance_voucher_options']['projects'])->pluck('code')->all());
        $this->assertContains('payment', collect($payload['finance_voucher_options']['voucher_types'])->pluck('value')->all());
    }

    public function test_dashboard_bootstrap_exposes_policy_aware_approval_inbox_options_to_internal_users_only(): void
    {
        $this->seed();

        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $company = Company::where('code', 'B360D')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();
        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();

        PurchaseRequisition::create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'requested_by_user_id' => $construction->id,
            'requisition_number' => 'PR-APPROVAL-INBOX',
            'department' => 'Construction',
            'required_by' => now()->addDays(5)->toDateString(),
            'priority' => 'urgent',
            'status' => 'submitted',
            'items' => [
                ['item' => 'Cement bags', 'quantity' => 100, 'rate' => 400],
            ],
            'estimated_total' => 40000,
            'purpose' => 'Approval inbox regression material indent',
            'workflow_history' => [
                ['status' => 'submitted', 'by' => $construction->email],
            ],
        ]);

        $directorPayload = app(Builder360Bootstrap::class)->forUser($director);
        $partnerPayload = app(Builder360Bootstrap::class)->forUser($partner);

        $this->assertSame('business-records', $directorPayload['approval_inbox_options']['source']);
        $this->assertArrayHasKey('filters', $directorPayload['approval_inbox_options']);
        $this->assertArrayHasKey('summary', $directorPayload['approval_inbox_options']);
        $this->assertNotEmpty($directorPayload['approval_inbox_options']['rows']);
        $this->assertContains('PR-APPROVAL-INBOX', collect($directorPayload['approval_inbox_options']['rows'])->pluck('number')->all());

        $firstRow = $directorPayload['approval_inbox_options']['rows'][0];
        $this->assertArrayHasKey('record_id', $firstRow);
        $this->assertArrayHasKey('number', $firstRow);
        $this->assertArrayHasKey('source_module', $firstRow);
        $this->assertArrayHasKey('amount_value', $firstRow);
        $this->assertArrayHasKey('can_approve', $firstRow);
        $this->assertArrayHasKey('approve_url', $firstRow);
        $this->assertArrayHasKey('approve_payload_key', $firstRow);

        $actionableRows = collect($directorPayload['approval_inbox_options']['rows'])
            ->where('can_approve', true)
            ->values();

        $this->assertNotEmpty($actionableRows);
        $actionableRows->each(function (array $row): void {
            $this->assertNotNull($row['approve_url']);
            $this->assertStringStartsWith('/', $row['approve_url']);
            $this->assertContains($row['approve_payload_key'], ['note', 'decision_note']);
        });

        $this->assertSame(
            collect($directorPayload['approval_inbox_options']['rows'])->where('priority', 'high')->count(),
            $directorPayload['approval_inbox_options']['summary']['high_priority'],
        );
        $this->assertSame(
            collect($directorPayload['approval_inbox_options']['rows'])->where('can_approve', true)->count(),
            $directorPayload['approval_inbox_options']['summary']['actionable'],
        );
        $this->assertSame(
            count($directorPayload['approval_inbox_options']['rows']),
            $directorPayload['approval_inbox_options']['summary']['pending'],
        );
        $this->assertArrayHasKey('approve_url', $directorPayload['dashboard']['approvals'][0]);
        $this->assertNull($partnerPayload['approval_inbox_options']);
        $this->assertSame([], $partnerPayload['dashboard']['approvals']);
    }

    public function test_dashboard_bootstrap_exposes_inventory_pricing_options_to_authorized_internal_users_only(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();

        $salesPayload = app(Builder360Bootstrap::class)->forUser($sales);
        $partnerPayload = app(Builder360Bootstrap::class)->forUser($partner);

        $this->assertSame('laravel-sqlite', $salesPayload['inventory_pricing_options']['source']);
        $this->assertSame('/inventory/units', $salesPayload['inventory_pricing_options']['units_index_url']);
        $this->assertSame('/inventory/units/export', $salesPayload['inventory_pricing_options']['units_export_url']);
        $this->assertSame('/projects/cost-roi/export', $salesPayload['inventory_pricing_options']['project_cost_roi_export_url']);
        $this->assertSame('/inventory/unit-price-versions', $salesPayload['inventory_pricing_options']['price_versions_index_url']);
        $this->assertSame('/inventory/unit-price-versions', $salesPayload['inventory_pricing_options']['price_versions_store_url']);
        $this->assertSame('/inventory/unit-price-versions/__PRICE_VERSION__/approve', $salesPayload['inventory_pricing_options']['price_versions_approve_url_template']);
        $this->assertTrue($salesPayload['inventory_pricing_options']['can_view_units']);
        $this->assertTrue($salesPayload['inventory_pricing_options']['can_export_units']);
        $this->assertTrue($salesPayload['inventory_pricing_options']['can_export_project_cost_roi']);
        $this->assertTrue($salesPayload['inventory_pricing_options']['can_create_price_version']);
        $this->assertNotEmpty($salesPayload['inventory_pricing_options']['projects']);
        $this->assertNotEmpty($salesPayload['inventory_pricing_options']['units']);
        $this->assertNotEmpty($salesPayload['inventory_pricing_options']['price_versions']);
        $this->assertContains('available', collect($salesPayload['inventory_pricing_options']['unit_statuses'])->pluck('value')->all());
        $this->assertContains('SKY-A-1205', collect($salesPayload['inventory_pricing_options']['units'])->pluck('unit_code')->all());
        $this->assertNotContains('MTO-B-1803', collect($salesPayload['inventory_pricing_options']['units'])->pluck('unit_code')->all());
        $this->assertSame(
            ProjectUnit::where('company_id', $sales->company_id)->count(),
            $salesPayload['inventory_pricing_options']['summary']['total_units'],
        );
        $this->assertSame(
            UnitPriceVersion::where('company_id', $sales->company_id)->where('status', 'active')->count(),
            $salesPayload['inventory_pricing_options']['summary']['active_price_versions'],
        );
        $this->assertArrayHasKey('active_price_version', $salesPayload['inventory_pricing_options']['units'][0]);
        $this->assertNull($partnerPayload['inventory_pricing_options']);
    }

    public function test_dashboard_bootstrap_exposes_report_exports_to_authorized_internal_users_only(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $auditor = User::where('email', 'ishaan.trivedi@builder360.test')->firstOrFail();
        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();

        $salesPayload = app(Builder360Bootstrap::class)->forUser($sales);
        $auditorPayload = app(Builder360Bootstrap::class)->forUser($auditor);
        $partnerPayload = app(Builder360Bootstrap::class)->forUser($partner);

        $this->assertSame('laravel-sqlite', $salesPayload['governance_report_options']['source']);
        $this->assertSame('/governance/management-summary', $salesPayload['governance_report_options']['management_summary_url']);
        $this->assertSame('/governance/report-register', $salesPayload['governance_report_options']['register_url']);
        $this->assertSame('/governance/report-pins', $salesPayload['governance_report_options']['report_pin_store_url']);
        $this->assertSame('/governance/report-pins/__PIN__', $salesPayload['governance_report_options']['report_pin_delete_url_template']);
        $this->assertSame('/governance/report-schedules', $salesPayload['governance_report_options']['report_schedule_store_url']);
        $this->assertSame('/governance/report-schedules/__SCHEDULE__/archive', $salesPayload['governance_report_options']['report_schedule_archive_url_template']);
        $this->assertSame(['bookings', 'collections', 'payroll', 'service_tickets', 'leads', 'inventory_units', 'stock_items', 'stock_movements', 'purchase_orders', 'vendors', 'construction_milestones', 'daily_progress_reports', 'rera_registrations'], $salesPayload['governance_report_options']['supported_reports']);
        $this->assertSame('Consumption', collect($salesPayload['governance_report_options']['supported_report_statuses']['stock_movements'])->firstWhere('value', 'consumption')['label']);
        $this->assertSame('Partially Received', collect($salesPayload['governance_report_options']['supported_report_statuses']['purchase_orders'])->firstWhere('value', 'partially_received')['label']);
        $this->assertSame('Verified', collect($salesPayload['governance_report_options']['supported_report_statuses']['rera_registrations'])->firstWhere('value', 'verified')['label']);
        $this->assertContains('audit_events', $auditorPayload['governance_report_options']['supported_reports']);
        $this->assertNotContains('audit_events', $salesPayload['governance_report_options']['supported_reports']);
        $this->assertContains('pdf', $salesPayload['governance_report_options']['supported_formats']);
        $this->assertContains('excel', $salesPayload['governance_report_options']['supported_formats']);
        $this->assertSame(['daily', 'weekly', 'monthly'], $salesPayload['governance_report_options']['schedule_frequencies']);
        $this->assertSame([], $salesPayload['governance_report_options']['pinned_reports']);
        $this->assertSame([], $salesPayload['governance_report_options']['scheduled_reports']);
        $this->assertSame('laravel-sqlite', $auditorPayload['audit_trail_options']['source']);
        $this->assertSame('/governance/audit-events', $auditorPayload['audit_trail_options']['index_url']);
        $this->assertSame('/governance/audit-events/export', $auditorPayload['audit_trail_options']['export_url']);
        $this->assertContains('request_id', $auditorPayload['audit_trail_options']['supported_filters']);
        $this->assertSame(['csv'], $auditorPayload['audit_trail_options']['supported_exports']);
        $this->assertNull($salesPayload['audit_trail_options']);
        $this->assertNull($partnerPayload['governance_report_options']);
        $this->assertNull($partnerPayload['audit_trail_options']);
    }

    public function test_dashboard_bootstrap_exposes_self_service_helpdesk_creation_to_employees_only(): void
    {
        $this->seed();

        $employeeUser = User::where('email', 'amit.verma@builder360.test')->firstOrFail();
        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $recruiter = User::where('email', 'ananya.sen@builder360.test')->firstOrFail();
        $compliance = User::where('email', 'meera.kapoor@builder360.test')->firstOrFail();
        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();
        $company = Company::where('code', 'B360D')->firstOrFail();
        $viewerRole = Role::create([
            'slug' => 'hr_viewer_bootstrap_test',
            'name' => 'HR Viewer Bootstrap Test',
            'scope_level' => 'department',
            'permissions' => ['hr.view'],
            'is_active' => true,
        ]);
        $viewer = User::factory()->create([
            'role_id' => $viewerRole->id,
            'company_id' => $company->id,
            'email' => 'hr.viewer.bootstrap@example.test',
            'status' => 'active',
        ]);

        $employeePayload = app(Builder360Bootstrap::class)->forUser($employeeUser);
        $hrPayload = app(Builder360Bootstrap::class)->forUser($hr);
        $recruiterPayload = app(Builder360Bootstrap::class)->forUser($recruiter);
        $compliancePayload = app(Builder360Bootstrap::class)->forUser($compliance);
        $viewerPayload = app(Builder360Bootstrap::class)->forUser($viewer);
        $partnerPayload = app(Builder360Bootstrap::class)->forUser($partner);

        $this->assertSame('laravel-sqlite', $employeePayload['hr_helpdesk_options']['source']);
        $this->assertSame('/hr/helpdesk-tickets', $employeePayload['hr_helpdesk_options']['store_url']);
        $this->assertTrue($employeePayload['hr_helpdesk_options']['can_create']);
        $this->assertSame('EMP-0030', $employeePayload['hr_helpdesk_options']['self_employee']['employee_code']);
        $this->assertContains('attendance', collect($employeePayload['hr_helpdesk_options']['categories'])->pluck('value')->all());
        $this->assertContains('critical', collect($employeePayload['hr_helpdesk_options']['priorities'])->pluck('value')->all());
        $this->assertSame('laravel-sqlite', $employeePayload['hr_self_service_options']['source']);
        $this->assertSame('/hr/expense-claims', $employeePayload['hr_self_service_options']['claims_index_url']);
        $this->assertSame('/hr/expense-claims', $employeePayload['hr_self_service_options']['claim_store_url']);
        $this->assertTrue($employeePayload['hr_self_service_options']['can_create_claim']);
        $this->assertTrue($employeePayload['hr_self_service_options']['can_view_claims']);
        $this->assertSame('/hr/leave-requests', $employeePayload['hr_self_service_options']['leave_requests_index_url']);
        $this->assertSame('/hr/leave-requests', $employeePayload['hr_self_service_options']['leave_request_store_url']);
        $this->assertSame('/hr/leave-balances', $employeePayload['hr_self_service_options']['leave_balances_index_url']);
        $this->assertSame('/hr/attendance-regularizations', $employeePayload['hr_self_service_options']['attendance_regularizations_index_url']);
        $this->assertSame('/hr/attendance-regularizations', $employeePayload['hr_self_service_options']['attendance_regularization_store_url']);
        $this->assertSame('/hr/attendance-records', $employeePayload['hr_self_service_options']['attendance_records_index_url']);
        $this->assertSame('/hr/attendance-shifts', $employeePayload['hr_self_service_options']['attendance_shifts_index_url']);
        $this->assertSame('/hr/employees/'.$employeePayload['hr_self_service_options']['self_employee']['id'].'/payroll-summary', $employeePayload['hr_self_service_options']['payroll_summary_url']);
        $this->assertSame('/payroll/tax-documents/__DOCUMENT__/acknowledge', $employeePayload['hr_self_service_options']['tax_document_acknowledge_url_template']);
        $this->assertSame('/hr/performance-reviews?employee_id='.$employeePayload['hr_self_service_options']['self_employee']['id'], $employeePayload['hr_self_service_options']['performance_reviews_index_url']);
        $this->assertSame('/hr/performance-reviews/__REVIEW__/self-submit', $employeePayload['hr_self_service_options']['performance_review_self_submit_url_template']);
        $this->assertSame('/hr/policy-acknowledgements?employee_id='.$employeePayload['hr_self_service_options']['self_employee']['id'], $employeePayload['hr_self_service_options']['policy_acknowledgements_index_url']);
        $this->assertSame('/hr/policy-acknowledgements', $employeePayload['hr_self_service_options']['policy_acknowledgement_store_url']);
        $this->assertTrue($employeePayload['hr_self_service_options']['can_create_leave_request']);
        $this->assertTrue($employeePayload['hr_self_service_options']['can_view_leave_requests']);
        $this->assertTrue($employeePayload['hr_self_service_options']['can_create_attendance_regularization']);
        $this->assertTrue($employeePayload['hr_self_service_options']['can_view_attendance_regularizations']);
        $this->assertTrue($employeePayload['hr_self_service_options']['can_view_performance_reviews']);
        $this->assertTrue($employeePayload['hr_self_service_options']['can_submit_self_review']);
        $this->assertTrue($employeePayload['hr_self_service_options']['can_create_policy_acknowledgement']);
        $this->assertTrue($employeePayload['hr_self_service_options']['can_view_policy_acknowledgements']);
        $this->assertTrue($employeePayload['hr_self_service_options']['can_view_payroll_summary']);
        $this->assertTrue($employeePayload['hr_self_service_options']['can_acknowledge_tax_documents']);
        $this->assertSame('EMP-0030', $employeePayload['hr_self_service_options']['self_employee']['employee_code']);
        $this->assertContains('fuel', collect($employeePayload['hr_self_service_options']['claim_types'])->pluck('value')->all());
        $this->assertContains('EL', collect($employeePayload['hr_self_service_options']['leave_types'])->pluck('code')->all());
        $this->assertContains('available_days', array_keys($employeePayload['hr_self_service_options']['leave_types'][0]));
        $this->assertArrayHasKey('summary', $employeePayload['hr_self_service_options']);
        $this->assertArrayHasKey('attendance_percent', $employeePayload['hr_self_service_options']['summary']);
        $this->assertArrayHasKey('attendance_marked_days', $employeePayload['hr_self_service_options']['summary']);
        $this->assertArrayHasKey('leave_available_days', $employeePayload['hr_self_service_options']['summary']);
        $this->assertArrayHasKey('open_requests', $employeePayload['hr_self_service_options']['summary']);
        $this->assertArrayHasKey('latest_payslip_period', $employeePayload['hr_self_service_options']['summary']);
        $this->assertArrayHasKey('latest_payslip_status', $employeePayload['hr_self_service_options']['summary']);
        $this->assertArrayHasKey('latest_payslip_net_payable', $employeePayload['hr_self_service_options']['summary']);
        $this->assertIsArray($employeePayload['hr_self_service_options']['recent_attendance']);
        $this->assertSame('laravel-sqlite', $employeePayload['hr_leave_options']['source']);
        $this->assertSame('/hr/leave-requests', $employeePayload['hr_leave_options']['leave_requests_index_url']);
        $this->assertSame('/hr/leave-requests', $employeePayload['hr_leave_options']['leave_request_store_url']);
        $this->assertSame('/hr/leave-balances', $employeePayload['hr_leave_options']['leave_balances_index_url']);
        $this->assertTrue($employeePayload['hr_leave_options']['can_create_leave_request']);
        $this->assertNull($employeePayload['hr_leave_options']['leave_processing_runs_index_url']);
        $this->assertNull($employeePayload['hr_leave_options']['leave_processing_runs_store_url']);
        $this->assertSame('/hr/leave-encashments', $employeePayload['hr_leave_options']['leave_encashments_index_url']);
        $this->assertSame('/hr/leave-encashments', $employeePayload['hr_leave_options']['leave_encashments_store_url']);
        $this->assertFalse($employeePayload['hr_leave_options']['can_view_processing_runs']);
        $this->assertFalse($employeePayload['hr_leave_options']['can_create_processing_run']);
        $this->assertTrue($employeePayload['hr_leave_options']['can_create_encashment']);
        $this->assertSame('EMP-0030', $employeePayload['hr_leave_options']['self_employee']['employee_code']);
        $this->assertContains('EL', collect($employeePayload['hr_leave_options']['leave_types'])->pluck('code')->all());
        $this->assertContains('EMP-0030', collect($employeePayload['hr_leave_options']['employees'])->pluck('employee_code')->all());
        $this->assertSame('laravel-sqlite', $employeePayload['hr_attendance_options']['source']);
        $this->assertSame('/hr/attendance-records', $employeePayload['hr_attendance_options']['attendance_records_index_url']);
        $this->assertSame('/hr/attendance-regularizations', $employeePayload['hr_attendance_options']['attendance_regularizations_index_url']);
        $this->assertNull($employeePayload['hr_attendance_options']['attendance_regularization_approve_url_template']);
        $this->assertNull($employeePayload['hr_attendance_options']['attendance_regularization_reject_url_template']);
        $this->assertSame('/hr/attendance-shifts', $employeePayload['hr_attendance_options']['shifts_index_url']);
        $this->assertNull($employeePayload['hr_attendance_options']['shifts_store_url']);
        $this->assertTrue($employeePayload['hr_attendance_options']['can_view_attendance_records']);
        $this->assertTrue($employeePayload['hr_attendance_options']['can_view_attendance_regularizations']);
        $this->assertFalse($employeePayload['hr_attendance_options']['can_approve_regularization']);
        $this->assertTrue($employeePayload['hr_attendance_options']['can_view_shifts']);
        $this->assertFalse($employeePayload['hr_attendance_options']['can_create_shift']);
        $this->assertNull($employeePayload['hr_report_options']);
        $this->assertNull($employeePayload['hr_operations_options']);
        $this->assertSame('laravel-sqlite', $hrPayload['hr_attendance_options']['source']);
        $this->assertSame('/hr/attendance-records', $hrPayload['hr_attendance_options']['attendance_records_index_url']);
        $this->assertSame('/hr/attendance-regularizations', $hrPayload['hr_attendance_options']['attendance_regularizations_index_url']);
        $this->assertSame('/hr/attendance-regularizations/__REGULARIZATION__/approve', $hrPayload['hr_attendance_options']['attendance_regularization_approve_url_template']);
        $this->assertSame('/hr/attendance-regularizations/__REGULARIZATION__/reject', $hrPayload['hr_attendance_options']['attendance_regularization_reject_url_template']);
        $this->assertSame('/hr/attendance-shifts', $hrPayload['hr_attendance_options']['shifts_index_url']);
        $this->assertSame('/hr/attendance-shifts', $hrPayload['hr_attendance_options']['shifts_store_url']);
        $this->assertTrue($hrPayload['hr_attendance_options']['can_view_attendance_records']);
        $this->assertTrue($hrPayload['hr_attendance_options']['can_view_attendance_regularizations']);
        $this->assertTrue($hrPayload['hr_attendance_options']['can_approve_regularization']);
        $this->assertTrue($hrPayload['hr_attendance_options']['can_view_shifts']);
        $this->assertTrue($hrPayload['hr_attendance_options']['can_create_shift']);
        $this->assertContains('B360D', collect($hrPayload['hr_attendance_options']['companies'])->pluck('code')->all());
        $this->assertContains('night', collect($hrPayload['hr_attendance_options']['shift_types'])->pluck('value')->all());
        $this->assertContains('late', collect($hrPayload['hr_attendance_options']['status_filters'])->pluck('value')->all());
        $this->assertContains('submitted', collect($hrPayload['hr_attendance_options']['regularization_status_filters'])->pluck('value')->all());
        $this->assertContains('mobile_gps', collect($hrPayload['hr_attendance_options']['source_filters'])->pluck('value')->all());
        $this->assertSame('laravel-sqlite', $hrPayload['hr_dashboard_options']['source']);
        $this->assertArrayHasKey('active_headcount', $hrPayload['hr_dashboard_options']['summary']);
        $this->assertArrayHasKey('pending_approvals', $hrPayload['hr_dashboard_options']['summary']);
        $this->assertArrayHasKey('latest_payroll_net_payable', $hrPayload['hr_dashboard_options']['summary']);
        $this->assertContains('B360D', collect($hrPayload['hr_dashboard_options']['company_headcount'])->pluck('code')->all());
        $this->assertNotEmpty($hrPayload['hr_dashboard_options']['department_headcount']);
        $this->assertIsArray($hrPayload['hr_dashboard_options']['approval_inbox']);
        $this->assertIsArray($hrPayload['hr_dashboard_options']['lifecycle_due']);
        $this->assertIsArray($hrPayload['hr_dashboard_options']['compliance_risk']);
        $this->assertSame('laravel-sqlite', $hrPayload['hr_leave_options']['source']);
        $this->assertSame('/hr/leave-requests', $hrPayload['hr_leave_options']['leave_requests_index_url']);
        $this->assertSame('/hr/leave-requests', $hrPayload['hr_leave_options']['leave_request_store_url']);
        $this->assertSame('/hr/leave-processing-runs', $hrPayload['hr_leave_options']['leave_processing_runs_index_url']);
        $this->assertSame('/hr/leave-processing-runs', $hrPayload['hr_leave_options']['leave_processing_runs_store_url']);
        $this->assertSame('/hr/leave-processing-runs/__RUN__/post', $hrPayload['hr_leave_options']['leave_processing_run_post_url_template']);
        $this->assertSame('/hr/leave-requests/__REQUEST__/reject', $hrPayload['hr_leave_options']['leave_request_reject_url_template']);
        $this->assertSame('/hr/leave-encashments', $hrPayload['hr_leave_options']['leave_encashments_index_url']);
        $this->assertSame('/hr/leave-encashments/__ENCASHMENT__/approve', $hrPayload['hr_leave_options']['leave_encashment_approve_url_template']);
        $this->assertSame('/hr/leave-encashments/__ENCASHMENT__/reject', $hrPayload['hr_leave_options']['leave_encashment_reject_url_template']);
        $this->assertNull($hrPayload['hr_leave_options']['leave_encashment_mark_payroll_url_template']);
        $this->assertTrue($hrPayload['hr_leave_options']['can_view_processing_runs']);
        $this->assertTrue($hrPayload['hr_leave_options']['can_create_leave_request']);
        $this->assertTrue($hrPayload['hr_leave_options']['can_create_processing_run']);
        $this->assertTrue($hrPayload['hr_leave_options']['can_post_processing_run']);
        $this->assertTrue($hrPayload['hr_leave_options']['can_approve_encashment']);
        $this->assertFalse($hrPayload['hr_leave_options']['can_mark_encashment_payroll']);
        $this->assertContains('monthly_accrual', collect($hrPayload['hr_leave_options']['processing_types'])->pluck('value')->all());
        $this->assertContains('B360D', collect($hrPayload['hr_leave_options']['companies'])->pluck('code')->all());
        $this->assertContains('EMP-0030', collect($hrPayload['hr_leave_options']['employees'])->pluck('employee_code')->all());
        $this->assertSame('laravel-sqlite', $hrPayload['hr_performance_options']['source']);
        $this->assertSame('/hr/performance-cycles', $hrPayload['hr_performance_options']['performance_cycles_index_url']);
        $this->assertSame('/hr/performance-cycles', $hrPayload['hr_performance_options']['performance_cycles_store_url']);
        $this->assertSame('/hr/performance-reviews', $hrPayload['hr_performance_options']['performance_reviews_index_url']);
        $this->assertSame('/hr/performance-reviews', $hrPayload['hr_performance_options']['performance_reviews_store_url']);
        $this->assertSame('/hr/performance-reviews/__REVIEW__/manager-submit', $hrPayload['hr_performance_options']['performance_review_manager_submit_url_template']);
        $this->assertSame('/hr/performance-reviews/__REVIEW__/close', $hrPayload['hr_performance_options']['performance_review_close_url_template']);
        $this->assertTrue($hrPayload['hr_performance_options']['can_view_performance_cycles']);
        $this->assertTrue($hrPayload['hr_performance_options']['can_create_performance_cycle']);
        $this->assertTrue($hrPayload['hr_performance_options']['can_view_performance_reviews']);
        $this->assertTrue($hrPayload['hr_performance_options']['can_create_performance_review']);
        $this->assertTrue($hrPayload['hr_performance_options']['can_close_performance_review']);
        $this->assertContains('monthly', collect($hrPayload['hr_performance_options']['frequencies'])->pluck('value')->all());
        $this->assertContains('Construction', $hrPayload['hr_performance_options']['departments']);
        $this->assertNotEmpty($hrPayload['hr_performance_options']['employees']);
        $this->assertSame('laravel-sqlite', $hrPayload['hr_lifecycle_options']['source']);
        $this->assertSame('/hr/confirmation-cases', $hrPayload['hr_lifecycle_options']['confirmation_cases_index_url']);
        $this->assertSame('/hr/confirmation-cases', $hrPayload['hr_lifecycle_options']['confirmation_cases_store_url']);
        $this->assertSame('/hr/confirmation-cases/__CASE__/recommend', $hrPayload['hr_lifecycle_options']['confirmation_case_recommend_url_template']);
        $this->assertSame('/hr/confirmation-cases/__CASE__/decide', $hrPayload['hr_lifecycle_options']['confirmation_case_decide_url_template']);
        $this->assertSame('/hr/separation-settlements', $hrPayload['hr_lifecycle_options']['separation_settlements_index_url']);
        $this->assertSame('/hr/separation-settlements', $hrPayload['hr_lifecycle_options']['separation_settlements_store_url']);
        $this->assertSame('/hr/separation-settlements/__SETTLEMENT__/hr-approve', $hrPayload['hr_lifecycle_options']['separation_settlement_hr_approve_url_template']);
        $this->assertSame('/hr/separation-settlements/__SETTLEMENT__/finance-approve', $hrPayload['hr_lifecycle_options']['separation_settlement_finance_approve_url_template']);
        $this->assertSame('/hr/separation-settlements/__SETTLEMENT__/complete', $hrPayload['hr_lifecycle_options']['separation_settlement_complete_url_template']);
        $this->assertSame('/hr/exit-interviews', $hrPayload['hr_lifecycle_options']['exit_interviews_index_url']);
        $this->assertSame('/hr/exit-interviews', $hrPayload['hr_lifecycle_options']['exit_interviews_store_url']);
        $this->assertSame('/hr/exit-interviews/summary', $hrPayload['hr_lifecycle_options']['exit_interviews_summary_url']);
        $this->assertSame('/hr/exit-interviews/__INTERVIEW__/submit', $hrPayload['hr_lifecycle_options']['exit_interview_submit_url_template']);
        $this->assertSame('/hr/exit-interviews/__INTERVIEW__/review', $hrPayload['hr_lifecycle_options']['exit_interview_review_url_template']);
        $this->assertTrue($hrPayload['hr_lifecycle_options']['can_create_confirmation']);
        $this->assertTrue($hrPayload['hr_lifecycle_options']['can_decide_confirmation']);
        $this->assertTrue($hrPayload['hr_lifecycle_options']['can_create_separation_settlement']);
        $this->assertTrue($hrPayload['hr_lifecycle_options']['can_hr_approve_separation_settlement']);
        $this->assertFalse($hrPayload['hr_lifecycle_options']['can_finance_approve_separation_settlement']);
        $this->assertTrue($hrPayload['hr_lifecycle_options']['can_create_exit_interview']);
        $this->assertTrue($hrPayload['hr_lifecycle_options']['can_review_exit_interview']);
        $this->assertContains('resignation', collect($hrPayload['hr_lifecycle_options']['separation_types'])->pluck('value')->all());
        $this->assertContains('career_growth', collect($hrPayload['hr_lifecycle_options']['exit_reasons'])->pluck('value')->all());
        $this->assertNotEmpty($hrPayload['hr_lifecycle_options']['employees']);
        $this->assertSame('laravel-sqlite', $recruiterPayload['hr_recruitment_options']['source']);
        $this->assertSame('/recruitment/job-openings', $recruiterPayload['hr_recruitment_options']['job_openings_index_url']);
        $this->assertSame('/recruitment/job-openings', $recruiterPayload['hr_recruitment_options']['job_openings_store_url']);
        $this->assertSame('/recruitment/job-openings/__OPENING__/approve', $recruiterPayload['hr_recruitment_options']['job_openings_approve_url_template']);
        $this->assertSame('/recruitment/candidates', $recruiterPayload['hr_recruitment_options']['candidates_index_url']);
        $this->assertSame('/recruitment/candidates', $recruiterPayload['hr_recruitment_options']['candidates_store_url']);
        $this->assertSame('/recruitment/candidates/__CANDIDATE__/stage', $recruiterPayload['hr_recruitment_options']['candidates_stage_url_template']);
        $this->assertSame('/recruitment/candidates/__CANDIDATE__/convert-to-employee', $recruiterPayload['hr_recruitment_options']['candidates_convert_url_template']);
        $this->assertSame('/recruitment/interviews', $recruiterPayload['hr_recruitment_options']['interviews_index_url']);
        $this->assertSame('/recruitment/interviews', $recruiterPayload['hr_recruitment_options']['interviews_store_url']);
        $this->assertSame('/recruitment/interviews/__INTERVIEW__/feedback', $recruiterPayload['hr_recruitment_options']['interviews_feedback_url_template']);
        $this->assertSame($recruiter->id, $recruiterPayload['hr_recruitment_options']['current_user']['id']);
        $this->assertSame($recruiter->email, $recruiterPayload['hr_recruitment_options']['current_user']['email']);
        $this->assertSame('/recruitment/offers', $recruiterPayload['hr_recruitment_options']['offers_index_url']);
        $this->assertSame('/recruitment/offers', $recruiterPayload['hr_recruitment_options']['offers_store_url']);
        $this->assertSame('/recruitment/offers/__OFFER__/release', $recruiterPayload['hr_recruitment_options']['offers_release_url_template']);
        $this->assertTrue($recruiterPayload['hr_recruitment_options']['can_view_job_openings']);
        $this->assertTrue($recruiterPayload['hr_recruitment_options']['can_create_job_opening']);
        $this->assertFalse($recruiterPayload['hr_recruitment_options']['can_approve_job_openings']);
        $this->assertTrue($recruiterPayload['hr_recruitment_options']['can_view_candidates']);
        $this->assertTrue($recruiterPayload['hr_recruitment_options']['can_create_candidate']);
        $this->assertTrue($recruiterPayload['hr_recruitment_options']['can_update_candidate_stage']);
        $this->assertFalse($recruiterPayload['hr_recruitment_options']['can_convert_candidates']);
        $this->assertTrue($recruiterPayload['hr_recruitment_options']['can_schedule_interview']);
        $this->assertTrue($recruiterPayload['hr_recruitment_options']['can_view_offers']);
        $this->assertTrue($recruiterPayload['hr_recruitment_options']['can_create_offer']);
        $this->assertFalse($recruiterPayload['hr_recruitment_options']['can_release_offers']);
        $this->assertSame('laravel-sqlite', $hrPayload['hr_recruitment_options']['source']);
        $this->assertNull($hrPayload['hr_recruitment_options']['job_openings_store_url']);
        $this->assertNull($hrPayload['hr_recruitment_options']['candidates_store_url']);
        $this->assertNull($hrPayload['hr_recruitment_options']['offers_store_url']);
        $this->assertFalse($hrPayload['hr_recruitment_options']['can_create_job_opening']);
        $this->assertTrue($hrPayload['hr_recruitment_options']['can_approve_job_openings']);
        $this->assertFalse($hrPayload['hr_recruitment_options']['can_create_candidate']);
        $this->assertFalse($hrPayload['hr_recruitment_options']['can_update_candidate_stage']);
        $this->assertTrue($hrPayload['hr_recruitment_options']['can_convert_candidates']);
        $this->assertFalse($hrPayload['hr_recruitment_options']['can_create_offer']);
        $this->assertTrue($hrPayload['hr_recruitment_options']['can_release_offers']);
        $this->assertContains('offer_letter_v4', collect($recruiterPayload['hr_recruitment_options']['offer_templates'])->pluck('value')->all());
        $this->assertContains('B360D', collect($recruiterPayload['hr_recruitment_options']['companies'])->pluck('code')->all());
        $this->assertContains('SKY-PUN', collect($recruiterPayload['hr_recruitment_options']['projects'])->pluck('code')->all());
        $this->assertContains('full_time', collect($recruiterPayload['hr_recruitment_options']['employment_types'])->pluck('value')->all());
        $this->assertContains('screening', collect($recruiterPayload['hr_recruitment_options']['candidate_stages'])->pluck('value')->all());
        $this->assertNotEmpty($recruiterPayload['hr_recruitment_options']['candidate_sources']);
        $this->assertNotEmpty($recruiterPayload['hr_recruitment_options']['panel_users']);
        $this->assertArrayHasKey('open_positions', $recruiterPayload['hr_recruitment_options']['summary']);
        $this->assertNotEmpty($recruiterPayload['hr_recruitment_options']['job_openings']);
        $this->assertSame('laravel-sqlite', $hrPayload['hr_employee_options']['source']);
        $this->assertSame('/hr/employees', $hrPayload['hr_employee_options']['index_url']);
        $this->assertSame('/hr/employees/__EMPLOYEE__', $hrPayload['hr_employee_options']['show_url_template']);
        $this->assertSame('/hr/employees/__EMPLOYEE__/profile-sections', $hrPayload['hr_employee_options']['profile_sections_url_template']);
        $this->assertSame('/hr/employees/__EMPLOYEE__/payroll-summary', $hrPayload['hr_employee_options']['payroll_summary_url_template']);
        $this->assertSame('/hr/employees/__EMPLOYEE__/audit-events', $hrPayload['hr_employee_options']['audit_events_url_template']);
        $this->assertSame('/hr/employees', $hrPayload['hr_employee_options']['store_url']);
        $this->assertSame('/hr/attendance-records', $hrPayload['hr_employee_options']['attendance_records_url']);
        $this->assertSame('/hr/leave-balances', $hrPayload['hr_employee_options']['leave_balances_url']);
        $this->assertSame('/hr/leave-requests', $hrPayload['hr_employee_options']['leave_requests_url']);
        $this->assertSame('/hr/assets', $hrPayload['hr_employee_options']['assets_url']);
        $this->assertSame('hr_employees', $hrPayload['hr_employee_options']['import_type']);
        $this->assertSame('/settings/data-imports/preview', $hrPayload['hr_employee_options']['import_preview_url']);
        $this->assertSame('/settings/data-imports/__BATCH__/post', $hrPayload['hr_employee_options']['import_post_url_template']);
        $this->assertContains('employee_code', $hrPayload['hr_employee_options']['import_required_headers']);
        $this->assertContains('bank_account', $hrPayload['hr_employee_options']['import_required_headers']);
        $this->assertTrue($hrPayload['hr_employee_options']['can_create']);
        $this->assertTrue($hrPayload['hr_employee_options']['can_update_profile_sections']);
        $this->assertTrue($hrPayload['hr_employee_options']['can_view_attendance_records']);
        $this->assertTrue($hrPayload['hr_employee_options']['can_view_leave_records']);
        $this->assertTrue($hrPayload['hr_employee_options']['can_view_asset_records']);
        $this->assertTrue($hrPayload['hr_employee_options']['can_view_payroll_records']);
        $this->assertTrue($hrPayload['hr_employee_options']['can_view_employee_audit_events']);
        $this->assertContains('B360D', collect($hrPayload['hr_employee_options']['companies'])->pluck('code')->all());
        $this->assertContains('PNQ-HO', collect($hrPayload['hr_employee_options']['branches'])->pluck('code')->all());
        $this->assertContains('SKY-PUN', collect($hrPayload['hr_employee_options']['projects'])->pluck('code')->all());
        $this->assertContains('EMP-0018', collect($hrPayload['hr_employee_options']['managers'])->pluck('employee_code')->all());
        $this->assertContains('full_time', collect($hrPayload['hr_employee_options']['employment_types'])->pluck('value')->all());
        $this->assertSame('laravel-sqlite', $hrPayload['hr_operations_options']['source']);
        $this->assertSame('/hr/assets', $hrPayload['hr_operations_options']['assets_index_url']);
        $this->assertSame('/hr/assets', $hrPayload['hr_operations_options']['assets_store_url']);
        $this->assertSame('/hr/assets/__ASSET__/assign', $hrPayload['hr_operations_options']['asset_assign_url_template']);
        $this->assertSame('/hr/assets/__ASSET__/recover', $hrPayload['hr_operations_options']['asset_recover_url_template']);
        $this->assertSame('/hr/employee-documents', $hrPayload['hr_operations_options']['employee_documents_index_url']);
        $this->assertSame('/hr/employees/__EMPLOYEE__/documents/__DOCUMENT__/approve', $hrPayload['hr_operations_options']['employee_document_approve_url_template']);
        $this->assertSame('/hr/expense-claims', $hrPayload['hr_operations_options']['claims_index_url']);
        $this->assertSame('/hr/expense-claims/__CLAIM__/approve', $hrPayload['hr_operations_options']['claim_approve_url_template']);
        $this->assertSame('/hr/expense-claims/__CLAIM__/reject', $hrPayload['hr_operations_options']['claim_reject_url_template']);
        $this->assertSame('/hr/expense-claims/__CLAIM__/pay', $hrPayload['hr_operations_options']['claim_pay_url_template']);
        $this->assertSame('/hr/loans', $hrPayload['hr_operations_options']['loans_index_url']);
        $this->assertSame('/hr/loans/__LOAN__/approve', $hrPayload['hr_operations_options']['loan_approve_url_template']);
        $this->assertSame('/hr/loans/__LOAN__/reject', $hrPayload['hr_operations_options']['loan_reject_url_template']);
        $this->assertSame('/hr/loans/__LOAN__/disburse', $hrPayload['hr_operations_options']['loan_disburse_url_template']);
        $this->assertSame('/hr/helpdesk-tickets', $hrPayload['hr_operations_options']['helpdesk_index_url']);
        $this->assertSame('/hr/helpdesk-tickets', $hrPayload['hr_operations_options']['helpdesk_store_url']);
        $this->assertSame('/hr/helpdesk-tickets/__TICKET__/assign', $hrPayload['hr_operations_options']['helpdesk_assign_url_template']);
        $this->assertSame('/hr/helpdesk-tickets/__TICKET__/resolve', $hrPayload['hr_operations_options']['helpdesk_resolve_url_template']);
        $this->assertSame('/hr/helpdesk-tickets/__TICKET__/close', $hrPayload['hr_operations_options']['helpdesk_close_url_template']);
        $this->assertTrue($hrPayload['hr_operations_options']['can_view_assets']);
        $this->assertTrue($hrPayload['hr_operations_options']['can_create_assets']);
        $this->assertTrue($hrPayload['hr_operations_options']['can_assign_assets']);
        $this->assertTrue($hrPayload['hr_operations_options']['can_recover_assets']);
        $this->assertTrue($hrPayload['hr_operations_options']['can_view_employee_documents']);
        $this->assertTrue($hrPayload['hr_operations_options']['can_approve_employee_documents']);
        $this->assertTrue($hrPayload['hr_operations_options']['can_view_claims']);
        $this->assertTrue($hrPayload['hr_operations_options']['can_view_loans']);
        $this->assertTrue($hrPayload['hr_operations_options']['can_view_helpdesk']);
        $this->assertTrue($hrPayload['hr_operations_options']['can_create_helpdesk']);
        $this->assertTrue($hrPayload['hr_operations_options']['can_approve_claims']);
        $this->assertFalse($hrPayload['hr_operations_options']['can_pay_claims']);
        $this->assertTrue($hrPayload['hr_operations_options']['can_approve_loans']);
        $this->assertFalse($hrPayload['hr_operations_options']['can_disburse_loans']);
        $this->assertTrue($hrPayload['hr_operations_options']['can_assign_helpdesk']);
        $this->assertTrue($hrPayload['hr_operations_options']['can_resolve_helpdesk']);
        $this->assertTrue($hrPayload['hr_operations_options']['can_close_helpdesk']);
        $this->assertContains('assigned', $hrPayload['hr_operations_options']['asset_statuses']);
        $this->assertContains('approved', $hrPayload['hr_operations_options']['employee_document_statuses']);
        $this->assertContains('paid', $hrPayload['hr_operations_options']['claim_statuses']);
        $this->assertContains('disbursed', $hrPayload['hr_operations_options']['loan_statuses']);
        $this->assertContains('resolved', $hrPayload['hr_operations_options']['helpdesk_statuses']);
        $this->assertNotEmpty($hrPayload['hr_operations_options']['asset_assignable_employees']);
        $this->assertNotEmpty($hrPayload['hr_operations_options']['helpdesk_assignees']);
        $this->assertNotEmpty($hrPayload['hr_operations_options']['helpdesk_request_employees']);
        $this->assertContains('B360D', collect($hrPayload['hr_operations_options']['companies'])->pluck('code')->all());
        $this->assertSame('laravel-sqlite', $compliancePayload['hr_compliance_options']['source']);
        $this->assertSame('/hr/compliance-rule-settings', $compliancePayload['hr_compliance_options']['index_url']);
        $this->assertSame('/hr/compliance-rule-settings', $compliancePayload['hr_compliance_options']['store_url']);
        $this->assertSame('/hr/compliance-rule-settings/__SETTING__/approve', $compliancePayload['hr_compliance_options']['approve_url_template']);
        $this->assertContains('payroll.tax_rules', collect($compliancePayload['hr_compliance_options']['setting_keys'])->pluck('value')->all());
        $this->assertContains('hr.statutory.pf', collect($compliancePayload['hr_compliance_options']['setting_keys'])->pluck('value')->all());
        $this->assertArrayHasKey('default_hr_statutory', $compliancePayload['hr_compliance_options']['presets']);
        $this->assertNotEmpty($compliancePayload['hr_compliance_options']['settings']);
        $this->assertSame('laravel-sqlite', $viewerPayload['hr_employee_options']['source']);
        $this->assertSame('/hr/employees', $viewerPayload['hr_employee_options']['index_url']);
        $this->assertSame('/hr/employees/__EMPLOYEE__', $viewerPayload['hr_employee_options']['show_url_template']);
        $this->assertSame('/hr/employees/__EMPLOYEE__/profile-sections', $viewerPayload['hr_employee_options']['profile_sections_url_template']);
        $this->assertSame('/hr/employees/__EMPLOYEE__/payroll-summary', $viewerPayload['hr_employee_options']['payroll_summary_url_template']);
        $this->assertSame('/hr/employees/__EMPLOYEE__/audit-events', $viewerPayload['hr_employee_options']['audit_events_url_template']);
        $this->assertFalse($viewerPayload['hr_employee_options']['can_create']);
        $this->assertFalse($viewerPayload['hr_employee_options']['can_import']);
        $this->assertFalse($viewerPayload['hr_employee_options']['can_update_profile_sections']);
        $this->assertFalse($viewerPayload['hr_employee_options']['can_view_attendance_records']);
        $this->assertFalse($viewerPayload['hr_employee_options']['can_view_leave_records']);
        $this->assertFalse($viewerPayload['hr_employee_options']['can_view_asset_records']);
        $this->assertFalse($viewerPayload['hr_employee_options']['can_view_payroll_records']);
        $this->assertFalse($viewerPayload['hr_employee_options']['can_view_employee_audit_events']);
        $this->assertNull($viewerPayload['hr_attendance_options']);
        $this->assertNull($viewerPayload['hr_operations_options']);
        $this->assertSame('laravel-sqlite', $hrPayload['hr_report_options']['source']);
        $this->assertSame('/hr/employees/export', $hrPayload['hr_report_options']['export_url']);
        $this->assertTrue($hrPayload['hr_report_options']['can_export']);
        $this->assertContains('csv', collect($hrPayload['hr_report_options']['formats'])->pluck('value')->all());
        $this->assertContains('on_notice', collect($hrPayload['hr_report_options']['status_filters'])->pluck('value')->all());
        $this->assertContains('B360D · Builder360 Developers Pvt Ltd', collect($hrPayload['hr_report_options']['company_filters'])->pluck('label')->all());
        $this->assertContains('HR', collect($hrPayload['hr_report_options']['department_filters'])->pluck('value')->all());
        $this->assertNotEmpty($hrPayload['hr_report_options']['employee_filters']);
        $this->assertContains('Employee Master Register', collect($hrPayload['hr_report_options']['report_catalog'])->pluck('value')->all());
        $this->assertArrayHasKey('employees_in_scope', $hrPayload['hr_report_options']['summary']);
        $this->assertArrayHasKey('average_attendance_percent', $hrPayload['hr_report_options']['summary']);
        $this->assertArrayHasKey('departments', $hrPayload['hr_report_options']['summary']);
        $this->assertArrayHasKey('exports_audited', $hrPayload['hr_report_options']['summary']);
        $this->assertContains('Employee Code', $hrPayload['hr_report_options']['default_columns']);
        $this->assertSame('/settings/system-settings', $hrPayload['hr_report_options']['custom_mis_store_url']);
        $this->assertSame('hr.custom_mis_reports', $hrPayload['hr_report_options']['custom_mis_setting_key']);
        $this->assertArrayHasKey('can_create_custom_mis', $hrPayload['hr_report_options']);
        $this->assertTrue($hrPayload['hr_report_options']['compensation_visible']);
        $this->assertNull($partnerPayload['hr_attendance_options']);
        $this->assertNull($partnerPayload['hr_helpdesk_options']);
        $this->assertNull($partnerPayload['hr_leave_options']);
        $this->assertNull($partnerPayload['hr_self_service_options']);
        $this->assertNull($partnerPayload['hr_recruitment_options']);
        $this->assertNull($partnerPayload['hr_performance_options']);
        $this->assertNull($partnerPayload['hr_lifecycle_options']);
        $this->assertNull($partnerPayload['hr_dashboard_options']);
        $this->assertNull($partnerPayload['hr_employee_options']);
        $this->assertNull($partnerPayload['hr_operations_options']);
        $this->assertNull($partnerPayload['hr_compliance_options']);
        $this->assertNull($partnerPayload['hr_report_options']);
    }

    public function test_dashboard_bootstrap_exposes_payroll_workspace_options_to_authorized_users_only(): void
    {
        $this->seed();

        $payroll = User::where('email', 'kavita.shah@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();

        $payrollPayload = app(Builder360Bootstrap::class)->forUser($payroll);
        $financePayload = app(Builder360Bootstrap::class)->forUser($finance);
        $partnerPayload = app(Builder360Bootstrap::class)->forUser($partner);

        $this->assertSame('laravel-sqlite', $payrollPayload['hr_payroll_options']['source']);
        $this->assertSame('/payroll/components', $payrollPayload['hr_payroll_options']['components_index_url']);
        $this->assertSame('/payroll/salary-structures', $payrollPayload['hr_payroll_options']['salary_structures_index_url']);
        $this->assertSame('/payroll/runs', $payrollPayload['hr_payroll_options']['payroll_runs_index_url']);
        $this->assertSame('/payroll/runs', $payrollPayload['hr_payroll_options']['payroll_runs_generate_url']);
        $this->assertSame('/payroll/runs/__RUN__/approve', $payrollPayload['hr_payroll_options']['payroll_run_approve_url_template']);
        $this->assertSame('/payroll/bank-transfer-batches', $payrollPayload['hr_payroll_options']['bank_transfer_batches_index_url']);
        $this->assertSame('/payroll/runs/__RUN__/bank-transfer-batches', $payrollPayload['hr_payroll_options']['bank_transfer_batch_prepare_url_template']);
        $this->assertSame('/payroll/bank-transfer-batches/__BATCH__/release', $payrollPayload['hr_payroll_options']['bank_transfer_batch_release_url_template']);
        $this->assertSame('/payroll/tax-documents', $payrollPayload['hr_payroll_options']['tax_documents_index_url']);
        $this->assertSame('/payroll/tax-documents', $payrollPayload['hr_payroll_options']['tax_documents_store_url']);
        $this->assertSame('/payroll/tax-documents/__DOCUMENT__/issue', $payrollPayload['hr_payroll_options']['tax_document_issue_url_template']);
        $this->assertSame('/payroll/commission-rules', $payrollPayload['hr_payroll_options']['commission_rules_index_url']);
        $this->assertSame('/payroll/commission-runs', $payrollPayload['hr_payroll_options']['commission_runs_index_url']);
        $this->assertSame('/payroll/commission-runs', $payrollPayload['hr_payroll_options']['commission_runs_store_url']);
        $this->assertSame('/payroll/commission-runs/__RUN__/approve', $payrollPayload['hr_payroll_options']['commission_run_approve_url_template']);
        $this->assertTrue($payrollPayload['hr_payroll_options']['can_generate_payroll_run']);
        $this->assertTrue($payrollPayload['hr_payroll_options']['can_prepare_bank_transfer_batch']);
        $this->assertTrue($payrollPayload['hr_payroll_options']['can_create_commission_run']);
        $this->assertTrue($payrollPayload['hr_payroll_options']['can_generate_tax_document']);
        $this->assertFalse($payrollPayload['hr_payroll_options']['can_approve_payroll_run']);
        $this->assertFalse($payrollPayload['hr_payroll_options']['can_release_bank_transfer_batch']);
        $this->assertSame('HDFC Bank', $payrollPayload['hr_payroll_options']['default_bank_batch']['bank_name']);
        $this->assertNotEmpty($payrollPayload['hr_payroll_options']['employees']);
        $this->assertContains('EMP-0030', collect($payrollPayload['hr_payroll_options']['employees'])->pluck('employee_code')->all());

        $this->assertSame('laravel-sqlite', $financePayload['hr_payroll_options']['source']);
        $this->assertTrue($financePayload['hr_payroll_options']['can_approve_payroll_run']);
        $this->assertTrue($financePayload['hr_payroll_options']['can_release_bank_transfer_batch']);
        $this->assertFalse($financePayload['hr_payroll_options']['can_generate_payroll_run']);
        $this->assertNull($partnerPayload['hr_payroll_options']);
    }

    public function test_dashboard_bootstrap_exposes_possession_handover_options_to_internal_users_only(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();
        $unit = ProjectUnit::where('unit_code', 'GRN-B-0802')->firstOrFail();
        $customer = Customer::where('code', 'CUS-1002')->firstOrFail();
        $lead = Lead::where('lead_code', 'LD-1002')->firstOrFail();

        Booking::create([
            'company_id' => $unit->company_id,
            'project_id' => $unit->project_id,
            'project_unit_id' => $unit->id,
            'customer_id' => $customer->id,
            'lead_id' => $lead->id,
            'partner_id' => $lead->partner_id,
            'booked_by_user_id' => $sales->id,
            'booking_code' => 'BK-BOOTSTRAP-HANDOVER',
            'status' => 'confirmed',
            'booked_on' => now()->subDays(7)->toDateString(),
            'agreement_value' => 1500000,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'net_receivable' => 1500000,
            'booking_amount' => 150000,
            'commercials' => ['source' => 'bootstrap-test'],
        ]);

        $salesPayload = app(Builder360Bootstrap::class)->forUser($sales);
        $financePayload = app(Builder360Bootstrap::class)->forUser($finance);
        $partnerPayload = app(Builder360Bootstrap::class)->forUser($partner);

        $this->assertSame('laravel-sqlite', $salesPayload['possession_handover_options']['source']);
        $this->assertSame('/possession/handovers', $salesPayload['possession_handover_options']['index_url']);
        $this->assertSame('/possession/handovers', $salesPayload['possession_handover_options']['store_url']);
        $this->assertSame('/possession/handovers/__HANDOVER__/letter', $salesPayload['possession_handover_options']['letter_url_template']);
        $this->assertSame('/possession/handovers/__HANDOVER__/complete', $salesPayload['possession_handover_options']['complete_url_template']);
        $this->assertSame('/possession/snags', $salesPayload['possession_handover_options']['snags_store_url']);
        $this->assertSame('/possession/snags/__SNAG__/resolve', $salesPayload['possession_handover_options']['snag_resolve_url_template']);
        $this->assertTrue($salesPayload['possession_handover_options']['can_create']);
        $this->assertTrue($salesPayload['possession_handover_options']['can_issue_letter']);
        $this->assertFalse($salesPayload['possession_handover_options']['can_complete']);
        $this->assertTrue($salesPayload['possession_handover_options']['can_report_snag']);
        $this->assertTrue($salesPayload['possession_handover_options']['can_resolve_snag']);
        $this->assertContains('BK-BOOTSTRAP-HANDOVER', collect($salesPayload['possession_handover_options']['bookings'])->pluck('booking_code')->all());
        $this->assertNotContains('BK-1001', collect($salesPayload['possession_handover_options']['bookings'])->pluck('booking_code')->all());
        $this->assertTrue(PossessionHandover::where('booking_id', Booking::where('booking_code', 'BK-1001')->value('id'))->exists());
        $this->assertContains('PH-1001', collect($salesPayload['possession_handover_options']['handovers'])->pluck('handover_number')->all());
        $this->assertContains('SNAG-1001', collect($salesPayload['possession_handover_options']['snags'])->pluck('snag_number')->all());
        $this->assertSame(
            PossessionHandover::where('company_id', $sales->company_id)->count(),
            $salesPayload['possession_handover_options']['summary']['total_handovers'],
        );
        $this->assertSame(
            PossessionHandover::where('company_id', $sales->company_id)->where('status', 'completed')->count(),
            $salesPayload['possession_handover_options']['summary']['completed_handovers'],
        );
        $this->assertSame(
            PossessionHandover::where('company_id', $sales->company_id)->where('financial_outstanding', '>', 0)->count(),
            $salesPayload['possession_handover_options']['summary']['payment_pending'],
        );
        $this->assertSame(
            HandoverSnag::where('company_id', $sales->company_id)->where('status', 'open')->count(),
            $salesPayload['possession_handover_options']['summary']['open_snags'],
        );

        $bootstrapBooking = collect($salesPayload['possession_handover_options']['bookings'])->firstWhere('booking_code', 'BK-BOOTSTRAP-HANDOVER');
        $this->assertSame($unit->unit_code, $bootstrapBooking['unit']['unit_code']);
        $this->assertSame($customer->name, $bootstrapBooking['customer']['name']);
        $this->assertEqualsWithDelta(1500000, $bootstrapBooking['financial_outstanding'], 0.01);
        $bootstrapHandover = collect($salesPayload['possession_handover_options']['handovers'])->firstWhere('handover_number', 'PH-1001');
        $this->assertSame('BK-1001', $bootstrapHandover['booking_code']);
        $this->assertSame('blocked', $bootstrapHandover['status']);
        $this->assertGreaterThan(0, $bootstrapHandover['open_snags_count']);
        $this->assertNotEmpty($bootstrapHandover['checklist']);
        $bootstrapSnag = collect($salesPayload['possession_handover_options']['snags'])->firstWhere('snag_number', 'SNAG-1001');
        $this->assertSame($bootstrapHandover['id'], $bootstrapSnag['possession_handover_id']);
        $this->assertSame('open', $bootstrapSnag['status']);

        $this->assertSame('laravel-sqlite', $financePayload['possession_handover_options']['source']);
        $this->assertFalse($financePayload['possession_handover_options']['can_create']);
        $this->assertFalse($financePayload['possession_handover_options']['can_issue_letter']);
        $this->assertTrue($financePayload['possession_handover_options']['can_complete']);
        $this->assertFalse($financePayload['possession_handover_options']['can_report_snag']);
        $this->assertFalse($financePayload['possession_handover_options']['can_resolve_snag']);
        $this->assertSame('/possession/handovers/__HANDOVER__/complete', $financePayload['possession_handover_options']['complete_url_template']);
        $this->assertNull($partnerPayload['possession_handover_options']);
    }

    public function test_dashboard_bootstrap_exposes_after_sales_options_to_internal_users_only(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();

        $salesPayload = app(Builder360Bootstrap::class)->forUser($sales);
        $financePayload = app(Builder360Bootstrap::class)->forUser($finance);
        $partnerPayload = app(Builder360Bootstrap::class)->forUser($partner);

        $this->assertSame('laravel-sqlite', $salesPayload['after_sales_options']['source']);
        $this->assertSame('/after-sales/tickets', $salesPayload['after_sales_options']['index_url']);
        $this->assertSame('/after-sales/tickets', $salesPayload['after_sales_options']['store_url']);
        $this->assertSame('/after-sales/tickets/__TICKET__/assign', $salesPayload['after_sales_options']['assign_url_template']);
        $this->assertSame('/after-sales/tickets/__TICKET__/resolve', $salesPayload['after_sales_options']['resolve_url_template']);
        $this->assertSame('/after-sales/tickets/__TICKET__/close', $salesPayload['after_sales_options']['close_url_template']);
        $this->assertSame('/after-sales/work-orders', $salesPayload['after_sales_options']['work_orders_url']);
        $this->assertTrue($salesPayload['after_sales_options']['can_create']);
        $this->assertTrue($salesPayload['after_sales_options']['can_assign']);
        $this->assertTrue($salesPayload['after_sales_options']['can_resolve']);
        $this->assertTrue($salesPayload['after_sales_options']['can_close']);
        $this->assertContains('maintenance', collect($salesPayload['after_sales_options']['categories'])->pluck('value')->all());
        $this->assertContains('critical', collect($salesPayload['after_sales_options']['priorities'])->pluck('value')->all());
        $this->assertContains('AST-1001', collect($salesPayload['after_sales_options']['tickets'])->pluck('ticket_number')->all());
        $this->assertNotEmpty($salesPayload['after_sales_options']['bookings']);
        $this->assertNotEmpty($salesPayload['after_sales_options']['assignees']);
        $this->assertSame(
            ServiceTicket::where('company_id', $sales->company_id)->whereIn('status', ['open', 'assigned', 'in_progress'])->count(),
            $salesPayload['after_sales_options']['summary']['open_tickets'],
        );

        $this->assertSame('laravel-sqlite', $financePayload['after_sales_options']['source']);
        $this->assertFalse($financePayload['after_sales_options']['can_create']);
        $this->assertFalse($financePayload['after_sales_options']['can_assign']);
        $this->assertFalse($financePayload['after_sales_options']['can_resolve']);
        $this->assertNull($partnerPayload['after_sales_options']);
    }

    public function test_dashboard_bootstrap_exposes_legal_compliance_options_to_authorized_users_only(): void
    {
        $this->seed();

        $compliance = User::where('email', 'meera.kapoor@builder360.test')->firstOrFail();
        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();

        $payload = app(Builder360Bootstrap::class)->forUser($compliance);
        $partnerPayload = app(Builder360Bootstrap::class)->forUser($partner);

        $this->assertSame('laravel-sqlite', $payload['legal_compliance_options']['source']);
        $this->assertSame('/legal/rera-registrations', $payload['legal_compliance_options']['rera_index_url']);
        $this->assertSame('/legal/project-approvals', $payload['legal_compliance_options']['approval_index_url']);
        $this->assertSame('/legal/compliance-obligations', $payload['legal_compliance_options']['obligation_index_url']);
        $this->assertSame('/legal/rera-registrations/__RERA__/verify', $payload['legal_compliance_options']['rera_verify_url_template']);
        $this->assertSame('/legal/project-approvals/__APPROVAL__/verify', $payload['legal_compliance_options']['approval_verify_url_template']);
        $this->assertSame('/legal/compliance-obligations/__OBLIGATION__/complete', $payload['legal_compliance_options']['obligation_complete_url_template']);
        $this->assertTrue($payload['legal_compliance_options']['can_create']);
        $this->assertTrue($payload['legal_compliance_options']['can_verify']);
        $this->assertTrue($payload['legal_compliance_options']['can_complete']);
        $this->assertNotEmpty($payload['legal_compliance_options']['projects']);
        $this->assertSame(
            Project::where('company_id', $compliance->company_id)->where('status', 'active')->orderBy('code')->value('code'),
            $payload['legal_compliance_options']['projects'][0]['code'],
        );
        $this->assertNotEmpty($payload['legal_compliance_options']['rera_registrations']);
        $this->assertNotEmpty($payload['legal_compliance_options']['project_approvals']);
        $this->assertNotEmpty($payload['legal_compliance_options']['compliance_obligations']);
        $this->assertSame(
            ReraRegistration::where('company_id', $compliance->company_id)->distinct('project_id')->count('project_id'),
            $payload['legal_compliance_options']['summary']['rera_projects'],
        );
        $this->assertSame(
            ProjectApproval::where('company_id', $compliance->company_id)->whereIn('status', ['approved', 'verified'])->count(),
            $payload['legal_compliance_options']['summary']['approvals_valid'],
        );
        $this->assertSame(
            ComplianceObligation::where('company_id', $compliance->company_id)->where('status', 'open')->whereDate('due_on', '<=', now()->addDays(30)->toDateString())->count(),
            $payload['legal_compliance_options']['summary']['compliance_due'],
        );
        $this->assertNull($partnerPayload['legal_compliance_options']);
    }

    public function test_dashboard_bootstrap_exposes_document_management_options_to_document_users_only(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $compliance = User::where('email', 'meera.kapoor@builder360.test')->firstOrFail();
        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();

        $salesPayload = app(Builder360Bootstrap::class)->forUser($sales);
        $compliancePayload = app(Builder360Bootstrap::class)->forUser($compliance);
        $partnerPayload = app(Builder360Bootstrap::class)->forUser($partner);

        $this->assertSame('laravel-sqlite', $salesPayload['document_management_options']['source']);
        $this->assertSame('/documents', $salesPayload['document_management_options']['index_url']);
        $this->assertSame('/documents/categories', $salesPayload['document_management_options']['categories_url']);
        $this->assertSame('/documents', $salesPayload['document_management_options']['store_url']);
        $this->assertSame('/documents/__DOCUMENT__/approve', $salesPayload['document_management_options']['approve_url_template']);
        $this->assertSame('/documents/__DOCUMENT__/download', $salesPayload['document_management_options']['download_url_template']);
        $this->assertTrue($salesPayload['document_management_options']['can_create']);
        $this->assertFalse($salesPayload['document_management_options']['can_approve']);
        $this->assertNotEmpty($salesPayload['document_management_options']['documents']);
        $this->assertNotEmpty($salesPayload['document_management_options']['categories']);
        $this->assertNotEmpty($salesPayload['document_management_options']['owners']['project']);
        $this->assertContains('pdf', $salesPayload['document_management_options']['file_policy']['allowed_extensions']);
        $this->assertSame(
            ManagedDocument::where('company_id', $sales->company_id)->count(),
            $salesPayload['document_management_options']['summary']['total_documents'],
        );
        $this->assertSame(
            DocumentCategory::query()
                ->where('is_active', true)
                ->where(function ($query) use ($sales): void {
                    $query->whereNull('company_id')->orWhere('company_id', $sales->company_id);
                })
                ->count(),
            count($salesPayload['document_management_options']['categories']),
        );

        $this->assertSame('laravel-sqlite', $compliancePayload['document_management_options']['source']);
        $this->assertFalse($compliancePayload['document_management_options']['can_create']);
        $this->assertTrue($compliancePayload['document_management_options']['can_approve']);
        $this->assertNull($partnerPayload['document_management_options']);
    }

    public function test_dashboard_bootstrap_exposes_maintenance_society_options_to_authorized_users_only(): void
    {
        $this->seed();

        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();

        $directorPayload = app(Builder360Bootstrap::class)->forUser($director);
        $financePayload = app(Builder360Bootstrap::class)->forUser($finance);
        $partnerPayload = app(Builder360Bootstrap::class)->forUser($partner);

        $this->assertSame('laravel-sqlite', $directorPayload['maintenance_society_options']['source']);
        $this->assertSame('/maintenance/societies', $directorPayload['maintenance_society_options']['societies_index_url']);
        $this->assertSame('/maintenance/societies', $directorPayload['maintenance_society_options']['societies_store_url']);
        $this->assertSame('/maintenance/societies/__SOCIETY__/status', $directorPayload['maintenance_society_options']['society_status_url_template']);
        $this->assertSame('/maintenance/handover-items', $directorPayload['maintenance_society_options']['handover_items_index_url']);
        $this->assertSame('/maintenance/handover-items/__HANDOVER_ITEM__', $directorPayload['maintenance_society_options']['handover_item_update_url_template']);
        $this->assertSame('/maintenance/handover-items/__HANDOVER_ITEM__/sign-off', $directorPayload['maintenance_society_options']['handover_item_signoff_url_template']);
        $this->assertSame('/maintenance/dues', $directorPayload['maintenance_society_options']['dues_index_url']);
        $this->assertSame('/maintenance/dues', $directorPayload['maintenance_society_options']['dues_store_url']);
        $this->assertSame('/maintenance/dues/__DUE__/mark-paid', $directorPayload['maintenance_society_options']['due_mark_paid_url_template']);
        $this->assertSame('/maintenance/dues/__DUE__/remind', $directorPayload['maintenance_society_options']['due_remind_url_template']);
        $this->assertTrue($directorPayload['maintenance_society_options']['can_create_society']);
        $this->assertTrue($directorPayload['maintenance_society_options']['can_update_handover']);
        $this->assertTrue($directorPayload['maintenance_society_options']['can_raise_due']);
        $this->assertNotEmpty($directorPayload['maintenance_society_options']['societies']);
        $this->assertNotEmpty($directorPayload['maintenance_society_options']['handover_items']);
        $this->assertNotEmpty($directorPayload['maintenance_society_options']['maintenance_dues']);
        $this->assertSame(
            SocietyFormation::count(),
            $directorPayload['maintenance_society_options']['summary']['societies_formed']
            + $directorPayload['maintenance_society_options']['summary']['societies_in_progress'],
        );
        $this->assertSame(
            CommonAreaHandoverItem::whereIn('status', ['pending', 'in_progress', 'pending_snags'])->count(),
            $directorPayload['maintenance_society_options']['summary']['pending_common_works'],
        );
        $this->assertSame(
            round((float) MaintenanceDue::whereIn('status', ['due', 'overdue'])->sum('balance_amount'), 2),
            $directorPayload['maintenance_society_options']['summary']['maintenance_due'],
        );

        $this->assertSame('laravel-sqlite', $financePayload['maintenance_society_options']['source']);
        $this->assertFalse($financePayload['maintenance_society_options']['can_create_society']);
        $this->assertFalse($financePayload['maintenance_society_options']['can_update_handover']);
        $this->assertTrue($financePayload['maintenance_society_options']['can_view_due']);
        $this->assertTrue($financePayload['maintenance_society_options']['can_raise_due']);
        $this->assertTrue($financePayload['maintenance_society_options']['can_mark_due_paid']);
        $this->assertNull($partnerPayload['maintenance_society_options']);
    }

    public function test_dashboard_bootstrap_exposes_construction_boq_options_to_construction_users_only(): void
    {
        $this->seed();

        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();

        $constructionPayload = app(Builder360Bootstrap::class)->forUser($construction);
        $financePayload = app(Builder360Bootstrap::class)->forUser($finance);
        $partnerPayload = app(Builder360Bootstrap::class)->forUser($partner);

        $this->assertSame('laravel-sqlite', $constructionPayload['construction_boq_options']['source']);
        $this->assertSame('/construction/boq-items', $constructionPayload['construction_boq_options']['boq_index_url']);
        $this->assertSame('/construction/boq-items', $constructionPayload['construction_boq_options']['boq_store_url']);
        $this->assertSame('/construction/contractor-measurements', $constructionPayload['construction_boq_options']['measurement_index_url']);
        $this->assertSame('/construction/contractor-measurements', $constructionPayload['construction_boq_options']['measurement_store_url']);
        $this->assertSame('/construction/contractor-measurements/__MEASUREMENT__/approve', $constructionPayload['construction_boq_options']['measurement_approve_url_template']);
        $this->assertSame('/construction/contractor-measurements/__MEASUREMENT__/reject', $constructionPayload['construction_boq_options']['measurement_reject_url_template']);
        $this->assertSame('/construction/contractor-bills', $constructionPayload['construction_boq_options']['bill_index_url']);
        $this->assertSame('/construction/contractor-bills', $constructionPayload['construction_boq_options']['bill_store_url']);
        $this->assertSame('/construction/contractor-bills/__BILL__/approve', $constructionPayload['construction_boq_options']['bill_approve_url_template']);
        $this->assertSame('/construction/contractor-bills/__BILL__/mark-paid', $constructionPayload['construction_boq_options']['bill_mark_paid_url_template']);
        $this->assertTrue($constructionPayload['construction_boq_options']['can_create_boq']);
        $this->assertTrue($constructionPayload['construction_boq_options']['can_create_measurement']);
        $this->assertTrue($constructionPayload['construction_boq_options']['can_create_bill']);
        $this->assertNotEmpty($constructionPayload['construction_boq_options']['projects']);
        $this->assertNotEmpty($constructionPayload['construction_boq_options']['contractors']);
        $this->assertNotEmpty($constructionPayload['construction_boq_options']['boq_items']);
        $this->assertSame(
            BoqItem::where('company_id', $construction->company_id)->count(),
            $constructionPayload['construction_boq_options']['summary']['boq_items'],
        );
        $this->assertSame(
            ContractorMeasurement::where('company_id', $construction->company_id)->where('status', 'submitted')->count(),
            $constructionPayload['construction_boq_options']['summary']['pending_measurements'],
        );
        $this->assertSame(
            ContractorBill::where('company_id', $construction->company_id)->where('status', 'submitted')->count(),
            $constructionPayload['construction_boq_options']['summary']['pending_bills'],
        );

        $this->assertSame('laravel-sqlite', $financePayload['construction_boq_options']['source']);
        $this->assertFalse($financePayload['construction_boq_options']['can_create_boq']);
        $this->assertFalse($financePayload['construction_boq_options']['can_create_measurement']);
        $this->assertFalse($financePayload['construction_boq_options']['can_create_bill']);
        $this->assertTrue($financePayload['construction_boq_options']['can_mark_bill_paid']);
        $this->assertNull($partnerPayload['construction_boq_options']);
    }

    public function test_dashboard_bootstrap_exposes_construction_site_and_procurement_options_to_authorized_internal_users_only(): void
    {
        $this->seed();

        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();

        $constructionPayload = app(Builder360Bootstrap::class)->forUser($construction);
        $financePayload = app(Builder360Bootstrap::class)->forUser($finance);
        $partnerPayload = app(Builder360Bootstrap::class)->forUser($partner);

        $this->assertSame('laravel-sqlite', $constructionPayload['construction_site_options']['source']);
        $this->assertSame('/construction/milestones', $constructionPayload['construction_site_options']['milestones_index_url']);
        $this->assertSame('/construction/daily-progress-reports', $constructionPayload['construction_site_options']['daily_reports_index_url']);
        $this->assertSame('/construction/daily-progress-reports', $constructionPayload['construction_site_options']['daily_reports_store_url']);
        $this->assertSame('/construction/daily-progress-reports/__REPORT__/approve', $constructionPayload['construction_site_options']['daily_report_approve_url_template']);
        $this->assertSame('/procurement/stock-items', $constructionPayload['construction_site_options']['stock_items_index_url']);
        $this->assertSame('/procurement/stock-issues', $constructionPayload['construction_site_options']['stock_issue_store_url']);
        $this->assertSame('/procurement/requisitions', $constructionPayload['construction_site_options']['requisitions_index_url']);
        $this->assertSame('/procurement/requisitions/__REQUISITION__/approve', $constructionPayload['construction_site_options']['requisition_approve_url_template']);
        $this->assertSame('/procurement/purchase-orders', $constructionPayload['construction_site_options']['purchase_orders_index_url']);
        $this->assertSame('/procurement/purchase-orders/__PURCHASE_ORDER__/approve', $constructionPayload['construction_site_options']['purchase_order_approve_url_template']);
        $this->assertSame('/procurement/goods-receipts', $constructionPayload['construction_site_options']['goods_receipts_index_url']);
        $this->assertSame('/procurement/goods-receipts', $constructionPayload['construction_site_options']['goods_receipts_store_url']);
        $this->assertSame('/procurement/vendors', $constructionPayload['construction_site_options']['vendors_index_url']);
        $this->assertSame('/procurement/vendors', $constructionPayload['construction_site_options']['vendors_store_url']);
        $this->assertSame('/procurement/vendors/__VENDOR__/performance', $constructionPayload['construction_site_options']['vendor_performance_url_template']);
        $this->assertNotEmpty($constructionPayload['construction_site_options']['companies']);
        $this->assertSame('material', $constructionPayload['construction_site_options']['vendor_type_options'][0]['value']);
        $this->assertSame('active', $constructionPayload['construction_site_options']['vendor_status_options'][0]['value']);
        $this->assertTrue($constructionPayload['construction_site_options']['can_view_construction']);
        $this->assertTrue($constructionPayload['construction_site_options']['can_create_milestone']);
        $this->assertTrue($constructionPayload['construction_site_options']['can_create_daily_report']);
        $this->assertTrue($constructionPayload['construction_site_options']['can_view_procurement']);
        $this->assertTrue($constructionPayload['construction_site_options']['can_create_requisition']);
        $this->assertTrue($constructionPayload['construction_site_options']['can_manage_stock']);
        $this->assertTrue($constructionPayload['construction_site_options']['can_transfer_stock']);
        $this->assertTrue($constructionPayload['construction_site_options']['can_receive_goods']);
        $this->assertNotEmpty($constructionPayload['construction_site_options']['projects']);
        $this->assertNotEmpty($constructionPayload['construction_site_options']['milestones']);
        $this->assertNotEmpty($constructionPayload['construction_site_options']['daily_reports']);
        $this->assertNotEmpty($constructionPayload['construction_site_options']['stock_items']);
        $stockRow = $constructionPayload['construction_site_options']['stock_items'][0];
        $this->assertArrayHasKey('company_id', $stockRow);
        $this->assertArrayHasKey('project_id', $stockRow);
        $this->assertNotEmpty($constructionPayload['construction_site_options']['requisitions']);
        $this->assertNotEmpty($constructionPayload['construction_site_options']['purchase_orders']);
        $this->assertNotEmpty($constructionPayload['construction_site_options']['vendors']);
        $vendorRow = $constructionPayload['construction_site_options']['vendors'][0];
        $this->assertArrayHasKey('purchase_orders_count', $vendorRow);
        $this->assertArrayHasKey('purchase_value_total', $vendorRow);
        $this->assertArrayHasKey('open_purchase_value', $vendorRow);
        $this->assertArrayHasKey('latest_purchase_order', $vendorRow);
        $this->assertSame(
            ConstructionMilestone::where('company_id', $construction->company_id)->whereIn('status', ['planned', 'in_progress', 'delayed', 'blocked'])->count(),
            $constructionPayload['construction_site_options']['summary']['active_milestones'],
        );
        $this->assertSame(
            DailyProgressReport::where('company_id', $construction->company_id)->whereDate('report_date', now()->toDateString())->count(),
            $constructionPayload['construction_site_options']['summary']['reports_today'],
        );
        $this->assertSame(
            StockItem::where('company_id', $construction->company_id)->whereColumn('on_hand_quantity', '<=', 'minimum_stock_quantity')->where('minimum_stock_quantity', '>', 0)->count(),
            $constructionPayload['construction_site_options']['summary']['low_stock_items'],
        );
        $this->assertSame(
            PurchaseRequisition::where('company_id', $construction->company_id)->whereIn('status', ['draft', 'submitted'])->count(),
            $constructionPayload['construction_site_options']['summary']['open_indents'],
        );
        $this->assertSame(
            PurchaseOrder::where('company_id', $construction->company_id)->whereIn('status', ['approved', 'partially_received'])->count(),
            $constructionPayload['construction_site_options']['summary']['pending_grn'],
        );

        $this->assertSame('laravel-sqlite', $financePayload['construction_site_options']['source']);
        $this->assertFalse($financePayload['construction_site_options']['can_create_milestone']);
        $this->assertFalse($financePayload['construction_site_options']['can_create_daily_report']);
        $this->assertFalse($financePayload['construction_site_options']['can_create_requisition']);
        $this->assertFalse($financePayload['construction_site_options']['can_manage_stock']);
        $this->assertNull($partnerPayload['construction_site_options']);
    }

    public function test_dashboard_bootstrap_fails_closed_for_non_global_user_without_company_assignment(): void
    {
        $this->seed();

        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $finance->forceFill(['company_id' => null])->save();

        $payload = app(Builder360Bootstrap::class)->forUser($finance);

        $this->assertSame(0, $payload['dashboard']['scope']['company_id']);
        $this->assertNull($payload['dashboard']['scope']['company_code']);
        $this->assertSame('company', $payload['dashboard']['scope']['level']);
        $this->assertSame(0, $payload['dashboard']['kpis']['projects']);
        $this->assertSame(0, $payload['dashboard']['kpis']['totalUnits']);
        $this->assertSame(0, $payload['dashboard']['kpis']['leads']);
        $this->assertSame([], collect($payload['dashboard']['projects'])->pluck('code')->all());
        $this->assertSame([], collect($payload['companies'])->pluck('code')->all());
        $this->assertSame([], collect($payload['projects'])->pluck('code')->all());
    }

    public function test_dashboard_bootstrap_exposes_admin_governance_options_to_authorized_users_only(): void
    {
        $this->seed();

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();

        $adminPayload = app(Builder360Bootstrap::class)->forUser($admin);
        $salesPayload = app(Builder360Bootstrap::class)->forUser($sales);
        $partnerPayload = app(Builder360Bootstrap::class)->forUser($partner);

        $this->assertSame('laravel-sqlite', $adminPayload['admin_governance_options']['source']);
        $this->assertSame('/admin/users', $adminPayload['admin_governance_options']['admin_users_index_url']);
        $this->assertSame('/admin/users', $adminPayload['admin_governance_options']['admin_users_store_url']);
        $this->assertSame('/admin/roles', $adminPayload['admin_governance_options']['admin_roles_index_url']);
        $this->assertSame('/admin/roles', $adminPayload['admin_governance_options']['admin_roles_store_url']);
        $this->assertSame('/admin/roles/__ROLE__', $adminPayload['admin_governance_options']['admin_role_update_url_template']);
        $this->assertSame('/settings/system-settings', $adminPayload['admin_governance_options']['system_settings_index_url']);
        $this->assertSame('/settings/system-settings', $adminPayload['admin_governance_options']['system_settings_store_url']);
        $this->assertSame('/settings/system-settings/__SETTING__/approve', $adminPayload['admin_governance_options']['system_setting_approve_url_template']);
        $this->assertTrue($adminPayload['admin_governance_options']['can_view_users']);
        $this->assertTrue($adminPayload['admin_governance_options']['can_manage_users']);
        $this->assertTrue($adminPayload['admin_governance_options']['can_view_roles']);
        $this->assertTrue($adminPayload['admin_governance_options']['can_manage_roles']);
        $this->assertTrue($adminPayload['admin_governance_options']['can_view_settings']);
        $this->assertTrue($adminPayload['admin_governance_options']['can_manage_settings']);
        $this->assertTrue($adminPayload['admin_governance_options']['can_approve_settings']);
        $this->assertNotEmpty($adminPayload['admin_governance_options']['users']);
        $this->assertNotEmpty($adminPayload['admin_governance_options']['roles']);
        $this->assertNotEmpty($adminPayload['admin_governance_options']['modules']);
        $this->assertNotEmpty($adminPayload['admin_governance_options']['settings']);
        $this->assertContains('workflow.approval_chains', collect($adminPayload['admin_governance_options']['settings'])->pluck('setting_key')->all());
        $this->assertContains('governance.backup_dr', collect($adminPayload['admin_governance_options']['settings'])->pluck('setting_key')->all());
        $this->assertNotEmpty($adminPayload['admin_governance_options']['approval_chains']);
        $this->assertSame(
            SystemSetting::where('company_id', $admin->company_id)->where('status', 'active')->count(),
            $adminPayload['admin_governance_options']['summary']['active_settings'],
        );

        $this->assertNull($salesPayload['admin_governance_options']);
        $this->assertNull($partnerPayload['admin_governance_options']);
    }

    public function test_director_dashboard_bootstrap_exposes_approved_compact_navigation_only(): void
    {
        $this->seed();

        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $payload = app(Builder360Bootstrap::class)->forUser($director);
        $employee = User::where('email', 'amit.verma@builder360.test')->firstOrFail();
        $employeePayload = app(Builder360Bootstrap::class)->forUser($employee);

        $modules = collect($payload['modules'])
            ->pluck('items')
            ->flatten(1);

        $moduleRoutes = $modules->pluck('route')->all();

        $approvedRoutes = [
            'dashboard',
            'approvals',
            'notifications',
            'reports',
            'tasks',
            'calendar',
            'chat',
            'mailbox',
            'leads',
            'qualification',
            'sitevisits',
            'sales',
            'marketing',
            'collections',
            'funnel',
            'performance',
            'projects',
            'inventory',
            'pricing',
            'cost',
            'planning',
            'progress',
            'materials',
            'procurement',
            'vendors',
            'contractors',
            'boq',
            'hr',
            'payroll',
            'recruitment',
            'finance',
            'legal',
            'documents',
            'possession',
            'maintenance',
            'complaints',
            'inquiry',
            'admin',
            'workflows',
            'audit',
            'settings',
            'scoring',
        ];

        foreach ($approvedRoutes as $route) {
            $this->assertContains($route, $moduleRoutes, "Expected approved navigation route [{$route}] to be exposed.");
        }

        foreach ([
            'purchase-requisitions',
            'purchase-orders',
            'goods-receipts',
            'measurements',
            'contractor-bills',
            'hr-attendance',
            'hr-leave',
            'hr-performance',
            'hr-confirmation',
            'hr-separation',
            'hr-assets',
            'hr-claims',
            'hr-loans',
            'hr-helpdesk',
            'hr-documents',
            'hr-compliance',
            'payroll-structures',
            'payroll-components',
            'payroll-bank-batches',
            'payroll-commissions',
            'payroll-tax-documents',
            'recruitment-candidates',
            'recruitment-interviews',
            'recruitment-offers',
            'recruitment-sources',
            'finance-vouchers',
            'finance-payment-requests',
            'finance-gst-entries',
            'finance-gst-returns',
            'legal-project-approvals',
            'legal-obligations',
            'document-categories',
            'possession-snags',
            'maintenance-handover-items',
            'maintenance-dues',
            'admin-users',
            'admin-roles',
            'data-imports',
        ] as $route) {
            $this->assertNotContains($route, $moduleRoutes, "Placeholder navigation route [{$route}] must not be exposed in the main sidebar.");
        }

        $this->assertNotContains('ess', $moduleRoutes, 'Director should not see the personal ESS route without a linked employee profile.');
        $this->assertNotContains('buyer', $moduleRoutes, 'Director must not receive the buyer portal route.');
        $this->assertNotContains('mobile', $moduleRoutes, 'Excluded native application diagnostics must not appear in business navigation.');
        $this->assertNotContains('auth', $moduleRoutes, 'Operational authentication diagnostics must not appear in business navigation.');
        $this->assertContains(
            'ess',
            collect($employeePayload['modules'])->pluck('items')->flatten(1)->pluck('route')->all(),
            'Employee role must keep Employee Self-Service navigation.',
        );

        $this->assertSame(
            'complaints',
            $modules->firstWhere('slug', 'after-sales')['route'] ?? null,
            'After-Sales navigation must use the shell route that renders the complaints module.',
        );
    }

    public function test_dashboard_bootstrap_limits_partner_payload_to_partner_portal_scope(): void
    {
        $this->seed();

        $channelPartner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();
        $broker = User::where('email', 'farhan.shaikh@partners.builder360.test')->firstOrFail();

        $payload = app(Builder360Bootstrap::class)->forUser($channelPartner);

        $this->assertSame(['channel_partner'], collect($payload['roles'])->pluck('slug')->all());

        $moduleSlugs = collect($payload['modules'])
            ->pluck('items')
            ->flatten(1)
            ->pluck('slug')
            ->all();

        $this->assertContains('partner', $moduleSlugs);
        $this->assertSame(['partner'], $moduleSlugs);
        $this->assertNotContains('hr', $moduleSlugs);
        $this->assertNotContains('finance', $moduleSlugs);
        $this->assertNotContains('administration', $moduleSlugs);
        $this->assertNotContains('settings', $moduleSlugs);
        $this->assertNotContains('audit', $moduleSlugs);

        $this->assertSame(['CP-001'], collect($payload['partner_pipeline']['partners'])->pluck('code')->all());
        $this->assertNotContains('BR-001', collect($payload['partner_pipeline']['partners'])->pluck('code')->all());
        $this->assertSame(['CP-001'], collect($payload['partner_portal']['scope']['partners'])->pluck('code')->all());
        $this->assertGreaterThan(0, $payload['partner_portal']['metrics']['leads']);
        $this->assertGreaterThan(0, $payload['partner_portal']['metrics']['bookings']);
        $this->assertNotEmpty($payload['partner_portal']['my_leads']);
        $this->assertNotEmpty($payload['partner_portal']['site_visits']);
        $this->assertNotEmpty($payload['partner_portal']['bookings']);
        $this->assertNotContains('ORC-MUM', collect($payload['projects'])->pluck('code')->all());
        $this->assertSame([0.0], collect($payload['projects'])->pluck('budget_amount')->unique()->values()->all());
        $this->assertSame([0.0], collect($payload['projects'])->pluck('target_roi_percent')->unique()->values()->all());
        $this->assertSame([0.0], collect($payload['dashboard']['projects'])->pluck('budget')->unique()->values()->all());
        $this->assertSame([0.0], collect($payload['dashboard']['projects'])->pluck('roi')->unique()->values()->all());
        $this->assertSame(0.0, $payload['dashboard']['kpis']['expenses']);
        $this->assertNull($payload['finance_dashboard']);
        $this->assertNull($payload['finance_payment_request_options']);
        $this->assertNull($payload['finance_voucher_options']);

        $brokerPayload = app(Builder360Bootstrap::class)->forUser($broker);

        $this->assertSame(['executive_partner_broker'], collect($brokerPayload['roles'])->pluck('slug')->all());
        $this->assertSame(['BR-001'], collect($brokerPayload['partner_pipeline']['partners'])->pluck('code')->all());
        $this->assertNotContains('CP-001', collect($brokerPayload['partner_pipeline']['partners'])->pluck('code')->all());
        $this->assertSame(['BR-001'], collect($brokerPayload['partner_portal']['scope']['partners'])->pluck('code')->all());
        $this->assertSame(0, $brokerPayload['partner_portal']['metrics']['leads']);
        $this->assertSame([], collect($brokerPayload['projects'])->pluck('code')->all());
    }

    public function test_dashboard_bootstrap_limits_buyer_payload_to_customer_scope(): void
    {
        $this->seed();

        $buyer = User::where('email', 'rohan.shah@example.test')->firstOrFail();
        $payload = app(Builder360Bootstrap::class)->forUser($buyer);

        $this->assertSame(['buyer'], collect($payload['roles'])->pluck('slug')->all());
        $this->assertSame(['Customer'], collect($payload['modules'])->pluck('group')->all());
        $this->assertSame(['buyer'], collect($payload['modules'])->pluck('items')->flatten(1)->pluck('route')->all());
        $this->assertSame([], collect($payload['partner_pipeline']['partners'])->pluck('code')->all());
        $this->assertNull($payload['partner_portal']);
        $this->assertSame('CUS-1001', $payload['buyer_portal']['customer']['code']);
        $this->assertSame(1, $payload['buyer_portal']['bookings_count']);
        $this->assertNotEmpty($payload['buyer_portal']['payment_schedule']);
        $this->assertNotEmpty($payload['buyer_portal']['documents']);
        $this->assertNotEmpty($payload['buyer_portal']['service_tickets']);
        $this->assertSame(route('buyer.summary', [], false), $payload['buyer_portal']['endpoints']['summary_url']);
        $this->assertSame(route('buyer.payment-requests.index', [], false), $payload['buyer_portal']['endpoints']['payment_requests_url']);
        $this->assertSame('/buyer/payment-requests/__PAYMENT_REQUEST__/pay', $payload['buyer_portal']['endpoints']['payment_request_pay_url_template']);
        $this->assertSame(route('buyer.service-tickets.store', [], false), $payload['buyer_portal']['endpoints']['service_tickets_store_url']);
        $this->assertSame(['SKY-PUN'], collect($payload['projects'])->pluck('code')->all());
        $this->assertSame(['B360D'], collect($payload['companies'])->pluck('code')->all());
        $this->assertSame([0.0], collect($payload['projects'])->pluck('budget_amount')->unique()->values()->all());
        $this->assertSame([0.0], collect($payload['projects'])->pluck('target_roi_percent')->unique()->values()->all());
        $this->assertSame([0.0], collect($payload['dashboard']['projects'])->pluck('budget')->unique()->values()->all());
        $this->assertSame([0.0], collect($payload['dashboard']['projects'])->pluck('roi')->unique()->values()->all());
        $this->assertNull($payload['sales_funnel_metrics']);
        $this->assertNull($payload['sales_performance_metrics']);
    }

    public function test_dashboard_bootstrap_includes_recipient_scoped_notifications(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();

        $salesPayload = app(Builder360Bootstrap::class)->forUser($sales);
        $constructionPayload = app(Builder360Bootstrap::class)->forUser($construction);

        $this->assertSame('current-records', $salesPayload['notifications']['source']);
        $this->assertSame($sales->id, $salesPayload['notifications']['scope']['recipient_user_id']);
        $this->assertSame(
            UserNotification::where('recipient_user_id', $sales->id)->where('status', 'unread')->count(),
            $salesPayload['notifications']['counts']['unread'],
        );
        $this->assertContains('NTF-10001', collect($salesPayload['notifications']['recent'])->pluck('notification_number')->all());
        $this->assertNotContains('NTF-10002', collect($salesPayload['notifications']['recent'])->pluck('notification_number')->all());

        $this->assertSame($construction->id, $constructionPayload['notifications']['scope']['recipient_user_id']);
        $this->assertContains('NTF-10002', collect($constructionPayload['notifications']['recent'])->pluck('notification_number')->all());
        $this->assertNotContains('NTF-10001', collect($constructionPayload['notifications']['recent'])->pluck('notification_number')->all());
    }

    public function test_dashboard_metrics_are_partner_scoped_not_company_scoped(): void
    {
        $this->seed();

        $channelPartner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();
        $broker = User::where('email', 'farhan.shaikh@partners.builder360.test')->firstOrFail();
        $brokerPartner = Partner::where('code', 'BR-001')->firstOrFail();
        $company = Company::where('code', 'B360D')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();
        $customer = Customer::where('code', 'CUS-1002')->firstOrFail();
        $owner = User::where('email', 'priya.nair@builder360.test')->firstOrFail();

        Lead::create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'customer_id' => $customer->id,
            'partner_id' => $brokerPartner->id,
            'owner_user_id' => $owner->id,
            'lead_code' => 'LD-BROKER-SCOPE',
            'source' => 'Broker scope test',
            'stage' => 'Negotiation',
            'status' => 'open',
            'budget_min' => 9000000,
            'budget_max' => 12000000,
            'expected_value' => 10000000,
            'follow_up_at' => now()->subDay(),
        ]);

        $channelPayload = app(Builder360Bootstrap::class)->forUser($channelPartner);
        $brokerPayload = app(Builder360Bootstrap::class)->forUser($broker);

        $this->assertSame(3, $channelPayload['dashboard']['kpis']['leads']);
        $this->assertSame(1, $brokerPayload['dashboard']['kpis']['leads']);
        $this->assertSame(4, Company::where('code', 'B360D')->firstOrFail()->leads()->count());
        $this->assertSame(3, collect($channelPayload['companies'])->firstWhere('code', 'B360D')['counts']['leads']);
        $this->assertSame(1, collect($brokerPayload['companies'])->firstWhere('code', 'B360D')['counts']['leads']);
        $this->assertSame(0, collect($channelPayload['companies'])->firstWhere('code', 'B360D')['counts']['employees']);
        $this->assertSame(0, collect($brokerPayload['companies'])->firstWhere('code', 'B360D')['counts']['employees']);
        $this->assertSame('partner', $channelPayload['dashboard']['scope']['level']);
        $this->assertSame('partner', $brokerPayload['dashboard']['scope']['level']);
        $this->assertSame(3, collect($channelPayload['dashboard']['funnel'])->firstWhere('stage', 'Total Leads')['n']);
        $this->assertSame(1, collect($brokerPayload['dashboard']['funnel'])->firstWhere('stage', 'Total Leads')['n']);
        $this->assertSame(3, $channelPayload['sales_funnel_metrics']['summary']['total_leads']);
        $this->assertSame(1, $brokerPayload['sales_funnel_metrics']['summary']['total_leads']);
        $this->assertContains('Broker scope test', collect($brokerPayload['sales_funnel_metrics']['source_conversion'])->pluck('label')->all());
        $this->assertNotContains('Broker scope test', collect($channelPayload['sales_funnel_metrics']['source_conversion'])->pluck('label')->all());
        $this->assertSame('partner', $channelPayload['sales_performance_metrics']['scope']['type']);
        $this->assertSame('partner', $brokerPayload['sales_performance_metrics']['scope']['type']);
        $this->assertSame(3, collect($channelPayload['sales_performance_metrics']['sales_rows'])->sum('assigned'));
        $this->assertSame(1, collect($brokerPayload['sales_performance_metrics']['sales_rows'])->sum('assigned'));
        $this->assertContains('Shaikh Executive Brokers', collect($brokerPayload['sales_performance_metrics']['sales_rows'])->pluck('name')->all());
        $this->assertNotContains('Shaikh Executive Brokers', collect($channelPayload['sales_performance_metrics']['sales_rows'])->pluck('name')->all());
        $this->assertSame([], $channelPayload['dashboard']['approvals']);
        $this->assertSame([], $brokerPayload['dashboard']['approvals']);
    }

    public function test_guest_users_are_redirected_to_login(): void
    {
        $response = $this->get('/');

        $response
            ->assertRedirect(route('login', absolute: false));
    }

    public function test_seeded_user_can_login_and_logout(): void
    {
        $this->seed();

        $login = $this->post(route('login.store'), [
            'email' => 'aditya.mehra@builder360.test',
            'password' => 'Builder360@123',
        ]);

        $login->assertRedirect(route('builder360.dashboard', absolute: false));
        $this->assertAuthenticated();

        $logout = $this->post(route('logout'));

        $logout->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_production_seeding_requires_explicit_demo_password(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        config(['builder360.demo_seed_password' => null]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('BUILDER360_DEMO_PASSWORD must be configured before seeding demo users in production.');

        app(DatabaseSeeder::class)->run();
    }

    public function test_invalid_login_is_rejected(): void
    {
        $this->seed();

        $response = $this->from(route('login'))->post(route('login.store'), [
            'email' => 'aditya.mehra@builder360.test',
            'password' => 'wrong-password',
        ]);

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_builder360_foundation_seed_contains_core_erp_records(): void
    {
        $this->seed();

        $this->assertDatabaseHas('roles', ['slug' => 'director']);
        $this->assertDatabaseHas('roles', ['slug' => 'channel_partner']);
        $this->assertDatabaseHas('erp_modules', ['slug' => 'partner']);
        $this->assertDatabaseHas('companies', ['code' => 'B360D']);
        $this->assertDatabaseHas('projects', ['code' => 'SKY-PUN']);
        $this->assertDatabaseHas('partners', ['code' => 'CP-001']);
        $this->assertGreaterThanOrEqual(13, Role::count());
        $this->assertGreaterThanOrEqual(3, Company::count());
        $this->assertGreaterThanOrEqual(3, Lead::count());
    }

    public function test_seeded_roles_enforce_expected_permission_gates(): void
    {
        $this->seed();

        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $channelPartner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();
        $broker = User::where('email', 'farhan.shaikh@partners.builder360.test')->firstOrFail();

        $this->assertTrue(Gate::forUser($director)->allows('hr.manage'));
        $this->assertTrue(Gate::forUser($director)->allows('finance.approve'));
        $this->assertTrue(Gate::forUser($channelPartner)->allows('partner.portal'));
        $this->assertTrue(Gate::forUser($broker)->allows('partner.portal'));
        $this->assertFalse(Gate::forUser($channelPartner)->allows('hr.manage'));
        $this->assertFalse(Gate::forUser($broker)->allows('finance.approve'));
    }
}
