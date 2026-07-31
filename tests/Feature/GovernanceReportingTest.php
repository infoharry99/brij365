<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Company;
use App\Models\Partner;
use App\Models\PayrollRun;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Governance\ManagementReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class GovernanceReportingTest extends TestCase
{
    use RefreshDatabase;

    public function test_auditor_can_browse_filtered_company_scoped_audit_events(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $auditor = User::where('email', 'ishaan.trivedi@builder360.test')->firstOrFail();
        $company = Company::where('code', 'B360D')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();
        $partner = Partner::where('code', 'CP-001')->firstOrFail();

        $this
            ->withHeaders([
                'X-Request-Id' => 'governance-audit-request-001',
                'User-Agent' => 'Builder360 Governance Test/1.0',
            ])
            ->actingAs($sales)
            ->postJson(route('crm.leads.store'), [
                'company_id' => $company->id,
                'project_id' => $project->id,
                'partner_id' => $partner->id,
                'customer_name' => 'Governance Audit Buyer',
                'customer_email' => 'governance.audit@example.test',
                'customer_phone' => '+91 98111 44001',
                'source' => 'Channel walk-in',
                'stage' => 'New',
                'expected_value' => 9500000,
                'follow_up_at' => now()->addDay()->toISOString(),
            ])->assertCreated();

        $this->actingAs($auditor)
            ->getJson(route('governance.audit-events.index', [
                'event_type' => 'crm.lead.created',
                'request_method' => 'POST',
                'request_id' => 'governance-audit-request-001',
            ]))
            ->assertOk()
            ->assertJsonPath('data.0.event_type', 'crm.lead.created')
            ->assertJsonPath('data.0.request_method', 'POST')
            ->assertJsonPath('data.0.request_path', 'crm/leads')
            ->assertJsonPath('data.0.request_id', 'governance-audit-request-001')
            ->assertJsonPath('data.0.user_agent', 'Builder360 Governance Test/1.0')
            ->assertJsonPath('data.0.user.email', 'priya.nair@builder360.test');
    }

    public function test_auditor_can_use_native_blade_audit_trail_workspace(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $auditor = User::where('email', 'ishaan.trivedi@builder360.test')->firstOrFail();
        $company = Company::where('code', 'B360D')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();
        $partner = Partner::where('code', 'CP-001')->firstOrFail();

        $this
            ->withHeaders([
                'X-Request-Id' => 'governance-audit-blade-001',
                'User-Agent' => 'Builder360 Governance Blade Test/1.0',
            ])
            ->actingAs($sales)
            ->postJson(route('crm.leads.store'), [
                'company_id' => $company->id,
                'project_id' => $project->id,
                'partner_id' => $partner->id,
                'customer_name' => 'Governance Blade Buyer',
                'customer_email' => 'governance.blade@example.test',
                'customer_phone' => '+91 98111 44003',
                'source' => 'Channel walk-in',
                'stage' => 'New',
                'expected_value' => 9700000,
                'follow_up_at' => now()->addDay()->toISOString(),
            ])->assertCreated();

        $this->actingAs($auditor)
            ->get(route('governance.audit-events.index', [
                'event_type' => 'crm.lead.created',
                'request_method' => 'POST',
                'request_id' => 'governance-audit-blade-001',
            ]))
            ->assertOk()
            ->assertSee('Audit register')
            ->assertSee('Audit filters')
            ->assertSee('Audit event register')
            ->assertSee('name="event_type"', false)
            ->assertSee('crm.lead.created')
            ->assertSee('governance-audit-blade-001')
            ->assertSee('priya.nair@builder360.test')
            ->assertDontSee('window.Builder360Server', false)
            ->assertDontSee('id="root"', false);

        $this->actingAs($auditor)
            ->get(route('governance.audit-events.export', [
                'event_type' => 'crm.lead.created',
                'request_id' => 'governance-audit-blade-001',
            ]))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertSee('governance-audit-blade-001');
    }

    public function test_auditor_can_export_filtered_audit_events_as_governed_csv(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $auditor = User::where('email', 'ishaan.trivedi@builder360.test')->firstOrFail();
        $company = Company::where('code', 'B360D')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();
        $partner = Partner::where('code', 'CP-001')->firstOrFail();

        $this
            ->withHeaders([
                'X-Request-Id' => 'governance-audit-export-001',
                'User-Agent' => 'Builder360 Governance Export Test/1.0',
            ])
            ->actingAs($sales)
            ->postJson(route('crm.leads.store'), [
                'company_id' => $company->id,
                'project_id' => $project->id,
                'partner_id' => $partner->id,
                'customer_name' => 'Governance Export Buyer',
                'customer_email' => 'governance.export@example.test',
                'customer_phone' => '+91 98111 44002',
                'source' => 'Channel walk-in',
                'stage' => 'New',
                'expected_value' => 9600000,
                'follow_up_at' => now()->addDay()->toISOString(),
            ])->assertCreated();

        $response = $this->actingAs($auditor)
            ->get(route('governance.audit-events.export', [
                'event_type' => 'crm.lead.created',
                'request_method' => 'POST',
                'request_id' => 'governance-audit-export-001',
            ]));

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertSee('event_type')
            ->assertSee('crm.lead.created')
            ->assertSee('governance-audit-export-001')
            ->assertSee('priya.nair@builder360.test');

        $this->assertStringContainsString('builder360-audit-trail.csv', $response->headers->get('Content-Disposition', ''));
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control', ''));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control', ''));

        $exportAudit = AuditEvent::query()
            ->where('event_type', 'governance.audit_events.exported')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($auditor->id, $exportAudit->user_id);
        $this->assertSame('Exported governance audit trail', $exportAudit->action);
        $this->assertSame('csv', $exportAudit->metadata['format']);
        $this->assertSame('crm.lead.created', $exportAudit->metadata['filters']['event_type']);
        $this->assertSame('governance-audit-export-001', $exportAudit->metadata['filters']['request_id']);
        $this->assertGreaterThanOrEqual(1, $exportAudit->metadata['row_count']);
    }

    public function test_audit_event_index_validates_filter_contract(): void
    {
        $this->seed();

        $auditor = User::where('email', 'ishaan.trivedi@builder360.test')->firstOrFail();

        $this->actingAs($auditor)
            ->getJson(route('governance.audit-events.index', ['page' => 1]))
            ->assertOk()
            ->assertJsonPath('meta.current_page', 1);

        $this->actingAs($auditor)
            ->getJson(route('governance.audit-events.index', ['report' => 'bookings']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['report'])
            ->assertJsonPath('errors.report.0', 'The selected filter is not available for this endpoint.');

        $this->actingAs($auditor)
            ->getJson(route('governance.audit-events.index', ['date_from' => now()->toDateString(), 'date_to' => now()->subDay()->toDateString()]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date_to']);
    }

    public function test_management_summary_returns_scoped_operational_kpis(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $expectedApprovedPayroll = (float) PayrollRun::query()
            ->where('company_id', $sales->company_id)
            ->where('status', 'approved')
            ->sum('net_payable');

        $response = $this->actingAs($sales)
            ->getJson(route('governance.management-summary.show'))
            ->assertOk()
            ->assertJsonPath('data.scope.company_id', $sales->company_id)
            ->assertJsonStructure([
                'data' => [
                    'crm' => ['open_leads', 'won_leads', 'pipeline_value', 'by_stage'],
                    'sales' => ['confirmed_bookings', 'net_receivable', 'by_status'],
                    'collections' => ['approved_amount', 'submitted_amount', 'by_status'],
                    'inventory' => ['total_units', 'by_status'],
                    'construction' => ['active_projects', 'milestones_by_status'],
                    'payroll' => ['runs_by_status', 'approved_net_payable'],
                    'after_sales' => ['open_tickets', 'overdue_tickets', 'by_status'],
                    'audit' => ['events_last_7_days', 'by_event_type'],
                ],
            ]);

        $this->assertEqualsWithDelta(
            $expectedApprovedPayroll,
            (float) $response->json('data.payroll.approved_net_payable'),
            0.01,
        );

        $event = AuditEvent::query()
            ->where('event_type', 'governance.management_summary.viewed')
            ->where('user_id', $sales->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($sales->company_id, $event->metadata['company_id']);
        $this->assertIsInt($event->metadata['open_leads']);
        $this->assertIsInt($event->metadata['confirmed_bookings']);
        $this->assertIsInt($event->metadata['open_service_tickets']);
    }

    public function test_management_summary_can_be_exported_as_governed_csv(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();

        $response = $this->actingAs($sales)
            ->get(route('governance.management-summary.show', ['format' => 'csv']));

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertSee('section')
            ->assertSee('metric')
            ->assertSee('crm')
            ->assertSee('open_leads')
            ->assertSee('payroll')
            ->assertSee('approved_net_payable');

        $this->assertStringContainsString('builder360-management-summary.csv', $response->headers->get('Content-Disposition', ''));
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control', ''));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control', ''));

        $event = AuditEvent::query()
            ->where('event_type', 'governance.management_summary.exported')
            ->where('user_id', $sales->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($sales->company_id, $event->metadata['company_id']);
        $this->assertSame('csv', $event->metadata['format']);
        $this->assertIsInt($event->metadata['open_leads']);
        $this->assertIsInt($event->metadata['confirmed_bookings']);
    }

    public function test_payroll_report_uses_real_payroll_columns_and_period_filters(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();

        PayrollRun::create([
            'company_id' => $sales->company_id,
            'generated_by_user_id' => $sales->id,
            'run_number' => 'PAY-GOV-209901',
            'period_year' => 2099,
            'period_month' => 1,
            'period_start' => '2099-01-01',
            'period_end' => '2099-01-31',
            'working_days' => 26,
            'status' => 'generated',
            'gross_earnings' => 123456.78,
            'total_deductions' => 23456.78,
            'net_payable' => 100000.00,
        ]);

        PayrollRun::create([
            'company_id' => $sales->company_id,
            'generated_by_user_id' => $sales->id,
            'run_number' => 'PAY-GOV-209902',
            'period_year' => 2099,
            'period_month' => 2,
            'period_start' => '2099-02-01',
            'period_end' => '2099-02-28',
            'working_days' => 24,
            'status' => 'generated',
            'gross_earnings' => 200000,
            'total_deductions' => 30000,
            'net_payable' => 170000,
        ]);

        $this->actingAs($sales)
            ->getJson(route('governance.report-register.index', [
                'report' => 'payroll',
                'status' => 'generated',
                'date_from' => '2099-01-01',
                'date_to' => '2099-01-31',
            ]))
            ->assertOk()
            ->assertJsonPath('data.report', 'payroll')
            ->assertJsonPath('data.row_count', 1)
            ->assertJsonPath('data.rows.0.run_number', 'PAY-GOV-209901')
            ->assertJsonPath('data.rows.0.gross_earnings', 123456.78)
            ->assertJsonPath('data.rows.0.total_deductions', 23456.78)
            ->assertJsonPath('data.rows.0.net_payable', 100000)
            ->assertJsonMissingPath('data.rows.0.net_payable_total');
    }

    public function test_report_register_returns_json_and_csv_exports(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();

        $this->actingAs($sales)
            ->getJson(route('governance.report-register.index', ['report' => 'bookings']))
            ->assertOk()
            ->assertJsonPath('data.report', 'bookings')
            ->assertJsonPath('data.rows.0.booking_code', 'BK-1001');

        $this->assertDatabaseHas('audit_events', [
            'user_id' => $sales->id,
            'event_type' => 'governance.report.generated',
            'action' => 'Generated governance report register',
        ]);

        $response = $this->actingAs($sales)
            ->get(route('governance.report-register.index', ['report' => 'service_tickets', 'format' => 'csv']));

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertSee('ticket_number')
            ->assertSee('AST-1001');

        $this->assertStringContainsString('private', $response->headers->get('Cache-Control', ''));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control', ''));

        $exportAudit = AuditEvent::where('event_type', 'governance.report.exported')->latest('id')->firstOrFail();

        $this->assertSame($sales->id, $exportAudit->user_id);
        $this->assertSame('Exported governance report register', $exportAudit->action);
        $this->assertSame('service_tickets', $exportAudit->metadata['report']);
        $this->assertSame('csv', $exportAudit->metadata['format']);
        $this->assertGreaterThanOrEqual(1, $exportAudit->metadata['row_count']);
    }

    public function test_report_register_supports_crm_inventory_construction_and_audit_reports(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $auditor = User::where('email', 'ishaan.trivedi@builder360.test')->firstOrFail();

        $this->actingAs($sales)
            ->getJson(route('governance.report-register.index', ['report' => 'leads', 'status' => 'open']))
            ->assertOk()
            ->assertJsonPath('data.report', 'leads')
            ->assertJsonFragment(['lead_code' => 'LD-1002'])
            ->assertJsonFragment(['stage' => 'Site Visit Planned'])
            ->assertJsonMissingPath('data.rows.0.deleted_at');

        $this->actingAs($sales)
            ->getJson(route('governance.report-register.index', ['report' => 'inventory_units', 'status' => 'booked']))
            ->assertOk()
            ->assertJsonPath('data.report', 'inventory_units')
            ->assertJsonPath('data.rows.0.unit_code', 'SKY-A-1204')
            ->assertJsonPath('data.rows.0.status', 'booked')
            ->assertJsonPath('data.rows.0.booking_code', 'BK-1001');

        $this->actingAs($sales)
            ->getJson(route('governance.report-register.index', ['report' => 'stock_items', 'status' => 'active']))
            ->assertOk()
            ->assertJsonPath('data.report', 'stock_items')
            ->assertJsonFragment(['item_code' => 'CEMENT-OPC-53'])
            ->assertJsonFragment(['below_minimum' => 'no'])
            ->assertJsonMissingPath('data.rows.0.metadata');

        $this->actingAs($sales)
            ->getJson(route('governance.report-register.index', ['report' => 'stock_movements', 'status' => 'inward']))
            ->assertOk()
            ->assertJsonPath('data.report', 'stock_movements')
            ->assertJsonFragment(['movement_number' => 'STM-1001'])
            ->assertJsonFragment(['movement_type' => 'inward'])
            ->assertJsonMissingPath('data.rows.0.metadata');

        $this->actingAs($sales)
            ->getJson(route('governance.report-register.index', ['report' => 'purchase_orders', 'status' => 'partially_received']))
            ->assertOk()
            ->assertJsonPath('data.report', 'purchase_orders')
            ->assertJsonFragment(['po_number' => 'PO-1001'])
            ->assertJsonFragment(['vendor_code' => 'VEN-1001'])
            ->assertJsonMissingPath('data.rows.0.items');

        $this->actingAs($sales)
            ->getJson(route('governance.report-register.index', ['report' => 'vendors', 'status' => 'active']))
            ->assertOk()
            ->assertJsonPath('data.report', 'vendors')
            ->assertJsonFragment(['vendor_code' => 'VEN-1001'])
            ->assertJsonMissingPath('data.rows.0.pan')
            ->assertJsonMissingPath('data.rows.0.pan_encrypted')
            ->assertJsonMissingPath('data.rows.0.bank_details');

        $this->actingAs($sales)
            ->getJson(route('governance.report-register.index', ['report' => 'construction_milestones', 'status' => 'in_progress']))
            ->assertOk()
            ->assertJsonPath('data.report', 'construction_milestones')
            ->assertJsonPath('data.rows.0.milestone_code', 'SKY-SLAB-03')
            ->assertJsonPath('data.rows.0.status', 'in_progress')
            ->assertJsonPath('data.rows.0.project', 'SKY-PUN');

        $this->actingAs($sales)
            ->getJson(route('governance.report-register.index', ['report' => 'daily_progress_reports', 'status' => 'approved']))
            ->assertOk()
            ->assertJsonPath('data.report', 'daily_progress_reports')
            ->assertJsonPath('data.rows.0.report_number', 'DPR-1001')
            ->assertJsonPath('data.rows.0.status', 'approved')
            ->assertJsonPath('data.rows.0.project', 'SKY-PUN')
            ->assertJsonPath('data.rows.0.manpower_count', 86)
            ->assertJsonPath('data.rows.0.progress_item_count', 2)
            ->assertJsonPath('data.rows.0.open_blocker_count', 0)
            ->assertJsonMissingPath('data.rows.0.workflow_history');

        $this->actingAs($sales)
            ->getJson(route('governance.report-register.index', ['report' => 'rera_registrations', 'status' => 'verified']))
            ->assertOk()
            ->assertJsonPath('data.report', 'rera_registrations')
            ->assertJsonPath('data.rows.0.registration_number', 'P52100012345')
            ->assertJsonPath('data.rows.0.status', 'verified')
            ->assertJsonPath('data.rows.0.project', 'SKY-PUN')
            ->assertJsonPath('data.rows.0.authority_name', 'MahaRERA')
            ->assertJsonPath('data.rows.0.state_code', 'MH')
            ->assertJsonPath('data.rows.0.condition_count', 2)
            ->assertJsonMissingPath('data.rows.0.workflow_history')
            ->assertJsonMissingPath('data.rows.0.metadata')
            ->assertJsonMissingPath('data.rows.0.conditions');

        $this->actingAs($sales)
            ->getJson(route('governance.report-register.index', ['report' => 'audit_events']))
            ->assertForbidden();

        $this->actingAs($auditor)
            ->getJson(route('governance.management-summary.show'))
            ->assertOk();

        $this->actingAs($auditor)
            ->getJson(route('governance.report-register.index', ['report' => 'audit_events']))
            ->assertOk()
            ->assertJsonPath('data.report', 'audit_events')
            ->assertJsonFragment(['event_type' => 'governance.report.generated'])
            ->assertJsonMissingPath('data.rows.0.metadata');
    }

    public function test_procurement_reports_are_company_scoped_and_export_safe(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $otherCompany = Company::where('code', 'B360P')->firstOrFail();
        $otherProject = Project::where('company_id', $otherCompany->id)->firstOrFail();

        $otherVendor = Vendor::create([
            'company_id' => $otherCompany->id,
            'vendor_code' => 'VEN-OTHER',
            'name' => 'Other Company Vendor',
            'vendor_type' => 'material',
            'contact_name' => 'Other Contact',
            'email' => 'other.vendor@example.test',
            'phone' => '+91 90000 00000',
            'gstin' => '27AACCO0000A1Z5',
            'pan' => 'AACCO0000A',
            'bank_details' => [
                'account_holder' => 'Other Company Vendor',
                'account_number' => '123456789012',
                'ifsc' => 'HDFC0001234',
            ],
            'status' => 'active',
        ]);

        $otherItem = StockItem::create([
            'company_id' => $otherCompany->id,
            'project_id' => $otherProject->id,
            'store_type' => 'site',
            'item_code' => 'OTHER-STOCK',
            'description' => 'Other company stock',
            'unit' => 'bag',
            'on_hand_quantity' => 10,
            'stock_value' => 1000,
            'average_rate' => 100,
            'minimum_stock_quantity' => 1,
            'status' => 'active',
            'last_movement_at' => now(),
        ]);

        $otherOrder = PurchaseOrder::create([
            'company_id' => $otherCompany->id,
            'project_id' => $otherProject->id,
            'vendor_id' => $otherVendor->id,
            'created_by_user_id' => $sales->id,
            'po_number' => 'PO-OTHER',
            'po_date' => now()->toDateString(),
            'expected_delivery_on' => now()->addDays(5)->toDateString(),
            'status' => 'approved',
            'items' => [['item_code' => 'OTHER-STOCK', 'description' => 'Other company stock', 'unit' => 'bag', 'quantity' => 10, 'rate' => 100]],
            'subtotal' => 1000,
            'tax_amount' => 180,
            'total_amount' => 1180,
        ]);

        StockMovement::create([
            'company_id' => $otherCompany->id,
            'project_id' => $otherProject->id,
            'stock_item_id' => $otherItem->id,
            'purchase_order_id' => $otherOrder->id,
            'created_by_user_id' => $sales->id,
            'movement_number' => 'STM-OTHER',
            'movement_type' => 'inward',
            'movement_date' => now()->toDateString(),
            'store_type' => 'site',
            'item_code' => 'OTHER-STOCK',
            'description' => 'Other company stock',
            'unit' => 'bag',
            'quantity' => 10,
            'rate' => 100,
            'amount' => 1000,
            'balance_after_quantity' => 10,
            'balance_after_value' => 1000,
            'source_type' => 'purchase_order',
            'source_id' => $otherOrder->id,
        ]);

        $vendorResponse = $this->actingAs($sales)
            ->getJson(route('governance.report-register.index', ['report' => 'vendors']))
            ->assertOk()
            ->assertJsonPath('data.report', 'vendors')
            ->assertJsonMissing(['vendor_code' => 'VEN-OTHER']);

        $this->assertStringNotContainsString('bank_details', json_encode($vendorResponse->json('data.rows'), JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('pan_encrypted', json_encode($vendorResponse->json('data.rows'), JSON_THROW_ON_ERROR));

        $this->actingAs($sales)
            ->getJson(route('governance.report-register.index', ['report' => 'stock_items']))
            ->assertOk()
            ->assertJsonMissing(['item_code' => 'OTHER-STOCK']);

        $this->actingAs($sales)
            ->getJson(route('governance.report-register.index', ['report' => 'stock_movements']))
            ->assertOk()
            ->assertJsonMissing(['movement_number' => 'STM-OTHER']);

        $this->actingAs($sales)
            ->getJson(route('governance.report-register.index', ['report' => 'purchase_orders']))
            ->assertOk()
            ->assertJsonMissing(['po_number' => 'PO-OTHER']);
    }

    public function test_report_register_returns_excel_and_pdf_exports(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();

        $excelResponse = $this->actingAs($sales)
            ->get(route('governance.report-register.index', ['report' => 'bookings', 'format' => 'excel']));

        $excelResponse
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.ms-excel; charset=UTF-8')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->assertStringContainsString('builder360-bookings-report.xls', $excelResponse->headers->get('Content-Disposition', ''));
        $this->assertStringContainsString('<?mso-application progid="Excel.Sheet"?>', $excelResponse->getContent());
        $this->assertStringContainsString('booking_code', $excelResponse->getContent());
        $this->assertStringContainsString('BK-1001', $excelResponse->getContent());

        $pdfResponse = $this->actingAs($sales)
            ->get(route('governance.report-register.index', ['report' => 'service_tickets', 'format' => 'pdf']));

        $pdfResponse
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->assertStringContainsString('builder360-service_tickets-report.pdf', $pdfResponse->headers->get('Content-Disposition', ''));
        $this->assertStringStartsWith('%PDF-1.4', $pdfResponse->getContent());
        $this->assertStringEndsWith('%%EOF', $pdfResponse->getContent());

        $this->assertDatabaseHas('audit_events', [
            'user_id' => $sales->id,
            'event_type' => 'governance.report.exported',
        ]);

        $formats = AuditEvent::where('event_type', 'governance.report.exported')
            ->where('user_id', $sales->id)
            ->latest('id')
            ->limit(2)
            ->get()
            ->pluck('metadata')
            ->map(fn (array $metadata): string => $metadata['format'])
            ->all();

        $this->assertContains('excel', $formats);
        $this->assertContains('pdf', $formats);
    }

    public function test_report_register_uses_configured_export_row_limit(): void
    {
        $this->seed();

        Config::set('builder360.reports.max_export_rows', 1);

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();

        $this->actingAs($sales)
            ->getJson(route('governance.report-register.index', ['report' => 'bookings']))
            ->assertOk()
            ->assertJsonPath('data.report', 'bookings')
            ->assertJsonPath('data.row_count', 1)
            ->assertJsonCount(1, 'data.rows');
    }

    public function test_report_csv_output_escapes_spreadsheet_formula_cells(): void
    {
        $csv = app(ManagementReportService::class)->csv([
            [
                'customer' => '=HYPERLINK("https://example.test")',
                'amount' => '-100',
                'notes' => 'Safe text',
            ],
        ]);

        $this->assertStringContainsString('"\'=HYPERLINK(""https://example.test"")"', $csv);
        $this->assertStringContainsString('\'-100', $csv);
        $this->assertStringContainsString('Safe text', $csv);
    }

    public function test_report_excel_output_escapes_spreadsheet_formula_cells(): void
    {
        $excel = app(ManagementReportService::class)->excelXml([
            [
                'customer' => '=HYPERLINK("https://example.test")',
                'amount' => '-100',
                'notes' => 'Safe text',
            ],
        ]);

        $this->assertStringContainsString('&apos;=HYPERLINK(&quot;https://example.test&quot;)', $excel);
        $this->assertStringContainsString('&apos;-100', $excel);
        $this->assertStringContainsString('Safe text', $excel);
    }

    public function test_report_users_can_pin_and_schedule_governed_reports(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();

        $pinResponse = $this->actingAs($sales)
            ->postJson(route('governance.report-pins.store'), [
                'report_key' => 'bookings',
                'label' => 'Weekly Booking Report',
                'filters' => ['status' => 'confirmed'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.report_key', 'bookings')
            ->assertJsonPath('data.label', 'Weekly Booking Report');

        $this->assertDatabaseHas('report_pins', [
            'id' => $pinResponse->json('data.id'),
            'user_id' => $sales->id,
            'report_key' => 'bookings',
        ]);

        $scheduleResponse = $this->actingAs($sales)
            ->postJson(route('governance.report-schedules.store'), [
                'report_key' => 'collections',
                'label' => 'Monthly Collection Export',
                'frequency' => 'monthly',
                'format' => 'csv',
                'recipients' => ['finance@example.test', 'sales@example.test'],
                'starts_on' => now()->addDay()->toDateString(),
                'filters' => ['status' => 'approved'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.report_key', 'collections')
            ->assertJsonPath('data.frequency', 'monthly')
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('report_schedules', [
            'id' => $scheduleResponse->json('data.id'),
            'user_id' => $sales->id,
            'report_key' => 'collections',
            'status' => 'active',
        ]);

        $payload = app(\App\Services\Builder360\Builder360Bootstrap::class)->forUser($sales);

        $this->assertContains('bookings', collect($payload['governance_report_options']['pinned_reports'])->pluck('report_key')->all());
        $this->assertContains('collections', collect($payload['governance_report_options']['scheduled_reports'])->pluck('report_key')->all());
        $this->assertContains('daily_progress_reports', $payload['governance_report_options']['supported_reports']);
        $this->assertContains('approved', collect($payload['governance_report_options']['supported_report_statuses']['daily_progress_reports'])->pluck('value')->all());
        $this->assertContains('rera_registrations', $payload['governance_report_options']['supported_reports']);
        $this->assertContains('verified', collect($payload['governance_report_options']['supported_report_statuses']['rera_registrations'])->pluck('value')->all());
        $this->assertSame('/governance/report-pins', $payload['governance_report_options']['report_pin_store_url']);
        $this->assertSame('/governance/report-schedules', $payload['governance_report_options']['report_schedule_store_url']);

        $this->assertDatabaseHas('audit_events', [
            'user_id' => $sales->id,
            'event_type' => 'governance.report.pinned',
        ]);

        $this->assertDatabaseHas('audit_events', [
            'user_id' => $sales->id,
            'event_type' => 'governance.report_schedule.created',
        ]);
    }

    public function test_partner_cannot_access_governance_audit_or_reports(): void
    {
        $this->seed();

        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();

        $this->actingAs($partner)
            ->getJson(route('governance.audit-events.index'))
            ->assertForbidden();

        $this->actingAs($partner)
            ->getJson(route('governance.management-summary.show'))
            ->assertForbidden();

        $this->assertDatabaseMissing('audit_events', [
            'user_id' => $partner->id,
            'event_type' => 'governance.management_summary.viewed',
        ]);

        $this->actingAs($partner)
            ->getJson(route('governance.report-register.index'))
            ->assertForbidden();

        $this->actingAs($partner)
            ->postJson(route('governance.report-pins.store'), [
                'report_key' => 'bookings',
                'label' => 'Blocked Partner Pin',
            ])
            ->assertForbidden();

        $this->actingAs($partner)
            ->postJson(route('governance.report-schedules.store'), [
                'report_key' => 'bookings',
                'label' => 'Blocked Partner Schedule',
                'frequency' => 'weekly',
                'format' => 'csv',
                'recipients' => ['partner@example.test'],
                'starts_on' => now()->addDay()->toDateString(),
            ])
            ->assertForbidden();
    }

    public function test_report_register_validation_rejects_unknown_report_and_bad_date_range(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();

        $this->actingAs($sales)
            ->getJson(route('governance.report-register.index', [
                'report' => 'unknown',
                'date_from' => now()->toDateString(),
                'date_to' => now()->subDay()->toDateString(),
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['report', 'date_to']);
    }

    public function test_report_register_rejects_invalid_status_for_selected_report(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();

        $this->actingAs($sales)
            ->getJson(route('governance.report-register.index', [
                'report' => 'payroll',
                'status' => 'confirmed',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status'])
            ->assertJsonPath('errors.status.0', 'The selected status is not valid for the payroll report.');
    }

    public function test_report_register_rejects_project_filter_for_payroll_report(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();

        $this->actingAs($sales)
            ->getJson(route('governance.report-register.index', [
                'report' => 'payroll',
                'project_id' => $project->id,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id'])
            ->assertJsonPath('errors.project_id.0', 'Project filtering is not available for the payroll report.');
    }

    public function test_report_register_rejects_unbounded_historical_date_ranges(): void
    {
        $this->seed();
        Config::set('builder360.reports.max_date_range_days', 30);

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();

        $this->actingAs($sales)
            ->getJson(route('governance.report-register.index', [
                'report' => 'bookings',
                'date_from' => now()->subDays(31)->toDateString(),
                'date_to' => now()->toDateString(),
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date_to'])
            ->assertJsonPath('errors.date_to.0', 'The report date range may not exceed 30 days.');
    }

    public function test_non_global_reporting_users_without_company_assignment_fail_closed(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $auditor = User::where('email', 'ishaan.trivedi@builder360.test')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();

        $sales->forceFill(['company_id' => null])->save();

        $this->actingAs($sales)
            ->getJson(route('governance.management-summary.show'))
            ->assertOk()
            ->assertJsonPath('data.scope.company_id', 0)
            ->assertJsonPath('data.crm.open_leads', 0)
            ->assertJsonPath('data.sales.confirmed_bookings', 0)
            ->assertJsonPath('data.collections.approved_amount', 0)
            ->assertJsonPath('data.after_sales.open_tickets', 0);

        $this->actingAs($sales)
            ->getJson(route('governance.report-register.index', ['report' => 'bookings']))
            ->assertOk()
            ->assertJsonPath('data.row_count', 0)
            ->assertJsonCount(0, 'data.rows');

        $this->actingAs($sales)
            ->getJson(route('governance.report-register.index', [
                'report' => 'bookings',
                'project_id' => $project->id,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id']);

        $auditor->forceFill(['company_id' => null])->save();

        $this->actingAs($auditor)
            ->getJson(route('governance.audit-events.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }
}
