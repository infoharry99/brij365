<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\ManagedDocument;
use App\Models\ProjectUnit;
use App\Models\ServiceTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BuyerPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_register_routes_render_native_blade_workspaces(): void
    {
        $this->seed();

        $buyer = User::where('email', 'rohan.shah@example.test')->firstOrFail();

        foreach ([
            'buyer.bookings.index' => 'My Bookings',
            'buyer.receipts.index' => 'My Receipts',
            'buyer.payment-requests.index' => 'My Payments',
            'buyer.documents.index' => 'My Documents',
            'buyer.service-tickets.index' => 'My Service Tickets',
        ] as $route => $heading) {
            $this->actingAs($buyer)->get(route($route))
                ->assertOk()
                ->assertSee($heading)
                ->assertSee('aria-label="Buyer portal navigation"', false)
                ->assertDontSee('id="root"', false);
        }
    }

    public function test_buyer_can_view_scoped_portal_summary_bookings_receipts_and_documents(): void
    {
        $this->seed();

        $buyer = User::where('email', 'rohan.shah@example.test')->firstOrFail();

        $this->actingAs($buyer)
            ->getJson(route('buyer.summary'))
            ->assertOk()
            ->assertJsonPath('data.customer.code', 'CUS-1001')
            ->assertJsonPath('data.bookings_count', 1)
            ->assertJsonPath('data.open_tickets_count', 1)
            ->assertJsonPath('data.approved_receipts_total', 500000)
            ->assertJsonPath('data.payment_schedule.0.booking_code', 'BK-1001')
            ->assertJsonPath('data.documents.0.download_url', fn (?string $url): bool => is_string($url) && str_contains($url, '/documents/'))
            ->assertJsonPath('data.service_tickets.0.ticket_number', 'AST-1001');

        $event = AuditEvent::query()
            ->where('event_type', 'buyer.portal_summary.viewed')
            ->where('user_id', $buyer->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(1, $event->metadata['bookings_count']);
        $this->assertSame(1, $event->metadata['open_tickets_count']);
        $this->assertSame(2, $event->metadata['documents_count']);

        $this->actingAs($buyer)
            ->getJson(route('buyer.bookings.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.booking_code', 'BK-1001')
            ->assertJsonPath('data.0.customer.email', 'rohan.shah@example.test');

        $this->actingAs($buyer)
            ->getJson(route('buyer.receipts.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.receipt_number', 'RCPT-1001')
            ->assertJsonPath('data.0.status', 'approved');

        $this->actingAs($buyer)
            ->getJson(route('buyer.receipts.index', ['status' => 'approved']))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.status', 'approved');

        $this->actingAs($buyer)
            ->getJson(route('buyer.documents.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.download_url', fn (?string $url): bool => is_string($url) && str_contains($url, '/documents/'))
            ->assertJsonMissingPath('data.0.storage_disk')
            ->assertJsonMissingPath('data.0.storage_path')
            ->assertJsonMissingPath('data.0.checksum_sha256')
            ->assertJsonFragment(['document_number' => 'DOC-1002'])
            ->assertJsonFragment(['document_number' => 'DOC-1003']);

        $this->actingAs($buyer)
            ->getJson(route('buyer.documents.index', ['status' => 'approved', 'owner_type' => 'booking']))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonFragment(['document_number' => 'DOC-1002']);
    }

    public function test_buyer_can_use_native_blade_portal_summary_workspace(): void
    {
        $this->seed();

        $buyer = User::where('email', 'rohan.shah@example.test')->firstOrFail();
        $ownBooking = Booking::where('booking_code', 'BK-1001')->firstOrFail();

        $this->actingAs($buyer)
            ->get(route('buyer.summary'))
            ->assertOk()
            ->assertSee('Secure buyer workspace')
            ->assertDontSee('Native Laravel Blade buyer workspace')
            ->assertSee('BK-1001')
            ->assertSee('RCPT-1001', false)
            ->assertSee('DOC-1002')
            ->assertSee('AST-1001')
            ->assertSee('name="booking_id"', false)
            ->assertDontSee('window.Builder360Server', false)
            ->assertDontSee('id="root"', false);

        $this->actingAs($buyer)
            ->post(route('buyer.service-tickets.store'), [
                'booking_id' => $ownBooking->id,
                'category' => 'maintenance',
                'priority' => 'medium',
                'subject' => 'Blade buyer portal service request',
                'description' => 'Buyer created this service request from the native Blade buyer portal.',
            ])
            ->assertRedirect(route('buyer.summary'))
            ->assertSessionHas('status');

        $ticket = ServiceTicket::where('subject', 'Blade buyer portal service request')->firstOrFail();

        $this->assertSame('portal', $ticket->source);
        $this->assertSame($ownBooking->customer_id, $ticket->customer_id);
        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'after_sales.ticket.created',
            'auditable_id' => $ticket->id,
            'user_id' => $buyer->id,
        ]);
    }

    public function test_buyer_blade_close_action_is_customer_scoped_and_redirects(): void
    {
        $this->seed();

        $buyer = User::where('email', 'rohan.shah@example.test')->firstOrFail();
        $ownTicket = ServiceTicket::where('ticket_number', 'AST-1001')->firstOrFail();

        $ownTicket->forceFill([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolution_summary' => 'Kitchen sink leakage was repaired and verified.',
        ])->save();

        $this->actingAs($buyer)
            ->patch(route('buyer.service-tickets.close', $ownTicket), [
                'customer_rating' => 5,
                'note' => 'Closed from native Blade buyer portal.',
            ])
            ->assertRedirect(route('buyer.summary'))
            ->assertSessionHas('status');

        $ownTicket->refresh();

        $this->assertSame('closed', $ownTicket->status);
        $this->assertSame(5, $ownTicket->customer_rating);
        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'after_sales.ticket.closed',
            'user_id' => $buyer->id,
        ]);
    }

    public function test_global_internal_user_cannot_use_buyer_only_write_routes(): void
    {
        $this->seed();

        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $booking = Booking::where('booking_code', 'BK-1001')->firstOrFail();
        $ticket = ServiceTicket::where('ticket_number', 'AST-1001')->firstOrFail();

        $ticket->forceFill([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolution_summary' => 'Resolved for buyer close route authorization test.',
        ])->save();

        $this->actingAs($director)
            ->postJson(route('buyer.service-tickets.store'), [
                'booking_id' => $booking->id,
                'category' => 'maintenance',
                'priority' => 'medium',
                'subject' => 'Internal user must not create buyer ticket',
                'description' => 'This request must be denied because the route is buyer-role only.',
            ])
            ->assertForbidden();

        $this->actingAs($director)
            ->patchJson(route('buyer.service-tickets.close', $ticket), [
                'customer_rating' => 5,
                'note' => 'Internal user must not close through buyer route.',
            ])
            ->assertForbidden();
    }

    public function test_buyer_can_raise_ticket_only_for_own_confirmed_booking(): void
    {
        $this->seed();

        $buyer = User::where('email', 'rohan.shah@example.test')->firstOrFail();
        $ownBooking = Booking::where('booking_code', 'BK-1001')->firstOrFail();
        $otherBooking = $this->createDifferentCustomerBooking();

        $this->actingAs($buyer)
            ->postJson(route('buyer.service-tickets.store'), [
                'booking_id' => $ownBooking->id,
                'category' => 'maintenance',
                'priority' => 'medium',
                'source' => 'internal',
                'subject' => 'Balcony door service request',
                'description' => 'Balcony sliding door requires alignment check and service.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.source', 'portal')
            ->assertJsonPath('data.booking.booking_code', 'BK-1001')
            ->assertJsonPath('data.customer.email', 'rohan.shah@example.test');

        $this->actingAs($buyer)
            ->postJson(route('buyer.service-tickets.store'), [
                'booking_id' => $otherBooking->id,
                'category' => 'maintenance',
                'priority' => 'medium',
                'subject' => 'Invalid service request',
                'description' => 'This should fail because the booking belongs to another customer.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('booking_id');
    }

    public function test_buyer_ticket_close_route_is_buyer_only_and_customer_scoped(): void
    {
        $this->seed();

        $buyer = User::where('email', 'rohan.shah@example.test')->firstOrFail();
        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $ownTicket = ServiceTicket::where('ticket_number', 'AST-1001')->firstOrFail();

        $ownTicket->forceFill([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolution_summary' => 'Kitchen sink leakage was repaired and verified.',
        ])->save();

        $this->actingAs($sales)
            ->patchJson(route('buyer.service-tickets.close', $ownTicket), [
                'customer_rating' => 4,
                'note' => 'Internal user must not close through buyer route.',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('audit_events', [
            'event_type' => 'after_sales.ticket.closed',
            'user_id' => $sales->id,
        ]);

        $otherBooking = $this->createDifferentCustomerBooking();
        $otherTicket = ServiceTicket::create([
            'company_id' => $otherBooking->company_id,
            'project_id' => $otherBooking->project_id,
            'booking_id' => $otherBooking->id,
            'customer_id' => $otherBooking->customer_id,
            'project_unit_id' => $otherBooking->project_unit_id,
            'raised_by_user_id' => $sales->id,
            'ticket_number' => 'AST-PORTAL-OTHER',
            'category' => 'maintenance',
            'priority' => 'medium',
            'source' => 'portal',
            'subject' => 'Other customer resolved ticket',
            'description' => 'Resolved ticket for another buyer portal customer.',
            'status' => 'resolved',
            'first_response_due_at' => now()->addHours(12),
            'sla_due_at' => now()->addHours(24),
            'resolved_at' => now(),
            'resolution_summary' => 'Resolved for another customer.',
            'workflow_history' => [],
            'metadata' => ['source' => 'buyer_portal_test'],
        ]);

        $this->actingAs($buyer)
            ->patchJson(route('buyer.service-tickets.close', $otherTicket), [
                'customer_rating' => 5,
                'note' => 'Attempt to close another customer ticket.',
            ])
            ->assertForbidden();

        $this->assertSame('resolved', $otherTicket->fresh()->status);

        $this->actingAs($buyer)
            ->patchJson(route('buyer.service-tickets.close', $ownTicket), [
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

    public function test_buyer_document_access_is_limited_to_portal_scope_and_owned_downloads(): void
    {
        $this->seed();

        Storage::fake('local');

        $buyer = User::where('email', 'rohan.shah@example.test')->firstOrFail();
        $ownDocument = ManagedDocument::where('document_number', 'DOC-1002')->firstOrFail();
        $projectDocument = ManagedDocument::where('document_number', 'DOC-1001')->firstOrFail();

        Storage::disk('local')->put($ownDocument->storage_path, 'Buyer agreement copy');
        Storage::disk('local')->put($projectDocument->storage_path, 'Internal project document');

        $this->actingAs($buyer)
            ->getJson(route('documents.index'))
            ->assertForbidden();

        $ownDownload = $this->actingAs($buyer)->get(route('documents.download', $ownDocument));

        $ownDownload
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->assertSame('Buyer agreement copy', $ownDownload->streamedContent());

        $this->actingAs($buyer)
            ->get(route('documents.download', $projectDocument))
            ->assertForbidden();
    }

    public function test_buyer_ticket_list_is_customer_scoped_and_partner_is_forbidden(): void
    {
        $this->seed();

        $buyer = User::where('email', 'rohan.shah@example.test')->firstOrFail();
        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();
        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();

        $this->actingAs($buyer)
            ->getJson(route('buyer.service-tickets.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.ticket_number', 'AST-1001');

        $this->actingAs($partner)
            ->getJson(route('buyer.summary'))
            ->assertForbidden();

        $this->assertDatabaseMissing('audit_events', [
            'user_id' => $partner->id,
            'event_type' => 'buyer.portal_summary.viewed',
        ]);

        $this->actingAs($partner)
            ->getJson(route('buyer.bookings.index'))
            ->assertForbidden();

        $this->actingAs($director)
            ->getJson(route('buyer.summary'))
            ->assertForbidden();

        $this->actingAs($director)
            ->getJson(route('buyer.bookings.index'))
            ->assertForbidden();
    }

    public function test_buyer_portal_indexes_validate_filters_and_booking_scope(): void
    {
        $this->seed();

        $buyer = User::where('email', 'rohan.shah@example.test')->firstOrFail();
        $otherBooking = $this->createDifferentCustomerBooking();

        $this->actingAs($buyer)
            ->getJson(route('buyer.bookings.index', ['per_page' => 500]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);

        $this->actingAs($buyer)
            ->getJson(route('buyer.bookings.index', ['page' => 1]))
            ->assertOk()
            ->assertJsonPath('meta.current_page', 1);

        $this->actingAs($buyer)
            ->getJson(route('buyer.bookings.index', ['unexpected_filter' => 'ignored-before-hardening']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['unexpected_filter'])
            ->assertJsonPath('errors.unexpected_filter.0', 'The selected filter is not available for this endpoint.');

        $this->actingAs($buyer)
            ->getJson(route('buyer.payment-requests.index', ['status' => 'invalid']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->actingAs($buyer)
            ->getJson(route('buyer.service-tickets.index', ['category' => 'invalid']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category']);

        $this->actingAs($buyer)
            ->getJson(route('buyer.bookings.index', ['status' => 'paid']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status'])
            ->assertJsonPath('errors.status.0', 'The selected status is not valid for this buyer portal endpoint.');

        $this->actingAs($buyer)
            ->getJson(route('buyer.receipts.index', ['status' => 'paid']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->actingAs($buyer)
            ->getJson(route('buyer.documents.index', ['booking_id' => $otherBooking->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['booking_id'])
            ->assertJsonPath('errors.booking_id.0', 'The selected filter is not available for this endpoint.');

        $this->actingAs($buyer)
            ->getJson(route('buyer.bookings.index', ['category' => 'maintenance']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category'])
            ->assertJsonPath('errors.category.0', 'The selected filter is not available for this endpoint.');

        $this->actingAs($buyer)
            ->getJson(route('buyer.service-tickets.index', ['owner_type' => 'booking']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['owner_type']);

        $this->actingAs($buyer)
            ->getJson(route('buyer.receipts.index', ['booking_id' => $otherBooking->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['booking_id']);
    }

    private function createDifferentCustomerBooking(): Booking
    {
        $unit = ProjectUnit::where('unit_code', 'SKY-A-1205')->firstOrFail();
        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $customer = Customer::create([
            'code' => 'CUS-PORTAL-OTHER',
            'name' => 'Other Portal Customer',
            'email' => 'other.portal.customer@example.test',
            'phone' => '+91 98999 90000',
            'source' => 'Direct',
            'status' => 'active',
        ]);

        return Booking::create([
            'company_id' => $unit->company_id,
            'project_id' => $unit->project_id,
            'project_unit_id' => $unit->id,
            'customer_id' => $customer->id,
            'booked_by_user_id' => $sales->id,
            'booking_code' => 'BK-PORTAL-OTHER',
            'status' => 'confirmed',
            'booked_on' => now()->subDays(3)->toDateString(),
            'agreement_value' => 8500000,
            'discount_amount' => 0,
            'tax_amount' => 425000,
            'net_receivable' => 8925000,
            'booking_amount' => 500000,
            'commercials' => ['source' => 'buyer_portal_test'],
        ]);
    }
}
