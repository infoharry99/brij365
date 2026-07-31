<?php

namespace Tests\Feature;

use App\Models\BoqItem;
use App\Models\Company;
use App\Models\ConstructionMilestone;
use App\Models\ContractorBill;
use App\Models\ContractorMeasurement;
use App\Models\Project;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConstructionBoqMeasurementTest extends TestCase
{
    use RefreshDatabase;

    public function test_construction_user_can_list_and_create_boq_items(): void
    {
        $this->seed();

        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();
        $milestone = ConstructionMilestone::where('milestone_code', 'SKY-SLAB-03')->firstOrFail();
        $contractor = Vendor::where('vendor_code', 'CON-1001')->firstOrFail();

        $this->actingAs($construction)
            ->getJson(route('construction.boq-items.index', ['project_id' => $project->id]))
            ->assertOk()
            ->assertJsonPath('data.0.boq_code', 'BOQ-SKY-RCC-001')
            ->assertJsonPath('data.0.certified_quantity', 200)
            ->assertJsonPath('data.0.balance_quantity', 800);

        $this->actingAs($construction)
            ->postJson(route('construction.boq-items.store'), [
                'project_id' => $project->id,
                'construction_milestone_id' => $milestone->id,
                'vendor_id' => $contractor->id,
                'boq_code' => 'BOQ-SKY-MAS-001',
                'trade' => 'Masonry',
                'description' => 'AAC block masonry for podium level',
                'unit' => 'sqm',
                'planned_quantity' => 650,
                'rate' => 740,
                'specifications' => ['block_size' => '600x200x150mm'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.boq_code', 'BOQ-SKY-MAS-001')
            ->assertJsonPath('data.budget_amount', 481000)
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'construction.boq_item.created',
            'user_id' => $construction->id,
        ]);
    }

    public function test_non_global_construction_users_without_company_assignment_fail_closed_for_boq_and_measurements(): void
    {
        $this->seed();

        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();
        $milestone = ConstructionMilestone::where('milestone_code', 'SKY-SLAB-03')->firstOrFail();
        $contractor = Vendor::where('vendor_code', 'CON-1001')->firstOrFail();
        $boqItem = BoqItem::where('boq_code', 'BOQ-SKY-RCC-001')->firstOrFail();

        $measurement = ContractorMeasurement::create([
            'company_id' => $project->company_id,
            'project_id' => $project->id,
            'vendor_id' => $contractor->id,
            'submitted_by_user_id' => $construction->id,
            'measurement_number' => 'MB-SCOPE-0001',
            'measurement_date' => now()->toDateString(),
            'status' => 'submitted',
            'measured_total' => 1250,
            'certified_total' => 1250,
            'lines' => [
                [
                    'boq_item_id' => $boqItem->id,
                    'boq_code' => $boqItem->boq_code,
                    'description' => $boqItem->description,
                    'trade' => $boqItem->trade,
                    'unit' => $boqItem->unit,
                    'rate' => (float) $boqItem->rate,
                    'planned_quantity' => (float) $boqItem->planned_quantity,
                    'previous_certified_quantity' => (float) $boqItem->certified_quantity,
                    'measured_quantity' => 1,
                    'certified_quantity' => 1,
                    'measured_amount' => 1250,
                    'certified_amount' => 1250,
                ],
            ],
            'workflow_history' => [],
        ]);

        $construction->forceFill(['company_id' => null])->save();
        $finance->forceFill(['company_id' => null])->save();

        $this->actingAs($construction)
            ->getJson(route('construction.boq-items.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($construction)
            ->getJson(route('construction.contractor-measurements.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($construction)
            ->getJson(route('construction.boq-items.index', [
                'project_id' => $project->id,
                'vendor_id' => $contractor->id,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id', 'vendor_id']);

        $this->actingAs($construction)
            ->getJson(route('construction.contractor-measurements.index', [
                'project_id' => $project->id,
                'vendor_id' => $contractor->id,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id', 'vendor_id']);

        $this->actingAs($construction)
            ->postJson(route('construction.boq-items.store'), [
                'project_id' => $project->id,
                'construction_milestone_id' => $milestone->id,
                'vendor_id' => $contractor->id,
                'boq_code' => 'BOQ-SCOPE-001',
                'trade' => 'Scope',
                'description' => 'Scope guard BOQ',
                'unit' => 'sqm',
                'planned_quantity' => 10,
                'rate' => 100,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id']);

        $this->actingAs($construction)
            ->postJson(route('construction.contractor-measurements.store'), [
                'project_id' => $project->id,
                'vendor_id' => $contractor->id,
                'measurement_date' => now()->toDateString(),
                'lines' => [
                    [
                        'boq_item_id' => $boqItem->id,
                        'measured_quantity' => 1,
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id']);

        $this->actingAs($finance)
            ->patchJson(route('construction.contractor-measurements.approve', $measurement), [
                'note' => 'Scope guard should reject approval.',
            ])
            ->assertForbidden();

        $this->actingAs($finance)
            ->patchJson(route('construction.contractor-measurements.reject', $measurement), [
                'reason' => 'Scope guard should reject rejection.',
            ])
            ->assertForbidden();
    }

    public function test_construction_boq_and_measurement_indexes_validate_filters_and_scope(): void
    {
        $this->seed();

        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $otherCompany = Company::where('code', 'B360P')->firstOrFail();
        $otherProject = Project::where('company_id', $otherCompany->id)->firstOrFail();
        $otherVendor = Vendor::create([
            'company_id' => $otherCompany->id,
            'vendor_code' => 'CON-OTHER-001',
            'name' => 'Other Company Contractor',
            'vendor_type' => 'contractor',
            'status' => 'active',
        ]);

        $this->actingAs($construction)
            ->getJson(route('construction.boq-items.index', ['status' => 'invalid']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->actingAs($construction)
            ->getJson(route('construction.boq-items.index', ['unexpected_filter' => 'ignored-before-hardening']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['unexpected_filter'])
            ->assertJsonPath('errors.unexpected_filter.0', 'The selected filter is not available for this endpoint.');

        $this->actingAs($construction)
            ->getJson(route('construction.boq-items.index', ['project_id' => $otherProject->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id']);

        $this->actingAs($construction)
            ->getJson(route('construction.boq-items.index', ['vendor_id' => $otherVendor->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['vendor_id']);

        $this->actingAs($construction)
            ->getJson(route('construction.boq-items.index', ['per_page' => 500]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);

        $this->actingAs($construction)
            ->getJson(route('construction.contractor-measurements.index', ['status' => 'invalid']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->actingAs($construction)
            ->getJson(route('construction.contractor-measurements.index', ['unexpected_filter' => 'ignored-before-hardening']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['unexpected_filter'])
            ->assertJsonPath('errors.unexpected_filter.0', 'The selected filter is not available for this endpoint.');

        $this->actingAs($construction)
            ->getJson(route('construction.contractor-measurements.index', ['project_id' => $otherProject->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id']);

        $this->actingAs($construction)
            ->getJson(route('construction.contractor-measurements.index', ['vendor_id' => $otherVendor->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['vendor_id']);

        $this->actingAs($construction)
            ->getJson(route('construction.contractor-measurements.index', [
                'date_from' => now()->toDateString(),
                'date_to' => now()->subDay()->toDateString(),
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date_to']);

        $this->actingAs($construction)
            ->getJson(route('construction.contractor-measurements.index', [
                'status' => 'approved',
                'date_from' => now()->subWeek()->toDateString(),
                'date_to' => now()->toDateString(),
                'per_page' => 10,
            ]))
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_measurement_submission_and_approval_update_boq_certified_quantities(): void
    {
        $this->seed();

        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();
        $contractor = Vendor::where('vendor_code', 'CON-1001')->firstOrFail();
        $boqItem = BoqItem::where('boq_code', 'BOQ-SKY-RCC-001')->firstOrFail();

        $measurementNumber = $this->actingAs($construction)
            ->postJson(route('construction.contractor-measurements.store'), [
                'project_id' => $project->id,
                'vendor_id' => $contractor->id,
                'measurement_date' => now()->toDateString(),
                'bill_reference' => 'PC/RA/002',
                'lines' => [
                    [
                        'boq_item_id' => $boqItem->id,
                        'measured_quantity' => 150,
                        'certified_quantity' => 145,
                        'remarks' => 'Deducted 5 sqm pending QA check.',
                    ],
                ],
                'remarks' => 'Second RA bill for RCC work.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.measured_total', 187500)
            ->assertJsonPath('data.certified_total', 181250)
            ->json('data.measurement_number');

        $measurement = ContractorMeasurement::where('measurement_number', $measurementNumber)->firstOrFail();

        $this->actingAs($construction)
            ->patchJson(route('construction.contractor-measurements.approve', $measurement))
            ->assertForbidden();

        $this->actingAs($finance)
            ->patchJson(route('construction.contractor-measurements.approve', $measurement), [
                'note' => str_repeat('x', 1001),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('note');

        $this->actingAs($finance)
            ->patchJson(route('construction.contractor-measurements.approve', $measurement), [
                'note' => 'Certified after checking joint measurement sheet.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.approved_by.email', 'suresh.iyer@builder360.test');

        $measurement->refresh();

        $this->assertSame('Certified after checking joint measurement sheet.', collect($measurement->workflow_history)->last()['note']);

        $boqItem->refresh();
        $this->assertSame(350.0, (float) $boqItem->measured_quantity);
        $this->assertSame(345.0, (float) $boqItem->certified_quantity);
        $this->assertSame(431250.0, (float) $boqItem->certified_amount);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'construction.contractor_measurement.approved',
            'auditable_id' => $measurement->id,
            'user_id' => $finance->id,
            'metadata->note' => 'Certified after checking joint measurement sheet.',
        ]);
    }

    public function test_measurement_approval_blocks_over_certification_against_boq_quantity(): void
    {
        $this->seed();

        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();
        $contractor = Vendor::where('vendor_code', 'CON-1001')->firstOrFail();
        $boqItem = BoqItem::where('boq_code', 'BOQ-SKY-RCC-001')->firstOrFail();

        $measurementNumber = $this->actingAs($construction)
            ->postJson(route('construction.contractor-measurements.store'), [
                'project_id' => $project->id,
                'vendor_id' => $contractor->id,
                'measurement_date' => now()->toDateString(),
                'lines' => [
                    [
                        'boq_item_id' => $boqItem->id,
                        'measured_quantity' => 900,
                        'certified_quantity' => 900,
                    ],
                ],
            ])
            ->assertCreated()
            ->json('data.measurement_number');

        $measurement = ContractorMeasurement::where('measurement_number', $measurementNumber)->firstOrFail();

        $this->actingAs($finance)
            ->patchJson(route('construction.contractor-measurements.approve', $measurement))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.certified_quantity']);

        $this->assertDatabaseHas('boq_items', [
            'id' => $boqItem->id,
            'certified_quantity' => 200,
            'certified_amount' => 250000,
        ]);
    }

    public function test_measurement_rejection_does_not_update_boq_and_partner_is_denied(): void
    {
        $this->seed();

        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();
        $contractor = Vendor::where('vendor_code', 'CON-1001')->firstOrFail();
        $boqItem = BoqItem::where('boq_code', 'BOQ-SKY-RCC-001')->firstOrFail();
        $initialCertifiedQuantity = (float) $boqItem->certified_quantity;

        $measurementNumber = $this->actingAs($construction)
            ->postJson(route('construction.contractor-measurements.store'), [
                'project_id' => $project->id,
                'vendor_id' => $contractor->id,
                'measurement_date' => now()->toDateString(),
                'lines' => [
                    [
                        'boq_item_id' => $boqItem->id,
                        'measured_quantity' => 50,
                        'certified_quantity' => 50,
                    ],
                ],
            ])
            ->assertCreated()
            ->json('data.measurement_number');

        $measurement = ContractorMeasurement::where('measurement_number', $measurementNumber)->firstOrFail();

        $this->actingAs($finance)
            ->patchJson(route('construction.contractor-measurements.reject', $measurement), [
                'reason' => 'Measurement backup sheet is missing.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $this->assertSame($initialCertifiedQuantity, (float) $boqItem->fresh()->certified_quantity);

        $this->actingAs($partner)
            ->getJson(route('construction.boq-items.index'))
            ->assertForbidden();

        $this->actingAs($partner)
            ->getJson(route('construction.contractor-measurements.index'))
            ->assertForbidden();

        $this->actingAs($partner)
            ->getJson(route('construction.contractor-bills.index'))
            ->assertForbidden();
    }

    public function test_contractor_bill_calculates_retention_deductions_payable_and_payment_status(): void
    {
        $this->seed();

        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();
        $contractor = Vendor::where('vendor_code', 'CON-1001')->firstOrFail();
        $boqItem = BoqItem::where('boq_code', 'BOQ-SKY-RCC-001')->firstOrFail();

        $measurementNumber = $this->actingAs($construction)
            ->postJson(route('construction.contractor-measurements.store'), [
                'project_id' => $project->id,
                'vendor_id' => $contractor->id,
                'measurement_date' => now()->toDateString(),
                'bill_reference' => 'RA-BILL-003',
                'lines' => [
                    [
                        'boq_item_id' => $boqItem->id,
                        'measured_quantity' => 100,
                        'certified_quantity' => 100,
                    ],
                ],
            ])
            ->assertCreated()
            ->json('data.measurement_number');

        $measurement = ContractorMeasurement::where('measurement_number', $measurementNumber)->firstOrFail();

        $this->actingAs($finance)
            ->patchJson(route('construction.contractor-measurements.approve', $measurement), [
                'note' => 'Approved for billing.',
            ])
            ->assertOk();

        $measurement->refresh();

        $billNumber = $this->actingAs($construction)
            ->postJson(route('construction.contractor-bills.store'), [
                'contractor_measurement_id' => $measurement->id,
                'bill_date' => now()->toDateString(),
                'retention_percent' => 5,
                'tax_amount' => 18000,
                'deductions' => [
                    [
                        'code' => 'QA_DEDUCTION',
                        'description' => 'Quality rectification hold',
                        'amount' => 1000,
                    ],
                ],
                'remarks' => 'RA bill after certified measurement.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.gross_amount', 125000)
            ->assertJsonPath('data.retention_amount', 6250)
            ->assertJsonPath('data.deduction_amount', 1000)
            ->assertJsonPath('data.tax_amount', 18000)
            ->assertJsonPath('data.payable_amount', 135750)
            ->assertJsonPath('data.balance_amount', 135750)
            ->json('data.bill_number');

        $bill = ContractorBill::where('bill_number', $billNumber)->firstOrFail();

        $this->actingAs($construction)
            ->patchJson(route('construction.contractor-bills.approve', $bill), [
                'note' => 'Self approval must be blocked.',
            ])
            ->assertForbidden();

        $this->actingAs($finance)
            ->patchJson(route('construction.contractor-bills.approve', $bill), [
                'note' => 'Approved after bill verification.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.approved_by.email', 'suresh.iyer@builder360.test');

        $bill->refresh();

        $this->actingAs($finance)
            ->patchJson(route('construction.contractor-bills.mark-paid', $bill), [
                'paid_amount' => 50000,
                'paid_on' => now()->toDateString(),
                'payment_reference' => 'NEFT-CON-001',
                'note' => 'First part payment.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'partially_paid')
            ->assertJsonPath('data.paid_amount', 50000)
            ->assertJsonPath('data.balance_amount', 85750);

        $bill->refresh();

        $this->actingAs($finance)
            ->patchJson(route('construction.contractor-bills.mark-paid', $bill), [
                'paid_amount' => 90000,
                'paid_on' => now()->toDateString(),
                'payment_reference' => 'NEFT-CON-OVER',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['paid_amount']);

        $this->actingAs($finance)
            ->patchJson(route('construction.contractor-bills.mark-paid', $bill->fresh()), [
                'paid_amount' => 85750,
                'paid_on' => now()->toDateString(),
                'payment_reference' => 'NEFT-CON-002',
                'note' => 'Final payment.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.paid_amount', 135750)
            ->assertJsonPath('data.balance_amount', 0);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'construction.contractor_bill.submitted',
            'auditable_id' => $bill->id,
            'metadata->payable_amount' => '135750.00',
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'construction.contractor_bill.payment_recorded',
            'auditable_id' => $bill->id,
            'metadata->payment_reference' => 'NEFT-CON-002',
        ]);
    }

    public function test_contractor_bill_validates_configured_limits_duplicates_and_filters(): void
    {
        $this->seed();

        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();
        $contractor = Vendor::where('vendor_code', 'CON-1001')->firstOrFail();
        $boqItem = BoqItem::where('boq_code', 'BOQ-SKY-RCC-001')->firstOrFail();

        $measurementNumber = $this->actingAs($construction)
            ->postJson(route('construction.contractor-measurements.store'), [
                'project_id' => $project->id,
                'vendor_id' => $contractor->id,
                'measurement_date' => now()->toDateString(),
                'lines' => [
                    [
                        'boq_item_id' => $boqItem->id,
                        'measured_quantity' => 80,
                        'certified_quantity' => 80,
                    ],
                ],
            ])
            ->assertCreated()
            ->json('data.measurement_number');

        $measurement = ContractorMeasurement::where('measurement_number', $measurementNumber)->firstOrFail();

        $this->actingAs($finance)
            ->patchJson(route('construction.contractor-measurements.approve', $measurement))
            ->assertOk();

        $measurement->refresh();

        $this->actingAs($construction)
            ->postJson(route('construction.contractor-bills.store'), [
                'contractor_measurement_id' => $measurement->id,
                'bill_date' => now()->toDateString(),
                'retention_percent' => 11,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['retention_percent']);

        $this->actingAs($construction)
            ->postJson(route('construction.contractor-bills.store'), [
                'contractor_measurement_id' => $measurement->id,
                'bill_date' => now()->toDateString(),
                'deductions' => [
                    [
                        'code' => 'EXCESS_DEDUCTION',
                        'description' => 'Beyond configured deduction threshold',
                        'amount' => 40000,
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['deductions']);

        $this->actingAs($construction)
            ->postJson(route('construction.contractor-bills.store'), [
                'contractor_measurement_id' => $measurement->id,
                'bill_date' => now()->toDateString(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.retention_percent', 5)
            ->assertJsonPath('data.payable_amount', 95000);

        $this->actingAs($construction)
            ->postJson(route('construction.contractor-bills.store'), [
                'contractor_measurement_id' => $measurement->id,
                'bill_date' => now()->toDateString(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['contractor_measurement_id']);

        $this->actingAs($construction)
            ->getJson(route('construction.contractor-bills.index', ['status' => 'unknown']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->actingAs($construction)
            ->getJson(route('construction.contractor-bills.index', ['unexpected_filter' => 'blocked']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['unexpected_filter']);

        $this->actingAs($construction)
            ->getJson(route('construction.contractor-bills.index', [
                'project_id' => $project->id,
                'vendor_id' => $contractor->id,
                'status' => 'submitted',
                'per_page' => 10,
            ]))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.payable_amount', 95000);
    }
}
