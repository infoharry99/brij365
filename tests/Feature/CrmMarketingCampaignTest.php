<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\MarketingCampaign;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmMarketingCampaignTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_user_can_open_native_marketing_and_activity_workspaces(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();

        $this->actingAs($sales)
            ->get(route('crm.campaigns.index'))
            ->assertOk()
            ->assertSee('Marketing Campaigns')
            ->assertSee('Campaign outcomes')
            ->assertSee('Skyline Channel Partner Launch');

        $this->actingAs($sales)
            ->get(route('crm.lead-activities.index'))
            ->assertOk()
            ->assertSee('Lead Activities')
            ->assertSee('Lead activity history');
    }

    public function test_sales_user_can_submit_native_campaign_and_activity_forms(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $company = Company::where('code', 'B360D')->firstOrFail();
        $lead = Lead::where('lead_code', 'LD-1002')->firstOrFail();
        $project = Project::findOrFail($lead->project_id);

        $this->actingAs($sales)
            ->post(route('crm.campaigns.store'), [
                'company_id' => $company->id,
                'project_id' => $project->id,
                'name' => 'Native Campaign Workspace QA',
                'channel' => 'digital',
                'source' => 'Search',
                'status' => 'draft',
                'start_on' => now()->toDateString(),
                'budget_amount' => 100000,
                'target_leads' => 10,
                'target_bookings' => 2,
            ])
            ->assertRedirect(route('crm.campaigns.index'));

        $campaign = MarketingCampaign::where('name', 'Native Campaign Workspace QA')->firstOrFail();

        $this->actingAs($sales)
            ->post(route('crm.lead-activities.store'), [
                'lead_id' => $lead->id,
                'marketing_campaign_id' => $campaign->id,
                'activity_type' => 'note',
                'subject' => 'Native workspace note',
            ])
            ->assertRedirect(route('crm.lead-activities.index'));

        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $lead->id,
            'subject' => 'Native workspace note',
        ]);
    }

    public function test_sales_user_can_list_campaigns_with_real_response_metrics(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();

        $this->actingAs($sales)
            ->getJson(route('crm.campaigns.index', [
                'q' => 'Skyline Channel',
                'channel' => 'channel_partner',
            ]))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.campaign_code', 'MC-10001')
            ->assertJsonPath('data.0.metrics.total_leads', 1)
            ->assertJsonPath('data.0.metrics.won_leads', 1)
            ->assertJsonPath('data.0.metrics.bookings', 1);
    }

    public function test_sales_user_can_create_campaign_update_status_and_audit_is_recorded(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $company = Company::where('code', 'B360D')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();

        $campaignId = $this->actingAs($sales)
            ->postJson(route('crm.campaigns.store'), [
                'company_id' => $company->id,
                'project_id' => $project->id,
                'name' => 'Skyline Digital Retargeting',
                'channel' => 'digital',
                'source' => 'Google Ads',
                'status' => 'draft',
                'start_on' => now()->toDateString(),
                'end_on' => now()->addDays(30)->toDateString(),
                'budget_amount' => 250000,
                'target_leads' => 40,
                'target_bookings' => 5,
                'utm_source' => 'google',
                'utm_medium' => 'cpc',
                'utm_campaign' => 'skyline_retargeting',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Skyline Digital Retargeting')
            ->assertJsonPath('data.status', 'draft')
            ->json('data.id');

        $campaign = MarketingCampaign::findOrFail($campaignId);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'crm.campaign.created',
            'auditable_type' => MarketingCampaign::class,
            'auditable_id' => $campaign->id,
        ]);

        $this->actingAs($sales)
            ->patchJson(route('crm.campaigns.status.update', $campaign), [
                'status' => 'active',
                'note' => 'Approved by sales head for launch.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.approved_by.email', 'priya.nair@builder360.test');

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'crm.campaign.status_updated',
            'auditable_type' => MarketingCampaign::class,
            'auditable_id' => $campaign->id,
        ]);
    }

    public function test_lead_creation_with_campaign_creates_attribution_and_activity_history(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $company = Company::where('code', 'B360D')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();
        $campaign = MarketingCampaign::where('campaign_code', 'MC-10001')->firstOrFail();

        $leadId = $this->actingAs($sales)
            ->postJson(route('crm.leads.store'), [
                'company_id' => $company->id,
                'project_id' => $project->id,
                'marketing_campaign_id' => $campaign->id,
                'customer_name' => 'Campaign Buyer',
                'customer_email' => 'campaign.buyer@example.test',
                'source' => 'Channel walk-in',
                'stage' => 'New',
                'expected_value' => 9800000,
            ])
            ->assertCreated()
            ->assertJsonPath('data.marketing_campaign.campaign_code', 'MC-10001')
            ->json('data.id');

        $lead = Lead::findOrFail($leadId);

        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'marketing_campaign_id' => $campaign->id,
            'source' => 'Channel walk-in',
        ]);

        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $lead->id,
            'marketing_campaign_id' => $campaign->id,
            'activity_type' => 'created',
            'subject' => 'Lead created',
        ]);

        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $lead->id,
            'marketing_campaign_id' => $campaign->id,
            'activity_type' => 'campaign_response',
            'subject' => 'Campaign response captured',
        ]);

        $this->actingAs($sales)
            ->getJson(route('crm.lead-activities.index', [
                'lead_id' => $lead->id,
                'marketing_campaign_id' => $campaign->id,
            ]))
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    public function test_manual_lead_activity_updates_follow_up_and_records_audit(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $lead = Lead::where('lead_code', 'LD-1002')->firstOrFail();
        $campaign = MarketingCampaign::where('campaign_code', 'MC-10002')->firstOrFail();
        $activityAt = now()->setTime(10, 30);
        $nextFollowUp = now()->addDays(3)->setTime(16, 0);

        $activityNumber = $this->actingAs($sales)
            ->postJson(route('crm.lead-activities.store'), [
                'lead_id' => $lead->id,
                'marketing_campaign_id' => $campaign->id,
                'activity_type' => 'call',
                'activity_at' => $activityAt->toISOString(),
                'subject' => 'Budget confirmation call',
                'description' => 'Customer confirmed preferred unit size and requested revised quote.',
                'outcome' => 'follow_up_required',
                'next_follow_up_at' => $nextFollowUp->toISOString(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.activity_type', 'call')
            ->assertJsonPath('data.marketing_campaign.campaign_code', 'MC-10002')
            ->json('data.activity_number');

        $activity = LeadActivity::where('activity_number', $activityNumber)->firstOrFail();

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'crm.lead_activity.created',
            'auditable_type' => LeadActivity::class,
            'auditable_id' => $activity->id,
        ]);

        $this->assertTrue($lead->fresh()->follow_up_at->equalTo($nextFollowUp));
    }

    public function test_campaign_and_activity_filters_validate_scope_and_partner_access_is_denied(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();
        $otherCompany = Company::where('code', 'B360P')->firstOrFail();
        $otherProject = Project::where('company_id', $otherCompany->id)->firstOrFail();
        $otherCampaign = MarketingCampaign::create([
            'company_id' => $otherCompany->id,
            'project_id' => $otherProject->id,
            'created_by_user_id' => User::where('email', 'aditya.mehra@builder360.test')->firstOrFail()->id,
            'campaign_code' => 'MC-OTHER',
            'name' => 'Other Company Campaign',
            'channel' => 'digital',
            'source' => 'Other Source',
            'status' => 'active',
            'start_on' => now()->toDateString(),
            'budget_amount' => 10000,
            'target_leads' => 1,
            'target_bookings' => 0,
            'workflow_history' => [],
            'metadata' => [],
            'approved_at' => now(),
        ]);

        $this->actingAs($sales)
            ->getJson(route('crm.campaigns.index', ['customer_id' => 1]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id']);

        $this->actingAs($sales)
            ->getJson(route('crm.campaigns.index', ['project_id' => $otherProject->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id']);

        $this->actingAs($sales)
            ->getJson(route('crm.lead-activities.index', ['marketing_campaign_id' => $otherCampaign->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['marketing_campaign_id']);

        $this->actingAs($partner)
            ->getJson(route('crm.campaigns.index'))
            ->assertForbidden();

        $this->actingAs($partner)
            ->getJson(route('crm.lead-activities.index'))
            ->assertForbidden();
    }

    public function test_lead_creation_rejects_campaign_from_another_company(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $company = Company::where('code', 'B360D')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();
        $otherCompany = Company::where('code', 'B360P')->firstOrFail();
        $otherProject = Project::where('company_id', $otherCompany->id)->firstOrFail();
        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $otherCampaign = MarketingCampaign::create([
            'company_id' => $otherCompany->id,
            'project_id' => $otherProject->id,
            'created_by_user_id' => $director->id,
            'campaign_code' => 'MC-CROSS',
            'name' => 'Cross Company Campaign',
            'channel' => 'digital',
            'source' => 'Cross Source',
            'status' => 'active',
            'start_on' => now()->toDateString(),
            'budget_amount' => 10000,
            'target_leads' => 1,
            'target_bookings' => 0,
            'workflow_history' => [],
            'metadata' => [],
            'approved_at' => now(),
        ]);

        $this->actingAs($sales)
            ->postJson(route('crm.leads.store'), [
                'company_id' => $company->id,
                'project_id' => $project->id,
                'marketing_campaign_id' => $otherCampaign->id,
                'customer_name' => 'Invalid Campaign Lead',
                'customer_phone' => '+91 98111 44444',
                'source' => 'Cross Source',
                'stage' => 'New',
                'expected_value' => 5000000,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['marketing_campaign_id']);
    }
}
