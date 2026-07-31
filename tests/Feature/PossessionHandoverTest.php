<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\CollectionReceipt;
use App\Models\Company;
use App\Models\Customer;
use App\Models\HandoverSnag;
use App\Models\Lead;
use App\Models\PossessionHandover;
use App\Models\Project;
use App\Models\ProjectUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PossessionHandoverTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_user_can_list_seeded_handover_and_snags(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();

        $this->actingAs($sales)
            ->getJson(route('possession.handovers.index'))
            ->assertOk()
            ->assertJsonPath('data.0.handover_number', 'PH-1001')
            ->assertJsonPath('data.0.status', 'blocked');

        $this->actingAs($sales)
            ->getJson(route('possession.snags.index'))
            ->assertOk()
            ->assertJsonPath('data.0.snag_number', 'SNAG-1001')
            ->assertJsonPath('data.0.status', 'open');
    }

    public function test_users_can_use_native_blade_possession_handover_workspace(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $booking = $this->createPaidBooking($sales);

        $this->actingAs($sales)
            ->get(route('possession.handovers.index'))
            ->assertOk()
            ->assertSee('Workspace for possession eligibility')
            ->assertSee('name="booking_id"', false)
            ->assertSee('PH-1001')
            ->assertDontSee('window.Builder360Server', false)
            ->assertDontSee('id="root"', false);

        $this->actingAs($sales)
            ->post(route('possession.handovers.store'), [
                'booking_id' => $booking->id,
                'target_handover_on' => now()->addDays(7)->toDateString(),
            ])
            ->assertRedirect(route('possession.handovers.index'))
            ->assertSessionHas('status');

        $handover = PossessionHandover::where('booking_id', $booking->id)->firstOrFail();

        $this->assertSame('blocked', $handover->status);
        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'possession.handover.initiated',
            'auditable_id' => $handover->id,
            'user_id' => $sales->id,
        ]);

        $this->actingAs($sales)
            ->patch(route('possession.handovers.checklist.update', $handover), [
                'checklist' => $this->completedChecklist(),
            ])
            ->assertRedirect(route('possession.handovers.index'))
            ->assertSessionHas('status');

        $this->assertSame('ready', $handover->fresh()->status);

        $this->actingAs($sales)
            ->patch(route('possession.handovers.letter.issue', $handover), [
                'possession_letter_reference' => 'PL-BLADE-READY',
            ])
            ->assertRedirect(route('possession.handovers.index'))
            ->assertSessionHas('status');

        $this->assertSame('PL-BLADE-READY', $handover->fresh()->possession_letter_reference);

        $this->actingAs($finance)
            ->patch(route('possession.handovers.complete', $handover), [
                'actual_handover_on' => now()->toDateString(),
                'possession_letter_reference' => 'PL-BLADE-READY',
            ])
            ->assertRedirect(route('possession.handovers.index'))
            ->assertSessionHas('status');

        $handover->refresh();

        $this->assertSame('completed', $handover->status);
        $this->assertDatabaseHas('project_units', [
            'id' => $handover->project_unit_id,
            'status' => 'handed_over',
        ]);
    }

    public function test_users_can_use_native_blade_handover_snag_workspace(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $booking = $this->createPaidBooking($sales);

        $this->actingAs($sales)
            ->post(route('possession.handovers.store'), [
                'booking_id' => $booking->id,
                'checklist' => $this->completedChecklist(),
            ])
            ->assertRedirect(route('possession.handovers.index'));

        $handover = PossessionHandover::where('booking_id', $booking->id)->firstOrFail();

        $this->assertSame('ready', $handover->status);

        $this->actingAs($sales)
            ->get(route('possession.snags.index'))
            ->assertOk()
            ->assertSee('Workspace for reporting possession snags')
            ->assertSee('name="possession_handover_id"', false)
            ->assertSee('SNAG-1001')
            ->assertDontSee('window.Builder360Server', false)
            ->assertDontSee('id="root"', false);

        $this->actingAs($sales)
            ->post(route('possession.snags.store'), [
                'possession_handover_id' => $handover->id,
                'area' => 'Bedroom',
                'category' => 'Electrical',
                'severity' => 'high',
                'description' => 'Switch plate alignment issue from Blade workspace.',
                'target_resolution_on' => now()->addDays(2)->toDateString(),
            ])
            ->assertRedirect(route('possession.snags.index'))
            ->assertSessionHas('status');

        $snag = HandoverSnag::where('description', 'Switch plate alignment issue from Blade workspace.')->firstOrFail();

        $this->assertSame('open', $snag->status);
        $this->assertSame('blocked', $handover->fresh()->status);

        $this->actingAs($construction)
            ->patch(route('possession.snags.resolve', $snag), [
                'resolution_notes' => 'Resolved from Blade workspace.',
            ])
            ->assertRedirect(route('possession.snags.index'))
            ->assertSessionHas('status');

        $this->assertSame('resolved', $snag->fresh()->status);
        $this->assertSame('ready', $handover->fresh()->status);
    }

    public function test_non_global_possession_users_without_company_assignment_fail_closed(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $handover = PossessionHandover::where('handover_number', 'PH-1001')->firstOrFail();
        $snag = HandoverSnag::where('snag_number', 'SNAG-1001')->firstOrFail();
        $paidBooking = $this->createPaidBooking($sales);

        $sales->forceFill(['company_id' => null])->save();
        $construction->forceFill(['company_id' => null])->save();
        $finance->forceFill(['company_id' => null])->save();

        $this->actingAs($sales)
            ->getJson(route('possession.handovers.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($sales)
            ->getJson(route('possession.snags.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($sales)
            ->getJson(route('possession.handovers.index', ['project_id' => $handover->project_id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id']);

        $this->actingAs($sales)
            ->getJson(route('possession.snags.index', ['possession_handover_id' => $handover->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['possession_handover_id']);

        $this->actingAs($sales)
            ->postJson(route('possession.handovers.store'), [
                'booking_id' => $paidBooking->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['booking_id']);

        $this->actingAs($sales)
            ->postJson(route('possession.snags.store'), [
                'possession_handover_id' => $handover->id,
                'area' => 'Living Room',
                'category' => 'Civil',
                'severity' => 'medium',
                'description' => 'Scope guard should reject this snag.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['possession_handover_id']);

        $this->actingAs($sales)
            ->patchJson(route('possession.handovers.checklist.update', $handover), [
                'checklist' => $this->completedChecklist(),
            ])
            ->assertForbidden();

        $this->actingAs($sales)
            ->patchJson(route('possession.handovers.letter.issue', $handover), [
                'possession_letter_reference' => 'PL-SCOPE-DENIED',
            ])
            ->assertForbidden();

        $this->actingAs($finance)
            ->patchJson(route('possession.handovers.complete', $handover), [
                'actual_handover_on' => now()->toDateString(),
                'possession_letter_reference' => 'PL-SCOPE-DENIED',
            ])
            ->assertForbidden();

        $this->actingAs($construction)
            ->patchJson(route('possession.snags.resolve', $snag), [
                'resolution_notes' => 'Scope guard should reject this resolution.',
            ])
            ->assertForbidden();
    }

    public function test_possession_indexes_validate_filters_and_company_scope(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $handover = PossessionHandover::where('handover_number', 'PH-1001')->firstOrFail();
        $externalHandover = $this->createExternalCompanyHandover();

        $this->actingAs($sales)
            ->getJson(route('possession.handovers.index', ['status' => 'invalid']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->actingAs($sales)
            ->getJson(route('possession.handovers.index', ['unexpected_filter' => 'ignored-before-hardening']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['unexpected_filter'])
            ->assertJsonPath('errors.unexpected_filter.0', 'The selected filter is not available for this endpoint.');

        $this->actingAs($sales)
            ->getJson(route('possession.handovers.index', ['per_page' => 500]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');

        $this->actingAs($sales)
            ->getJson(route('possession.handovers.index', ['project_id' => $externalHandover->project_id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('project_id');

        $this->actingAs($sales)
            ->getJson(route('possession.snags.index', ['status' => 'invalid']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->actingAs($sales)
            ->getJson(route('possession.snags.index', ['unexpected_filter' => 'ignored-before-hardening']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['unexpected_filter'])
            ->assertJsonPath('errors.unexpected_filter.0', 'The selected filter is not available for this endpoint.');

        $this->actingAs($sales)
            ->getJson(route('possession.snags.index', ['severity' => 'urgent']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('severity');

        $this->actingAs($sales)
            ->getJson(route('possession.snags.index', ['per_page' => 500]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');

        $this->actingAs($sales)
            ->getJson(route('possession.snags.index', ['possession_handover_id' => $externalHandover->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('possession_handover_id');

        $this->actingAs($sales)
            ->getJson(route('possession.handovers.index', [
                'project_id' => $handover->project_id,
                'status' => 'blocked',
                'per_page' => 10,
            ]))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.handover_number', 'PH-1001');

        $this->actingAs($sales)
            ->getJson(route('possession.snags.index', [
                'possession_handover_id' => $handover->id,
                'status' => 'open',
                'severity' => 'medium',
                'per_page' => 10,
            ]))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.snag_number', 'SNAG-1001');
    }

    public function test_handover_initiation_calculates_ready_state_for_fully_paid_booking(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $booking = $this->createPaidBooking($sales);

        $this->actingAs($sales)
            ->postJson(route('possession.handovers.store'), [
                'booking_id' => $booking->id,
                'target_handover_on' => now()->addDays(5)->toDateString(),
                'checklist' => $this->completedChecklist(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.financial_outstanding', 0)
            ->assertJsonPath('data.booking.booking_code', 'BK-HANDOVER-TEST');

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'possession.handover.initiated',
            'user_id' => $sales->id,
        ]);
    }

    public function test_possession_letter_requires_clear_handover_and_is_audited(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $booking = $this->createPaidBooking($sales);

        $handoverNumber = $this->actingAs($sales)
            ->postJson(route('possession.handovers.store'), [
                'booking_id' => $booking->id,
                'checklist' => $this->completedChecklist(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'ready')
            ->json('data.handover_number');

        $handover = PossessionHandover::where('handover_number', $handoverNumber)->firstOrFail();

        $this->actingAs($sales)
            ->patchJson(route('possession.handovers.letter.issue', $handover), [
                'possession_letter_reference' => 'PL-HANDOVER-READY',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.possession_letter_reference', 'PL-HANDOVER-READY');

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'possession.handover.letter_issued',
            'user_id' => $sales->id,
        ]);

        $blockedHandover = PossessionHandover::where('handover_number', 'PH-1001')->firstOrFail();

        $this->actingAs($sales)
            ->patchJson(route('possession.handovers.letter.issue', $blockedHandover), [
                'possession_letter_reference' => 'PL-BLOCKED-DENIED',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('handover');
    }

    public function test_handover_completion_is_blocked_until_payment_checklist_and_snags_clear(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $handover = PossessionHandover::where('handover_number', 'PH-1001')->firstOrFail();

        $this->actingAs($finance)
            ->patchJson(route('possession.handovers.complete', $handover), [
                'actual_handover_on' => now()->toDateString(),
                'possession_letter_reference' => 'PL-TEST-001',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('handover');

        CollectionReceipt::create([
            'company_id' => $handover->company_id,
            'project_id' => $handover->project_id,
            'booking_id' => $handover->booking_id,
            'booking_payment_schedule_id' => null,
            'customer_id' => $handover->customer_id,
            'collected_by_user_id' => $sales->id,
            'approved_by_user_id' => $finance->id,
            'receipt_number' => 'RCPT-HANDOVER-CLEAR',
            'status' => 'approved',
            'receipt_date' => now()->toDateString(),
            'payment_mode' => 'neft',
            'instrument_number' => 'NEFT-HANDOVER-CLEAR',
            'bank_name' => 'Demo Bank',
            'amount' => 13627000,
            'tax_deducted_amount' => 0,
            'notes' => 'Final clearance for handover test.',
            'metadata' => ['source' => 'test'],
            'approved_at' => now(),
        ]);

        $this->actingAs($sales)
            ->patchJson(route('possession.handovers.checklist.update', $handover), [
                'checklist' => $this->completedChecklist(),
            ])
            ->assertOk()
            ->assertJsonPath('data.financial_outstanding', 0)
            ->assertJsonPath('data.status', 'blocked');

        $snag = HandoverSnag::where('snag_number', 'SNAG-1001')->firstOrFail();

        $this->actingAs($construction)
            ->patchJson(route('possession.snags.resolve', $snag), [
                'resolution_notes' => 'Paint touch-up completed and verified.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'resolved');

        $this->actingAs($finance)
            ->patchJson(route('possession.handovers.complete', $handover), [
                'actual_handover_on' => now()->toDateString(),
                'possession_letter_reference' => 'PL-TEST-001',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('possession_letter_reference');

        $this->actingAs($sales)
            ->patchJson(route('possession.handovers.letter.issue', $handover), [
                'possession_letter_reference' => 'PL-TEST-001',
            ])
            ->assertOk()
            ->assertJsonPath('data.possession_letter_reference', 'PL-TEST-001');

        $this->actingAs($finance)
            ->patchJson(route('possession.handovers.complete', $handover), [
                'actual_handover_on' => now()->toDateString(),
                'possession_letter_reference' => 'PL-MISMATCH',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('possession_letter_reference');

        $this->actingAs($finance)
            ->patchJson(route('possession.handovers.complete', $handover), [
                'actual_handover_on' => now()->toDateString(),
                'possession_letter_reference' => 'PL-TEST-001',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.possession_letter_reference', 'PL-TEST-001')
            ->assertJsonPath('data.unit.status', 'handed_over');

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'possession.handover.completed',
            'user_id' => $finance->id,
        ]);
    }

    public function test_snag_reporting_blocks_ready_handover_until_resolved(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $booking = $this->createPaidBooking($sales);

        $handoverNumber = $this->actingAs($sales)
            ->postJson(route('possession.handovers.store'), [
                'booking_id' => $booking->id,
                'checklist' => $this->completedChecklist(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'ready')
            ->json('data.handover_number');

        $handover = PossessionHandover::where('handover_number', $handoverNumber)->firstOrFail();

        $snagNumber = $this->actingAs($sales)
            ->postJson(route('possession.snags.store'), [
                'possession_handover_id' => $handover->id,
                'area' => 'Bedroom',
                'category' => 'Electrical',
                'severity' => 'high',
                'description' => 'Switch plate alignment issue.',
                'target_resolution_on' => now()->addDays(2)->toDateString(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'open')
            ->json('data.snag_number');

        $this->assertDatabaseHas('possession_handovers', [
            'id' => $handover->id,
            'status' => 'blocked',
        ]);

        $snag = HandoverSnag::where('snag_number', $snagNumber)->firstOrFail();

        $this->actingAs($construction)
            ->patchJson(route('possession.snags.resolve', $snag), [
                'resolution_notes' => 'Switch plate aligned.',
            ])
            ->assertOk();

        $this->assertDatabaseHas('possession_handovers', [
            'id' => $handover->id,
            'status' => 'ready',
        ]);
    }

    public function test_duplicate_handover_and_partner_access_are_rejected(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();
        $booking = Booking::where('booking_code', 'BK-1001')->firstOrFail();
        $handover = PossessionHandover::where('handover_number', 'PH-1001')->firstOrFail();
        $snag = HandoverSnag::where('snag_number', 'SNAG-1001')->firstOrFail();

        $this->actingAs($sales)
            ->postJson(route('possession.handovers.store'), [
                'booking_id' => $booking->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('booking_id');

        $this->actingAs($partner)
            ->getJson(route('possession.handovers.index'))
            ->assertForbidden();

        $this->actingAs($partner)
            ->postJson(route('possession.snags.store'), [])
            ->assertForbidden();

        $this->actingAs($partner)
            ->patchJson(route('possession.handovers.checklist.update', $handover), [
                'checklist' => $this->completedChecklist(),
            ])
            ->assertForbidden();

        $this->actingAs($partner)
            ->patchJson(route('possession.handovers.letter.issue', $handover), [
                'possession_letter_reference' => 'PL-PARTNER-DENIED',
            ])
            ->assertForbidden();

        $this->actingAs($partner)
            ->patchJson(route('possession.handovers.complete', $handover), [
                'actual_handover_on' => now()->toDateString(),
                'possession_letter_reference' => 'PL-PARTNER-DENIED',
            ])
            ->assertForbidden();

        $this->actingAs($partner)
            ->patchJson(route('possession.snags.resolve', $snag), [
                'resolution_notes' => 'Partner must not resolve internal handover snags.',
            ])
            ->assertForbidden();
    }

    private function createPaidBooking(User $sales): Booking
    {
        $unit = ProjectUnit::where('unit_code', 'GRN-B-0802')->firstOrFail();
        $customer = Customer::where('code', 'CUS-1002')->firstOrFail();
        $lead = Lead::where('lead_code', 'LD-1002')->firstOrFail();

        $booking = Booking::create([
            'company_id' => $unit->company_id,
            'project_id' => $unit->project_id,
            'project_unit_id' => $unit->id,
            'customer_id' => $customer->id,
            'lead_id' => $lead->id,
            'partner_id' => $lead->partner_id,
            'booked_by_user_id' => $sales->id,
            'booking_code' => 'BK-HANDOVER-TEST',
            'status' => 'confirmed',
            'booked_on' => now()->subDays(30)->toDateString(),
            'agreement_value' => 100000,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'net_receivable' => 100000,
            'booking_amount' => 100000,
            'commercials' => ['source' => 'test'],
        ]);

        CollectionReceipt::create([
            'company_id' => $booking->company_id,
            'project_id' => $booking->project_id,
            'booking_id' => $booking->id,
            'booking_payment_schedule_id' => null,
            'customer_id' => $booking->customer_id,
            'collected_by_user_id' => $sales->id,
            'approved_by_user_id' => User::where('email', 'suresh.iyer@builder360.test')->firstOrFail()->id,
            'receipt_number' => 'RCPT-HANDOVER-TEST',
            'status' => 'approved',
            'receipt_date' => now()->subDays(5)->toDateString(),
            'payment_mode' => 'neft',
            'instrument_number' => 'NEFT-HANDOVER-TEST',
            'bank_name' => 'Demo Bank',
            'amount' => 100000,
            'tax_deducted_amount' => 0,
            'notes' => 'Paid booking for handover test.',
            'metadata' => ['source' => 'test'],
            'approved_at' => now()->subDays(4),
        ]);

        return $booking;
    }

    private function createExternalCompanyHandover(): PossessionHandover
    {
        $company = Company::create([
            'code' => 'EXTPOS',
            'name' => 'External Possession Co',
            'legal_name' => 'External Possession Co Private Limited',
            'state' => 'MH',
            'status' => 'active',
        ]);
        $project = Project::create([
            'company_id' => $company->id,
            'code' => 'EXT-POS',
            'name' => 'External Possession Project',
            'project_type' => 'residential',
            'city' => 'Mumbai',
            'state' => 'MH',
            'status' => 'active',
            'budget_amount' => 5000000,
            'target_roi_percent' => 12,
        ]);
        $unit = ProjectUnit::create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'unit_code' => 'EXT-POS-101',
            'tower' => 'A',
            'floor' => '1',
            'unit_number' => '101',
            'unit_type' => '2BHK',
            'carpet_area_sqft' => 800,
            'saleable_area_sqft' => 1000,
            'base_rate' => 8000,
            'base_price' => 8000000,
            'floor_rise' => 0,
            'parking_charges' => 0,
            'other_charges' => 0,
            'tax_amount' => 0,
            'total_price' => 8000000,
            'status' => 'booked',
        ]);
        $customer = Customer::where('code', 'CUS-1002')->firstOrFail();
        $booking = Booking::create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'project_unit_id' => $unit->id,
            'customer_id' => $customer->id,
            'lead_id' => null,
            'partner_id' => null,
            'booked_by_user_id' => null,
            'booking_code' => 'BK-EXT-POS',
            'status' => 'confirmed',
            'booked_on' => now()->subDays(10)->toDateString(),
            'agreement_value' => 8000000,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'net_receivable' => 8000000,
            'booking_amount' => 8000000,
            'commercials' => ['source' => 'scope-test'],
        ]);

        return PossessionHandover::create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'booking_id' => $booking->id,
            'customer_id' => $customer->id,
            'project_unit_id' => $unit->id,
            'initiated_by_user_id' => null,
            'completed_by_user_id' => null,
            'handover_number' => 'PH-EXT-1001',
            'target_handover_on' => null,
            'actual_handover_on' => null,
            'status' => 'blocked',
            'financial_outstanding' => 8000000,
            'checklist' => $this->completedChecklist(),
            'blockers' => [['code' => 'financial_outstanding']],
            'possession_letter_reference' => null,
            'workflow_history' => [['status' => 'initiated', 'actor' => 'Scope Test', 'note' => 'External handover', 'at' => now()->toISOString()]],
            'completed_at' => null,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function completedChecklist(): array
    {
        return [
            ['code' => 'final_payment_clearance', 'label' => 'Final payment clearance', 'required' => true, 'completed' => true],
            ['code' => 'documents_verified', 'label' => 'Customer and booking documents verified', 'required' => true, 'completed' => true],
            ['code' => 'unit_inspection_done', 'label' => 'Unit inspection completed', 'required' => true, 'completed' => true],
            ['code' => 'keys_ready', 'label' => 'Keys and access cards ready', 'required' => true, 'completed' => true],
        ];
    }
}
