<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\CommonAreaHandoverItem;
use App\Models\MaintenanceDue;
use App\Models\Project;
use App\Models\SocietyFormation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceSocietyWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_manage_society_handover_and_maintenance_dues(): void
    {
        $this->seed();

        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();
        $booking = Booking::where('booking_code', 'BK-1001')->firstOrFail();

        $this->actingAs($director)
            ->getJson(route('maintenance.societies.index', ['project_id' => $project->id]))
            ->assertOk()
            ->assertJsonPath('data.0.formation_number', 'SOC-1001');

        $createdFormationId = $this->actingAs($director)
            ->postJson(route('maintenance.societies.store'), [
                'project_id' => $project->id,
                'society_name' => 'Skyline Residency Phase 2 Association',
                'association_type' => 'apartment_association',
                'total_units' => 120,
                'occupied_units' => 76,
                'status' => 'application_filed',
                'progress_percent' => 35,
                'current_stage' => 'Application filed',
                'next_step' => 'Committee nomination pending',
            ])
            ->assertCreated()
            ->assertJsonPath('data.society_name', 'Skyline Residency Phase 2 Association')
            ->json('data.id');

        $formation = SocietyFormation::findOrFail($createdFormationId);

        $this->actingAs($director)
            ->patchJson(route('maintenance.societies.status', $formation), [
                'status' => 'formed',
                'progress_percent' => 90,
                'current_stage' => 'Registered',
                'next_step' => 'Handover pack sign-off',
                'registration_number' => 'PNA/SOC/2026/9001',
                'note' => 'Registrar certificate received.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'formed')
            ->assertJsonPath('data.registration_number', 'PNA/SOC/2026/9001');

        $handoverItem = CommonAreaHandoverItem::where('item_number', 'CAH-1002')->firstOrFail();

        $this->actingAs($director)
            ->patchJson(route('maintenance.handover-items.update', $handoverItem), [
                'checklist_completed' => $handoverItem->checklist_total,
                'status' => 'complete',
                'note' => 'All fire safety documents verified.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'complete');

        $handoverItem->refresh();

        $this->actingAs($director)
            ->patchJson(route('maintenance.handover-items.sign-off', $handoverItem), [
                'note' => 'Signed off after facility inspection.',
            ])
            ->assertOk()
            ->assertJsonPath('data.signed_off_by.name', $director->name);

        $periodStart = now()->addQuarter()->startOfQuarter()->toDateString();
        $periodEnd = now()->addQuarter()->startOfQuarter()->addMonths(2)->endOfMonth()->toDateString();

        $createdDueId = $this->actingAs($director)
            ->postJson(route('maintenance.dues.store'), [
                'booking_id' => $booking->id,
                'period_start_on' => $periodStart,
                'period_end_on' => $periodEnd,
                'due_on' => now()->addQuarter()->startOfQuarter()->addDays(15)->toDateString(),
                'amount' => 18600,
            ])
            ->assertCreated()
            ->assertJsonPath('data.amount', 18600)
            ->assertJsonPath('data.balance_amount', 18600)
            ->json('data.id');

        $due = MaintenanceDue::findOrFail($createdDueId);

        $this->actingAs($director)
            ->patchJson(route('maintenance.dues.remind', $due), [
                'note' => 'Reminder sent before due date.',
            ])
            ->assertOk()
            ->assertJsonPath('data.due_number', $due->due_number);

        $this->actingAs($director)
            ->patchJson(route('maintenance.dues.mark-paid', $due), [
                'paid_amount' => 18600,
                'payment_reference' => 'UTR-MNT-9001',
                'note' => 'Bank receipt reconciled.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.balance_amount', 0);

        $this->assertDatabaseHas('audit_events', ['event_type' => 'maintenance.society_formation.created']);
        $this->assertDatabaseHas('audit_events', ['event_type' => 'maintenance.common_area_handover.signed_off']);
        $this->assertDatabaseHas('audit_events', ['event_type' => 'maintenance.due.payment_recorded']);
        $this->assertGreaterThanOrEqual(3, AuditEvent::where('event_type', 'like', 'maintenance.%')->count());
    }

    public function test_authorized_user_can_use_native_blade_society_workspace(): void
    {
        $this->seed();

        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();

        $this->actingAs($director)
            ->get(route('maintenance.societies.index'))
            ->assertOk()
            ->assertSee('Workspace for society or association formation')
            ->assertSee('name="society_name"', false)
            ->assertSee('SOC-1001')
            ->assertDontSee('window.Builder360Server', false)
            ->assertDontSee('id="root"', false);

        $this->actingAs($director)
            ->post(route('maintenance.societies.store'), [
                'project_id' => $project->id,
                'society_name' => 'Blade Skyline Association',
                'association_type' => 'apartment_association',
                'total_units' => 120,
                'occupied_units' => 80,
                'status' => 'application_filed',
                'progress_percent' => 35,
                'current_stage' => 'Application filed',
                'next_step' => 'Committee nomination pending',
            ])
            ->assertRedirect(route('maintenance.societies.index'))
            ->assertSessionHas('status');

        $formation = SocietyFormation::where('society_name', 'Blade Skyline Association')->firstOrFail();

        $this->assertSame('application_filed', $formation->status);
        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'maintenance.society_formation.created',
            'auditable_id' => $formation->id,
            'user_id' => $director->id,
        ]);

        $this->actingAs($director)
            ->patch(route('maintenance.societies.status', $formation), [
                'status' => 'formed',
                'progress_percent' => 90,
                'current_stage' => 'Registered',
                'next_step' => 'Handover pack sign-off',
                'registration_number' => 'PNA/BLADE/2026/001',
                'note' => 'Registered from Blade workspace.',
            ])
            ->assertRedirect(route('maintenance.societies.index'))
            ->assertSessionHas('status');

        $formation->refresh();

        $this->assertSame('formed', $formation->status);
        $this->assertSame('PNA/BLADE/2026/001', $formation->registration_number);
    }

    public function test_authorized_user_can_use_native_blade_common_area_handover_workspace(): void
    {
        $this->seed();

        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $handoverItem = CommonAreaHandoverItem::where('item_number', 'CAH-1002')->firstOrFail();

        $this->actingAs($director)
            ->get(route('maintenance.handover-items.index'))
            ->assertOk()
            ->assertSee('Workspace for common-area facility checklist progress')
            ->assertSee('CAH-1002')
            ->assertDontSee('window.Builder360Server', false)
            ->assertDontSee('id="root"', false);

        $this->actingAs($director)
            ->patch(route('maintenance.handover-items.update', $handoverItem), [
                'checklist_completed' => $handoverItem->checklist_total,
                'status' => 'complete',
                'note' => 'Completed from Blade workspace.',
            ])
            ->assertRedirect(route('maintenance.handover-items.index'))
            ->assertSessionHas('status');

        $handoverItem->refresh();

        $this->assertSame('complete', $handoverItem->status);

        $this->actingAs($director)
            ->patch(route('maintenance.handover-items.sign-off', $handoverItem), [
                'note' => 'Signed off from Blade workspace.',
            ])
            ->assertRedirect(route('maintenance.handover-items.index'))
            ->assertSessionHas('status');

        $handoverItem->refresh();

        $this->assertSame($director->id, $handoverItem->signed_off_by_user_id);
        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'maintenance.common_area_handover.signed_off',
            'auditable_id' => $handoverItem->id,
            'user_id' => $director->id,
        ]);
    }

    public function test_authorized_user_can_use_native_blade_maintenance_dues_workspace(): void
    {
        $this->seed();

        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $booking = Booking::where('booking_code', 'BK-1001')->firstOrFail();
        $periodStart = now()->addYear()->startOfMonth()->toDateString();
        $periodEnd = now()->addYear()->endOfMonth()->toDateString();

        $this->actingAs($director)
            ->get(route('maintenance.dues.index'))
            ->assertOk()
            ->assertSee('Workspace for raising unit-wise maintenance dues')
            ->assertSee('name="booking_id"', false)
            ->assertSee('MDU-1001')
            ->assertDontSee('window.Builder360Server', false)
            ->assertDontSee('id="root"', false);

        $this->actingAs($director)
            ->post(route('maintenance.dues.store'), [
                'booking_id' => $booking->id,
                'period_start_on' => $periodStart,
                'period_end_on' => $periodEnd,
                'due_on' => now()->addYear()->startOfMonth()->addDays(10)->toDateString(),
                'amount' => 12500,
            ])
            ->assertRedirect(route('maintenance.dues.index'))
            ->assertSessionHas('status');

        $due = MaintenanceDue::where('amount', 12500)->where('booking_id', $booking->id)->firstOrFail();

        $this->assertSame('due', $due->status);
        $this->assertSame('12500.00', $due->balance_amount);

        $this->actingAs($director)
            ->patch(route('maintenance.dues.remind', $due), [
                'note' => 'Reminder from Blade workspace.',
            ])
            ->assertRedirect(route('maintenance.dues.index'))
            ->assertSessionHas('status');

        $this->assertNotNull($due->fresh()->last_reminded_at);

        $this->actingAs($director)
            ->patch(route('maintenance.dues.mark-paid', $due), [
                'paid_amount' => 12500,
                'payment_reference' => 'UTR-BLADE-MNT-001',
                'paid_at' => now()->toDateString(),
                'note' => 'Payment from Blade workspace.',
            ])
            ->assertRedirect(route('maintenance.dues.index'))
            ->assertSessionHas('status');

        $due->refresh();

        $this->assertSame('paid', $due->status);
        $this->assertSame('0.00', $due->balance_amount);
        $this->assertSame('UTR-BLADE-MNT-001', $due->payment_reference);
    }

    public function test_partner_is_denied_maintenance_society_workspace(): void
    {
        $this->seed();

        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();
        $due = MaintenanceDue::where('due_number', 'MDU-1001')->firstOrFail();

        $this->actingAs($partner)
            ->getJson(route('maintenance.societies.index'))
            ->assertForbidden();

        $this->actingAs($partner)
            ->patchJson(route('maintenance.dues.remind', $due), ['note' => 'Not allowed'])
            ->assertForbidden();
    }
}
