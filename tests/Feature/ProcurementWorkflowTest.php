<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Company;
use App\Models\FinancialVoucher;
use App\Models\FinancialVoucherLine;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequisition;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProcurementWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_construction_user_can_list_procurement_registers(): void
    {
        $this->seed();

        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();

        $this->actingAs($construction)
            ->getJson(route('procurement.dashboard'))
            ->assertOk()
            ->assertJsonPath('data.summary.purchase_orders.partially_received', 1);

        $this->actingAs($construction)
            ->getJson(route('procurement.vendors.index'))
            ->assertOk()
            ->assertJsonPath('data.0.vendor_code', 'VEN-1001');

        $this->actingAs($construction)
            ->getJson(route('procurement.requisitions.index'))
            ->assertOk()
            ->assertJsonPath('data.0.requisition_number', 'PR-1001');

        $this->actingAs($construction)
            ->getJson(route('procurement.purchase-orders.index'))
            ->assertOk()
            ->assertJsonPath('data.0.po_number', 'PO-1001');

        $this->actingAs($construction)
            ->getJson(route('procurement.goods-receipts.index'))
            ->assertOk()
            ->assertJsonPath('data.0.grn_number', 'GRN-1001');

        $this->actingAs($construction)
            ->getJson(route('procurement.stock-items.index'))
            ->assertOk()
            ->assertJsonPath('data.0.item_code', 'CEMENT-OPC-53')
            ->assertJsonPath('data.0.on_hand_quantity', 300)
            ->assertJsonPath('data.0.stock_value', 114000);
    }

    public function test_construction_user_can_open_native_blade_procurement_workspace(): void
    {
        $this->seed();

        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();

        $this->actingAs($construction)
            ->get(route('procurement.dashboard'))
            ->assertOk()
            ->assertSee('Procurement Workspace')
            ->assertSee('Material and purchase summary')
            ->assertSee('Vendor master')
            ->assertSee('Purchase requisitions')
            ->assertSee('Stock register');

        $this->actingAs($construction)
            ->get(route('procurement.vendors.index'))
            ->assertOk()
            ->assertSee('BuildMat Supplies Pvt Ltd');

        $this->actingAs($construction)
            ->get(route('procurement.requisitions.index'))
            ->assertOk()
            ->assertSee('PR-1001');

        $this->actingAs($construction)
            ->get(route('procurement.stock-items.index'))
            ->assertOk()
            ->assertSee('CEMENT-OPC-53');
    }

    public function test_construction_user_can_submit_blade_vendor_and_update_status(): void
    {
        $this->seed();

        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();

        $this->actingAs($construction)
            ->from(route('procurement.vendors.index'))
            ->post(route('procurement.vendors.store'), [
                'vendor_code' => 'VEN-BLADE-01',
                'name' => 'Blade Procurement Vendor Pvt Ltd',
                'vendor_type' => 'material',
                'contact_name' => 'Anil Vendor',
                'email' => 'blade.vendor@example.test',
                'phone' => '+91 98765 43210',
                'gstin' => '27ABCDE1234F1Z5',
                'pan' => 'ABCDE1234F',
                'address' => [
                    'city' => 'Pune',
                    'state' => 'Maharashtra',
                ],
            ])
            ->assertRedirect(route('procurement.vendors.index', ['vendor_type' => 'material', 'status' => 'active']))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $vendor = Vendor::where('vendor_code', 'VEN-BLADE-01')->firstOrFail();

        $this->assertDatabaseHas('vendors', [
            'id' => $vendor->id,
            'name' => 'Blade Procurement Vendor Pvt Ltd',
            'status' => 'active',
            'pan_last4' => '234F',
        ]);

        $this->actingAs($construction)
            ->from(route('procurement.vendors.index'))
            ->patch(route('procurement.vendors.status.update', $vendor), [
                'status' => 'inactive',
                'reason' => 'Temporary onboarding hold from Blade workspace.',
            ])
            ->assertRedirect(route('procurement.vendors.index', ['status' => 'inactive']))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('vendors', [
            'id' => $vendor->id,
            'status' => 'inactive',
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'procurement.vendor.status_updated',
            'user_id' => $construction->id,
        ]);
    }

    public function test_construction_user_can_submit_blade_requisition_and_finance_can_approve(): void
    {
        $this->seed();

        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();
        $requiredBy = now()->addDays(8)->toDateString();

        $this->actingAs($construction)
            ->from(route('procurement.requisitions.index'))
            ->post(route('procurement.requisitions.store'), [
                'project_id' => $project->id,
                'department' => 'Construction',
                'required_by' => $requiredBy,
                'priority' => 'high',
                'purpose' => 'Blade workspace material requisition.',
                'items' => [
                    [
                        'item_code' => 'BLADE-CEMENT',
                        'description' => 'Blade test cement',
                        'unit' => 'bag',
                        'quantity' => 25,
                        'estimated_rate' => 410,
                    ],
                ],
            ])
            ->assertRedirect(route('procurement.requisitions.index', ['project_id' => $project->id, 'status' => 'submitted']))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $requisition = PurchaseRequisition::where('purpose', 'Blade workspace material requisition.')->firstOrFail();

        $this->assertDatabaseHas('purchase_requisitions', [
            'id' => $requisition->id,
            'project_id' => $project->id,
            'required_by' => $requiredBy.' 00:00:00',
            'status' => 'submitted',
            'estimated_total' => 10250,
        ]);

        $this->actingAs($finance)
            ->from(route('procurement.requisitions.index'))
            ->patch(route('procurement.requisitions.approve', $requisition), [
                'note' => 'Approved from Blade procurement workspace.',
            ])
            ->assertRedirect(route('procurement.requisitions.index', ['project_id' => $project->id, 'status' => 'approved']))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('purchase_requisitions', [
            'id' => $requisition->id,
            'status' => 'approved',
            'approved_by_user_id' => $finance->id,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'procurement.requisition.approved',
            'user_id' => $finance->id,
        ]);
    }

    public function test_procurement_quote_comparison_uses_scoped_requisition_and_linked_purchase_orders(): void
    {
        $this->seed();

        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $requisition = PurchaseRequisition::where('requisition_number', 'PR-1001')->firstOrFail();

        $response = $this->actingAs($construction)
            ->getJson(route('procurement.requisitions.quote-comparison', $requisition))
            ->assertOk()
            ->assertJsonPath('data.source', 'laravel_purchase_requisition_purchase_orders')
            ->assertJsonPath('data.requisition.requisition_number', 'PR-1001')
            ->assertJsonPath('data.summary.candidate_count', 1)
            ->assertJsonPath('data.summary.lowest_po_number', 'PO-1001')
            ->assertJsonPath('data.summary.lowest_total_amount', 410050)
            ->assertJsonPath('data.summary.variance_against_estimate', 57550)
            ->assertJsonPath('data.summary.recommendation_status', 'single_candidate')
            ->assertJsonPath('data.candidates.0.vendor.name', 'BuildMat Supplies Pvt Ltd')
            ->assertJsonPath('data.item_comparison.0.item_code', 'CEMENT-OPC-53')
            ->assertJsonPath('data.item_comparison.0.lowest_rate', 380)
            ->assertJsonPath('data.item_comparison.0.rate_variance_against_estimate', -5);

        $this->assertCount(2, $response->json('data.item_comparison'));
    }

    public function test_procurement_dashboard_reports_pending_delivery_and_low_stock_from_database(): void
    {
        $this->seed();

        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $stockItem = StockItem::where('item_code', 'CEMENT-OPC-53')->firstOrFail();
        $stockItem->forceFill(['minimum_stock_quantity' => 400])->save();

        $response = $this->actingAs($construction)
            ->getJson(route('procurement.dashboard', ['project_id' => $stockItem->project_id]))
            ->assertOk();

        $payload = $response->json('data');

        $this->assertSame($stockItem->project_id, $payload['filters']['project_id']);
        $this->assertSame(1, $payload['summary']['purchase_orders']['partially_received']);
        $this->assertSame(1, $payload['summary']['pending_delivery']['purchase_orders']);
        $this->assertSame(2, $payload['summary']['pending_delivery']['lines']);
        $this->assertSame(2700.0, (float) $payload['summary']['pending_delivery']['quantity']);
        $this->assertSame(233500.0, (float) $payload['summary']['pending_delivery']['amount']);
        $this->assertSame(1, $payload['summary']['stock']['low_stock_items']);

        $pendingByItem = collect($payload['pending_deliveries'])->keyBy('item_code');

        $this->assertSame(200.0, (float) $pendingByItem->get('CEMENT-OPC-53')['pending_quantity']);
        $this->assertSame(76000.0, (float) $pendingByItem->get('CEMENT-OPC-53')['pending_amount']);
        $this->assertSame(2500.0, (float) $pendingByItem->get('STEEL-TMT-12')['pending_quantity']);
        $this->assertSame(157500.0, (float) $pendingByItem->get('STEEL-TMT-12')['pending_amount']);

        $this->assertSame('CEMENT-OPC-53', $payload['low_stock_items'][0]['item_code']);
        $this->assertSame(300.0, (float) $payload['low_stock_items'][0]['on_hand_quantity']);
        $this->assertSame(400.0, (float) $payload['low_stock_items'][0]['minimum_stock_quantity']);
    }

    public function test_non_global_procurement_users_without_company_assignment_fail_closed(): void
    {
        $this->seed();

        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();
        $vendor = Vendor::where('vendor_code', 'VEN-1001')->firstOrFail();
        $requisition = PurchaseRequisition::where('requisition_number', 'PR-1001')->firstOrFail();
        $purchaseOrder = PurchaseOrder::where('po_number', 'PO-1001')->firstOrFail();

        $construction->forceFill(['company_id' => null])->save();
        $finance->forceFill(['company_id' => null])->save();

        $this->actingAs($construction)
            ->getJson(route('procurement.dashboard'))
            ->assertOk()
            ->assertJsonPath('data.summary.purchase_orders.total', 0)
            ->assertJsonPath('data.summary.pending_delivery.lines', 0);

        $this->actingAs($construction)
            ->getJson(route('procurement.vendors.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($construction)
            ->getJson(route('procurement.requisitions.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($construction)
            ->getJson(route('procurement.purchase-orders.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($construction)
            ->getJson(route('procurement.goods-receipts.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($construction)
            ->getJson(route('procurement.stock-items.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($construction)
            ->getJson(route('procurement.requisitions.index', ['project_id' => $project->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id']);

        $this->actingAs($construction)
            ->getJson(route('procurement.requisitions.quote-comparison', $requisition))
            ->assertForbidden();

        $this->actingAs($construction)
            ->getJson(route('procurement.purchase-orders.index', [
                'project_id' => $project->id,
                'vendor_id' => $vendor->id,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id', 'vendor_id']);

        $this->actingAs($construction)
            ->getJson(route('procurement.goods-receipts.index', [
                'project_id' => $project->id,
                'purchase_order_id' => $purchaseOrder->id,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id', 'purchase_order_id']);

        $this->actingAs($construction)
            ->getJson(route('procurement.stock-items.index', ['project_id' => $project->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id']);

        $this->actingAs($construction)
            ->getJson(route('procurement.dashboard', ['project_id' => $project->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id']);

        $this->actingAs($construction)
            ->getJson(route('procurement.dashboard', [
                'project_id' => $project->id,
                'vendor_id' => $vendor->id,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id', 'vendor_id']);

        $this->actingAs($construction)
            ->postJson(route('procurement.requisitions.store'), [
                'project_id' => $project->id,
                'department' => 'Construction',
                'required_by' => now()->addDays(5)->toDateString(),
                'priority' => 'normal',
                'items' => [
                    [
                        'item_code' => 'SCOPE-CEMENT',
                        'description' => 'Scope Cement',
                        'unit' => 'bag',
                        'quantity' => 10,
                        'estimated_rate' => 300,
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id']);

        $this->actingAs($construction)
            ->postJson(route('procurement.purchase-orders.store'), [
                'purchase_requisition_id' => $requisition->id,
                'vendor_id' => $vendor->id,
                'po_date' => now()->toDateString(),
                'items' => [
                    [
                        'item_code' => 'SCOPE-CEMENT',
                        'description' => 'Scope Cement',
                        'unit' => 'bag',
                        'quantity' => 10,
                        'rate' => 300,
                        'tax_rate' => 18,
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['purchase_requisition_id', 'vendor_id']);

        $this->actingAs($finance)
            ->patchJson(route('procurement.requisitions.approve', $requisition), [
                'note' => 'Scope guard should reject this approval.',
            ])
            ->assertForbidden();

        $this->actingAs($finance)
            ->patchJson(route('procurement.purchase-orders.approve', $purchaseOrder), [
                'note' => 'Scope guard should reject this approval.',
            ])
            ->assertForbidden();

        $this->actingAs($construction)
            ->postJson(route('procurement.goods-receipts.store'), [
                'purchase_order_id' => $purchaseOrder->id,
                'received_on' => now()->toDateString(),
                'items' => [
                    [
                        'item_code' => 'BRICK-FLYASH',
                        'accepted_quantity' => 1,
                    ],
                ],
            ])
            ->assertForbidden();
    }

    public function test_vendor_sensitive_statutory_and_bank_details_are_encrypted_and_masked(): void
    {
        $this->seed();

        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();

        $vendor = Vendor::create([
            'company_id' => $construction->company_id,
            'vendor_code' => 'VEN-SEC-001',
            'name' => 'A Secure Vendor',
            'vendor_type' => 'material',
            'contact_name' => 'Secure Contact',
            'gstin' => '27AABCS1234F1Z8',
            'pan' => 'AABCS1234F',
            'bank_details' => [
                'account_holder' => 'A Secure Vendor',
                'account_number' => '1234567893210',
                'ifsc' => 'HDFC0001234',
                'account_masked' => 'XXXXXX3210',
            ],
            'status' => 'active',
        ]);

        $raw = DB::table('vendors')->where('id', $vendor->id)->first();
        $rawBankDetails = json_decode((string) $raw->bank_details, true, 512, JSON_THROW_ON_ERROR);

        $this->assertNull($raw->pan);
        $this->assertNotSame('AABCS1234F', $raw->pan_encrypted);
        $this->assertSame('234F', $raw->pan_last4);
        $this->assertNotSame('1234567893210', $rawBankDetails['account_number']);
        $this->assertNotSame('HDFC0001234', $rawBankDetails['ifsc']);
        $this->assertSame('XXXXXX3210', $rawBankDetails['account_masked']);

        $freshVendor = Vendor::findOrFail($vendor->id);
        $this->assertSame('AABCS1234F', $freshVendor->pan);
        $this->assertSame('1234567893210', $freshVendor->bank_details['account_number']);
        $this->assertSame('HDFC0001234', $freshVendor->bank_details['ifsc']);

        $this->actingAs($construction)
            ->getJson(route('procurement.vendors.index', ['search' => 'VEN-SEC-001']))
            ->assertOk()
            ->assertJsonPath('data.0.vendor_code', 'VEN-SEC-001')
            ->assertJsonPath('data.0.pan', '******234F')
            ->assertJsonPath('data.0.pan_last4', '234F')
            ->assertJsonPath('data.0.bank_details.account_holder', 'A Secure Vendor')
            ->assertJsonPath('data.0.bank_details.account_number', '******3210')
            ->assertJsonPath('data.0.bank_details.ifsc', '******1234')
            ->assertJsonPath('data.0.bank_details.account_masked', 'XXXXXX3210');
    }

    public function test_procurement_manager_can_create_update_and_change_vendor_status(): void
    {
        $this->seed();

        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();

        $vendorId = $this->actingAs($construction)
            ->postJson(route('procurement.vendors.store'), [
                'vendor_code' => 'CON-LIFT-001',
                'name' => 'Lift Installation Contractors Pvt Ltd',
                'vendor_type' => 'contractor',
                'contact_name' => 'Anand Rao',
                'email' => 'accounts@liftcontractors.example',
                'phone' => '+91 98765 43210',
                'gstin' => '27ABCDE1234F1Z5',
                'pan' => 'ABCDE1234F',
                'address' => [
                    'line1' => 'Plot 21, Industrial Estate',
                    'city' => 'Pune',
                    'state' => 'Maharashtra',
                    'pin_code' => '411001',
                ],
                'bank_details' => [
                    'account_holder' => 'Lift Installation Contractors Pvt Ltd',
                    'account_number' => '9876543210987',
                    'ifsc' => 'hdfc0004321',
                    'account_masked' => 'XXXXXX0987',
                ],
                'compliance_documents' => [
                    ['type' => 'gst_certificate', 'status' => 'verified'],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.vendor_code', 'CON-LIFT-001')
            ->assertJsonPath('data.vendor_type', 'contractor')
            ->assertJsonPath('data.pan', '******234F')
            ->assertJsonPath('data.pan_last4', '234F')
            ->assertJsonPath('data.bank_details.account_number', '******0987')
            ->assertJsonPath('data.bank_details.ifsc', '******4321')
            ->json('data.id');

        $vendor = Vendor::findOrFail($vendorId);
        $raw = DB::table('vendors')->where('id', $vendor->id)->first();
        $rawBankDetails = json_decode((string) $raw->bank_details, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame($construction->company_id, $vendor->company_id);
        $this->assertNull($raw->pan);
        $this->assertNotSame('ABCDE1234F', $raw->pan_encrypted);
        $this->assertSame('234F', $raw->pan_last4);
        $this->assertNotSame('9876543210987', $rawBankDetails['account_number']);
        $this->assertNotSame('HDFC0004321', $rawBankDetails['ifsc']);

        $this->actingAs($finance)
            ->patchJson(route('procurement.vendors.update', $vendor), [
                'name' => 'Finance cannot update vendor master',
            ])
            ->assertForbidden();

        $this->actingAs($construction)
            ->patchJson(route('procurement.vendors.update', $vendor), [
                'name' => 'Lift Installation Contractors LLP',
                'contact_name' => 'Anand R.',
                'pan' => 'ABCDE9999F',
                'bank_details' => [
                    'account_holder' => 'Lift Installation Contractors LLP',
                    'account_number' => '111122223333',
                    'ifsc' => 'icic0007890',
                    'account_masked' => 'XXXXXX3333',
                ],
                'metadata' => [
                    'evaluation_grade' => 'A',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Lift Installation Contractors LLP')
            ->assertJsonPath('data.contact_name', 'Anand R.')
            ->assertJsonPath('data.pan_last4', '999F')
            ->assertJsonPath('data.bank_details.account_number', '******3333')
            ->assertJsonPath('data.bank_details.ifsc', '******7890');

        $this->actingAs($construction)
            ->patchJson(route('procurement.vendors.status.update', $vendor), [
                'status' => 'inactive',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);

        $this->actingAs($construction)
            ->patchJson(route('procurement.vendors.status.update', $vendor), [
                'status' => 'inactive',
                'reason' => 'Contract completed and vendor moved to inactive list.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'inactive');

        $vendor->refresh();

        $this->assertSame('inactive', $vendor->status);
        $this->assertSame('Contract completed and vendor moved to inactive list.', $vendor->metadata['last_status_reason']);
        $this->assertSame('inactive', collect($vendor->metadata['status_history'])->last()['to']);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'procurement.vendor.created',
            'auditable_id' => $vendor->id,
            'metadata->vendor_code' => 'CON-LIFT-001',
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'procurement.vendor.updated',
            'auditable_id' => $vendor->id,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'procurement.vendor.status_updated',
            'auditable_id' => $vendor->id,
            'metadata->to_status' => 'inactive',
        ]);
    }

    public function test_vendor_master_validation_scope_and_partner_restrictions(): void
    {
        $this->seed();

        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();
        $otherCompany = Company::where('code', 'B360P')->firstOrFail();
        $otherVendor = Vendor::create([
            'company_id' => $otherCompany->id,
            'vendor_code' => 'VEN-OTHER-MASTER',
            'name' => 'Other Company Master Vendor',
            'vendor_type' => 'service',
            'status' => 'active',
        ]);

        $this->actingAs($construction)
            ->postJson(route('procurement.vendors.store'), [
                'company_id' => $otherCompany->id,
                'vendor_code' => 'VEN-SCOPE-001',
                'name' => 'Cross Company Vendor',
                'vendor_type' => 'material',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['company_id']);

        $this->actingAs($construction)
            ->postJson(route('procurement.vendors.store'), [
                'vendor_code' => 'VEN-1001',
                'name' => 'Duplicate Vendor Code',
                'vendor_type' => 'material',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['vendor_code']);

        $this->actingAs($construction)
            ->postJson(route('procurement.vendors.store'), [
                'vendor_code' => 'VEN-DUP-GST',
                'name' => 'Duplicate GST Vendor',
                'vendor_type' => 'material',
                'gstin' => '27AABCB1234F1Z5',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['gstin']);

        $this->actingAs($construction)
            ->patchJson(route('procurement.vendors.update', $otherVendor), [
                'name' => 'Cross company update must fail',
            ])
            ->assertForbidden();

        $this->actingAs($partner)
            ->postJson(route('procurement.vendors.store'), [])
            ->assertForbidden();

        $this->actingAs($partner)
            ->patchJson(route('procurement.vendors.update', Vendor::where('vendor_code', 'VEN-1001')->firstOrFail()), [
                'name' => 'Partner cannot update vendor',
            ])
            ->assertForbidden();

        $construction->forceFill(['company_id' => null])->save();

        $this->actingAs($construction)
            ->postJson(route('procurement.vendors.store'), [
                'vendor_code' => 'VEN-NO-COMPANY',
                'name' => 'No Company Vendor',
                'vendor_type' => 'service',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['company_id']);
    }

    public function test_vendor_performance_returns_purchase_history_payable_position_and_rating(): void
    {
        $this->seed();

        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $vendor = Vendor::where('vendor_code', 'VEN-1001')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();

        $voucher = FinancialVoucher::create([
            'company_id' => $vendor->company_id,
            'project_id' => $project->id,
            'created_by_user_id' => $finance->id,
            'approved_by_user_id' => $finance->id,
            'voucher_number' => 'PV-VEN-1001',
            'voucher_type' => 'payment',
            'status' => 'approved',
            'voucher_date' => now()->toDateString(),
            'reference_number' => 'PAY-VEN-1001',
            'narration' => 'Partial payment to vendor against accepted material.',
            'currency' => 'INR',
            'total_debit' => 40000,
            'total_credit' => 40000,
            'workflow_history' => [],
            'approved_at' => now(),
        ]);

        FinancialVoucherLine::create([
            'financial_voucher_id' => $voucher->id,
            'project_id' => $project->id,
            'line_number' => 1,
            'account_code' => 'AP-MATERIAL',
            'account_name' => 'Vendor Payable',
            'line_type' => 'debit',
            'amount' => 40000,
            'party_type' => Vendor::class,
            'party_id' => $vendor->id,
            'description' => 'Vendor payable settled partially.',
        ]);

        FinancialVoucherLine::create([
            'financial_voucher_id' => $voucher->id,
            'project_id' => $project->id,
            'line_number' => 2,
            'account_code' => 'BANK-HDFC-001',
            'account_name' => 'HDFC Bank',
            'line_type' => 'credit',
            'amount' => 40000,
            'description' => 'Bank payment to vendor.',
        ]);

        $this->actingAs($construction)
            ->getJson(route('procurement.vendors.performance', $vendor))
            ->assertOk()
            ->assertJsonPath('data.vendor.vendor_code', 'VEN-1001')
            ->assertJsonPath('data.summary.purchase_orders', 1)
            ->assertJsonPath('data.summary.open_purchase_orders', 1)
            ->assertJsonPath('data.summary.purchase_order_total', 410050)
            ->assertJsonPath('data.summary.goods_receipts', 1)
            ->assertJsonPath('data.summary.accepted_amount', 114000)
            ->assertJsonPath('data.summary.paid_amount', 40000)
            ->assertJsonPath('data.summary.payable_amount', 74000)
            ->assertJsonPath('data.summary.acceptance_rate_percent', 100)
            ->assertJsonPath('data.summary.fulfillment_rate_percent', 10)
            ->assertJsonPath('data.summary.on_time_delivery_rate_percent', 100)
            ->assertJsonPath('data.summary.rating_score', 5)
            ->assertJsonPath('data.summary.rating_label', 'Excellent')
            ->assertJsonPath('data.purchase_history.0.po_number', 'PO-1001')
            ->assertJsonPath('data.purchase_history.0.accepted_total', 114000)
            ->assertJsonPath('data.goods_receipt_history.0.grn_number', 'GRN-1001');
    }

    public function test_procurement_indexes_validate_filters_and_company_scope(): void
    {
        $this->seed();

        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $otherCompany = Company::where('code', 'B360P')->firstOrFail();
        $otherProject = Project::where('company_id', $otherCompany->id)->firstOrFail();
        $purchaseOrder = PurchaseOrder::where('po_number', 'PO-1001')->firstOrFail();
        $otherVendor = Vendor::create([
            'company_id' => $otherCompany->id,
            'vendor_code' => 'VEN-OTHER-001',
            'name' => 'Other Company Supplier',
            'vendor_type' => 'material',
            'status' => 'active',
        ]);
        $otherPurchaseOrder = PurchaseOrder::create([
            'company_id' => $otherCompany->id,
            'project_id' => $otherProject->id,
            'vendor_id' => $otherVendor->id,
            'po_number' => 'PO-OTHER-001',
            'po_date' => now()->toDateString(),
            'status' => 'approved',
            'items' => [],
            'subtotal' => 0,
            'tax_amount' => 0,
            'total_amount' => 0,
        ]);

        $this->actingAs($construction)
            ->getJson(route('procurement.vendors.index', ['unexpected_filter' => 'ignored-before-hardening']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['unexpected_filter'])
            ->assertJsonPath('errors.unexpected_filter.0', 'The selected filter is not available for this endpoint.');

        $this->actingAs($construction)
            ->getJson(route('procurement.vendors.index', ['status' => 'unknown']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->actingAs($construction)
            ->getJson(route('procurement.vendors.index', ['vendor_type' => 'unknown']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['vendor_type']);

        $this->actingAs($construction)
            ->getJson(route('procurement.vendors.index', ['search' => str_repeat('x', 121)]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['search']);

        $this->actingAs($construction)
            ->getJson(route('procurement.requisitions.index', ['status' => 'unknown']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->actingAs($construction)
            ->getJson(route('procurement.requisitions.index', ['unexpected_filter' => 'ignored-before-hardening']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['unexpected_filter'])
            ->assertJsonPath('errors.unexpected_filter.0', 'The selected filter is not available for this endpoint.');

        $this->actingAs($construction)
            ->getJson(route('procurement.requisitions.index', ['project_id' => $otherProject->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id']);

        $this->actingAs($construction)
            ->getJson(route('procurement.purchase-orders.index', ['status' => 'unknown']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->actingAs($construction)
            ->getJson(route('procurement.purchase-orders.index', ['unexpected_filter' => 'ignored-before-hardening']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['unexpected_filter'])
            ->assertJsonPath('errors.unexpected_filter.0', 'The selected filter is not available for this endpoint.');

        $this->actingAs($construction)
            ->getJson(route('procurement.purchase-orders.index', ['vendor_id' => $otherVendor->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['vendor_id']);

        $this->actingAs($construction)
            ->getJson(route('procurement.vendors.performance', $otherVendor))
            ->assertForbidden();

        $this->actingAs($construction)
            ->getJson(route('procurement.purchase-orders.index', ['project_id' => $otherProject->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id']);

        $this->actingAs($construction)
            ->getJson(route('procurement.goods-receipts.index', ['purchase_order_id' => $otherPurchaseOrder->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['purchase_order_id']);

        $this->actingAs($construction)
            ->getJson(route('procurement.goods-receipts.index', ['unexpected_filter' => 'ignored-before-hardening']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['unexpected_filter'])
            ->assertJsonPath('errors.unexpected_filter.0', 'The selected filter is not available for this endpoint.');

        $this->actingAs($construction)
            ->getJson(route('procurement.goods-receipts.index', ['project_id' => $otherProject->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id']);

        $this->actingAs($construction)
            ->getJson(route('procurement.stock-items.index', ['unexpected_filter' => 'ignored-before-hardening']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['unexpected_filter'])
            ->assertJsonPath('errors.unexpected_filter.0', 'The selected filter is not available for this endpoint.');

        $this->actingAs($construction)
            ->getJson(route('procurement.stock-items.index', ['project_id' => $otherProject->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id']);

        $this->actingAs($construction)
            ->getJson(route('procurement.stock-items.index', [
                'project_id' => $purchaseOrder->project_id,
                'item_code' => 'cement',
                'store_type' => 'site',
                'low_stock' => false,
                'per_page' => 10,
            ]))
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->actingAs($construction)
            ->getJson(route('procurement.purchase-orders.index', [
                'status' => 'partially_received',
                'vendor_id' => $purchaseOrder->vendor_id,
                'per_page' => 10,
            ]))
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_requisition_submission_and_finance_approval_workflow(): void
    {
        $this->seed();

        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();

        $payload = [
            'project_id' => $project->id,
            'department' => 'Construction',
            'required_by' => now()->addDays(12)->toDateString(),
            'priority' => 'urgent',
            'purpose' => 'Waterproofing material for podium work.',
            'items' => [
                [
                    'item_code' => 'WATERPROOF-CHEM',
                    'description' => 'Waterproofing Chemical',
                    'unit' => 'litre',
                    'quantity' => 120,
                    'estimated_rate' => 240,
                ],
            ],
        ];

        $requisitionNumber = $this->actingAs($construction)
            ->postJson(route('procurement.requisitions.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.estimated_total', 28800)
            ->json('data.requisition_number');

        $requisition = PurchaseRequisition::where('requisition_number', $requisitionNumber)->firstOrFail();

        $this->actingAs($construction)
            ->patchJson(route('procurement.requisitions.approve', $requisition))
            ->assertForbidden();

        $this->actingAs($finance)
            ->patchJson(route('procurement.requisitions.approve', $requisition), [
                'note' => str_repeat('x', 1001),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('note');

        $this->actingAs($finance)
            ->patchJson(route('procurement.requisitions.approve', $requisition), [
                'note' => 'Approved after budget availability check.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.approved_by.email', 'suresh.iyer@builder360.test');

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'procurement.requisition.approved',
            'user_id' => $finance->id,
        ]);

        $requisition->refresh();

        $this->assertSame('Approved after budget availability check.', collect($requisition->workflow_history)->last()['note']);
        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'procurement.requisition.approved',
            'metadata->note' => 'Approved after budget availability check.',
        ]);
    }

    public function test_purchase_order_creation_approval_and_goods_receipt(): void
    {
        $this->seed();

        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $requisition = PurchaseRequisition::where('requisition_number', 'PR-1001')->firstOrFail();
        $vendor = Vendor::where('vendor_code', 'VEN-1001')->firstOrFail();

        $poPayload = [
            'purchase_requisition_id' => $requisition->id,
            'vendor_id' => $vendor->id,
            'po_date' => now()->toDateString(),
            'expected_delivery_on' => now()->addDays(7)->toDateString(),
            'payment_terms' => '15 days after accepted GRN',
            'terms' => 'Subject to quality acceptance.',
            'items' => [
                [
                    'item_code' => 'BRICK-FLYASH',
                    'description' => 'Fly Ash Bricks',
                    'unit' => 'piece',
                    'quantity' => 1000,
                    'rate' => 9.5,
                    'tax_rate' => 5,
                ],
            ],
        ];

        $poNumber = $this->actingAs($construction)
            ->postJson(route('procurement.purchase-orders.store'), $poPayload)
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.subtotal', 9500)
            ->assertJsonPath('data.tax_amount', 475)
            ->assertJsonPath('data.total_amount', 9975)
            ->json('data.po_number');

        $purchaseOrder = PurchaseOrder::where('po_number', $poNumber)->firstOrFail();

        $this->actingAs($construction)
            ->patchJson(route('procurement.purchase-orders.approve', $purchaseOrder))
            ->assertForbidden();

        $this->actingAs($finance)
            ->patchJson(route('procurement.purchase-orders.approve', $purchaseOrder), [
                'note' => str_repeat('x', 1001),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('note');

        $this->actingAs($finance)
            ->patchJson(route('procurement.purchase-orders.approve', $purchaseOrder), [
                'note' => 'Approved subject to site quality inspection.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.approved_by.email', 'suresh.iyer@builder360.test');

        $purchaseOrder->refresh();

        $this->assertSame('Approved subject to site quality inspection.', collect($purchaseOrder->workflow_history)->last()['note']);
        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'procurement.purchase_order.approved',
            'metadata->note' => 'Approved subject to site quality inspection.',
        ]);

        $receiptPayload = [
            'purchase_order_id' => $purchaseOrder->id,
            'received_on' => now()->toDateString(),
            'delivery_challan_number' => 'DC-TEST-1001',
            'quality_notes' => 'Partial delivery accepted.',
            'items' => [
                [
                    'item_code' => 'BRICK-FLYASH',
                    'accepted_quantity' => 400,
                    'rejected_quantity' => 0,
                    'remarks' => 'Accepted at site.',
                ],
            ],
        ];

        $this->actingAs($construction)
            ->postJson(route('procurement.goods-receipts.store'), $receiptPayload)
            ->assertCreated()
            ->assertJsonPath('data.status', 'received')
            ->assertJsonPath('data.accepted_total', 3800)
            ->assertJsonPath('data.stock_movements.0.movement_type', 'inward')
            ->assertJsonPath('data.stock_movements.0.item_code', 'BRICK-FLYASH')
            ->assertJsonPath('data.stock_movements.0.quantity', 400);

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $purchaseOrder->id,
            'status' => 'partially_received',
        ]);

        $stockItem = StockItem::where('project_id', $purchaseOrder->project_id)
            ->where('item_code', 'BRICK-FLYASH')
            ->firstOrFail();

        $this->assertSame(400.0, (float) $stockItem->on_hand_quantity);
        $this->assertSame(3800.0, (float) $stockItem->stock_value);
        $this->assertSame(9.5, (float) $stockItem->average_rate);
        $this->assertDatabaseHas('stock_movements', [
            'stock_item_id' => $stockItem->id,
            'purchase_order_id' => $purchaseOrder->id,
            'movement_type' => 'inward',
            'item_code' => 'BRICK-FLYASH',
            'quantity' => 400,
            'amount' => 3800,
        ]);

        $receiptPayload['items'][0]['accepted_quantity'] = 700;

        $this->actingAs($construction)
            ->postJson(route('procurement.goods-receipts.store'), $receiptPayload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items');

        $duplicatePayload = $receiptPayload;
        $duplicatePayload['items'] = [
            [
                'item_code' => 'BRICK-FLYASH',
                'accepted_quantity' => 10,
            ],
            [
                'item_code' => 'BRICK-FLYASH',
                'accepted_quantity' => 10,
            ],
        ];

        $this->actingAs($construction)
            ->postJson(route('procurement.goods-receipts.store'), $duplicatePayload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items');

        $this->assertSame(1, StockMovement::where('purchase_order_id', $purchaseOrder->id)->where('item_code', 'BRICK-FLYASH')->count());
    }

    public function test_stock_issue_reduces_balance_and_blocks_negative_stock(): void
    {
        $this->seed();

        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $stockItem = StockItem::where('item_code', 'CEMENT-OPC-53')->firstOrFail();

        $this->actingAs($construction)
            ->postJson(route('procurement.stock-issues.store'), [
                'stock_item_id' => $stockItem->id,
                'movement_type' => 'issue',
                'movement_date' => now()->toDateString(),
                'quantity' => 25,
                'issue_reference' => 'SITE-ISSUE-1001',
                'purpose' => 'Issue cement bags to Tower A slab crew.',
                'remarks' => 'Issued from site store.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.movement_type', 'issue')
            ->assertJsonPath('data.item_code', 'CEMENT-OPC-53')
            ->assertJsonPath('data.quantity', -25)
            ->assertJsonPath('data.amount', -9500)
            ->assertJsonPath('data.balance_after_quantity', 275)
            ->assertJsonPath('data.balance_after_value', 104500)
            ->assertJsonPath('data.metadata.issue_reference', 'SITE-ISSUE-1001')
            ->assertJsonPath('data.stock_item.on_hand_quantity', 275);

        $stockItem->refresh();

        $this->assertSame(275.0, (float) $stockItem->on_hand_quantity);
        $this->assertSame(104500.0, (float) $stockItem->stock_value);
        $this->assertSame(380.0, (float) $stockItem->average_rate);
        $this->assertDatabaseHas('stock_movements', [
            'stock_item_id' => $stockItem->id,
            'movement_type' => 'issue',
            'item_code' => 'CEMENT-OPC-53',
            'quantity' => -25,
            'amount' => -9500,
            'balance_after_quantity' => 275,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'procurement.stock_movement.issued',
            'metadata->item_code' => 'CEMENT-OPC-53',
            'metadata->quantity' => 25,
        ]);

        $this->actingAs($construction)
            ->postJson(route('procurement.stock-issues.store'), [
                'stock_item_id' => $stockItem->id,
                'movement_type' => 'consumption',
                'movement_date' => now()->toDateString(),
                'quantity' => 999,
                'purpose' => 'Attempt to over-consume stock.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity']);

        $this->actingAs($construction)
            ->postJson(route('procurement.stock-issues.store'), [
                'stock_item_id' => $stockItem->id,
                'movement_type' => 'return',
                'movement_date' => now()->toDateString(),
                'quantity' => 1,
                'purpose' => 'Invalid movement type.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['movement_type']);

        $this->assertSame(1, StockMovement::where('stock_item_id', $stockItem->id)->whereIn('movement_type', ['issue', 'consumption', 'wastage'])->count());
    }

    public function test_stock_issue_scope_and_partner_restrictions_are_enforced(): void
    {
        $this->seed();

        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();
        $otherCompany = Company::where('code', 'B360P')->firstOrFail();
        $otherProject = Project::where('company_id', $otherCompany->id)->firstOrFail();
        $otherStockItem = StockItem::create([
            'company_id' => $otherCompany->id,
            'project_id' => $otherProject->id,
            'store_type' => 'site',
            'item_code' => 'OTHER-CEMENT',
            'description' => 'Other Company Cement',
            'unit' => 'bag',
            'on_hand_quantity' => 10,
            'stock_value' => 4000,
            'average_rate' => 400,
            'status' => 'active',
        ]);

        $this->actingAs($construction)
            ->postJson(route('procurement.stock-issues.store'), [
                'stock_item_id' => $otherStockItem->id,
                'movement_type' => 'issue',
                'movement_date' => now()->toDateString(),
                'quantity' => 1,
                'purpose' => 'Cross-company issue should fail.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['stock_item_id']);

        $construction->forceFill(['company_id' => null])->save();

        $ownStockItem = StockItem::where('item_code', 'CEMENT-OPC-53')->firstOrFail();

        $this->actingAs($construction)
            ->postJson(route('procurement.stock-issues.store'), [
                'stock_item_id' => $ownStockItem->id,
                'movement_type' => 'wastage',
                'movement_date' => now()->toDateString(),
                'quantity' => 1,
                'purpose' => 'No company assignment should fail closed.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['stock_item_id']);

        $this->actingAs($partner)
            ->postJson(route('procurement.stock-issues.store'), [
                'stock_item_id' => $ownStockItem->id,
                'movement_type' => 'issue',
                'movement_date' => now()->toDateString(),
                'quantity' => 1,
                'purpose' => 'Partner cannot issue stock.',
            ])
            ->assertForbidden();
    }

    public function test_low_stock_alert_is_created_from_real_threshold_and_deduped_until_read(): void
    {
        $this->seed();

        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $stockItem = StockItem::where('item_code', 'CEMENT-OPC-53')->firstOrFail();
        $stockItem->forceFill(['minimum_stock_quantity' => 280])->save();

        $this->actingAs($construction)
            ->postJson(route('procurement.stock-issues.store'), [
                'stock_item_id' => $stockItem->id,
                'movement_type' => 'issue',
                'movement_date' => now()->toDateString(),
                'quantity' => 25,
                'issue_reference' => 'LOW-STOCK-ISSUE-1001',
                'purpose' => 'Bring cement below configured minimum threshold.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.balance_after_quantity', 275);

        $alertQuery = UserNotification::query()
            ->where('category', 'procurement')
            ->where('severity', 'warning')
            ->where('status', 'unread')
            ->where('notifiable_type', StockItem::class)
            ->where('notifiable_id', $stockItem->id)
            ->where('payload->alert_type', 'low_stock');

        $initialUnreadAlertCount = $alertQuery->count();

        $this->assertGreaterThan(0, $initialUnreadAlertCount);
        $this->assertDatabaseHas('user_notifications', [
            'category' => 'procurement',
            'severity' => 'warning',
            'status' => 'unread',
            'notifiable_type' => StockItem::class,
            'notifiable_id' => $stockItem->id,
            'payload->on_hand_quantity' => 275,
            'payload->minimum_stock_quantity' => 280,
        ]);

        $this->actingAs($construction)
            ->postJson(route('procurement.stock-issues.store'), [
                'stock_item_id' => $stockItem->id,
                'movement_type' => 'consumption',
                'movement_date' => now()->toDateString(),
                'quantity' => 1,
                'purpose' => 'Still below threshold but active unread alert already exists.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.balance_after_quantity', 274);

        $this->assertSame($initialUnreadAlertCount, $alertQuery->count());

        $alertQuery->update(['status' => 'read', 'read_at' => now(), 'updated_at' => now()]);

        $this->actingAs($construction)
            ->postJson(route('procurement.stock-issues.store'), [
                'stock_item_id' => $stockItem->id,
                'movement_type' => 'wastage',
                'movement_date' => now()->toDateString(),
                'quantity' => 1,
                'purpose' => 'Below threshold after previous alert was read.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.balance_after_quantity', 273);

        $this->assertSame($initialUnreadAlertCount, UserNotification::query()
            ->where('category', 'procurement')
            ->where('severity', 'warning')
            ->where('status', 'unread')
            ->where('notifiable_type', StockItem::class)
            ->where('notifiable_id', $stockItem->id)
            ->where('payload->alert_type', 'low_stock')
            ->count());
    }

    public function test_stock_return_increases_balance_and_creates_audited_ledger_entry(): void
    {
        $this->seed();

        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $stockItem = StockItem::where('item_code', 'CEMENT-OPC-53')->firstOrFail();

        $this->actingAs($construction)
            ->postJson(route('procurement.stock-returns.store'), [
                'stock_item_id' => $stockItem->id,
                'movement_date' => now()->toDateString(),
                'quantity' => 10,
                'return_reference' => 'RET-1001',
                'returned_from' => 'Tower A slab crew',
                'reason' => 'Unused cement bags returned after slab pour.',
                'remarks' => 'Bags inspected before return.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.movement_type', 'return')
            ->assertJsonPath('data.item_code', 'CEMENT-OPC-53')
            ->assertJsonPath('data.quantity', 10)
            ->assertJsonPath('data.amount', 3800)
            ->assertJsonPath('data.balance_after_quantity', 310)
            ->assertJsonPath('data.balance_after_value', 117800)
            ->assertJsonPath('data.metadata.return_reference', 'RET-1001')
            ->assertJsonPath('data.stock_item.on_hand_quantity', 310);

        $stockItem->refresh();

        $this->assertSame(310.0, (float) $stockItem->on_hand_quantity);
        $this->assertSame(117800.0, (float) $stockItem->stock_value);
        $this->assertDatabaseHas('stock_movements', [
            'stock_item_id' => $stockItem->id,
            'movement_type' => 'return',
            'source_type' => 'stock_return',
            'item_code' => 'CEMENT-OPC-53',
            'quantity' => 10,
            'amount' => 3800,
            'balance_after_quantity' => 310,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'procurement.stock_movement.returned',
            'metadata->item_code' => 'CEMENT-OPC-53',
            'metadata->quantity' => 10,
        ]);
    }

    public function test_stock_transfer_moves_balance_between_project_stores_and_blocks_invalid_transfers(): void
    {
        $this->seed();

        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $sourceItem = StockItem::where('item_code', 'CEMENT-OPC-53')->firstOrFail();
        $destinationProject = Project::where('code', 'GRN-PUN')->firstOrFail();

        $this->actingAs($construction)
            ->postJson(route('procurement.stock-transfers.store'), [
                'source_stock_item_id' => $sourceItem->id,
                'destination_project_id' => $destinationProject->id,
                'destination_store_type' => 'site',
                'movement_date' => now()->toDateString(),
                'quantity' => 40,
                'transfer_reference' => 'TRF-1001',
                'purpose' => 'Move cement to Greenwood site store for upcoming pour.',
                'remarks' => 'Approved by project manager.',
            ])
            ->assertSuccessful()
            ->assertJsonPath('data.0.movement_type', 'transfer_out')
            ->assertJsonPath('data.0.quantity', -40)
            ->assertJsonPath('data.0.balance_after_quantity', 260)
            ->assertJsonPath('data.1.movement_type', 'transfer_in')
            ->assertJsonPath('data.1.quantity', 40)
            ->assertJsonPath('data.1.balance_after_quantity', 40)
            ->assertJsonPath('data.1.metadata.transfer_reference', 'TRF-1001');

        $sourceItem->refresh();
        $destinationItem = StockItem::where('project_id', $destinationProject->id)
            ->where('store_type', 'site')
            ->where('item_code', 'CEMENT-OPC-53')
            ->firstOrFail();

        $this->assertSame(260.0, (float) $sourceItem->on_hand_quantity);
        $this->assertSame(98800.0, (float) $sourceItem->stock_value);
        $this->assertSame(40.0, (float) $destinationItem->on_hand_quantity);
        $this->assertSame(15200.0, (float) $destinationItem->stock_value);
        $this->assertDatabaseHas('stock_movements', [
            'stock_item_id' => $sourceItem->id,
            'movement_type' => 'transfer_out',
            'source_type' => 'stock_transfer',
            'quantity' => -40,
            'amount' => -15200,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'stock_item_id' => $destinationItem->id,
            'movement_type' => 'transfer_in',
            'source_type' => 'stock_transfer',
            'quantity' => 40,
            'amount' => 15200,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'procurement.stock_movement.transferred',
            'metadata->item_code' => 'CEMENT-OPC-53',
            'metadata->quantity' => 40,
        ]);

        $this->actingAs($construction)
            ->postJson(route('procurement.stock-transfers.store'), [
                'source_stock_item_id' => $sourceItem->id,
                'destination_project_id' => $destinationProject->id,
                'destination_store_type' => 'central',
                'movement_date' => now()->toDateString(),
                'quantity' => 999,
                'purpose' => 'Attempt to over-transfer stock.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity']);

        $this->actingAs($construction)
            ->postJson(route('procurement.stock-transfers.store'), [
                'source_stock_item_id' => $sourceItem->id,
                'destination_project_id' => $sourceItem->project_id,
                'destination_store_type' => $sourceItem->store_type,
                'movement_date' => now()->toDateString(),
                'quantity' => 1,
                'purpose' => 'Attempt same-location transfer.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['destination_project_id']);
    }

    public function test_purchase_order_cannot_be_created_from_unapproved_requisition(): void
    {
        $this->seed();

        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();
        $vendor = Vendor::where('vendor_code', 'VEN-1001')->firstOrFail();

        $requisition = PurchaseRequisition::create([
            'company_id' => $project->company_id,
            'project_id' => $project->id,
            'requested_by_user_id' => $construction->id,
            'requisition_number' => 'PR-1999',
            'department' => 'Construction',
            'required_by' => now()->addDays(5)->toDateString(),
            'priority' => 'normal',
            'status' => 'submitted',
            'items' => [['item_code' => 'SAND-RIVER', 'description' => 'River Sand', 'unit' => 'cft', 'quantity' => 100, 'estimated_rate' => 45, 'estimated_amount' => 4500]],
            'estimated_total' => 4500,
            'workflow_history' => [],
        ]);

        $this->actingAs($construction)
            ->postJson(route('procurement.purchase-orders.store'), [
                'purchase_requisition_id' => $requisition->id,
                'vendor_id' => $vendor->id,
                'po_date' => now()->toDateString(),
                'items' => [
                    [
                        'item_code' => 'SAND-RIVER',
                        'description' => 'River Sand',
                        'unit' => 'cft',
                        'quantity' => 100,
                        'rate' => 44,
                        'tax_rate' => 5,
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('purchase_requisition_id');
    }

    public function test_partner_cannot_access_internal_procurement_routes(): void
    {
        $this->seed();

        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();
        $vendor = Vendor::where('vendor_code', 'VEN-1001')->firstOrFail();

        $this->actingAs($partner)
            ->getJson(route('procurement.vendors.index'))
            ->assertForbidden();

        $this->actingAs($partner)
            ->getJson(route('procurement.vendors.performance', $vendor))
            ->assertForbidden();

        $this->actingAs($partner)
            ->postJson(route('procurement.vendors.store'), [])
            ->assertForbidden();

        $this->actingAs($partner)
            ->patchJson(route('procurement.vendors.status.update', $vendor), [
                'status' => 'inactive',
                'reason' => 'Partner cannot mutate internal vendor status.',
            ])
            ->assertForbidden();

        $this->actingAs($partner)
            ->getJson(route('procurement.dashboard'))
            ->assertForbidden();

        $requisition = PurchaseRequisition::where('requisition_number', 'PR-1001')->firstOrFail();

        $this->actingAs($partner)
            ->getJson(route('procurement.requisitions.quote-comparison', $requisition))
            ->assertForbidden();

        $this->actingAs($partner)
            ->getJson(route('procurement.stock-items.index'))
            ->assertForbidden();

        $this->actingAs($partner)
            ->postJson(route('procurement.requisitions.store'), [])
            ->assertForbidden();

        $this->actingAs($partner)
            ->postJson(route('procurement.stock-returns.store'), [])
            ->assertForbidden();

        $this->actingAs($partner)
            ->postJson(route('procurement.stock-transfers.store'), [])
            ->assertForbidden();
    }
}
