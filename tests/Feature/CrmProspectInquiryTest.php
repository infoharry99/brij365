<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Company;
use App\Models\Lead;
use App\Models\Project;
use App\Models\ProspectInquiry;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmProspectInquiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_prospect_inquiry_is_captured_with_project_company_and_audit(): void
    {
        $this->seed();

        $project = Project::where('code', 'SKY-PUN')->firstOrFail();
        $wrongCompany = Company::where('code', 'B360P')->firstOrFail();

        $response = $this->postJson(route('prospect-inquiries.store'), [
            'company_id' => $wrongCompany->id,
            'project_id' => $project->id,
            'name' => 'Public Prospect',
            'email' => 'public.prospect@example.test',
            'phone' => '+91 98000 11111',
            'source' => 'Website',
            'channel' => 'website',
            'preferred_contact_method' => 'phone',
            'budget_min' => 8000000,
            'budget_max' => 11000000,
            'message' => 'Interested in a 2BHK unit.',
            'consent_to_contact' => true,
            'status' => ProspectInquiry::STATUS_CONVERTED,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.status', ProspectInquiry::STATUS_NEW)
            ->assertJsonPath('data.project.code', 'SKY-PUN')
            ->assertJsonPath('data.company.code', 'B360D');

        $this->assertDatabaseHas('prospect_inquiries', [
            'email' => 'public.prospect@example.test',
            'company_id' => $project->company_id,
            'project_id' => $project->id,
            'status' => ProspectInquiry::STATUS_NEW,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'crm.prospect_inquiry.captured',
            'action' => 'Captured public prospect inquiry',
            'user_id' => null,
        ]);

        $this->assertGreaterThanOrEqual(1, UserNotification::where('category', 'crm')->count());
    }

    public function test_public_prospect_inquiry_detects_duplicate_active_project_contact(): void
    {
        $this->seed();

        $project = Project::where('code', 'SKY-PUN')->firstOrFail();

        $first = $this->postJson(route('prospect-inquiries.store'), [
            'project_id' => $project->id,
            'name' => 'Duplicate Prospect',
            'email' => 'duplicate.prospect@example.test',
            'source' => 'Website',
            'channel' => 'website',
            'consent_to_contact' => true,
        ])->assertCreated();

        $second = $this->postJson(route('prospect-inquiries.store'), [
            'project_id' => $project->id,
            'name' => 'Duplicate Prospect Again',
            'email' => 'duplicate.prospect@example.test',
            'source' => 'Website',
            'channel' => 'website',
            'consent_to_contact' => true,
        ]);

        $second
            ->assertCreated()
            ->assertJsonPath('data.status', ProspectInquiry::STATUS_DUPLICATE)
            ->assertJsonPath('data.duplicate_of.inquiry_number', $first->json('data.inquiry_number'));

        $this->assertDatabaseHas('prospect_inquiries', [
            'inquiry_number' => $second->json('data.inquiry_number'),
            'status' => ProspectInquiry::STATUS_DUPLICATE,
        ]);
    }

    public function test_crm_user_can_list_and_filter_company_scoped_prospect_inquiries(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();

        $this->actingAs($sales)
            ->getJson(route('crm.prospect-inquiries.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['inquiry_number', 'name', 'status', 'project', 'company'],
                ],
            ]);

        $this->actingAs($sales)
            ->getJson(route('crm.prospect-inquiries.index', ['q' => 'Sneha']))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Sneha Deshmukh');
    }

    public function test_crm_user_can_open_native_blade_prospect_inquiry_workspace(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();

        $this->actingAs($sales)
            ->get(route('crm.prospect-inquiries.index'))
            ->assertOk()
            ->assertSee('Prospect Inquiry Management')
            ->assertSee('Inquiry status summary')
            ->assertSee('Sneha Deshmukh')
            ->assertSee('Assign owner')
            ->assertSee('Convert to lead')
            ->assertSee('Close');
    }

    public function test_crm_user_can_submit_blade_assign_and_convert_actions(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $inquiry = ProspectInquiry::where('inquiry_number', 'PI-10001')->firstOrFail();

        $this->actingAs($sales)
            ->from(route('crm.prospect-inquiries.index'))
            ->patch(route('crm.prospect-inquiries.assign', $inquiry), [
                'assigned_to_user_id' => $sales->id,
                'note' => 'Assigned from Blade workspace.',
            ])
            ->assertRedirect(route('crm.prospect-inquiries.index'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('prospect_inquiries', [
            'id' => $inquiry->id,
            'assigned_to_user_id' => $sales->id,
            'status' => ProspectInquiry::STATUS_ASSIGNED,
        ]);

        $this->actingAs($sales)
            ->from(route('crm.prospect-inquiries.index'))
            ->patch(route('crm.prospect-inquiries.convert', $inquiry->refresh()), [
                'expected_value' => 10750000,
                'stage' => 'Qualified',
                'follow_up_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
                'note' => 'Converted from Blade workspace.',
            ])
            ->assertRedirect(route('crm.prospect-inquiries.index', ['status' => ProspectInquiry::STATUS_CONVERTED]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('prospect_inquiries', [
            'id' => $inquiry->id,
            'status' => ProspectInquiry::STATUS_CONVERTED,
        ]);

        $this->assertDatabaseHas('leads', [
            'project_id' => $inquiry->project_id,
            'owner_user_id' => $sales->id,
            'expected_value' => 10750000,
            'stage' => 'Qualified',
        ]);
    }

    public function test_crm_user_can_submit_blade_close_action(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $inquiry = ProspectInquiry::where('inquiry_number', 'PI-10002')->firstOrFail();

        $this->actingAs($sales)
            ->from(route('crm.prospect-inquiries.index'))
            ->patch(route('crm.prospect-inquiries.close', $inquiry), [
                'status' => ProspectInquiry::STATUS_CLOSED_UNQUALIFIED,
                'reason' => 'Budget mismatch confirmed during callback.',
            ])
            ->assertRedirect(route('crm.prospect-inquiries.index', ['status' => ProspectInquiry::STATUS_CLOSED_UNQUALIFIED]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('prospect_inquiries', [
            'id' => $inquiry->id,
            'status' => ProspectInquiry::STATUS_CLOSED_UNQUALIFIED,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'crm.prospect_inquiry.closed',
            'action' => 'Closed prospect inquiry',
            'user_id' => $sales->id,
        ]);
    }

    public function test_prospect_inquiry_filters_reject_cross_company_project_and_unexpected_query(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $otherProject = Project::where('code', 'MTO-PUN')->firstOrFail();

        $this->actingAs($sales)
            ->getJson(route('crm.prospect-inquiries.index', ['project_id' => $otherProject->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id']);

        $this->actingAs($sales)
            ->getJson(route('crm.prospect-inquiries.index', ['customer_id' => 1]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id']);
    }

    public function test_crm_manager_can_assign_and_convert_prospect_inquiry_to_lead(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $inquiry = ProspectInquiry::where('inquiry_number', 'PI-10001')->firstOrFail();

        $this->actingAs($sales)
            ->patchJson(route('crm.prospect-inquiries.assign', $inquiry), [
                'assigned_to_user_id' => $sales->id,
                'note' => 'Assign to sales owner for follow-up.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', ProspectInquiry::STATUS_ASSIGNED)
            ->assertJsonPath('data.assigned_to.email', 'priya.nair@builder360.test');

        $response = $this->actingAs($sales)
            ->patchJson(route('crm.prospect-inquiries.convert', $inquiry->refresh()), [
                'expected_value' => 10500000,
                'stage' => 'New',
                'follow_up_at' => now()->addDay()->toISOString(),
                'note' => 'Qualified from website inquiry.',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.status', ProspectInquiry::STATUS_CONVERTED)
            ->assertJsonPath('data.converted_lead.status', 'open');

        $this->assertDatabaseHas('leads', [
            'lead_code' => $response->json('data.converted_lead.lead_code'),
            'project_id' => $inquiry->project_id,
            'owner_user_id' => $sales->id,
            'expected_value' => 10500000,
        ]);

        $this->assertDatabaseHas('customers', [
            'email' => 'sneha.deshmukh@example.test',
            'name' => 'Sneha Deshmukh',
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'crm.prospect_inquiry.converted',
            'action' => 'Converted prospect inquiry to lead',
            'user_id' => $sales->id,
        ]);

        $this->assertTrue(Lead::where('lead_code', $response->json('data.converted_lead.lead_code'))->exists());
    }

    public function test_closed_or_duplicate_prospect_inquiry_cannot_be_converted_directly(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $duplicate = ProspectInquiry::where('inquiry_number', 'PI-10001')->firstOrFail()->replicate([
            'inquiry_number',
        ]);
        $duplicate->forceFill([
            'inquiry_number' => 'PI-DUPLICATE-TEST',
            'status' => ProspectInquiry::STATUS_DUPLICATE,
            'duplicate_of_id' => ProspectInquiry::where('inquiry_number', 'PI-10001')->value('id'),
        ])->save();

        $this->actingAs($sales)
            ->patchJson(route('crm.prospect-inquiries.convert', $duplicate), [
                'expected_value' => 10000000,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_partner_user_cannot_manage_internal_prospect_inquiries(): void
    {
        $this->seed();

        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();
        $inquiry = ProspectInquiry::where('inquiry_number', 'PI-10001')->firstOrFail();

        $this->actingAs($partner)
            ->getJson(route('crm.prospect-inquiries.index'))
            ->assertForbidden();

        $this->actingAs($partner)
            ->patchJson(route('crm.prospect-inquiries.assign', $inquiry), [
                'assigned_to_user_id' => $partner->id,
            ])
            ->assertForbidden();

        $this->actingAs($partner)
            ->patchJson(route('crm.prospect-inquiries.convert', $inquiry), [
                'expected_value' => 9000000,
            ])
            ->assertForbidden();
    }
}
