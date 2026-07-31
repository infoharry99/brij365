<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingPaymentSchedule;
use App\Models\AuditEvent;
use App\Models\CollectionReceipt;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Project;
use App\Models\ProjectUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceCollectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_users_can_list_company_scoped_collection_receipts(): void
    {
        $this->seed();

        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();

        $this->actingAs($finance)
            ->getJson(route('finance.collections.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.receipt_number', 'RCPT-1001')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['receipt_number', 'status', 'amount', 'booking', 'payment_schedule', 'customer'],
                ],
            ]);
    }

    public function test_authorized_users_can_open_native_blade_collection_workspace(): void
    {
        $this->seed();

        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();

        $this->actingAs($finance)
            ->get(route('finance.collections.index'))
            ->assertOk()
            ->assertSee('Customer Collections')
            ->assertSee('Workspace')
            ->assertSee('Capture receipt')
            ->assertSee('Open booking schedules')
            ->assertSee('Collection filters')
            ->assertSee('Collection receipt register')
            ->assertSee('name="booking_id"', false)
            ->assertSee('name="amount"', false)
            ->assertSee('RCPT-1001')
            ->assertDontSee('window.Builder360Server', false)
            ->assertDontSee('id="root"', false);
    }

    public function test_native_blade_collection_form_submits_receipt_and_redirects(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $booking = Booking::where('booking_code', 'BK-1001')->firstOrFail();
        $schedule = BookingPaymentSchedule::where('booking_id', $booking->id)
            ->where('sequence', 2)
            ->firstOrFail();

        $this->actingAs($sales)
            ->post(route('finance.collections.store'), [
                'booking_id' => $booking->id,
                'booking_payment_schedule_id' => $schedule->id,
                'receipt_date' => now()->toDateString(),
                'payment_mode' => 'upi',
                'instrument_number' => 'UPI-BLADE-2001',
                'bank_name' => 'UPI',
                'amount' => 500000,
                'tax_deducted_amount' => 0,
                'notes' => 'Native Blade collection receipt submission.',
            ])
            ->assertRedirect(route('finance.collections.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('collection_receipts', [
            'booking_id' => $booking->id,
            'booking_payment_schedule_id' => $schedule->id,
            'instrument_number' => 'UPI-BLADE-2001',
            'status' => 'submitted',
            'collected_by_user_id' => $sales->id,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'finance.collection.submitted',
            'action' => 'Submitted collection receipt',
            'user_id' => $sales->id,
        ]);
    }

    public function test_native_blade_collection_approval_redirects_and_updates_schedule(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $booking = Booking::where('booking_code', 'BK-1001')->firstOrFail();
        $schedule = BookingPaymentSchedule::where('booking_id', $booking->id)
            ->where('sequence', 2)
            ->firstOrFail();

        $this->actingAs($sales)
            ->post(route('finance.collections.store'), [
                'booking_id' => $booking->id,
                'booking_payment_schedule_id' => $schedule->id,
                'receipt_date' => now()->toDateString(),
                'payment_mode' => 'neft',
                'instrument_number' => 'NEFT-BLADE-2002',
                'bank_name' => 'Demo Bank',
                'amount' => 2825400,
            ])
            ->assertRedirect(route('finance.collections.index'));

        $receipt = CollectionReceipt::where('instrument_number', 'NEFT-BLADE-2002')->firstOrFail();

        $this->actingAs($finance)
            ->patch(route('finance.collections.approve', $receipt), [
                'note' => 'Approved from native Blade collection register.',
            ])
            ->assertRedirect(route('finance.collections.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('collection_receipts', [
            'id' => $receipt->id,
            'status' => 'approved',
            'approved_by_user_id' => $finance->id,
        ]);

        $this->assertDatabaseHas('booking_payment_schedules', [
            'id' => $schedule->id,
            'status' => 'paid',
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'finance.collection.approved',
            'action' => 'Approved collection receipt',
            'user_id' => $finance->id,
        ]);
    }

    public function test_non_global_finance_user_without_company_assignment_fails_closed_for_collections(): void
    {
        $this->seed();

        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $booking = Booking::where('booking_code', 'BK-1001')->firstOrFail();
        $project = Project::whereKey($booking->project_id)->firstOrFail();

        $receipt = CollectionReceipt::create([
            'company_id' => $booking->company_id,
            'project_id' => $booking->project_id,
            'booking_id' => $booking->id,
            'booking_payment_schedule_id' => null,
            'customer_id' => $booking->customer_id,
            'collected_by_user_id' => $sales->id,
            'receipt_number' => 'RCPT-FAIL-CLOSED',
            'status' => 'submitted',
            'receipt_date' => now()->toDateString(),
            'payment_mode' => 'neft',
            'instrument_number' => 'NEFT-FAIL-CLOSED',
            'bank_name' => 'Scope Test Bank',
            'amount' => 1000,
            'tax_deducted_amount' => 0,
            'notes' => 'Fail-closed scope regression record.',
            'metadata' => ['source' => 'test'],
        ]);

        $finance->forceFill(['company_id' => null])->save();

        $this->actingAs($finance)
            ->getJson(route('finance.collections.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($finance)
            ->getJson(route('finance.collections.index', ['project_id' => $project->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id']);

        $this->actingAs($finance)
            ->postJson(route('finance.collections.store'), [
                'booking_id' => $booking->id,
                'receipt_date' => now()->toDateString(),
                'payment_mode' => 'neft',
                'instrument_number' => 'NEFT-DENIED-SUBMIT',
                'amount' => 1000,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['booking_id']);

        $this->actingAs($finance)
            ->patchJson(route('finance.collections.approve', $receipt), [
                'note' => 'This approval must be denied by company scope.',
            ])
            ->assertForbidden();
    }

    public function test_sales_can_submit_collection_and_finance_can_approve_it(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $booking = Booking::where('booking_code', 'BK-1001')->firstOrFail();
        $schedule = BookingPaymentSchedule::where('booking_id', $booking->id)
            ->where('sequence', 2)
            ->firstOrFail();

        $submitResponse = $this->actingAs($sales)->postJson(route('finance.collections.store'), [
            'booking_id' => $booking->id,
            'booking_payment_schedule_id' => $schedule->id,
            'receipt_date' => now()->toDateString(),
            'payment_mode' => 'upi',
            'instrument_number' => 'UPI-TEST-2001',
            'bank_name' => 'UPI',
            'amount' => 2825400,
            'notes' => 'Agreement milestone collection.',
        ]);

        $submitResponse
            ->assertCreated()
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.payment_schedule.sequence', 2);

        $receipt = CollectionReceipt::where('receipt_number', $submitResponse->json('data.receipt_number'))->firstOrFail();

        $this->assertDatabaseHas('booking_payment_schedules', [
            'id' => $schedule->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'finance.collection.submitted',
            'action' => 'Submitted collection receipt',
            'user_id' => $sales->id,
        ]);

        $this->actingAs($finance)
            ->patchJson(route('finance.collections.approve', $receipt), [
                'note' => str_repeat('x', 1001),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('note');

        $this->actingAs($finance)
            ->patchJson(route('finance.collections.approve', $receipt), [
                'note' => 'Approved after bank statement verification.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.approved_by.email', 'suresh.iyer@builder360.test');

        $this->assertDatabaseHas('collection_receipts', [
            'id' => $receipt->id,
            'status' => 'approved',
            'approved_by_user_id' => $finance->id,
        ]);

        $receipt->refresh();

        $this->assertSame('Approved after bank statement verification.', $receipt->metadata['approval_note']);

        $this->assertDatabaseHas('booking_payment_schedules', [
            'id' => $schedule->id,
            'status' => 'paid',
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'finance.collection.approved',
            'action' => 'Approved collection receipt',
            'user_id' => $finance->id,
            'metadata->note' => 'Approved after bank statement verification.',
        ]);
    }

    public function test_collection_index_validates_filters_and_company_scope(): void
    {
        $this->seed();

        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $otherCompany = Company::where('code', 'B360P')->firstOrFail();
        $otherProject = Project::where('company_id', $otherCompany->id)->firstOrFail();

        $this->actingAs($finance)
            ->getJson(route('finance.collections.index', ['unexpected_filter' => 'ignored-before-hardening']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['unexpected_filter'])
            ->assertJsonPath('errors.unexpected_filter.0', 'The selected filter is not available for this endpoint.');

        $this->actingAs($finance)
            ->getJson(route('finance.collections.index', ['per_page' => 500]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);

        $this->actingAs($finance)
            ->getJson(route('finance.collections.index', ['status' => 'invalid']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->actingAs($finance)
            ->getJson(route('finance.collections.index', ['payment_mode' => 'crypto']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['payment_mode']);

        $this->actingAs($finance)
            ->getJson(route('finance.collections.index', ['project_id' => $otherProject->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id']);

        $this->actingAs($finance)
            ->getJson(route('finance.collections.index', ['date_from' => '2026-04-30', 'date_to' => '2026-04-01']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date_to']);
    }

    public function test_authorized_users_can_export_company_scoped_collection_receipts(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $otherCompany = Company::where('code', 'B360P')->firstOrFail();
        $otherProject = Project::where('company_id', $otherCompany->id)->firstOrFail();
        $otherUnit = ProjectUnit::where('project_id', $otherProject->id)->firstOrFail();
        $otherCustomer = Customer::create([
            'code' => 'CUS-OTHER-SCOPE',
            'name' => 'Other Scope Buyer',
            'email' => 'other-scope-buyer@example.test',
            'phone' => '9999999999',
            'source' => 'test',
            'status' => 'active',
        ]);
        $booking = Booking::create([
            'company_id' => $otherCompany->id,
            'project_id' => $otherProject->id,
            'project_unit_id' => $otherUnit->id,
            'customer_id' => $otherCustomer->id,
            'booked_by_user_id' => $sales->id,
            'booking_code' => 'BK-OTHER-SCOPE',
            'status' => 'confirmed',
            'booked_on' => now()->toDateString(),
            'agreement_value' => 1000000,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'net_receivable' => 1000000,
            'booking_amount' => 100000,
            'commercials' => ['source' => 'test'],
        ]);

        CollectionReceipt::create([
            'company_id' => $otherCompany->id,
            'project_id' => $otherProject->id,
            'booking_id' => $booking->id,
            'booking_payment_schedule_id' => null,
            'customer_id' => $booking->customer_id,
            'collected_by_user_id' => $sales->id,
            'receipt_number' => 'RCPT-OTHER-COMPANY',
            'status' => 'approved',
            'receipt_date' => now()->toDateString(),
            'payment_mode' => 'neft',
            'instrument_number' => 'NEFT-OTHER-COMPANY',
            'bank_name' => 'Other Company Bank',
            'amount' => 25000,
            'tax_deducted_amount' => 0,
            'notes' => 'Must not appear in B360D scoped export.',
            'metadata' => ['source' => 'test'],
        ]);

        $response = $this->actingAs($sales)
            ->get(route('finance.collections.export', ['status' => 'approved']));

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $csv = $response->getContent();

        $this->assertIsString($csv);
        $this->assertStringContainsString('receipt_number,status,receipt_date', $csv);
        $this->assertStringContainsString('RCPT-1001', $csv);
        $this->assertStringNotContainsString('RCPT-OTHER-COMPANY', $csv);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'finance.collection.exported',
            'action' => 'Exported collection receipt report',
            'user_id' => $sales->id,
        ]);

        $audit = AuditEvent::where('event_type', 'finance.collection.exported')->latest('id')->firstOrFail();
        $this->assertSame('csv', $audit->metadata['format']);
        $this->assertSame('approved', $audit->metadata['filters']['status']);
        $this->assertGreaterThanOrEqual(1, $audit->metadata['row_count']);
    }

    public function test_collector_cannot_approve_their_own_receipt(): void
    {
        $this->seed();

        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $booking = Booking::where('booking_code', 'BK-1001')->firstOrFail();
        $schedule = BookingPaymentSchedule::where('booking_id', $booking->id)
            ->where('sequence', 2)
            ->firstOrFail();

        $receiptNumber = $this->actingAs($finance)->postJson(route('finance.collections.store'), [
            'booking_id' => $booking->id,
            'booking_payment_schedule_id' => $schedule->id,
            'receipt_date' => now()->toDateString(),
            'payment_mode' => 'neft',
            'instrument_number' => 'NEFT-SELF-2002',
            'bank_name' => 'Demo Bank',
            'amount' => 500000,
        ])->assertCreated()->json('data.receipt_number');

        $receipt = CollectionReceipt::where('receipt_number', $receiptNumber)->firstOrFail();

        $this->actingAs($finance)
            ->patchJson(route('finance.collections.approve', $receipt))
            ->assertForbidden();
    }

    public function test_collection_submission_rejects_schedule_overcollection(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $booking = Booking::where('booking_code', 'BK-1001')->firstOrFail();
        $schedule = BookingPaymentSchedule::where('booking_id', $booking->id)
            ->where('sequence', 1)
            ->firstOrFail();

        $this->actingAs($sales)->postJson(route('finance.collections.store'), [
            'booking_id' => $booking->id,
            'booking_payment_schedule_id' => $schedule->id,
            'receipt_date' => now()->toDateString(),
            'payment_mode' => 'neft',
            'instrument_number' => 'NEFT-OVER-2003',
            'amount' => 1000000,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('amount');
    }

    public function test_partner_cannot_access_internal_collection_routes(): void
    {
        $this->seed();

        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();
        $booking = Booking::where('booking_code', 'BK-1001')->firstOrFail();

        $this->actingAs($partner)
            ->getJson(route('finance.collections.index'))
            ->assertForbidden();

        $this->actingAs($partner)->postJson(route('finance.collections.store'), [
            'booking_id' => $booking->id,
            'receipt_date' => now()->toDateString(),
            'payment_mode' => 'cash',
            'amount' => 10000,
        ])->assertForbidden();
    }
}
