<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\Lead;
use App\Models\ProjectUnit;
use App\Models\UnitPriceVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnitPricingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_can_list_seeded_unit_price_versions_and_preview_quote(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $unit = ProjectUnit::where('unit_code', 'SKY-A-1205')->firstOrFail();

        $this->actingAs($sales)
            ->getJson(route('inventory.unit-price-versions.index', [
                'project_unit_id' => $unit->id,
                'status' => 'active',
            ]))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.price_code', 'UPV-SKY-A-1205-V1')
            ->assertJsonPath('data.0.unit.unit_code', 'SKY-A-1205');

        $this->actingAs($sales)
            ->postJson(route('sales.booking-quotes.store'), [
                'project_unit_id' => $unit->id,
                'discount_amount' => 100000,
            ])
            ->assertOk()
            ->assertJsonPath('data.source', 'unit_price_version')
            ->assertJsonPath('data.price_code', 'UPV-SKY-A-1205-V1')
            ->assertJsonPath('data.requires_discount_approval', false);
    }

    public function test_sales_can_open_native_blade_unit_inventory_workspace(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();

        $this->actingAs($sales)
            ->get(route('inventory.units.index', ['status' => 'available']))
            ->assertOk()
            ->assertSee('Unit Inventory')
            ->assertSee('Workspace')
            ->assertSee('Availability filters')
            ->assertSee('Unit availability register')
            ->assertSee('name="project_id"', false)
            ->assertSee('name="status"', false)
            ->assertSee('SKY-A-1205')
            ->assertDontSee('window.Builder360Server', false)
            ->assertDontSee('id="root"', false);
    }

    public function test_sales_can_open_native_blade_unit_pricing_workspace(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();

        $this->actingAs($sales)
            ->get(route('inventory.unit-price-versions.index'))
            ->assertOk()
            ->assertSee('Unit Pricing')
            ->assertSee('Workspace')
            ->assertSee('Draft price version')
            ->assertSee('Price version filters')
            ->assertSee('Unit price version register')
            ->assertSee('name="project_unit_id"', false)
            ->assertSee('name="base_rate"', false)
            ->assertSee('UPV-SKY-A-1205-V1')
            ->assertDontSee('window.Builder360Server', false)
            ->assertDontSee('id="root"', false);
    }

    public function test_native_blade_price_version_form_drafts_and_finance_approves(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $unit = ProjectUnit::where('unit_code', 'SKY-A-1205')->firstOrFail();

        $this->actingAs($sales)
            ->post(route('inventory.unit-price-versions.store'), [
                'project_unit_id' => $unit->id,
                'effective_from' => now()->addDays(2)->toDateString(),
                'base_rate' => 9750,
                'floor_premium' => 260000,
                'location_premium' => 140000,
                'parking_charges' => 490000,
                'other_charges' => 310000,
                'tax_rate_percent' => 5,
                'charge_breakup' => [
                    'clubhouse' => 80000,
                    'legal' => 45000,
                ],
            ])
            ->assertRedirect(route('inventory.unit-price-versions.index'))
            ->assertSessionHas('status');

        $version = UnitPriceVersion::where('project_unit_id', $unit->id)
            ->where('base_rate', 9750)
            ->where('status', 'draft')
            ->latest('id')
            ->firstOrFail();

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'inventory.unit_price_version.created',
            'auditable_type' => UnitPriceVersion::class,
            'auditable_id' => $version->id,
            'user_id' => $sales->id,
        ]);

        $this->actingAs($finance)
            ->patch(route('inventory.unit-price-versions.approve', $version), [
                'note' => 'Approved from native Blade unit pricing register.',
            ])
            ->assertRedirect(route('inventory.unit-price-versions.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('unit_price_versions', [
            'id' => $version->id,
            'status' => 'active',
            'approved_by_user_id' => $finance->id,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'inventory.unit_price_version.approved',
            'auditable_type' => UnitPriceVersion::class,
            'auditable_id' => $version->id,
            'user_id' => $finance->id,
        ]);
    }

    public function test_authorized_users_can_export_scoped_unit_availability_csv_with_audit(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();

        $response = $this->actingAs($sales)
            ->get(route('inventory.units.export', [
                'status' => 'available',
                'format' => 'csv',
            ]))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $csv = $response->getContent();

        $this->assertStringContainsString('unit_code,status,company_code', $csv);
        $this->assertStringContainsString('SKY-A-1205', $csv);
        $this->assertStringNotContainsString('MTO-B-1803', $csv);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'inventory.unit_availability.exported',
            'auditable_type' => 'system',
        ]);

        $audit = AuditEvent::where('event_type', 'inventory.unit_availability.exported')->latest('id')->firstOrFail();
        $this->assertSame($sales->id, $audit->user_id);
        $this->assertSame('csv', $audit->metadata['format']);
        $this->assertSame('available', $audit->metadata['filters']['status']);
        $this->assertGreaterThanOrEqual(1, $audit->metadata['row_count']);

        $this->actingAs($sales)
            ->getJson(route('inventory.units.index', ['status' => 'on_hold']))
            ->assertOk();

        $this->actingAs($sales)
            ->getJson(route('inventory.units.export', ['format' => 'xlsx']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['format']);

        $this->actingAs($partner)
            ->get(route('inventory.units.export', ['format' => 'csv']))
            ->assertForbidden();
    }

    public function test_price_version_creation_approval_and_audit_workflow(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $unit = ProjectUnit::where('unit_code', 'SKY-A-1205')->firstOrFail();

        $versionId = $this->actingAs($sales)
            ->postJson(route('inventory.unit-price-versions.store'), [
                'project_unit_id' => $unit->id,
                'effective_from' => now()->addDay()->toDateString(),
                'base_rate' => 9500,
                'floor_premium' => 250000,
                'location_premium' => 125000,
                'parking_charges' => 475000,
                'other_charges' => 325000,
                'tax_rate_percent' => 5,
                'charge_breakup' => [
                    'clubhouse' => 75000,
                    'legal' => 50000,
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.version_number', 2)
            ->json('data.id');

        $version = UnitPriceVersion::findOrFail($versionId);

        $this->actingAs($sales)
            ->patchJson(route('inventory.unit-price-versions.approve', $version), [
                'note' => 'Creator cannot approve own price version.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['unit_price_version']);

        $this->actingAs($finance)
            ->patchJson(route('inventory.unit-price-versions.approve', $version), [
                'note' => 'Finance approved revised launch pricing.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.approved_by.email', 'suresh.iyer@builder360.test');

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'inventory.unit_price_version.created',
            'auditable_type' => UnitPriceVersion::class,
            'auditable_id' => $version->id,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'inventory.unit_price_version.approved',
            'auditable_type' => UnitPriceVersion::class,
            'auditable_id' => $version->id,
        ]);

        $this->assertDatabaseHas('unit_price_versions', [
            'project_unit_id' => $unit->id,
            'price_code' => 'UPV-SKY-A-1205-V1',
            'status' => 'active',
        ]);

        $this->assertSame(
            now()->toDateString(),
            UnitPriceVersion::where('price_code', 'UPV-SKY-A-1205-V1')->firstOrFail()->effective_to->toDateString(),
        );
    }

    public function test_booking_uses_approved_price_version_and_preserves_pricing_snapshot(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $unit = ProjectUnit::where('unit_code', 'SKY-A-1205')->firstOrFail();
        $lead = Lead::where('lead_code', 'LD-1003')->firstOrFail();

        $lead->forceFill([
            'company_id' => $unit->company_id,
            'project_id' => $unit->project_id,
        ])->save();

        $bookingCode = $this->actingAs($sales)
            ->postJson(route('sales.bookings.store'), [
                'project_unit_id' => $unit->id,
                'customer_id' => $lead->customer_id,
                'lead_id' => $lead->id,
                'partner_id' => $lead->partner_id,
                'booking_amount' => 500000,
                'discount_amount' => 100000,
            ])
            ->assertCreated()
            ->assertJsonPath('data.unit_price_version.price_code', 'UPV-SKY-A-1205-V1')
            ->json('data.booking_code');

        $booking = Booking::where('booking_code', $bookingCode)->firstOrFail();
        $originalVersionId = $booking->unit_price_version_id;
        $originalSnapshotCode = $booking->commercials['pricing_snapshot']['price_code'];

        $newVersionId = $this->actingAs($sales)
            ->postJson(route('inventory.unit-price-versions.store'), [
                'project_unit_id' => $unit->id,
                'effective_from' => now()->toDateString(),
                'base_rate' => 9900,
                'floor_premium' => 275000,
                'location_premium' => 150000,
                'parking_charges' => 500000,
                'other_charges' => 350000,
                'tax_rate_percent' => 5,
            ])
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($finance)
            ->patchJson(route('inventory.unit-price-versions.approve', UnitPriceVersion::findOrFail($newVersionId)), [
                'note' => 'Approved after booking to verify historical snapshot preservation.',
            ])
            ->assertOk();

        $booking->refresh();

        $this->assertSame($originalVersionId, $booking->unit_price_version_id);
        $this->assertSame($originalSnapshotCode, $booking->commercials['pricing_snapshot']['price_code']);
        $this->assertNotSame(UnitPriceVersion::findOrFail($newVersionId)->price_code, $booking->commercials['pricing_snapshot']['price_code']);
    }

    public function test_discount_authority_is_enforced_from_pricing_rules(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $unit = ProjectUnit::where('unit_code', 'SKY-A-1205')->firstOrFail();
        $lead = Lead::where('lead_code', 'LD-1003')->firstOrFail();

        $lead->forceFill([
            'company_id' => $unit->company_id,
            'project_id' => $unit->project_id,
        ])->save();

        $this->actingAs($sales)
            ->postJson(route('sales.bookings.store'), [
                'project_unit_id' => $unit->id,
                'customer_id' => $lead->customer_id,
                'lead_id' => $lead->id,
                'partner_id' => $lead->partner_id,
                'booking_amount' => 500000,
                'discount_amount' => 1000000,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['discount_amount']);

        $this->actingAs($director)
            ->postJson(route('sales.booking-quotes.store'), [
                'project_unit_id' => $unit->id,
                'discount_amount' => 1000000,
            ])
            ->assertOk()
            ->assertJsonPath('data.requires_discount_approval', true);
    }

    public function test_partner_and_out_of_scope_users_cannot_access_internal_pricing(): void
    {
        $this->seed();

        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();
        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $otherCompanyUnit = ProjectUnit::where('unit_code', 'MTO-B-1803')->firstOrFail();

        $this->actingAs($partner)
            ->getJson(route('inventory.unit-price-versions.index'))
            ->assertForbidden();

        $this->actingAs($partner)
            ->postJson(route('sales.booking-quotes.store'), [
                'project_unit_id' => ProjectUnit::where('unit_code', 'SKY-A-1205')->firstOrFail()->id,
            ])
            ->assertForbidden();

        $this->actingAs($sales)
            ->postJson(route('sales.booking-quotes.store'), [
                'project_unit_id' => $otherCompanyUnit->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_unit_id']);
    }
}
