<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Partner;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class CrmLeadTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_user_can_list_company_scoped_crm_leads(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();

        $response = $this->actingAs($sales)->getJson(route('crm.leads.index'));

        $response
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['lead_code', 'stage', 'company', 'project', 'customer', 'partner'],
                ],
            ]);
    }

    public function test_sales_user_can_open_native_blade_lead_management_workspace(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();

        $this->actingAs($sales)
            ->get(route('crm.leads.index'))
            ->assertOk()
            ->assertSee('Lead Management')
            ->assertSee('aria-label="Primary navigation"', false)
            ->assertSee('class="b360-nav-link is-active"', false)
            ->assertSee('ERP · CRM')
            ->assertSee('Sales &amp; Booking', false)
            ->assertSee('Site Visits')
            ->assertSee('Lead Funnel Analytics')
            ->assertSee('Workspace')
            ->assertSee('Capture new lead')
            ->assertSee('Lead filters')
            ->assertSee('Lead master')
            ->assertSee('name="company_id"', false)
            ->assertSee('name="customer_name"', false)
            ->assertSee('name="source"', false)
            ->assertSee('name="expected_value"', false)
            ->assertSee('Lead Qualification')
            ->assertSee('LD-1001')
            ->assertDontSee('window.Builder360Server', false)
            ->assertDontSee('id="root"', false);
    }

    public function test_native_blade_lead_form_creates_lead_with_audit_and_redirect(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $company = Company::where('code', 'B360D')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();
        $partner = Partner::where('code', 'CP-001')->firstOrFail();

        $this->actingAs($sales)
            ->from(route('crm.leads.index'))
            ->post(route('crm.leads.store'), [
                'company_id' => $company->id,
                'project_id' => $project->id,
                'partner_id' => $partner->id,
                'customer_name' => 'Blade Lead Customer',
                'customer_email' => 'blade.lead.customer@example.test',
                'customer_phone' => '+91 98111 44444',
                'source' => 'Channel walk-in',
                'stage' => 'New',
                'status' => 'open',
                'expected_value' => 9800000,
                'budget_min' => 8500000,
                'budget_max' => 10200000,
                'follow_up_at' => now()->addDay()->format('Y-m-d\TH:i'),
            ])
            ->assertRedirect(route('crm.leads.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('customers', [
            'email' => 'blade.lead.customer@example.test',
            'name' => 'Blade Lead Customer',
        ]);

        $this->assertDatabaseHas('leads', [
            'company_id' => $company->id,
            'project_id' => $project->id,
            'partner_id' => $partner->id,
            'source' => 'Channel walk-in',
            'stage' => 'New',
            'status' => 'open',
            'expected_value' => 9800000,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'crm.lead.created',
            'action' => 'Created CRM lead',
            'user_id' => $sales->id,
        ]);
    }

    public function test_non_global_crm_user_without_company_assignment_fails_closed(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $company = Company::where('code', 'B360D')->firstOrFail();
        $project = Project::where('company_id', $company->id)->firstOrFail();
        $lead = Lead::where('company_id', $company->id)->firstOrFail();

        $sales->forceFill(['company_id' => null])->save();

        $this->actingAs($sales)
            ->getJson(route('crm.leads.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($sales)
            ->getJson(route('crm.leads.index', ['project_id' => $project->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id']);

        $this->actingAs($sales)
            ->patchJson(route('crm.leads.stage.update', $lead), [
                'stage' => 'Negotiation',
                'status' => 'open',
            ])
            ->assertForbidden();

        $this->actingAs($sales)
            ->postJson(route('crm.leads.store'), [
                'company_id' => $company->id,
                'project_id' => $project->id,
                'customer_name' => 'Fail Closed Lead',
                'customer_email' => 'fail.closed.lead@example.test',
                'source' => 'Referral',
                'stage' => 'New',
                'expected_value' => 1000000,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['company_id']);
    }

    public function test_partner_user_only_sees_partner_scoped_leads(): void
    {
        $this->seed();

        $channelPartner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();
        $broker = User::where('email', 'farhan.shaikh@partners.builder360.test')->firstOrFail();

        $this->actingAs($channelPartner)
            ->getJson(route('partner.leads.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 3);

        $this->actingAs($broker)
            ->getJson(route('partner.leads.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_partner_lead_index_validates_filters_and_keeps_partner_scope(): void
    {
        $this->seed();

        $channelPartner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();
        $skylineProject = Project::where('code', 'SKY-PUN')->firstOrFail();
        $otherCompany = Company::where('code', 'B360P')->firstOrFail();
        $otherProject = Project::create([
            'company_id' => $otherCompany->id,
            'branch_id' => null,
            'code' => 'PARTNER-NO-LEADS',
            'name' => 'Partner No Leads Project',
            'project_type' => 'residential',
            'city' => 'Pune',
            'state' => 'MH',
            'status' => 'active',
            'budget_amount' => 10000000,
            'target_roi_percent' => 12,
            'starts_on' => now()->startOfYear()->toDateString(),
            'ends_on' => now()->addYear()->toDateString(),
        ]);

        $this->actingAs($channelPartner)
            ->getJson(route('partner.leads.index', ['per_page' => 500]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);

        $this->actingAs($channelPartner)
            ->getJson(route('partner.leads.index', ['page' => 1]))
            ->assertOk()
            ->assertJsonPath('meta.current_page', 1);

        $this->actingAs($channelPartner)
            ->getJson(route('partner.leads.index', ['customer_id' => 1]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id'])
            ->assertJsonPath('errors.customer_id.0', 'The selected filter is not available for this endpoint.');

        $this->actingAs($channelPartner)
            ->getJson(route('partner.leads.index', ['status' => 'invalid']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->actingAs($channelPartner)
            ->getJson(route('partner.leads.index', ['project_id' => $otherProject->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id']);

        $this->actingAs($channelPartner)
            ->getJson(route('partner.leads.index', ['project_id' => $skylineProject->id, 'status' => 'won']))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.lead_code', 'LD-1001');
    }

    public function test_partner_cannot_access_internal_crm_lead_index_or_create_leads(): void
    {
        $this->seed();

        $channelPartner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();

        $this->actingAs($channelPartner)
            ->getJson(route('crm.leads.index'))
            ->assertForbidden();

        $this->actingAs($channelPartner)
            ->postJson(route('crm.leads.store'), [])
            ->assertForbidden();

        $lead = Lead::where('lead_code', 'LD-1001')->firstOrFail();

        $this->actingAs($channelPartner)
            ->patchJson(route('crm.leads.stage.update', $lead), [
                'stage' => 'Negotiation',
                'status' => 'open',
            ])
            ->assertForbidden();
    }

    public function test_lead_index_validates_filters_and_project_scope(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $otherCompany = Company::where('code', 'B360P')->firstOrFail();
        $otherProject = Project::where('company_id', $otherCompany->id)->firstOrFail();

        $this->actingAs($sales)
            ->getJson(route('crm.leads.index', ['per_page' => 500]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);

        $this->actingAs($sales)
            ->getJson(route('crm.leads.index', ['page' => 1]))
            ->assertOk()
            ->assertJsonPath('meta.current_page', 1);

        $this->actingAs($sales)
            ->getJson(route('crm.leads.index', ['customer_id' => 1]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id'])
            ->assertJsonPath('errors.customer_id.0', 'The selected filter is not available for this endpoint.');

        $this->actingAs($sales)
            ->getJson(route('crm.leads.index', ['stage' => 'DROP TABLE']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['stage']);

        $this->actingAs($sales)
            ->getJson(route('crm.leads.index', ['project_id' => $otherProject->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id']);
    }

    public function test_lead_index_uses_configured_default_pagination_limit(): void
    {
        $this->seed();

        Config::set('builder360.pagination.default_max_per_page', 2);

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();

        $this->actingAs($sales)
            ->getJson(route('crm.leads.index', ['per_page' => 3]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);

        $this->actingAs($sales)
            ->getJson(route('crm.leads.index', ['per_page' => 2]))
            ->assertOk()
            ->assertJsonPath('meta.per_page', 2);
    }

    public function test_sales_user_can_create_lead_and_audit_event_is_recorded(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $company = Company::where('code', 'B360D')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();
        $partner = Partner::where('code', 'CP-001')->firstOrFail();

        $response = $this->actingAs($sales)->postJson(route('crm.leads.store'), [
            'company_id' => $company->id,
            'project_id' => $project->id,
            'partner_id' => $partner->id,
            'customer_name' => 'Amit Verma',
            'customer_email' => 'amit.verma@example.test',
            'customer_phone' => '+91 98111 22222',
            'source' => 'Channel walk-in',
            'stage' => 'New',
            'expected_value' => 11000000,
            'budget_min' => 9500000,
            'budget_max' => 12500000,
            'follow_up_at' => now()->addDay()->toISOString(),
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.company.id', $company->id)
            ->assertJsonPath('data.project.id', $project->id)
            ->assertJsonPath('data.partner.id', $partner->id)
            ->assertJsonPath('data.customer.name', 'Amit Verma')
            ->assertJsonPath('data.stage', 'New');

        $this->assertIsInt($response->json('data.customer.id'));

        $this->assertDatabaseHas('customers', [
            'email' => 'amit.verma@example.test',
            'name' => 'Amit Verma',
        ]);

        $this->assertDatabaseHas('leads', [
            'lead_code' => $response->json('data.lead_code'),
            'stage' => 'New',
            'expected_value' => 11000000,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'crm.lead.created',
            'action' => 'Created CRM lead',
            'user_id' => $sales->id,
        ]);
    }

    public function test_lead_creation_rejects_project_from_another_company(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $company = Company::where('code', 'B360P')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();

        $this->actingAs($sales)->postJson(route('crm.leads.store'), [
            'company_id' => $company->id,
            'project_id' => $project->id,
            'customer_name' => 'Invalid Project Customer',
            'customer_phone' => '+91 98111 33333',
            'source' => 'Referral',
            'stage' => 'New',
            'expected_value' => 5000000,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('project_id');
    }

    public function test_sales_user_can_update_lead_stage_and_audit_event_is_recorded(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $lead = Lead::where('lead_code', 'LD-1001')->firstOrFail();

        $this->actingAs($sales)->patchJson(route('crm.leads.stage.update', $lead), [
            'stage' => 'Negotiation',
            'status' => 'open',
        ])->assertOk()
            ->assertJsonPath('data.stage', 'Negotiation');

        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'stage' => 'Negotiation',
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'crm.lead.stage_updated',
            'action' => 'Updated CRM lead stage',
            'user_id' => $sales->id,
        ]);
    }

    public function test_sales_user_can_disposition_lead_and_activity_is_recorded(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $lead = Lead::where('lead_code', 'LD-1002')->firstOrFail();

        $response = $this->actingAs($sales)->patchJson(route('crm.leads.disposition.update', $lead), [
            'outcome' => 'lost',
            'reason' => 'Customer selected a competing project',
            'competitor_name' => 'Competing Realty',
            'note' => 'Budget and possession timeline did not match.',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.stage', 'Lost')
            ->assertJsonPath('data.status', 'lost')
            ->assertJsonPath('data.disposition.outcome', 'lost')
            ->assertJsonPath('data.disposition.reason', 'Customer selected a competing project');

        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'stage' => 'Lost',
            'status' => 'lost',
            'disposition_outcome' => 'lost',
            'disposition_reason' => 'Customer selected a competing project',
            'competitor_name' => 'Competing Realty',
            'dispositioned_by_user_id' => $sales->id,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'crm.lead.dispositioned',
            'action' => 'Dispositioned CRM lead',
            'user_id' => $sales->id,
        ]);

        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $lead->id,
            'actor_user_id' => $sales->id,
            'activity_type' => 'disposition',
            'outcome' => 'lost',
        ]);
    }

    public function test_lead_disposition_can_defer_with_required_follow_up(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $lead = Lead::where('lead_code', 'LD-1002')->firstOrFail();
        $followUp = now()->addDays(3)->toISOString();

        $this->actingAs($sales)->patchJson(route('crm.leads.disposition.update', $lead), [
            'outcome' => 'deferred',
            'reason' => 'Customer requested callback after bank loan approval',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['follow_up_at']);

        $this->actingAs($sales)->patchJson(route('crm.leads.disposition.update', $lead), [
            'outcome' => 'deferred',
            'reason' => 'Customer requested callback after bank loan approval',
            'follow_up_at' => $followUp,
        ])->assertOk()
            ->assertJsonPath('data.stage', 'Follow-up')
            ->assertJsonPath('data.status', 'on_hold')
            ->assertJsonPath('data.disposition.outcome', 'deferred');

        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'stage' => 'Follow-up',
            'status' => 'on_hold',
            'disposition_outcome' => 'deferred',
        ]);
    }

    public function test_partner_cannot_disposition_internal_crm_lead(): void
    {
        $this->seed();

        $channelPartner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();
        $lead = Lead::where('lead_code', 'LD-1001')->firstOrFail();

        $this->actingAs($channelPartner)
            ->patchJson(route('crm.leads.disposition.update', $lead), [
                'outcome' => 'lost',
                'reason' => 'Invalid partner-side action',
            ])
            ->assertForbidden();
    }

    public function test_closed_lead_cannot_be_dispositioned_again(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $lead = Lead::where('lead_code', 'LD-1001')->firstOrFail();

        $lead->forceFill(['status' => 'won', 'stage' => 'Booked'])->save();

        $this->actingAs($sales)
            ->patchJson(route('crm.leads.disposition.update', $lead), [
                'outcome' => 'lost',
                'reason' => 'Attempt after closure',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lead']);
    }
}
