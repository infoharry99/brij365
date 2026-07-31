<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Project;
use App\Models\ProjectUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesBookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_user_can_list_company_scoped_units_and_bookings(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();

        $this->actingAs($sales)
            ->getJson(route('inventory.units.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['unit_code', 'unit_type', 'status', 'project', 'is_bookable'],
                ],
            ]);

        $this->actingAs($sales)
            ->getJson(route('sales.bookings.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.booking_code', 'BK-1001');
    }

    public function test_sales_user_can_open_native_blade_booking_workspace(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();

        $this->actingAs($sales)
            ->get(route('sales.bookings.index'))
            ->assertOk()
            ->assertSee('Sales Booking')
            ->assertSee('Workspace')
            ->assertSee('Booking quote preview')
            ->assertSee('Create booking')
            ->assertSee('Booking filters')
            ->assertSee('Booking register')
            ->assertSee('name="project_unit_id"', false)
            ->assertSee('name="customer_id"', false)
            ->assertSee('BK-1001')
            ->assertDontSee('window.Builder360Server', false)
            ->assertDontSee('id="root"', false);
    }

    public function test_native_blade_booking_quote_form_redirects_with_quote_preview(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $unit = ProjectUnit::where('unit_code', 'SKY-A-1205')->firstOrFail();

        $this->actingAs($sales)
            ->post(route('sales.booking-quotes.store'), [
                'project_unit_id' => $unit->id,
                'quoted_on' => now()->toDateString(),
                'discount_amount' => 0,
            ])
            ->assertRedirect(route('sales.bookings.index'))
            ->assertSessionHas('quote');

        $this->actingAs($sales)
            ->get(route('sales.bookings.index'))
            ->assertOk()
            ->assertSee('Quote source')
            ->assertSee('Net payable')
            ->assertSee('SKY-A-1205');
    }

    public function test_native_blade_booking_form_creates_booking_and_redirects(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $unit = ProjectUnit::where('unit_code', 'SKY-A-1205')->firstOrFail();
        $lead = Lead::where('lead_code', 'LD-1003')->firstOrFail();

        $lead->forceFill([
            'company_id' => $unit->company_id,
            'project_id' => $unit->project_id,
        ])->save();

        $this->actingAs($sales)
            ->post(route('sales.bookings.store'), [
                'project_unit_id' => $unit->id,
                'customer_id' => $lead->customer_id,
                'lead_id' => $lead->id,
                'partner_id' => $lead->partner_id,
                'booking_amount' => 500000,
                'discount_amount' => 100000,
                'booked_on' => now()->toDateString(),
                'payment_schedule' => [
                    ['sequence' => 1, 'milestone' => 'Booking Amount', 'percentage' => 10],
                    ['sequence' => 2, 'milestone' => 'Agreement', 'percentage' => 20],
                    ['sequence' => 3, 'milestone' => 'Possession', 'percentage' => 70],
                ],
            ])
            ->assertRedirect(route('sales.bookings.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('bookings', [
            'project_unit_id' => $unit->id,
            'customer_id' => $lead->customer_id,
            'status' => 'confirmed',
        ]);

        $this->assertDatabaseHas('project_units', [
            'id' => $unit->id,
            'status' => 'booked',
        ]);

        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'stage' => 'Booked',
            'status' => 'won',
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'sales.booking.created',
            'action' => 'Created sales booking',
            'user_id' => $sales->id,
        ]);
    }

    public function test_non_global_sales_user_without_company_assignment_fails_closed_for_units_and_bookings(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $unit = ProjectUnit::where('unit_code', 'SKY-A-1205')->firstOrFail();
        $booking = Booking::where('booking_code', 'BK-1001')->firstOrFail();
        $lead = Lead::where('lead_code', 'LD-1003')->firstOrFail();

        $sales->forceFill(['company_id' => null])->save();

        $this->actingAs($sales)
            ->getJson(route('inventory.units.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($sales)
            ->getJson(route('sales.bookings.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($sales)
            ->getJson(route('inventory.units.index', ['project_id' => $unit->project_id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id']);

        $this->actingAs($sales)
            ->getJson(route('sales.bookings.index', ['project_id' => $booking->project_id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id']);

        $this->actingAs($sales)
            ->getJson(route('sales.bookings.index', ['customer_id' => $booking->customer_id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id']);

        $this->actingAs($sales)
            ->postJson(route('sales.bookings.store'), [
                'project_unit_id' => $unit->id,
                'customer_id' => $lead->customer_id,
                'lead_id' => $lead->id,
                'partner_id' => $lead->partner_id,
                'booking_amount' => 500000,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_unit_id']);
    }

    public function test_sales_user_can_create_booking_for_available_unit(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $unit = ProjectUnit::where('unit_code', 'SKY-A-1205')->firstOrFail();
        $lead = Lead::where('lead_code', 'LD-1003')->firstOrFail();

        $lead->forceFill([
            'company_id' => $unit->company_id,
            'project_id' => $unit->project_id,
        ])->save();

        $response = $this->actingAs($sales)->postJson(route('sales.bookings.store'), [
            'project_unit_id' => $unit->id,
            'customer_id' => $lead->customer_id,
            'lead_id' => $lead->id,
            'partner_id' => $lead->partner_id,
            'booking_amount' => 500000,
            'discount_amount' => 100000,
            'payment_schedule' => [
                ['sequence' => 1, 'milestone' => 'Booking Amount', 'percentage' => 10],
                ['sequence' => 2, 'milestone' => 'Agreement', 'percentage' => 20],
                ['sequence' => 3, 'milestone' => 'Possession', 'percentage' => 70],
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.unit.unit_code', 'SKY-A-1205')
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonCount(3, 'data.payment_schedules');

        $this->assertDatabaseHas('bookings', [
            'booking_code' => $response->json('data.booking_code'),
            'project_unit_id' => $unit->id,
            'customer_id' => $lead->customer_id,
            'status' => 'confirmed',
        ]);

        $this->assertDatabaseHas('project_units', [
            'id' => $unit->id,
            'status' => 'booked',
        ]);

        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'stage' => 'Booked',
            'status' => 'won',
        ]);

        $this->assertDatabaseHas('booking_payment_schedules', [
            'milestone' => 'Agreement',
            'sequence' => 2,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'sales.booking.created',
            'action' => 'Created sales booking',
            'user_id' => $sales->id,
        ]);
    }

    public function test_booking_rejects_already_booked_unit(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $existingBooking = Booking::where('booking_code', 'BK-1001')->firstOrFail();

        $this->actingAs($sales)->postJson(route('sales.bookings.store'), [
            'project_unit_id' => $existingBooking->project_unit_id,
            'customer_id' => $existingBooking->customer_id,
            'lead_id' => $existingBooking->lead_id,
            'partner_id' => $existingBooking->partner_id,
            'booking_amount' => 500000,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('project_unit_id');
    }

    public function test_booking_rejects_commercial_totals_above_net_receivable(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $unit = ProjectUnit::where('unit_code', 'SKY-A-1205')->firstOrFail();
        $lead = Lead::where('lead_code', 'LD-1003')->firstOrFail();

        $lead->forceFill([
            'company_id' => $unit->company_id,
            'project_id' => $unit->project_id,
        ])->save();

        $this->actingAs($sales)->postJson(route('sales.bookings.store'), [
            'project_unit_id' => $unit->id,
            'customer_id' => $lead->customer_id,
            'lead_id' => $lead->id,
            'partner_id' => $lead->partner_id,
            'booking_amount' => ((float) $unit->total_price) + 100000,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('booking_amount');

        $this->assertDatabaseMissing('bookings', [
            'project_unit_id' => $unit->id,
            'customer_id' => $lead->customer_id,
        ]);

        $this->assertDatabaseHas('project_units', [
            'id' => $unit->id,
            'status' => 'available',
        ]);
    }

    public function test_booking_rejects_payment_schedule_above_net_receivable(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $unit = ProjectUnit::where('unit_code', 'SKY-A-1205')->firstOrFail();
        $lead = Lead::where('lead_code', 'LD-1003')->firstOrFail();

        $lead->forceFill([
            'company_id' => $unit->company_id,
            'project_id' => $unit->project_id,
        ])->save();

        $this->actingAs($sales)->postJson(route('sales.bookings.store'), [
            'project_unit_id' => $unit->id,
            'customer_id' => $lead->customer_id,
            'lead_id' => $lead->id,
            'partner_id' => $lead->partner_id,
            'booking_amount' => 500000,
            'payment_schedule' => [
                ['sequence' => 1, 'milestone' => 'Booking Amount', 'percentage' => 60],
                ['sequence' => 2, 'milestone' => 'Agreement', 'percentage' => 50],
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('payment_schedule');

        $this->actingAs($sales)->postJson(route('sales.bookings.store'), [
            'project_unit_id' => $unit->id,
            'customer_id' => $lead->customer_id,
            'lead_id' => $lead->id,
            'partner_id' => $lead->partner_id,
            'booking_amount' => 500000,
            'payment_schedule' => [
                ['sequence' => 1, 'milestone' => 'Booking Amount', 'amount' => ((float) $unit->total_price) + 100000],
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('payment_schedule');

        $this->assertDatabaseMissing('bookings', [
            'project_unit_id' => $unit->id,
            'customer_id' => $lead->customer_id,
        ]);

        $this->assertDatabaseHas('project_units', [
            'id' => $unit->id,
            'status' => 'available',
        ]);
    }

    public function test_partner_cannot_create_booking_or_access_internal_inventory(): void
    {
        $this->seed();

        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();
        $unit = ProjectUnit::where('unit_code', 'SKY-A-1205')->firstOrFail();

        $this->actingAs($partner)
            ->getJson(route('inventory.units.index'))
            ->assertForbidden();

        $this->actingAs($partner)->postJson(route('sales.bookings.store'), [
            'project_unit_id' => $unit->id,
            'customer_id' => 1,
            'booking_amount' => 500000,
        ])->assertForbidden();
    }

    public function test_booking_index_validates_filters_and_project_scope(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $otherCompany = Company::where('code', 'B360P')->firstOrFail();
        $otherProject = Project::where('company_id', $otherCompany->id)->firstOrFail();
        $unrelatedCustomer = Customer::create([
            'code' => 'CUST-SALES-SCOPE',
            'name' => 'Sales Scope Test Customer',
            'email' => 'sales.scope.customer@example.test',
            'phone' => '+91 98000 00002',
            'source' => 'Scope Test',
            'status' => 'active',
        ]);

        $this->actingAs($sales)
            ->getJson(route('sales.bookings.index', ['per_page' => 500]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);

        $this->actingAs($sales)
            ->getJson(route('sales.bookings.index', ['page' => 1]))
            ->assertOk()
            ->assertJsonPath('meta.current_page', 1);

        $this->actingAs($sales)
            ->getJson(route('sales.bookings.index', ['stage' => 'Negotiation']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['stage'])
            ->assertJsonPath('errors.stage.0', 'The selected filter is not available for this endpoint.');

        $this->actingAs($sales)
            ->getJson(route('sales.bookings.index', ['status' => 'invalid']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->actingAs($sales)
            ->getJson(route('sales.bookings.index', ['project_id' => $otherProject->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id']);

        $this->actingAs($sales)
            ->getJson(route('sales.bookings.index', ['customer_id' => $unrelatedCustomer->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id']);
    }

    public function test_inventory_unit_index_validates_filters_and_project_scope(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $otherCompany = Company::where('code', 'B360P')->firstOrFail();
        $otherProject = Project::where('company_id', $otherCompany->id)->firstOrFail();

        $this->actingAs($sales)
            ->getJson(route('inventory.units.index', ['per_page' => 500]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);

        $this->actingAs($sales)
            ->getJson(route('inventory.units.index', ['page' => 1]))
            ->assertOk()
            ->assertJsonPath('meta.current_page', 1);

        $this->actingAs($sales)
            ->getJson(route('inventory.units.index', ['customer_id' => 1]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id'])
            ->assertJsonPath('errors.customer_id.0', 'The selected filter is not available for this endpoint.');

        $this->actingAs($sales)
            ->getJson(route('inventory.units.index', ['status' => 'invalid']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->actingAs($sales)
            ->getJson(route('inventory.units.index', ['project_id' => $otherProject->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id']);

        $this->actingAs($sales)
            ->getJson(route('inventory.units.index', [
                'status' => 'available',
                'unit_type' => '3BHK',
            ]))
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_partner_only_sees_own_bookings(): void
    {
        $this->seed();

        $channelPartner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();
        $broker = User::where('email', 'farhan.shaikh@partners.builder360.test')->firstOrFail();

        $this->actingAs($channelPartner)
            ->getJson(route('partner.bookings.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.booking_code', 'BK-1001');

        $this->actingAs($broker)
            ->getJson(route('partner.bookings.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_partner_booking_index_validates_filters_and_keeps_partner_scope(): void
    {
        $this->seed();

        $channelPartner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();
        $booking = Booking::where('booking_code', 'BK-1001')->firstOrFail();
        $otherProject = Project::where('company_id', Company::where('code', 'B360P')->value('id'))->firstOrFail();
        $unrelatedCustomer = Customer::create([
            'code' => 'CUST-PARTNER-SCOPE',
            'name' => 'Unrelated Partner Scope Customer',
            'email' => 'unrelated.partner.scope@example.test',
            'phone' => '+91 98000 00001',
            'source' => 'Scope Test',
            'status' => 'active',
        ]);

        $this->actingAs($channelPartner)
            ->getJson(route('partner.bookings.index', ['per_page' => 500]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);

        $this->actingAs($channelPartner)
            ->getJson(route('partner.bookings.index', ['page' => 1]))
            ->assertOk()
            ->assertJsonPath('meta.current_page', 1);

        $this->actingAs($channelPartner)
            ->getJson(route('partner.bookings.index', ['stage' => 'Negotiation']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['stage'])
            ->assertJsonPath('errors.stage.0', 'The selected filter is not available for this endpoint.');

        $this->actingAs($channelPartner)
            ->getJson(route('partner.bookings.index', ['status' => 'invalid']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->actingAs($channelPartner)
            ->getJson(route('partner.bookings.index', ['project_id' => $otherProject->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id']);

        $this->actingAs($channelPartner)
            ->getJson(route('partner.bookings.index', ['customer_id' => $unrelatedCustomer->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id']);

        $this->actingAs($channelPartner)
            ->getJson(route('partner.bookings.index', [
                'project_id' => $booking->project_id,
                'customer_id' => $booking->customer_id,
                'status' => 'confirmed',
            ]))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.booking_code', 'BK-1001');
    }

    public function test_booking_rejects_lead_customer_mismatch(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $unit = ProjectUnit::where('unit_code', 'SKY-A-1205')->firstOrFail();
        $lead = Lead::where('lead_code', 'LD-1002')->firstOrFail();

        $lead->forceFill([
            'company_id' => $unit->company_id,
            'project_id' => $unit->project_id,
        ])->save();

        $this->actingAs($sales)->postJson(route('sales.bookings.store'), [
            'project_unit_id' => $unit->id,
            'customer_id' => Lead::where('lead_code', 'LD-1003')->firstOrFail()->customer_id,
            'lead_id' => $lead->id,
            'partner_id' => $lead->partner_id,
            'booking_amount' => 500000,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('customer_id');

        $this->assertDatabaseCount('audit_events', AuditEvent::count());
    }
}
