<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\ComplianceObligation;
use App\Models\Company;
use App\Models\Project;
use App\Models\ProjectApproval;
use App\Models\ReraRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalComplianceTest extends TestCase
{
    use RefreshDatabase;

    public function test_compliance_user_can_list_legal_registers(): void
    {
        $this->seed();

        $compliance = User::where('email', 'meera.kapoor@builder360.test')->firstOrFail();

        $this->actingAs($compliance)
            ->getJson(route('legal.rera-registrations.index'))
            ->assertOk()
            ->assertJsonPath('data.0.registration_number', 'P52100012345');

        $this->actingAs($compliance)
            ->getJson(route('legal.project-approvals.index'))
            ->assertOk()
            ->assertJsonPath('data.0.approval_code', 'CC-SKY-001');

        $this->actingAs($compliance)
            ->getJson(route('legal.compliance-obligations.index'))
            ->assertOk()
            ->assertJsonPath('data.0.obligation_number', 'COMP-1001')
            ->assertJsonPath('data.0.status', 'open');
    }

    public function test_legal_register_indexes_validate_filters_and_project_scope(): void
    {
        $this->seed();

        $compliance = User::where('email', 'meera.kapoor@builder360.test')->firstOrFail();
        $otherCompany = Company::where('code', 'B360P')->firstOrFail();
        $otherProject = Project::where('company_id', $otherCompany->id)->firstOrFail();

        $this->actingAs($compliance)
            ->getJson(route('legal.rera-registrations.index', ['status' => 'invalid']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->actingAs($compliance)
            ->getJson(route('legal.rera-registrations.index', ['unexpected_filter' => 'ignored-before-hardening']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['unexpected_filter'])
            ->assertJsonPath('errors.unexpected_filter.0', 'The selected filter is not available for this endpoint.');

        $this->actingAs($compliance)
            ->getJson(route('legal.rera-registrations.index', ['expires_within_days' => -1]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['expires_within_days']);

        $this->actingAs($compliance)
            ->getJson(route('legal.rera-registrations.index', ['project_id' => $otherProject->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id']);

        $this->actingAs($compliance)
            ->getJson(route('legal.project-approvals.index', ['status' => 'invalid']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->actingAs($compliance)
            ->getJson(route('legal.project-approvals.index', ['unexpected_filter' => 'ignored-before-hardening']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['unexpected_filter'])
            ->assertJsonPath('errors.unexpected_filter.0', 'The selected filter is not available for this endpoint.');

        $this->actingAs($compliance)
            ->getJson(route('legal.project-approvals.index', ['expires_within_days' => 3651]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['expires_within_days']);

        $this->actingAs($compliance)
            ->getJson(route('legal.project-approvals.index', ['project_id' => $otherProject->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id']);

        $this->actingAs($compliance)
            ->getJson(route('legal.compliance-obligations.index', ['status' => 'invalid']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->actingAs($compliance)
            ->getJson(route('legal.compliance-obligations.index', ['unexpected_filter' => 'ignored-before-hardening']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['unexpected_filter'])
            ->assertJsonPath('errors.unexpected_filter.0', 'The selected filter is not available for this endpoint.');

        $this->actingAs($compliance)
            ->getJson(route('legal.compliance-obligations.index', ['due_within_days' => 3651]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['due_within_days']);

        $this->actingAs($compliance)
            ->getJson(route('legal.compliance-obligations.index', ['project_id' => $otherProject->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id']);

        $this->actingAs($compliance)
            ->getJson(route('legal.compliance-obligations.index', [
                'status' => 'open',
                'compliance_type' => 'RERA Quarterly Filing',
                'due_within_days' => 45,
                'per_page' => 10,
            ]))
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_non_global_compliance_user_without_company_assignment_fails_closed_for_legal_registers(): void
    {
        $this->seed();

        $compliance = User::where('email', 'meera.kapoor@builder360.test')->firstOrFail();
        $project = Project::where('code', 'GRN-PUN')->firstOrFail();

        $registrationNumber = $this->actingAs($compliance)
            ->postJson(route('legal.rera-registrations.store'), [
                'project_id' => $project->id,
                'registration_number' => 'P52100088888',
                'authority_name' => 'MahaRERA',
                'state_code' => 'MH',
                'registered_on' => now()->subDays(10)->toDateString(),
                'expires_on' => now()->addYears(3)->toDateString(),
            ])
            ->assertCreated()
            ->json('data.registration_number');

        $approvalCode = $this->actingAs($compliance)
            ->postJson(route('legal.project-approvals.store'), [
                'project_id' => $project->id,
                'approval_code' => 'LEGAL-SCOPE-001',
                'approval_type' => 'Scope NOC',
                'authority_name' => 'Municipal Authority',
                'applied_on' => now()->subDays(30)->toDateString(),
                'approved_on' => now()->subDay()->toDateString(),
                'expires_on' => now()->addYear()->toDateString(),
                'status' => 'approved',
            ])
            ->assertCreated()
            ->json('data.approval_code');

        $obligationNumber = $this->actingAs($compliance)
            ->postJson(route('legal.compliance-obligations.store'), [
                'project_id' => $project->id,
                'assigned_to_user_id' => $compliance->id,
                'title' => 'Fail closed compliance obligation',
                'compliance_type' => 'Scope Test',
                'due_on' => now()->addDays(10)->toDateString(),
                'frequency' => 'one_time',
                'priority' => 'normal',
            ])
            ->assertCreated()
            ->json('data.obligation_number');

        $registration = ReraRegistration::where('registration_number', $registrationNumber)->firstOrFail();
        $approval = ProjectApproval::where('approval_code', $approvalCode)->firstOrFail();
        $obligation = ComplianceObligation::where('obligation_number', $obligationNumber)->firstOrFail();

        $compliance->forceFill(['company_id' => null])->save();

        $this->actingAs($compliance)
            ->getJson(route('legal.rera-registrations.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($compliance)
            ->getJson(route('legal.project-approvals.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($compliance)
            ->getJson(route('legal.compliance-obligations.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($compliance)
            ->getJson(route('legal.rera-registrations.index', ['project_id' => $project->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('project_id');

        $this->actingAs($compliance)
            ->getJson(route('legal.project-approvals.index', ['project_id' => $project->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('project_id');

        $this->actingAs($compliance)
            ->getJson(route('legal.compliance-obligations.index', ['project_id' => $project->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('project_id');

        $this->actingAs($compliance)
            ->postJson(route('legal.rera-registrations.store'), [
                'project_id' => $project->id,
                'registration_number' => 'P52100077777',
                'authority_name' => 'MahaRERA',
                'state_code' => 'MH',
                'registered_on' => now()->subDays(5)->toDateString(),
                'expires_on' => now()->addYear()->toDateString(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('project_id');

        $this->actingAs($compliance)
            ->postJson(route('legal.compliance-obligations.store'), [
                'title' => 'Invalid missing company scope obligation',
                'compliance_type' => 'Scope Test',
                'due_on' => now()->addDays(15)->toDateString(),
                'frequency' => 'one_time',
                'priority' => 'normal',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('project_id');

        $this->actingAs($compliance)
            ->patchJson(route('legal.rera-registrations.verify', $registration), [
                'verification_note' => 'Should fail closed.',
            ])
            ->assertForbidden();

        $this->actingAs($compliance)
            ->patchJson(route('legal.project-approvals.verify', $approval), [
                'verification_note' => 'Should fail closed.',
            ])
            ->assertForbidden();

        $this->actingAs($compliance)
            ->patchJson(route('legal.compliance-obligations.complete', $obligation), [
                'evidence_document_reference' => 'FAIL-CLOSED-LEGAL-001',
                'notes' => 'Should fail closed.',
            ])
            ->assertForbidden();
    }

    public function test_rera_registration_submission_and_verification_workflow(): void
    {
        $this->seed();

        $compliance = User::where('email', 'meera.kapoor@builder360.test')->firstOrFail();
        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $project = Project::where('code', 'GRN-PUN')->firstOrFail();

        $registrationNumber = $this->actingAs($compliance)
            ->postJson(route('legal.rera-registrations.store'), [
                'project_id' => $project->id,
                'registration_number' => 'P52100099999',
                'authority_name' => 'MahaRERA',
                'state_code' => 'MH',
                'registered_on' => now()->subDays(10)->toDateString(),
                'expires_on' => now()->addYears(3)->toDateString(),
                'document_reference' => 'RERA-GRN-TEST',
                'conditions' => ['Quarterly updates required.'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'submitted')
            ->json('data.registration_number');

        $registration = ReraRegistration::where('registration_number', $registrationNumber)->firstOrFail();

        $this->actingAs($compliance)
            ->patchJson(route('legal.rera-registrations.verify', $registration))
            ->assertForbidden();

        $this->actingAs($director)
            ->patchJson(route('legal.rera-registrations.verify', $registration), [
                'verification_note' => str_repeat('x', 2001),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('verification_note');

        $this->actingAs($director)
            ->patchJson(route('legal.rera-registrations.verify', $registration), [
                'verification_note' => 'Verified against authority certificate.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'verified')
            ->assertJsonPath('data.verified_by.email', 'aditya.mehra@builder360.test');

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'legal.rera.verified',
            'user_id' => $director->id,
        ]);

        $registration->refresh();
        $this->assertSame('Verified against authority certificate.', collect($registration->workflow_history)->last()['note']);

        $audit = AuditEvent::query()
            ->where('event_type', 'legal.rera.verified')
            ->latest()
            ->firstOrFail();
        $this->assertSame('Verified against authority certificate.', $audit->metadata['verification_note']);
    }

    public function test_compliance_user_can_use_native_blade_rera_workspace(): void
    {
        $this->seed();

        $compliance = User::where('email', 'meera.kapoor@builder360.test')->firstOrFail();
        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $project = Project::where('code', 'GRN-PUN')->firstOrFail();

        $this->actingAs($compliance)
            ->get(route('legal.rera-registrations.index'))
            ->assertOk()
            ->assertSee('Workspace for project-wise RERA registration tracking')
            ->assertSee('name="registration_number"', false)
            ->assertSee('P52100012345')
            ->assertDontSee('window.Builder360Server', false)
            ->assertDontSee('id="root"', false);

        $this->actingAs($compliance)
            ->post(route('legal.rera-registrations.store'), [
                'project_id' => $project->id,
                'registration_number' => 'P521000BLADE1',
                'authority_name' => 'MahaRERA',
                'state_code' => 'MH',
                'registered_on' => now()->subDays(3)->toDateString(),
                'expires_on' => now()->addYears(2)->toDateString(),
                'document_reference' => 'RERA-BLADE-DOC-001',
                'conditions' => ['', 'Quarterly updates required.'],
            ])
            ->assertRedirect(route('legal.rera-registrations.index'))
            ->assertSessionHas('status');

        $registration = ReraRegistration::where('registration_number', 'P521000BLADE1')->firstOrFail();

        $this->assertSame('submitted', $registration->status);
        $this->assertSame(['Quarterly updates required.'], $registration->conditions);
        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'legal.rera.submitted',
            'auditable_id' => $registration->id,
            'user_id' => $compliance->id,
        ]);

        $this->actingAs($director)
            ->patch(route('legal.rera-registrations.verify', $registration), [
                'verification_note' => 'Verified from Blade workspace.',
            ])
            ->assertRedirect(route('legal.rera-registrations.index'))
            ->assertSessionHas('status');

        $this->assertSame('verified', $registration->fresh()->status);
    }

    public function test_duplicate_rera_registration_is_rejected(): void
    {
        $this->seed();

        $compliance = User::where('email', 'meera.kapoor@builder360.test')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();

        $this->actingAs($compliance)
            ->postJson(route('legal.rera-registrations.store'), [
                'project_id' => $project->id,
                'registration_number' => 'P52100012345',
                'authority_name' => 'MahaRERA',
                'state_code' => 'MH',
                'registered_on' => now()->subDays(5)->toDateString(),
                'expires_on' => now()->addYear()->toDateString(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('registration_number');
    }

    public function test_project_approval_creation_and_verification_workflow(): void
    {
        $this->seed();

        $compliance = User::where('email', 'meera.kapoor@builder360.test')->firstOrFail();
        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $project = Project::where('code', 'GRN-PUN')->firstOrFail();

        $approvalCode = $this->actingAs($compliance)
            ->postJson(route('legal.project-approvals.store'), [
                'project_id' => $project->id,
                'approval_code' => 'FIRE-GRN-001',
                'approval_type' => 'Fire NOC',
                'authority_name' => 'Fire Department',
                'application_number' => 'FIRE/GRN/001',
                'applied_on' => now()->subDays(30)->toDateString(),
                'approved_on' => now()->subDays(2)->toDateString(),
                'expires_on' => now()->addYear()->toDateString(),
                'status' => 'approved',
                'required_for' => 'Occupation certificate',
                'document_reference' => 'FIRE-GRN-DOC',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'approved')
            ->json('data.approval_code');

        $approval = ProjectApproval::where('approval_code', $approvalCode)->firstOrFail();

        $this->actingAs($director)
            ->patchJson(route('legal.project-approvals.verify', $approval), [
                'verification_note' => str_repeat('x', 2001),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('verification_note');

        $this->actingAs($director)
            ->patchJson(route('legal.project-approvals.verify', $approval), [
                'verification_note' => 'Verified supporting NOC document.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'verified');

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'legal.project_approval.verified',
            'user_id' => $director->id,
        ]);

        $approval->refresh();
        $this->assertSame('Verified supporting NOC document.', collect($approval->workflow_history)->last()['note']);

        $audit = AuditEvent::query()
            ->where('event_type', 'legal.project_approval.verified')
            ->latest()
            ->firstOrFail();
        $this->assertSame('Verified supporting NOC document.', $audit->metadata['verification_note']);
    }

    public function test_compliance_user_can_use_native_blade_project_approval_workspace(): void
    {
        $this->seed();

        $compliance = User::where('email', 'meera.kapoor@builder360.test')->firstOrFail();
        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $project = Project::where('code', 'GRN-PUN')->firstOrFail();

        $this->actingAs($compliance)
            ->get(route('legal.project-approvals.index'))
            ->assertOk()
            ->assertSee('Workspace for authority approvals')
            ->assertSee('name="approval_code"', false)
            ->assertSee('CC-SKY-001')
            ->assertDontSee('window.Builder360Server', false)
            ->assertDontSee('id="root"', false);

        $this->actingAs($compliance)
            ->post(route('legal.project-approvals.store'), [
                'project_id' => $project->id,
                'approval_code' => 'BLADE-FIRE-001',
                'approval_type' => 'Fire NOC',
                'authority_name' => 'Fire Department',
                'application_number' => 'FIRE/BLADE/001',
                'applied_on' => now()->subDays(15)->toDateString(),
                'approved_on' => now()->subDay()->toDateString(),
                'expires_on' => now()->addYear()->toDateString(),
                'status' => 'approved',
                'required_for' => 'Occupation certificate',
                'document_reference' => 'FIRE-BLADE-DOC-001',
                'conditions' => ['', 'Maintain fire equipment access.'],
            ])
            ->assertRedirect(route('legal.project-approvals.index'))
            ->assertSessionHas('status');

        $approval = ProjectApproval::where('approval_code', 'BLADE-FIRE-001')->firstOrFail();

        $this->assertSame('approved', $approval->status);
        $this->assertSame(['Maintain fire equipment access.'], $approval->conditions);

        $this->actingAs($director)
            ->patch(route('legal.project-approvals.verify', $approval), [
                'verification_note' => 'Verified from Blade workspace.',
            ])
            ->assertRedirect(route('legal.project-approvals.index'))
            ->assertSessionHas('status');

        $this->assertSame('verified', $approval->fresh()->status);
    }

    public function test_compliance_obligation_can_be_created_and_completed_with_evidence(): void
    {
        $this->seed();

        $compliance = User::where('email', 'meera.kapoor@builder360.test')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();

        $obligationNumber = $this->actingAs($compliance)
            ->postJson(route('legal.compliance-obligations.store'), [
                'project_id' => $project->id,
                'assigned_to_user_id' => $compliance->id,
                'title' => 'Upload sanctioned plan revision',
                'compliance_type' => 'Municipal Filing',
                'due_on' => now()->addDays(15)->toDateString(),
                'frequency' => 'one_time',
                'priority' => 'normal',
                'notes' => 'Revision to be filed with authority.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'open')
            ->json('data.obligation_number');

        $obligation = ComplianceObligation::where('obligation_number', $obligationNumber)->firstOrFail();

        $this->actingAs($compliance)
            ->patchJson(route('legal.compliance-obligations.complete', $obligation), [
                'evidence_document_reference' => 'MUNICIPAL-FILING-ACK-001',
                'notes' => 'Filed and acknowledgement uploaded.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.completed_by.email', 'meera.kapoor@builder360.test');

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'legal.compliance_obligation.completed',
            'user_id' => $compliance->id,
        ]);
    }

    public function test_compliance_user_can_use_native_blade_compliance_obligation_workspace(): void
    {
        $this->seed();

        $compliance = User::where('email', 'meera.kapoor@builder360.test')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();

        $this->actingAs($compliance)
            ->get(route('legal.compliance-obligations.index'))
            ->assertOk()
            ->assertSee('Workspace for project and company compliance tasks')
            ->assertSee('name="title"', false)
            ->assertSee('COMP-1001')
            ->assertDontSee('window.Builder360Server', false)
            ->assertDontSee('id="root"', false);

        $this->actingAs($compliance)
            ->post(route('legal.compliance-obligations.store'), [
                'project_id' => $project->id,
                'assigned_to_user_id' => $compliance->id,
                'title' => 'Blade compliance filing',
                'compliance_type' => 'Municipal Filing',
                'due_on' => now()->addDays(20)->toDateString(),
                'frequency' => 'one_time',
                'priority' => 'high',
                'notes' => 'Created from Blade workspace.',
            ])
            ->assertRedirect(route('legal.compliance-obligations.index'))
            ->assertSessionHas('status');

        $obligation = ComplianceObligation::where('title', 'Blade compliance filing')->firstOrFail();

        $this->assertSame('open', $obligation->status);
        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'legal.compliance_obligation.created',
            'auditable_id' => $obligation->id,
            'user_id' => $compliance->id,
        ]);

        $this->actingAs($compliance)
            ->patch(route('legal.compliance-obligations.complete', $obligation), [
                'evidence_document_reference' => 'BLADE-COMPLIANCE-EVIDENCE-001',
                'notes' => 'Completed from Blade workspace.',
            ])
            ->assertRedirect(route('legal.compliance-obligations.index'))
            ->assertSessionHas('status');

        $this->assertSame('completed', $obligation->fresh()->status);
    }

    public function test_partner_cannot_access_internal_legal_routes(): void
    {
        $this->seed();

        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();

        $this->actingAs($partner)
            ->getJson(route('legal.rera-registrations.index'))
            ->assertForbidden();

        $this->actingAs($partner)
            ->postJson(route('legal.compliance-obligations.store'), [])
            ->assertForbidden();

        $obligation = ComplianceObligation::where('obligation_number', 'COMP-1001')->firstOrFail();

        $this->actingAs($partner)
            ->patchJson(route('legal.compliance-obligations.complete', $obligation), [
                'evidence_document_reference' => 'PARTNER-DENIED-001',
                'notes' => 'Partner must not complete internal compliance obligations.',
            ])
            ->assertForbidden();
    }
}
