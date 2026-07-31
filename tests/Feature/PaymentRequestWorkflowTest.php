<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingPaymentSchedule;
use App\Models\CollectionReceipt;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\PaymentRequest;
use App\Models\Project;
use App\Models\ProjectUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class PaymentRequestWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_can_create_payment_request_and_buyer_can_pay_it(): void
    {
        $this->seed();

        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $buyer = User::where('email', 'rohan.shah@example.test')->firstOrFail();
        $booking = Booking::where('booking_code', 'BK-1001')->firstOrFail();
        $schedule = BookingPaymentSchedule::where('booking_id', $booking->id)
            ->where('sequence', 3)
            ->firstOrFail();

        $createResponse = $this->actingAs($finance)
            ->postJson(route('finance.payment-requests.store'), [
                'booking_id' => $booking->id,
                'booking_payment_schedule_id' => $schedule->id,
                'amount' => 5650800,
                'purpose' => 'Slab completion milestone payment link',
                'metadata' => ['source' => 'feature_test'],
            ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('data.status', 'requested')
            ->assertJsonPath('data.payment_schedule.sequence', 3)
            ->assertJsonPath('data.gateway_provider', 'prototype')
            ->assertJsonPath('data.gateway_mode', 'simulated')
            ->assertJsonPath('data.gateway_label', 'Internal simulated gateway')
            ->assertJsonStructure(['data' => ['request_number', 'gateway_reference', 'payment_url', 'checksum']]);

        $paymentRequest = PaymentRequest::where('request_number', $createResponse->json('data.request_number'))->firstOrFail();

        $this->actingAs($buyer)
            ->getJson(route('buyer.payment-requests.index', ['status' => 'requested']))
            ->assertOk()
            ->assertJsonFragment(['request_number' => $paymentRequest->request_number]);

        $payResponse = $this->actingAs($buyer)
            ->patchJson(route('buyer.payment-requests.pay', $paymentRequest), [
                'payment_mode' => 'upi',
                'instrument_number' => 'UPI-PAYREQ-3001',
                'gateway_response_code' => 'SIMULATED_SUCCESS',
            ]);

        $payResponse
            ->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.payment_mode', 'upi')
            ->assertJsonPath('data.collection_receipt.status', 'approved');

        $paymentRequest->refresh();
        $receipt = CollectionReceipt::findOrFail($paymentRequest->collection_receipt_id);

        $this->assertSame('approved', $receipt->status);
        $this->assertSame('Internal Simulated Gateway', $receipt->bank_name);
        $this->assertSame('Internal simulated payment link; no external gateway is invoked.', $paymentRequest->gateway_payload['simulation_notice']);
        $this->assertSame('payment_request', $receipt->metadata['source']);
        $this->assertSame($buyer->id, $paymentRequest->paid_by_user_id);

        $this->assertDatabaseHas('booking_payment_schedules', [
            'id' => $schedule->id,
            'status' => 'paid',
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'finance.payment_request.created',
            'action' => 'Created buyer payment request',
            'user_id' => $finance->id,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'finance.payment_request.paid',
            'action' => 'Paid buyer payment request',
            'user_id' => $buyer->id,
        ]);
    }

    public function test_finance_can_use_native_blade_payment_request_workspace(): void
    {
        $this->seed();

        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $booking = Booking::where('booking_code', 'BK-1001')->firstOrFail();
        $schedule = BookingPaymentSchedule::where('booking_id', $booking->id)
            ->where('sequence', 3)
            ->firstOrFail();

        $this->actingAs($finance)
            ->get(route('finance.payment-requests.index'))
            ->assertOk()
            ->assertSee('Workspace for creating buyer payment links')
            ->assertSee('name="booking_id"', false)
            ->assertSee('PAYREQ-10001')
            ->assertDontSee('window.Builder360Server', false)
            ->assertDontSee('id="root"', false);

        $this->actingAs($finance)
            ->post(route('finance.payment-requests.store'), [
                'booking_id' => $booking->id,
                'booking_payment_schedule_id' => $schedule->id,
                'amount' => 100000,
                'purpose' => 'Blade milestone payment link',
            ])
            ->assertRedirect(route('finance.payment-requests.index'))
            ->assertSessionHas('status');

        $paymentRequest = PaymentRequest::where('purpose', 'Blade milestone payment link')->firstOrFail();

        $this->assertSame('requested', $paymentRequest->status);
        $this->assertSame('prototype', $paymentRequest->gateway_provider);
        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'finance.payment_request.created',
            'auditable_id' => $paymentRequest->id,
            'user_id' => $finance->id,
        ]);

        $this->actingAs($finance)
            ->patch(route('finance.payment-requests.cancel', $paymentRequest), [
                'reason' => 'Cancelled from Blade workspace test.',
            ])
            ->assertRedirect(route('finance.payment-requests.index'))
            ->assertSessionHas('status');

        $this->assertSame('cancelled', $paymentRequest->fresh()->status);
    }

    public function test_duplicate_active_payment_request_for_schedule_is_rejected(): void
    {
        $this->seed();

        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $booking = Booking::where('booking_code', 'BK-1001')->firstOrFail();
        $schedule = BookingPaymentSchedule::where('booking_id', $booking->id)
            ->where('sequence', 2)
            ->firstOrFail();

        $this->actingAs($finance)
            ->postJson(route('finance.payment-requests.store'), [
                'booking_id' => $booking->id,
                'booking_payment_schedule_id' => $schedule->id,
                'amount' => 100000,
                'purpose' => 'Duplicate agreement payment link',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('booking_payment_schedule_id');
    }

    public function test_payment_request_uses_configured_gateway_provider_not_request_input(): void
    {
        $this->seed();

        Config::set('builder360.integrations.payment_gateway.provider', 'razorpay');

        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $booking = Booking::where('booking_code', 'BK-1001')->firstOrFail();
        $schedule = BookingPaymentSchedule::where('booking_id', $booking->id)
            ->where('sequence', 3)
            ->firstOrFail();

        $requestNumber = $this->actingAs($finance)
            ->postJson(route('finance.payment-requests.store'), [
                'booking_id' => $booking->id,
                'booking_payment_schedule_id' => $schedule->id,
                'amount' => 5650800,
                'purpose' => 'Configured provider payment link',
            ])
            ->assertCreated()
            ->assertJsonPath('data.gateway_provider', 'razorpay')
            ->assertJsonPath('data.gateway_mode', 'configured')
            ->assertJsonPath('data.gateway_label', 'Razorpay gateway')
            ->json('data.request_number');

        $paymentRequest = PaymentRequest::where('request_number', $requestNumber)->firstOrFail();

        $this->assertSame('razorpay', $paymentRequest->gateway_provider);
        $this->assertSame('razorpay', $paymentRequest->gateway_payload['provider']);
    }

    public function test_client_cannot_override_payment_gateway_provider(): void
    {
        $this->seed();

        Config::set('builder360.integrations.payment_gateway.provider', 'razorpay');

        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $booking = Booking::where('booking_code', 'BK-1001')->firstOrFail();
        $schedule = BookingPaymentSchedule::where('booking_id', $booking->id)
            ->where('sequence', 3)
            ->firstOrFail();

        $this->actingAs($finance)
            ->postJson(route('finance.payment-requests.store'), [
                'booking_id' => $booking->id,
                'booking_payment_schedule_id' => $schedule->id,
                'amount' => 5650800,
                'purpose' => 'Attempt to override provider',
                'gateway_provider' => 'attacker-controlled-provider',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('gateway_provider');

        $this->assertDatabaseMissing('payment_requests', [
            'gateway_provider' => 'attacker-controlled-provider',
        ]);
    }

    public function test_direct_buyer_payment_simulation_is_disabled_for_real_gateway_provider(): void
    {
        $this->seed();

        Config::set('builder360.integrations.payment_gateway.provider', 'razorpay');

        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $buyer = User::where('email', 'rohan.shah@example.test')->firstOrFail();
        $booking = Booking::where('booking_code', 'BK-1001')->firstOrFail();
        $schedule = BookingPaymentSchedule::where('booking_id', $booking->id)
            ->where('sequence', 3)
            ->firstOrFail();

        $requestNumber = $this->actingAs($finance)
            ->postJson(route('finance.payment-requests.store'), [
                'booking_id' => $booking->id,
                'booking_payment_schedule_id' => $schedule->id,
                'amount' => 5650800,
                'purpose' => 'Real gateway payment link',
            ])
            ->assertCreated()
            ->assertJsonPath('data.gateway_provider', 'razorpay')
            ->json('data.request_number');

        $paymentRequest = PaymentRequest::where('request_number', $requestNumber)->firstOrFail();

        $this->actingAs($buyer)
            ->patchJson(route('buyer.payment-requests.pay', $paymentRequest), [
                'payment_mode' => 'upi',
                'instrument_number' => 'UPI-REAL-GATEWAY-DIRECT',
                'gateway_response_code' => 'SIMULATED_SUCCESS',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payment_request');

        $paymentRequest->refresh();

        $this->assertSame('requested', $paymentRequest->status);
        $this->assertNull($paymentRequest->collection_receipt_id);
        $this->assertDatabaseMissing('collection_receipts', [
            'instrument_number' => 'UPI-REAL-GATEWAY-DIRECT',
        ]);
    }

    public function test_signed_payment_gateway_webhook_reconciles_real_gateway_payment(): void
    {
        $this->seed();

        Config::set('builder360.integrations.payment_gateway.provider', 'razorpay');
        Config::set('builder360.integrations.payment_gateway.webhook_secret', 'test-gateway-webhook-secret');

        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $booking = Booking::where('booking_code', 'BK-1001')->firstOrFail();
        $schedule = BookingPaymentSchedule::where('booking_id', $booking->id)
            ->where('sequence', 3)
            ->firstOrFail();

        $requestNumber = $this->actingAs($finance)
            ->postJson(route('finance.payment-requests.store'), [
                'booking_id' => $booking->id,
                'booking_payment_schedule_id' => $schedule->id,
                'amount' => 5650800,
                'purpose' => 'Gateway webhook payment link',
            ])
            ->assertCreated()
            ->assertJsonPath('data.gateway_provider', 'razorpay')
            ->json('data.request_number');

        $paymentRequest = PaymentRequest::where('request_number', $requestNumber)->firstOrFail();
        $payload = [
            'gateway_reference' => $paymentRequest->gateway_reference,
            'status' => 'captured',
            'amount' => 5650800,
            'currency' => 'INR',
            'transaction_reference' => 'RZP-PAY-10001',
            'payment_mode' => 'online',
            'gateway_response_code' => 'CAPTURED',
            'paid_at' => now()->toISOString(),
        ];

        $this->signedGatewayWebhook($payload)
            ->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.gateway_provider', 'razorpay')
            ->assertJsonPath('data.gateway_mode', 'configured')
            ->assertJsonPath('data.gateway_label', 'Razorpay gateway')
            ->assertJsonPath('data.instrument_number', 'RZP-PAY-10001')
            ->assertJsonPath('data.collection_receipt.status', 'approved');

        $paymentRequest->refresh();
        $receipt = CollectionReceipt::findOrFail($paymentRequest->collection_receipt_id);

        $this->assertNull($paymentRequest->paid_by_user_id);
        $this->assertSame('approved', $receipt->status);
        $this->assertSame('RAZORPAY Gateway', $receipt->bank_name);
        $this->assertSame('payment_gateway_webhook', $receipt->metadata['source']);

        $this->assertDatabaseHas('booking_payment_schedules', [
            'id' => $schedule->id,
            'status' => 'paid',
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'finance.payment_request.gateway_paid',
            'action' => 'Reconciled payment gateway webhook',
            'user_id' => null,
        ]);
    }

    public function test_payment_gateway_webhook_is_signature_protected_and_idempotent(): void
    {
        $this->seed();

        Config::set('builder360.integrations.payment_gateway.provider', 'razorpay');
        Config::set('builder360.integrations.payment_gateway.webhook_secret', 'test-gateway-webhook-secret');

        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $booking = Booking::where('booking_code', 'BK-1001')->firstOrFail();
        $schedule = BookingPaymentSchedule::where('booking_id', $booking->id)
            ->where('sequence', 3)
            ->firstOrFail();

        $requestNumber = $this->actingAs($finance)
            ->postJson(route('finance.payment-requests.store'), [
                'booking_id' => $booking->id,
                'booking_payment_schedule_id' => $schedule->id,
                'amount' => 5650800,
                'purpose' => 'Gateway webhook idempotency link',
            ])
            ->assertCreated()
            ->json('data.request_number');

        $paymentRequest = PaymentRequest::where('request_number', $requestNumber)->firstOrFail();
        $payload = [
            'gateway_reference' => $paymentRequest->gateway_reference,
            'status' => 'paid',
            'amount' => 5650800,
            'currency' => 'INR',
            'transaction_reference' => 'RZP-PAY-IDEMPOTENT',
            'payment_mode' => 'online',
        ];

        $this->unsignedGatewayWebhook($payload)
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid payment gateway webhook signature.');

        $this->assertNull($paymentRequest->fresh()->collection_receipt_id);

        $this->signedGatewayWebhook($payload)->assertOk();
        $receiptCount = CollectionReceipt::where('instrument_number', 'RZP-PAY-IDEMPOTENT')->count();

        $this->signedGatewayWebhook($payload)
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');

        $this->assertSame($receiptCount, CollectionReceipt::where('instrument_number', 'RZP-PAY-IDEMPOTENT')->count());
        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'finance.payment_request.gateway_webhook_idempotent',
            'user_id' => null,
        ]);
    }

    public function test_payment_gateway_webhook_rejects_unexpected_fields_and_uses_configured_amount_limit(): void
    {
        $this->seed();

        Config::set('builder360.integrations.payment_gateway.provider', 'razorpay');
        Config::set('builder360.integrations.payment_gateway.webhook_secret', 'test-gateway-webhook-secret');

        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $booking = Booking::where('booking_code', 'BK-1001')->firstOrFail();
        $schedule = BookingPaymentSchedule::where('booking_id', $booking->id)
            ->where('sequence', 3)
            ->firstOrFail();

        $requestNumber = $this->actingAs($finance)
            ->postJson(route('finance.payment-requests.store'), [
                'booking_id' => $booking->id,
                'booking_payment_schedule_id' => $schedule->id,
                'amount' => 5650800,
                'purpose' => 'Gateway webhook hardening link',
            ])
            ->assertCreated()
            ->json('data.request_number');

        $paymentRequest = PaymentRequest::where('request_number', $requestNumber)->firstOrFail();
        $payload = [
            'gateway_reference' => $paymentRequest->gateway_reference,
            'status' => 'captured',
            'amount' => 5650800,
            'currency' => 'INR',
            'transaction_reference' => 'RZP-PAY-HARDENED',
            'payment_mode' => 'online',
        ];

        $this->signedGatewayWebhook($payload + ['gateway_provider' => 'attacker-controlled'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['gateway_provider'])
            ->assertJsonPath('errors.gateway_provider.0', 'The selected field is not allowed for this payment gateway webhook.');

        $this->assertSame('requested', $paymentRequest->fresh()->status);
        $this->assertNull($paymentRequest->fresh()->collection_receipt_id);

        Config::set('builder360.money_input_limits.payment_amount_max', '1000');

        $this->signedGatewayWebhook($payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);

        $this->assertSame('requested', $paymentRequest->fresh()->status);
        $this->assertNull($paymentRequest->fresh()->collection_receipt_id);
    }

    public function test_non_global_finance_user_without_company_assignment_fails_closed_for_payment_requests(): void
    {
        $this->seed();

        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $booking = Booking::where('booking_code', 'BK-1001')->firstOrFail();
        $project = Project::whereKey($booking->project_id)->firstOrFail();
        $paymentRequest = PaymentRequest::create([
            'company_id' => $booking->company_id,
            'project_id' => $booking->project_id,
            'booking_id' => $booking->id,
            'booking_payment_schedule_id' => null,
            'customer_id' => $booking->customer_id,
            'created_by_user_id' => $finance->id,
            'request_number' => 'PAYREQ-FAIL-CLOSED',
            'gateway_provider' => 'prototype',
            'gateway_reference' => 'GATEWAY-FAIL-CLOSED',
            'status' => 'requested',
            'amount' => 1000,
            'currency' => 'INR',
            'purpose' => 'Fail-closed payment request',
            'expires_at' => now()->addDays(7),
            'checksum' => hash('sha256', 'fail-closed-payment-request'),
            'gateway_payload' => ['source' => 'test'],
            'workflow_history' => [],
            'metadata' => ['source' => 'test'],
        ]);

        $finance->forceFill(['company_id' => null])->save();

        $this->actingAs($finance)
            ->getJson(route('finance.payment-requests.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($finance)
            ->getJson(route('finance.payment-requests.index', ['project_id' => $project->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id']);

        $this->actingAs($finance)
            ->postJson(route('finance.payment-requests.store'), [
                'booking_id' => $booking->id,
                'amount' => 1000,
                'purpose' => 'Denied payment request',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['booking_id']);

        $this->actingAs($finance)
            ->patchJson(route('finance.payment-requests.cancel', $paymentRequest), [
                'reason' => 'This cancellation must be denied by company scope.',
            ])
            ->assertForbidden();
    }

    public function test_buyer_cannot_pay_another_customer_payment_request(): void
    {
        $this->seed();

        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $buyer = User::where('email', 'rohan.shah@example.test')->firstOrFail();
        $otherBooking = $this->createOtherCustomerBooking();
        $schedule = BookingPaymentSchedule::create([
            'booking_id' => $otherBooking->id,
            'sequence' => 1,
            'milestone' => 'Booking Amount',
            'percentage' => 100,
            'amount' => 100000,
            'due_on' => now()->addDays(10)->toDateString(),
            'status' => 'pending',
        ]);

        $requestNumber = $this->actingAs($finance)
            ->postJson(route('finance.payment-requests.store'), [
                'booking_id' => $otherBooking->id,
                'booking_payment_schedule_id' => $schedule->id,
                'amount' => 100000,
                'purpose' => 'Other customer payment link',
            ])
            ->assertCreated()
            ->json('data.request_number');

        $paymentRequest = PaymentRequest::where('request_number', $requestNumber)->firstOrFail();

        $this->actingAs($buyer)
            ->patchJson(route('buyer.payment-requests.pay', $paymentRequest), [
                'payment_mode' => 'upi',
                'instrument_number' => 'UPI-WRONG-BUYER',
            ])
            ->assertForbidden();
    }

    public function test_finance_can_cancel_requested_payment_link_and_buyer_cannot_pay_cancelled_link(): void
    {
        $this->seed();

        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $buyer = User::where('email', 'rohan.shah@example.test')->firstOrFail();
        $booking = Booking::where('booking_code', 'BK-1001')->firstOrFail();
        $schedule = BookingPaymentSchedule::where('booking_id', $booking->id)
            ->where('sequence', 4)
            ->firstOrFail();

        $requestNumber = $this->actingAs($finance)
            ->postJson(route('finance.payment-requests.store'), [
                'booking_id' => $booking->id,
                'booking_payment_schedule_id' => $schedule->id,
                'amount' => 100000,
                'purpose' => 'Possession advance payment link',
            ])
            ->assertCreated()
            ->json('data.request_number');

        $paymentRequest = PaymentRequest::where('request_number', $requestNumber)->firstOrFail();

        $this->actingAs($finance)
            ->patchJson(route('finance.payment-requests.cancel', $paymentRequest), [
                'reason' => 'Buyer requested revised link.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->actingAs($buyer)
            ->patchJson(route('buyer.payment-requests.pay', $paymentRequest), [
                'payment_mode' => 'upi',
                'instrument_number' => 'UPI-CANCELLED',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'finance.payment_request.cancelled',
            'action' => 'Cancelled buyer payment request',
            'user_id' => $finance->id,
        ]);
    }

    public function test_internal_payment_request_index_validates_filter_scope(): void
    {
        $this->seed();

        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $otherCompany = Company::where('code', 'B360P')->firstOrFail();
        $otherProject = Project::where('company_id', $otherCompany->id)->firstOrFail();
        $otherBooking = $this->createOtherCompanyBooking($otherCompany, $otherProject);
        $unrelatedCustomer = Customer::create([
            'code' => 'CUST-PAYREQ-SCOPE',
            'name' => 'Payment Request Scope Customer',
            'email' => 'payment.request.scope@example.test',
            'phone' => '+91 98000 00003',
            'source' => 'Scope Test',
            'status' => 'active',
        ]);

        $this->actingAs($finance)
            ->getJson(route('finance.payment-requests.index', ['unexpected_filter' => 'ignored-before-hardening']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['unexpected_filter'])
            ->assertJsonPath('errors.unexpected_filter.0', 'The selected filter is not available for this endpoint.');

        $this->actingAs($finance)
            ->getJson(route('finance.payment-requests.index', ['project_id' => $otherProject->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id']);

        $this->actingAs($finance)
            ->getJson(route('finance.payment-requests.index', ['booking_id' => $otherBooking->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['booking_id']);

        $this->actingAs($finance)
            ->getJson(route('finance.payment-requests.index', ['customer_id' => $unrelatedCustomer->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id']);
    }

    public function test_partner_is_denied_internal_and_buyer_payment_request_routes(): void
    {
        $this->seed();

        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();
        $paymentRequest = PaymentRequest::where('request_number', 'PAYREQ-10001')->firstOrFail();

        $this->actingAs($partner)
            ->getJson(route('finance.payment-requests.index'))
            ->assertForbidden();

        $this->actingAs($partner)
            ->postJson(route('finance.payment-requests.store'), [
                'booking_id' => $paymentRequest->booking_id,
                'amount' => 10000,
                'purpose' => 'Forbidden partner request',
            ])
            ->assertForbidden();

        $this->actingAs($partner)
            ->getJson(route('buyer.payment-requests.index'))
            ->assertForbidden();

        $this->actingAs($partner)
            ->patchJson(route('buyer.payment-requests.pay', $paymentRequest), [
                'payment_mode' => 'upi',
                'instrument_number' => 'UPI-PARTNER',
            ])
            ->assertForbidden();
    }

    private function createOtherCustomerBooking(): Booking
    {
        $customer = Customer::where('code', 'CUS-1002')->firstOrFail();
        $lead = Lead::where('lead_code', 'LD-1002')->firstOrFail();
        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $unit = ProjectUnit::where('status', 'available')
            ->where('project_id', $lead->project_id)
            ->firstOrFail();

        return Booking::create([
            'company_id' => $unit->company_id,
            'project_id' => $unit->project_id,
            'project_unit_id' => $unit->id,
            'customer_id' => $customer->id,
            'lead_id' => $lead->id,
            'partner_id' => $lead->partner_id,
            'booked_by_user_id' => $sales->id,
            'booking_code' => 'BK-OTHER-1001',
            'status' => 'confirmed',
            'booked_on' => now()->toDateString(),
            'agreement_value' => 1000000,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'net_receivable' => 1000000,
            'booking_amount' => 100000,
            'commercials' => ['source' => 'payment_request_test'],
        ]);
    }

    private function createOtherCompanyBooking(Company $company, Project $project): Booking
    {
        $customer = Customer::create([
            'code' => 'CUS-OTHER-COMPANY-PAYREQ',
            'name' => 'Other Company Payment Customer',
            'email' => 'other.company.payment@example.test',
            'phone' => '+91 98000 00004',
            'source' => 'Scope Test',
            'status' => 'active',
        ]);

        $unit = ProjectUnit::create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'unit_code' => 'PAYREQ-OTHER-COMPANY-UNIT',
            'tower' => 'T1',
            'floor' => '1',
            'unit_number' => '101',
            'unit_type' => '2BHK',
            'carpet_area_sqft' => 650,
            'saleable_area_sqft' => 900,
            'base_rate' => 7000,
            'base_price' => 6300000,
            'floor_rise' => 0,
            'parking_charges' => 0,
            'other_charges' => 0,
            'tax_amount' => 0,
            'total_price' => 6300000,
            'status' => 'booked',
        ]);

        return Booking::create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'project_unit_id' => $unit->id,
            'customer_id' => $customer->id,
            'lead_id' => null,
            'partner_id' => null,
            'booked_by_user_id' => null,
            'booking_code' => 'BK-OTHER-COMPANY-PAYREQ',
            'status' => 'confirmed',
            'booked_on' => now()->toDateString(),
            'agreement_value' => 6300000,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'net_receivable' => 6300000,
            'booking_amount' => 100000,
            'commercials' => ['source' => 'payment_request_scope_test'],
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function signedGatewayWebhook(array $payload)
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $json, (string) config('builder360.integrations.payment_gateway.webhook_secret'));

        return $this->call(
            'POST',
            route('finance.payment-gateway.webhook'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_BUILDER360_SIGNATURE' => $signature,
            ],
            $json,
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function unsignedGatewayWebhook(array $payload)
    {
        return $this->call(
            'POST',
            route('finance.payment-gateway.webhook'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_BUILDER360_SIGNATURE' => 'invalid-signature',
            ],
            json_encode($payload, JSON_THROW_ON_ERROR),
        );
    }
}
