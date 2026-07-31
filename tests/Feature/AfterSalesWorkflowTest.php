<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Company;
use App\Models\Customer;
use App\Models\MaintenanceWorkOrder;
use App\Models\Project;
use App\Models\ServiceTicket;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AfterSalesWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_internal_and_buyer_users_can_list_scoped_seeded_tickets(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $buyer = User::where('email', 'rohan.shah@example.test')->firstOrFail();

        $this->actingAs($sales)
            ->getJson(route('after-sales.tickets.index'))
            ->assertOk()
            ->assertJsonPath('data.0.ticket_number', 'AST-1001')
            ->assertJsonPath('data.0.status', 'in_progress')
            ->assertJsonPath('data.0.customer.email', 'rohan.shah@example.test');

        $this->actingAs($buyer)
            ->getJson(route('after-sales.tickets.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.ticket_number', 'AST-1001');

        $this->actingAs($sales)
            ->getJson(route('after-sales.work-orders.index'))
            ->assertOk()
            ->assertJsonPath('data.0.work_order_number', 'MWO-1001')
            ->assertJsonPath('data.0.status', 'scheduled');
    }

    public function test_authorized_user_can_use_native_blade_after_sales_ticket_workspace(): void
    {
        $this->seed();

        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $booking = Booking::where('booking_code', 'BK-1001')->firstOrFail();

        $this->actingAs($director)
            ->get(route('after-sales.tickets.index'))
            ->assertOk()
            ->assertSee('Workspace for complaint capture')
            ->assertSee('name="booking_id"', false)
            ->assertSee('AST-1001')
            ->assertDontSee('window.Builder360Server', false)
            ->assertDontSee('id="root"', false);

        $this->actingAs($director)
            ->post(route('after-sales.tickets.store'), [
                'booking_id' => $booking->id,
                'category' => 'documentation',
                'priority' => 'medium',
                'source' => 'phone',
                'subject' => 'Blade duplicate agreement copy request',
                'description' => 'Customer requested a duplicate agreement copy through the native Blade workspace.',
            ])
            ->assertRedirect(route('after-sales.tickets.index'))
            ->assertSessionHas('status');

        $ticket = ServiceTicket::where('subject', 'Blade duplicate agreement copy request')->firstOrFail();

        $this->assertSame('open', $ticket->status);
        $this->assertSame('phone', $ticket->source);

        $this->actingAs($director)
            ->patch(route('after-sales.tickets.assign', $ticket), [
                'assigned_to_user_id' => $construction->id,
                'note' => 'Assigned from Blade workspace.',
            ])
            ->assertRedirect(route('after-sales.tickets.index'))
            ->assertSessionHas('status');

        $ticket->refresh();

        $this->assertSame('assigned', $ticket->status);
        $this->assertSame($construction->id, $ticket->assigned_to_user_id);
        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'after_sales.ticket.assigned',
            'auditable_id' => $ticket->id,
            'user_id' => $director->id,
        ]);
    }

    public function test_authorized_user_can_use_native_blade_maintenance_work_order_workspace(): void
    {
        $this->seed();

        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $ticket = ServiceTicket::where('ticket_number', 'AST-1001')->firstOrFail();

        $this->actingAs($construction)
            ->get(route('after-sales.work-orders.index'))
            ->assertOk()
            ->assertSee('Workspace for creating, scheduling, assigning and completing maintenance')
            ->assertSee('name="service_ticket_id"', false)
            ->assertSee('MWO-1001')
            ->assertDontSee('window.Builder360Server', false)
            ->assertDontSee('id="root"', false);

        $this->actingAs($construction)
            ->post(route('after-sales.work-orders.store'), [
                'service_ticket_id' => $ticket->id,
                'assigned_to_user_id' => $construction->id,
                'scheduled_on' => now()->addDay()->toDateString(),
                'scope_of_work' => 'Inspect and repair the reported issue from the native Blade work-order workspace.',
                'estimated_cost' => 1800,
            ])
            ->assertRedirect(route('after-sales.work-orders.index'))
            ->assertSessionHas('status');

        $workOrder = MaintenanceWorkOrder::where('scope_of_work', 'Inspect and repair the reported issue from the native Blade work-order workspace.')->firstOrFail();

        $this->assertSame('scheduled', $workOrder->status);

        $this->actingAs($construction)
            ->patch(route('after-sales.work-orders.complete', $workOrder), [
                'completion_notes' => 'Completed from Blade workspace after site inspection.',
                'actual_cost' => 1750,
            ])
            ->assertRedirect(route('after-sales.work-orders.index'))
            ->assertSessionHas('status');

        $workOrder->refresh();

        $this->assertSame('completed', $workOrder->status);
        $this->assertSame('1750.00', $workOrder->actual_cost);
        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'after_sales.work_order.completed',
            'auditable_id' => $workOrder->id,
            'user_id' => $construction->id,
        ]);
    }

    public function test_non_global_after_sales_users_without_company_assignment_fail_closed(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $booking = Booking::where('booking_code', 'BK-1001')->firstOrFail();
        $ticket = ServiceTicket::where('ticket_number', 'AST-1001')->firstOrFail();
        $workOrder = MaintenanceWorkOrder::where('work_order_number', 'MWO-1001')->firstOrFail();

        $sales->forceFill(['company_id' => null])->save();
        $construction->forceFill(['company_id' => null])->save();

        $this->actingAs($sales)
            ->getJson(route('after-sales.tickets.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($sales)
            ->getJson(route('after-sales.work-orders.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($sales)
            ->getJson(route('after-sales.tickets.index', [
                'project_id' => $ticket->project_id,
                'booking_id' => $booking->id,
                'customer_id' => $ticket->customer_id,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id', 'booking_id', 'customer_id']);

        $this->actingAs($sales)
            ->getJson(route('after-sales.work-orders.index', [
                'service_ticket_id' => $ticket->id,
                'assigned_to_user_id' => $construction->id,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['service_ticket_id', 'assigned_to_user_id']);

        $this->actingAs($sales)
            ->postJson(route('after-sales.tickets.store'), [
                'booking_id' => $booking->id,
                'category' => 'maintenance',
                'priority' => 'medium',
                'source' => 'phone',
                'subject' => 'Scope guard ticket',
                'description' => 'Scope guard should reject this internal after-sales ticket.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['booking_id']);

        $this->actingAs($sales)
            ->patchJson(route('after-sales.tickets.assign', $ticket), [
                'assigned_to_user_id' => $construction->id,
                'note' => 'Scope guard should reject assignment.',
            ])
            ->assertForbidden();

        $this->actingAs($construction)
            ->postJson(route('after-sales.work-orders.store'), [
                'service_ticket_id' => $ticket->id,
                'assigned_to_user_id' => $construction->id,
                'scheduled_on' => now()->addDay()->toDateString(),
                'scope_of_work' => 'Scope guard should reject this work order.',
                'estimated_cost' => 100,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['service_ticket_id']);

        $this->actingAs($construction)
            ->patchJson(route('after-sales.work-orders.complete', $workOrder), [
                'completion_notes' => 'Scope guard should reject this completion.',
                'actual_cost' => 100,
            ])
            ->assertForbidden();

        $this->actingAs($construction)
            ->patchJson(route('after-sales.tickets.resolve', $ticket), [
                'resolution_summary' => 'Scope guard should reject this resolution.',
            ])
            ->assertForbidden();

        $ticket->forceFill(['status' => 'resolved'])->save();

        $this->actingAs($sales)
            ->patchJson(route('after-sales.tickets.close', $ticket), [
                'customer_rating' => 4,
                'note' => 'Scope guard should reject this close action.',
            ])
            ->assertForbidden();
    }

    public function test_buyer_can_create_ticket_only_for_own_booking_with_configured_sla(): void
    {
        $this->seed();

        $buyer = User::where('email', 'rohan.shah@example.test')->firstOrFail();
        $ownBooking = Booking::where('booking_code', 'BK-1001')->firstOrFail();
        $slaSetting = SystemSetting::where('company_id', $ownBooking->company_id)
            ->where('setting_key', 'after_sales.sla_hours')
            ->where('status', 'active')
            ->firstOrFail();

        $slaSetting->forceFill([
            'value' => [
                'low' => 96,
                'medium' => 48,
                'high' => 18,
                'critical' => 7,
            ],
        ])->save();

        $this->actingAs($buyer)
            ->postJson(route('after-sales.tickets.store'), [
                'booking_id' => $ownBooking->id,
                'category' => 'maintenance',
                'priority' => 'critical',
                'subject' => 'Lift lobby light not working',
                'description' => 'The lift lobby light near my unit is not working and needs urgent maintenance.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.priority', 'critical')
            ->assertJsonPath('data.source', 'portal')
            ->assertJsonPath('data.customer.email', 'rohan.shah@example.test');

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'after_sales.ticket.created',
            'user_id' => $buyer->id,
        ]);

        $ticket = ServiceTicket::where('subject', 'Lift lobby light not working')->firstOrFail();

        $this->assertEquals(7, $ticket->created_at->diffInHours($ticket->sla_due_at));
        $this->assertSame(7, $ticket->metadata['sla_hours']);
    }

    public function test_after_sales_indexes_validate_filters_and_company_scope(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $otherCompany = Company::where('code', 'B360P')->firstOrFail();
        $otherProject = Project::where('company_id', $otherCompany->id)->firstOrFail();
        $customer = Customer::where('code', 'CUS-1002')->firstOrFail();
        $otherTicket = ServiceTicket::create([
            'company_id' => $otherCompany->id,
            'project_id' => $otherProject->id,
            'customer_id' => $customer->id,
            'ticket_number' => 'AST-OTHER-1001',
            'category' => 'maintenance',
            'priority' => 'medium',
            'source' => 'internal',
            'subject' => 'Other company ticket',
            'description' => 'Other company ticket used for scope validation.',
            'status' => 'open',
            'first_response_due_at' => now()->addHours(12),
            'sla_due_at' => now()->addHours(24),
            'workflow_history' => [],
            'metadata' => ['source' => 'test'],
        ]);

        $this->actingAs($sales)
            ->getJson(route('after-sales.tickets.index', ['per_page' => 500]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);

        $this->actingAs($sales)
            ->getJson(route('after-sales.tickets.index', ['unexpected_filter' => 'ignored-before-hardening']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['unexpected_filter'])
            ->assertJsonPath('errors.unexpected_filter.0', 'The selected filter is not available for this endpoint.');

        $this->actingAs($sales)
            ->getJson(route('after-sales.tickets.index', ['status' => 'invalid']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->actingAs($sales)
            ->getJson(route('after-sales.tickets.index', ['project_id' => $otherProject->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id']);

        $this->actingAs($sales)
            ->getJson(route('after-sales.work-orders.index', ['status' => 'invalid']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->actingAs($sales)
            ->getJson(route('after-sales.work-orders.index', ['unexpected_filter' => 'ignored-before-hardening']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['unexpected_filter'])
            ->assertJsonPath('errors.unexpected_filter.0', 'The selected filter is not available for this endpoint.');

        $this->actingAs($sales)
            ->getJson(route('after-sales.work-orders.index', ['service_ticket_id' => $otherTicket->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['service_ticket_id']);
    }

    public function test_ticket_resolution_requires_active_work_orders_to_be_completed(): void
    {
        $this->seed();

        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $buyer = User::where('email', 'rohan.shah@example.test')->firstOrFail();
        $ticket = ServiceTicket::where('ticket_number', 'AST-1001')->firstOrFail();
        $workOrder = MaintenanceWorkOrder::where('work_order_number', 'MWO-1001')->firstOrFail();

        $this->actingAs($construction)
            ->patchJson(route('after-sales.tickets.resolve', $ticket), [
                'resolution_summary' => 'Leakage repair has been inspected and completed.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ticket');

        $this->actingAs($construction)
            ->patchJson(route('after-sales.work-orders.complete', $workOrder), [
                'completion_notes' => 'Leakage source repaired, sealant applied and cabinet wiped dry.',
                'actual_cost' => 3200,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.actual_cost', 3200);

        $this->actingAs($construction)
            ->patchJson(route('after-sales.tickets.resolve', $ticket), [
                'resolution_summary' => 'Kitchen sink leakage was repaired and verified after water-flow testing.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'resolved');

        $this->actingAs($buyer)
            ->patchJson(route('after-sales.tickets.close', $ticket), [
                'customer_rating' => 5,
                'note' => 'Issue resolved satisfactorily.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.customer_rating', 5);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'after_sales.ticket.closed',
            'user_id' => $buyer->id,
        ]);
    }

    public function test_assignment_and_work_order_creation_update_ticket_state(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $booking = Booking::where('booking_code', 'BK-1001')->firstOrFail();

        $ticketNumber = $this->actingAs($sales)
            ->postJson(route('after-sales.tickets.store'), [
                'booking_id' => $booking->id,
                'category' => 'documentation',
                'priority' => 'medium',
                'source' => 'phone',
                'subject' => 'Request for duplicate possession checklist',
                'description' => 'Customer requested a duplicate copy of the signed possession checklist.',
            ])
            ->assertCreated()
            ->json('data.ticket_number');

        $ticket = ServiceTicket::where('ticket_number', $ticketNumber)->firstOrFail();

        $this->actingAs($sales)
            ->patchJson(route('after-sales.tickets.assign', $ticket), [
                'assigned_to_user_id' => $construction->id,
                'note' => 'Assigning for document retrieval from site records.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'assigned')
            ->assertJsonPath('data.assigned_to.email', 'rajesh.kulkarni@builder360.test');

        $this->actingAs($construction)
            ->postJson(route('after-sales.work-orders.store'), [
                'service_ticket_id' => $ticket->id,
                'assigned_to_user_id' => $construction->id,
                'scheduled_on' => now()->addDay()->toDateString(),
                'scope_of_work' => 'Retrieve signed possession checklist from project records and attach copy.',
                'estimated_cost' => 0,
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'scheduled')
            ->assertJsonPath('data.service_ticket.ticket_number', $ticketNumber);

        $this->assertDatabaseHas('service_tickets', [
            'id' => $ticket->id,
            'status' => 'in_progress',
            'assigned_to_user_id' => $construction->id,
        ]);
    }

    public function test_assignment_and_work_order_creation_reject_cross_company_references(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $booking = Booking::where('booking_code', 'BK-1001')->firstOrFail();
        $otherCompany = Company::where('code', 'B360P')->firstOrFail();
        $externalAssignee = User::factory()->create([
            'company_id' => $otherCompany->id,
            'name' => 'External Maintenance User',
            'email' => 'external.maintenance@example.test',
            'status' => 'active',
        ]);
        $externalVendor = Vendor::create([
            'company_id' => $otherCompany->id,
            'vendor_code' => 'VEN-AFTER-EXT',
            'name' => 'External Maintenance Vendor',
            'vendor_type' => 'maintenance',
            'contact_name' => 'External Contact',
            'email' => 'external.vendor@example.test',
            'phone' => '9000000001',
            'status' => 'active',
            'bank_details' => [],
            'compliance_documents' => [],
            'metadata' => [],
        ]);

        $ticketNumber = $this->actingAs($sales)
            ->postJson(route('after-sales.tickets.store'), [
                'booking_id' => $booking->id,
                'category' => 'maintenance',
                'priority' => 'medium',
                'source' => 'phone',
                'subject' => 'Request for hallway fixture repair',
                'description' => 'Customer requested repair for the hallway fixture near the booked unit.',
            ])
            ->assertCreated()
            ->json('data.ticket_number');

        $ticket = ServiceTicket::where('ticket_number', $ticketNumber)->firstOrFail();

        $this->actingAs($sales)
            ->patchJson(route('after-sales.tickets.assign', $ticket), [
                'assigned_to_user_id' => $externalAssignee->id,
                'note' => 'This assignee belongs to another company and must be rejected.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['assigned_to_user_id']);

        $this->actingAs($construction)
            ->postJson(route('after-sales.work-orders.store'), [
                'service_ticket_id' => $ticket->id,
                'assigned_to_user_id' => $externalAssignee->id,
                'scheduled_on' => now()->addDay()->toDateString(),
                'scope_of_work' => 'Inspect and repair the hallway fixture using internal maintenance resources.',
                'estimated_cost' => 1200,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['assigned_to_user_id']);

        $this->actingAs($construction)
            ->postJson(route('after-sales.work-orders.store'), [
                'service_ticket_id' => $ticket->id,
                'assigned_to_user_id' => $construction->id,
                'vendor_id' => $externalVendor->id,
                'scheduled_on' => now()->addDay()->toDateString(),
                'scope_of_work' => 'Inspect and repair the hallway fixture using an external vendor.',
                'estimated_cost' => 1200,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['vendor_id']);
    }

    public function test_partner_cannot_access_after_sales_and_buyer_cannot_access_internal_work_orders(): void
    {
        $this->seed();

        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();
        $buyer = User::where('email', 'rohan.shah@example.test')->firstOrFail();
        $ticket = ServiceTicket::where('ticket_number', 'AST-1001')->firstOrFail();
        $workOrder = MaintenanceWorkOrder::where('work_order_number', 'MWO-1001')->firstOrFail();

        $this->actingAs($partner)
            ->getJson(route('after-sales.tickets.index'))
            ->assertForbidden();

        $this->actingAs($partner)
            ->postJson(route('after-sales.tickets.store'), [])
            ->assertForbidden();

        $this->actingAs($partner)
            ->patchJson(route('after-sales.tickets.assign', $ticket), [
                'assigned_to_user_id' => $buyer->id,
                'note' => 'Partner must not assign internal after-sales tickets.',
            ])
            ->assertForbidden();

        $this->actingAs($partner)
            ->patchJson(route('after-sales.tickets.resolve', $ticket), [
                'resolution_summary' => 'Partner must not resolve internal after-sales tickets.',
            ])
            ->assertForbidden();

        $this->actingAs($buyer)
            ->getJson(route('after-sales.work-orders.index'))
            ->assertForbidden();

        $this->actingAs($buyer)
            ->patchJson(route('after-sales.work-orders.complete', $workOrder), [
                'completion_notes' => 'Buyer must not complete internal work orders.',
                'actual_cost' => 0,
            ])
            ->assertForbidden();
    }
}
