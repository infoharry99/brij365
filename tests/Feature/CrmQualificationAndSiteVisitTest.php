<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\LeadQualification;
use App\Models\SiteVisit;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Builder360\Builder360Bootstrap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmQualificationAndSiteVisitTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_user_can_list_seeded_qualifications_and_site_visits(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();

        $this->actingAs($sales)
            ->getJson(route('crm.lead-qualifications.index', [
                'status' => 'qualified',
                'min_score' => 80,
            ]))
            ->assertOk()
            ->assertJsonPath('data.0.qualification_number', 'LQ-10001')
            ->assertJsonPath('data.0.score', 82);

        $this->actingAs($sales)
            ->getJson(route('crm.site-visits.index', [
                'status' => 'scheduled',
                'visit_mode' => 'site',
            ]))
            ->assertOk()
            ->assertJsonPath('data.0.visit_number', 'SV-10001')
            ->assertJsonPath('data.0.customer.name', 'Neha Patil');
    }

    public function test_lead_qualification_browser_route_renders_native_blade_workspace(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();

        $this->actingAs($sales)
            ->get(route('crm.lead-qualifications.index'))
            ->assertOk()
            ->assertSee('Lead Qualification')
            ->assertSee('Workspace')
            ->assertSee('Quality score configuration')
            ->assertSee('Run lead qualification')
            ->assertSee('Qualification records')
            ->assertSee('Budget Fit')
            ->assertSee('Decision Authority')
            ->assertSee('Requirement Clarity')
            ->assertSee('Purchase Timeline')
            ->assertSee('Hot Lead')
            ->assertSee('name="quality_conditions[budget]"', false)
            ->assertSee('name="quality_conditions[authority]"', false)
            ->assertSee('name="decision_notes"', false)
            ->assertSee('LQ-10001')
            ->assertDontSee('window.Builder360Server', false)
            ->assertDontSee('id="root"', false);
    }

    public function test_native_blade_lead_qualification_form_posts_to_governed_workflow(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $lead = Lead::where('lead_code', 'LD-1003')->firstOrFail();

        $this->actingAs($sales)
            ->from(route('crm.lead-qualifications.index'))
            ->post(route('crm.lead-qualifications.store'), [
                'lead_id' => $lead->id,
                'status' => 'qualified',
                'quality_conditions' => [
                    'budget' => 'confirmed_fit',
                    'authority' => 'decision_maker',
                    'need' => 'project_unit_fit',
                    'timeline' => 'within_90_days',
                ],
                'preferred_configuration' => '3BHK',
                'verified_budget_min' => 12000000,
                'verified_budget_max' => 15000000,
                'expected_booking_date' => now()->addDays(21)->toDateString(),
                'decision_notes' => 'Native Blade qualification form submitted with configured lead quality conditions.',
            ])
            ->assertRedirect(route('crm.lead-qualifications.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('lead_qualifications', [
            'lead_id' => $lead->id,
            'status' => 'qualified',
            'score' => 92,
            'preferred_configuration' => '3BHK',
        ]);

        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'stage' => 'Qualified',
            'status' => 'open',
        ]);
    }

    public function test_site_visit_browser_route_renders_native_blade_workspace(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();

        $this->actingAs($sales)
            ->get(route('crm.site-visits.index'))
            ->assertOk()
            ->assertSee('Site Visits')
            ->assertSee('Workspace')
            ->assertSee('Schedule site visit')
            ->assertSee('Visit filters')
            ->assertSee('Site visit calendar/list')
            ->assertSee('name="lead_id"', false)
            ->assertSee('name="scheduled_at"', false)
            ->assertSee('name="visit_mode"', false)
            ->assertSee('SV-10001')
            ->assertSee('Lead Management')
            ->assertDontSee('window.Builder360Server', false)
            ->assertDontSee('id="root"', false);
    }

    public function test_native_blade_site_visit_form_schedules_visit_with_notification_and_redirect(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $lead = Lead::where('lead_code', 'LD-1003')->firstOrFail();

        $this->actingAs($sales)
            ->from(route('crm.site-visits.index'))
            ->post(route('crm.site-visits.store'), [
                'lead_id' => $lead->id,
                'assigned_to_user_id' => $construction->id,
                'scheduled_at' => now()->addDays(15)->setTime(10, 30)->format('Y-m-d\TH:i'),
                'duration_minutes' => 60,
                'visit_mode' => 'site',
                'meeting_location' => 'Metro One Towers Site Office',
                'agenda' => 'Native Blade site visit scheduling workflow.',
                'attendees' => [
                    ['name' => 'Arvind Jain', 'phone' => '+91 98111 10003', 'role' => 'Buyer'],
                ],
            ])
            ->assertRedirect(route('crm.site-visits.index'))
            ->assertSessionHas('status');

        $visit = SiteVisit::where('lead_id', $lead->id)
            ->where('meeting_location', 'Metro One Towers Site Office')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('scheduled', $visit->status);
        $this->assertSame($construction->id, $visit->assigned_to_user_id);

        $this->assertDatabaseHas('user_notifications', [
            'recipient_user_id' => $construction->id,
            'triggered_by_user_id' => $sales->id,
            'category' => 'crm',
            'notifiable_type' => SiteVisit::class,
            'notifiable_id' => $visit->id,
        ]);
    }

    public function test_native_blade_site_visit_complete_and_cancel_forms_redirect_and_persist(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $lead = Lead::where('lead_code', 'LD-1003')->firstOrFail();
        $visit = SiteVisit::where('visit_number', 'SV-10001')->firstOrFail();

        $this->actingAs($sales)
            ->post(route('crm.site-visits.store'), [
                'lead_id' => $lead->id,
                'assigned_to_user_id' => $construction->id,
                'scheduled_at' => now()->addDays(18)->setTime(16, 0)->format('Y-m-d\TH:i'),
                'duration_minutes' => 60,
                'visit_mode' => 'office',
                'meeting_location' => 'Cancellation Test Sales Gallery',
                'agenda' => 'Visit created from Blade flow for cancellation test.',
            ])
            ->assertRedirect(route('crm.site-visits.index'));

        $cancelVisit = SiteVisit::where('meeting_location', 'Cancellation Test Sales Gallery')->latest('id')->firstOrFail();

        $this->actingAs($sales)
            ->from(route('crm.site-visits.index'))
            ->patch(route('crm.site-visits.complete', $visit), [
                'outcome' => 'booking_expected',
                'outcome_notes' => 'Native Blade completion captured customer booking interest.',
                'next_follow_up_at' => now()->addDays(4)->setTime(11, 30)->format('Y-m-d\TH:i'),
            ])
            ->assertRedirect(route('crm.site-visits.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('site_visits', [
            'id' => $visit->id,
            'status' => 'completed',
            'outcome' => 'booking_expected',
        ]);

        $this->actingAs($sales)
            ->from(route('crm.site-visits.index'))
            ->patch(route('crm.site-visits.cancel', $cancelVisit), [
                'reason' => 'Native Blade cancellation after customer requested new timing.',
            ])
            ->assertRedirect(route('crm.site-visits.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('site_visits', [
            'id' => $cancelVisit->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_crm_engagement_indexes_validate_filter_contracts(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();

        $this->actingAs($sales)
            ->getJson(route('crm.lead-qualifications.index', ['page' => 1]))
            ->assertOk()
            ->assertJsonPath('meta.current_page', 1);

        $this->actingAs($sales)
            ->getJson(route('crm.lead-qualifications.index', ['project_id' => 1]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id'])
            ->assertJsonPath('errors.project_id.0', 'The selected filter is not available for this endpoint.');

        $this->actingAs($sales)
            ->getJson(route('crm.lead-qualifications.index', ['status' => 'invalid']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->actingAs($sales)
            ->getJson(route('crm.site-visits.index', ['page' => 1]))
            ->assertOk()
            ->assertJsonPath('meta.current_page', 1);

        $this->actingAs($sales)
            ->getJson(route('crm.site-visits.index', ['score' => 80]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['score'])
            ->assertJsonPath('errors.score.0', 'The selected filter is not available for this endpoint.');

        $this->actingAs($sales)
            ->getJson(route('crm.site-visits.index', ['status' => 'invalid']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_lead_qualification_updates_lead_stage_budget_and_audit(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $lead = Lead::where('lead_code', 'LD-1003')->firstOrFail();

        $qualificationNumber = $this->actingAs($sales)
            ->postJson(route('crm.lead-qualifications.store'), [
                'lead_id' => $lead->id,
                'status' => 'nurture',
                'budget_score' => 15,
                'authority_score' => 15,
                'need_score' => 17,
                'timeline_score' => 10,
                'preferred_configuration' => '4BHK',
                'verified_budget_min' => 16000000,
                'verified_budget_max' => 19000000,
                'expected_booking_date' => now()->addDays(45)->toDateString(),
                'decision_notes' => 'Lead has budget and need but decision timeline requires nurturing.',
                'requirements' => [
                    'configuration' => '4BHK',
                    'purpose' => 'investment',
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'nurture')
            ->assertJsonPath('data.score', 57)
            ->json('data.qualification_number');

        $this->assertDatabaseHas('lead_qualifications', [
            'qualification_number' => $qualificationNumber,
            'lead_id' => $lead->id,
            'score' => 57,
        ]);

        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'stage' => 'Nurture',
            'status' => 'open',
        ]);

        $qualification = LeadQualification::where('qualification_number', $qualificationNumber)->firstOrFail();

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'crm.lead.qualified',
            'auditable_type' => LeadQualification::class,
            'auditable_id' => $qualification->id,
        ]);
    }

    public function test_lead_qualification_can_use_configured_quality_conditions(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $lead = Lead::where('lead_code', 'LD-1003')->firstOrFail();

        $response = $this->actingAs($sales)
            ->postJson(route('crm.lead-qualifications.store'), [
                'lead_id' => $lead->id,
                'status' => 'qualified',
                'quality_conditions' => [
                    'budget' => 'confirmed_fit',
                    'authority' => 'decision_maker',
                    'need' => 'project_unit_fit',
                    'timeline' => 'within_90_days',
                ],
                'decision_notes' => 'Customer meets configured hot-lead conditions and should be routed to sales.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'qualified')
            ->assertJsonPath('data.score', 92)
            ->assertJsonPath('data.budget_score', 25)
            ->assertJsonPath('data.authority_score', 25)
            ->assertJsonPath('data.need_score', 21)
            ->assertJsonPath('data.timeline_score', 21)
            ->assertJsonPath('data.quality_score.band.label', 'Hot Lead')
            ->assertJsonPath('data.quality_score.rules.source', 'application_default');

        $qualification = LeadQualification::where('qualification_number', $response->json('data.qualification_number'))->firstOrFail();

        $this->assertSame('Hot Lead', $qualification->metadata['quality_score']['band']['label']);
        $this->assertSame('confirmed_fit', $qualification->metadata['quality_score']['selected_conditions']['budget']);
    }

    public function test_crm_bootstrap_exposes_quality_score_rule_builder_contract(): void
    {
        $this->seed();

        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $bootstrap = app(Builder360Bootstrap::class)->forUser($director);

        $this->assertIsArray($bootstrap['crm_qualification_options']);
        $this->assertTrue($bootstrap['crm_qualification_options']['can_qualify']);
        $this->assertArrayHasKey('can_manage_scoring', $bootstrap['crm_qualification_options']);
        $this->assertSame('/scoring', $bootstrap['crm_qualification_options']['scoring_url']);
        $this->assertSame('lead_quality', $bootstrap['crm_qualification_options']['rules']['rule_key']);
        $this->assertArrayHasKey('budget', $bootstrap['crm_qualification_options']['rules']['criteria']);
        $this->assertArrayHasKey('authority', $bootstrap['crm_qualification_options']['rules']['criteria']);
        $this->assertArrayHasKey('need', $bootstrap['crm_qualification_options']['rules']['criteria']);
        $this->assertArrayHasKey('timeline', $bootstrap['crm_qualification_options']['rules']['criteria']);
    }

    public function test_lead_qualification_uses_active_system_setting_and_rejects_invalid_conditions(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $approver = User::whereKeyNot($sales->id)->firstOrFail();
        $lead = Lead::where('lead_code', 'LD-1003')->firstOrFail();

        SystemSetting::create([
            'company_id' => $lead->company_id,
            'created_by_user_id' => $approver->id,
            'approved_by_user_id' => $approver->id,
            'scope_key' => 'company:'.$lead->company_id,
            'setting_group' => 'CRM',
            'setting_key' => 'crm.lead_quality_score.rules',
            'label' => 'CRM Lead Quality Rules',
            'value_type' => 'json',
            'value' => [
                'criteria' => [
                    'budget' => ['label' => 'Budget', 'max_points' => 40, 'options' => [['value' => 'fit', 'label' => 'Budget fit', 'points' => 40]]],
                    'authority' => ['label' => 'Authority', 'max_points' => 20, 'options' => [['value' => 'owner', 'label' => 'Owner decision', 'points' => 20]]],
                    'need' => ['label' => 'Need', 'max_points' => 20, 'options' => [['value' => 'clear', 'label' => 'Clear need', 'points' => 20]]],
                    'timeline' => ['label' => 'Timeline', 'max_points' => 20, 'options' => [['value' => 'urgent', 'label' => 'Urgent', 'points' => 20]]],
                    'site_visit_readiness' => ['label' => 'Site Visit Readiness', 'max_points' => 20, 'options' => [['value' => 'visit_ready', 'label' => 'Ready for site visit', 'points' => 20]]],
                ],
                'bands' => [
                    ['label' => 'Priority Lead', 'min_score' => 80, 'status_hint' => 'qualified', 'tone' => 'green'],
                    ['label' => 'Review Lead', 'min_score' => 0, 'status_hint' => 'nurture', 'tone' => 'orange'],
                ],
            ],
            'status' => 'active',
            'version' => 3,
            'effective_from' => now()->subDay()->toDateString(),
            'approved_at' => now(),
            'workflow_history' => [],
            'metadata' => [],
        ]);

        $this->actingAs($sales)
            ->postJson(route('crm.lead-qualifications.store'), [
                'lead_id' => $lead->id,
                'status' => 'qualified',
                'quality_conditions' => [
                    'budget' => 'fit',
                    'authority' => 'owner',
                    'need' => 'clear',
                    'timeline' => 'urgent',
                    'site_visit_readiness' => 'visit_ready',
                ],
                'decision_notes' => 'Configured company rule should produce priority score.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.score', 100)
            ->assertJsonPath('data.budget_score', 40)
            ->assertJsonPath('data.quality_score.band.label', 'Priority Lead')
            ->assertJsonPath('data.quality_score.components.site_visit_readiness', 20)
            ->assertJsonPath('data.quality_score.labels.site_visit_readiness', 'Site Visit Readiness')
            ->assertJsonPath('data.quality_score.rules.source', 'system_setting')
            ->assertJsonPath('data.quality_score.rules.version', 3);

        $this->actingAs($sales)
            ->postJson(route('crm.lead-qualifications.store'), [
                'lead_id' => $lead->id,
                'status' => 'qualified',
                'quality_conditions' => [
                    'budget' => 'not-configured',
                    'authority' => 'owner',
                    'need' => 'clear',
                    'timeline' => 'urgent',
                    'site_visit_readiness' => 'visit_ready',
                ],
                'decision_notes' => 'Invalid condition must be rejected.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quality_conditions.budget']);

        $this->actingAs($sales)
            ->postJson(route('crm.lead-qualifications.store'), [
                'lead_id' => $lead->id,
                'status' => 'qualified',
                'quality_conditions' => [
                    'budget' => 'fit',
                    'authority' => 'owner',
                    'need' => 'clear',
                    'timeline' => 'urgent',
                ],
                'decision_notes' => 'Missing configured criterion must be rejected.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quality_conditions']);
    }

    public function test_lead_qualification_rejects_status_that_conflicts_with_score_band_routing(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $lead = Lead::where('lead_code', 'LD-1003')->firstOrFail();

        $lowScoreConditions = [
            'budget' => 'unverified',
            'authority' => 'unknown',
            'need' => 'vague',
            'timeline' => 'future',
        ];

        $this->actingAs($sales)
            ->postJson(route('crm.lead-qualifications.store'), [
                'lead_id' => $lead->id,
                'status' => 'qualified',
                'quality_conditions' => $lowScoreConditions,
                'decision_notes' => 'This should not be allowed because the score band routes to disqualified.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status'])
            ->assertJsonPath('errors.status.0', 'The submitted qualification status must be disqualified for the Disqualified Fit score band.');

        $this->actingAs($sales)
            ->postJson(route('crm.lead-qualifications.store'), [
                'lead_id' => $lead->id,
                'status' => 'disqualified',
                'quality_conditions' => $lowScoreConditions,
                'decision_notes' => 'The submitted status now follows the configured score band routing.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'disqualified')
            ->assertJsonPath('data.score', 10)
            ->assertJsonPath('data.quality_score.band.status_hint', 'disqualified');
    }

    public function test_site_visit_scheduling_notification_completion_and_conflict_validation(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $lead = Lead::where('lead_code', 'LD-1003')->firstOrFail();
        $scheduledAt = now()->addDays(9)->setTime(11, 0);

        $visitNumber = $this->actingAs($sales)
            ->postJson(route('crm.site-visits.store'), [
                'lead_id' => $lead->id,
                'assigned_to_user_id' => $construction->id,
                'scheduled_at' => $scheduledAt->toISOString(),
                'duration_minutes' => 60,
                'visit_mode' => 'site',
                'meeting_location' => 'Metro One Towers Site Office',
                'agenda' => 'Show sample commercial floor and review expected booking terms.',
                'attendees' => [
                    ['name' => 'Arvind Jain', 'phone' => '+91 98111 10003', 'role' => 'Buyer'],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'scheduled')
            ->assertJsonPath('data.assigned_to.email', 'rajesh.kulkarni@builder360.test')
            ->json('data.visit_number');

        $visit = SiteVisit::where('visit_number', $visitNumber)->firstOrFail();

        $this->assertDatabaseHas('user_notifications', [
            'recipient_user_id' => $construction->id,
            'triggered_by_user_id' => $sales->id,
            'category' => 'crm',
            'notifiable_type' => SiteVisit::class,
            'notifiable_id' => $visit->id,
        ]);

        $updatedAt = $scheduledAt->copy()->addDays(1)->setTime(14, 30);

        $this->actingAs($sales)
            ->patchJson(route('crm.site-visits.update', $visit), [
                'assigned_to_user_id' => $construction->id,
                'scheduled_at' => $updatedAt->toISOString(),
                'duration_minutes' => 90,
                'visit_mode' => 'office',
                'meeting_location' => 'Metro One Sales Gallery',
                'agenda' => 'Rescheduled discussion with updated commercial-unit shortlist.',
                'attendees' => [
                    ['name' => 'Arvind Jain', 'phone' => '+91 98111 10003', 'role' => 'Buyer'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'scheduled')
            ->assertJsonPath('data.visit_mode', 'office')
            ->assertJsonPath('data.duration_minutes', 90)
            ->assertJsonPath('data.meeting_location', 'Metro One Sales Gallery');

        $visit->refresh();

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'crm.site_visit.updated',
            'auditable_type' => SiteVisit::class,
            'auditable_id' => $visit->id,
        ]);

        $this->actingAs($sales)
            ->postJson(route('crm.site-visits.store'), [
                'lead_id' => $lead->id,
                'assigned_to_user_id' => $construction->id,
                'scheduled_at' => $updatedAt->copy()->addMinutes(15)->toISOString(),
                'duration_minutes' => 60,
                'visit_mode' => 'site',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['scheduled_at']);

        $this->actingAs($sales)
            ->patchJson(route('crm.site-visits.complete', $visit), [
                'outcome' => 'booking_expected',
                'outcome_notes' => 'Customer liked the inventory and requested booking-form draft.',
                'next_follow_up_at' => now()->addDays(2)->setTime(12, 0)->toISOString(),
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.outcome', 'booking_expected');

        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'stage' => 'Negotiation',
        ]);

        $this->actingAs($sales)
            ->patchJson(route('crm.site-visits.update', $visit), [
                'assigned_to_user_id' => $construction->id,
                'scheduled_at' => now()->addDays(20)->setTime(11, 0)->toISOString(),
                'duration_minutes' => 60,
                'visit_mode' => 'site',
            ])
            ->assertForbidden();
    }

    public function test_site_visit_cancellation_and_partner_restrictions(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();
        $visit = SiteVisit::where('visit_number', 'SV-10001')->firstOrFail();

        $this->actingAs($sales)
            ->patchJson(route('crm.site-visits.cancel', $visit), [
                'reason' => 'Customer requested reschedule due to travel.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'crm.site_visit.cancelled',
            'auditable_type' => SiteVisit::class,
            'auditable_id' => $visit->id,
        ]);

        $this->actingAs($partner)
            ->getJson(route('crm.lead-qualifications.index'))
            ->assertForbidden();

        $this->actingAs($partner)
            ->getJson(route('crm.site-visits.index'))
            ->assertForbidden();

        $this->actingAs($partner)
            ->patchJson(route('crm.site-visits.update', $visit), [
                'scheduled_at' => now()->addDays(10)->setTime(11, 0)->toISOString(),
                'duration_minutes' => 60,
                'visit_mode' => 'site',
            ])
            ->assertForbidden();
    }

    public function test_non_global_sales_users_without_company_assignment_fail_closed_for_engagements(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $lead = Lead::where('lead_code', 'LD-1003')->firstOrFail();
        $qualification = LeadQualification::where('qualification_number', 'LQ-10001')->firstOrFail();
        $visit = SiteVisit::where('visit_number', 'SV-10001')->firstOrFail();

        $sales->forceFill(['company_id' => null])->save();

        $this->actingAs($sales)
            ->getJson(route('crm.lead-qualifications.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($sales)
            ->getJson(route('crm.site-visits.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($sales)
            ->getJson(route('crm.lead-qualifications.index', ['lead_id' => $qualification->lead_id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lead_id');

        $this->actingAs($sales)
            ->getJson(route('crm.site-visits.index', [
                'lead_id' => $visit->lead_id,
                'project_id' => $visit->project_id,
                'assigned_to_user_id' => $visit->assigned_to_user_id,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lead_id', 'project_id', 'assigned_to_user_id']);

        $this->actingAs($sales)
            ->patchJson(route('crm.site-visits.update', $visit), [
                'assigned_to_user_id' => $construction->id,
                'scheduled_at' => now()->addDays(12)->setTime(13, 0)->toISOString(),
                'duration_minutes' => 60,
                'visit_mode' => 'site',
            ])
            ->assertForbidden();

        $this->actingAs($sales)
            ->postJson(route('crm.lead-qualifications.store'), [
                'lead_id' => $lead->id,
                'status' => 'qualified',
                'budget_score' => 20,
                'authority_score' => 20,
                'need_score' => 20,
                'timeline_score' => 20,
                'decision_notes' => 'Should fail closed without company assignment.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lead_id');

        $this->actingAs($sales)
            ->postJson(route('crm.site-visits.store'), [
                'lead_id' => $lead->id,
                'assigned_to_user_id' => $construction->id,
                'scheduled_at' => now()->addDays(12)->setTime(11, 0)->toISOString(),
                'duration_minutes' => 60,
                'visit_mode' => 'site',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lead_id', 'assigned_to_user_id']);

        $this->actingAs($sales)
            ->patchJson(route('crm.site-visits.complete', $visit), [
                'outcome' => 'interested',
                'outcome_notes' => 'Should fail closed.',
            ])
            ->assertForbidden();

        $this->actingAs($sales)
            ->patchJson(route('crm.site-visits.cancel', $visit), [
                'reason' => 'Should fail closed.',
            ])
            ->assertForbidden();
    }
}
