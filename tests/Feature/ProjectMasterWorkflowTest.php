<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Project;
use App\Models\ProjectTeamAssignment;
use App\Models\ProjectUnit;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Builder360\Builder360Bootstrap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectMasterWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_open_native_blade_project_master_workspace(): void
    {
        $this->seed();

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertSee('Project Master')
            ->assertSee('Workspace')
            ->assertSee('Create project master')
            ->assertSee('Project filters')
            ->assertSee('Project master register')
            ->assertSee('name="company_id"', false)
            ->assertSee('name="code"', false)
            ->assertSee('SKY-PUN')
            ->assertDontSee('window.Builder360Server', false)
            ->assertDontSee('id="root"', false);
    }

    public function test_native_blade_project_form_creates_project_and_redirects(): void
    {
        $this->seed();

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $company = Company::where('code', 'B360D')->firstOrFail();
        $branch = Branch::where('company_id', $company->id)->where('code', 'PNQ-HO')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('projects.store'), [
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'code' => 'BLADE-PUN',
                'name' => 'Blade Test Park',
                'project_type' => 'residential',
                'city' => 'Pune',
                'state' => 'MH',
                'status' => 'planned',
                'budget_amount' => '123000000.00',
                'target_roi_percent' => '19.25',
                'starts_on' => '2026-08-01',
                'ends_on' => '2028-03-31',
            ])
            ->assertRedirect(route('projects.index'))
            ->assertSessionHas('status');

        $project = Project::where('code', 'BLADE-PUN')->firstOrFail();

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'status' => 'planned',
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'projects.project.created',
            'auditable_type' => Project::class,
            'auditable_id' => $project->id,
            'user_id' => $admin->id,
        ]);
    }

    public function test_native_blade_project_update_redirects_and_persists_changes(): void
    {
        $this->seed();

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $company = Company::where('code', 'B360D')->firstOrFail();
        $branch = Branch::where('company_id', $company->id)->where('code', 'PNQ-HO')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('projects.update', $project), [
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'code' => 'SKY-BLADE',
                'name' => 'Skyline Blade Updated',
                'project_type' => 'residential',
                'city' => 'Pune',
                'state' => 'MH',
                'status' => 'active',
                'budget_amount' => '3400000000.00',
                'target_roi_percent' => '23.75',
                'starts_on' => '2026-08-01',
                'ends_on' => '2029-03-31',
            ])
            ->assertRedirect(route('projects.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'code' => 'SKY-BLADE',
            'name' => 'Skyline Blade Updated',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'projects.project.updated',
            'auditable_type' => Project::class,
            'auditable_id' => $project->id,
            'user_id' => $admin->id,
        ]);
    }

    public function test_native_blade_project_team_assignment_and_revoke_redirects(): void
    {
        $this->seed();

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $assignee = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('projects.team-assignments.store', $project), [
                'user_id' => $assignee->id,
                'employee_id' => $assignee->employee?->id,
                'role_label' => 'Blade CRM Owner',
                'department' => 'Sales',
                'access_level' => 'manage',
                'starts_on' => '2026-07-10',
                'notes' => 'Assigned from native Blade project master.',
            ])
            ->assertRedirect(route('projects.index'))
            ->assertSessionHas('status');

        $assignment = ProjectTeamAssignment::where('project_id', $project->id)
            ->where('user_id', $assignee->id)
            ->where('role_label', 'Blade CRM Owner')
            ->firstOrFail();

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'projects.team_assignment.created',
            'auditable_type' => ProjectTeamAssignment::class,
            'auditable_id' => $assignment->id,
            'user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('projects.team-assignments.destroy', [$project, $assignment]))
            ->assertRedirect(route('projects.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('project_team_assignments', [
            'id' => $assignment->id,
            'status' => 'revoked',
            'revoked_by_user_id' => $admin->id,
        ]);
    }

    public function test_system_admin_can_create_project_master_record_with_audit_and_notification(): void
    {
        $this->seed();

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $company = Company::where('code', 'B360D')->firstOrFail();
        $branch = Branch::where('company_id', $company->id)->where('code', 'PNQ-HO')->firstOrFail();

        $response = $this->actingAs($admin)
            ->postJson(route('projects.store'), [
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'code' => 'UTP-PUN',
                'name' => 'Unit Test Park',
                'project_type' => 'residential',
                'city' => 'Pune',
                'state' => 'MH',
                'status' => 'planned',
                'budget_amount' => '123456789.50',
                'target_roi_percent' => '18.75',
                'starts_on' => '2026-08-01',
                'ends_on' => '2028-03-31',
            ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'UTP-PUN')
            ->assertJsonPath('data.name', 'Unit Test Park')
            ->assertJsonPath('data.company.code', 'B360D')
            ->assertJsonPath('data.branch.code', 'PNQ-HO');

        $projectId = $response->json('data.id');

        $this->assertDatabaseHas('projects', [
            'id' => $projectId,
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'code' => 'UTP-PUN',
            'status' => 'planned',
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'projects.project.created',
            'auditable_type' => Project::class,
            'auditable_id' => $projectId,
            'user_id' => $admin->id,
        ]);

        $this->assertDatabaseHas('user_notifications', [
            'recipient_user_id' => $admin->id,
            'notifiable_type' => Project::class,
            'notifiable_id' => $projectId,
            'category' => 'project_master',
            'status' => 'unread',
        ]);

        $this->assertSame(1, AuditEvent::where('event_type', 'projects.project.created')->count());
        $this->assertSame(1, UserNotification::where('category', 'project_master')->count());
    }

    public function test_project_master_creation_enforces_role_company_and_branch_scope(): void
    {
        $this->seed();

        $salesHead = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $company = Company::where('code', 'B360D')->firstOrFail();
        $otherCompany = Company::where('code', 'B360P')->firstOrFail();
        $otherBranch = Branch::where('company_id', $otherCompany->id)->firstOrFail();

        $payload = [
            'company_id' => $company->id,
            'branch_id' => $otherBranch->id,
            'code' => 'BAD-PUN',
            'name' => 'Invalid Branch Project',
            'project_type' => 'residential',
            'city' => 'Pune',
            'state' => 'MH',
            'status' => 'planned',
        ];

        $this->actingAs($salesHead)
            ->postJson(route('projects.store'), $payload)
            ->assertForbidden();

        $this->actingAs($admin)
            ->postJson(route('projects.store'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['branch_id']);

        $this->actingAs($admin)
            ->postJson(route('projects.store'), [
                ...$payload,
                'branch_id' => null,
                'code' => 'bad code',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    }

    public function test_system_admin_can_update_project_master_record_with_audit_and_notification(): void
    {
        $this->seed();

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $company = Company::where('code', 'B360D')->firstOrFail();
        $branch = Branch::where('company_id', $company->id)->where('code', 'PNQ-HO')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();

        $response = $this->actingAs($admin)
            ->patchJson(route('projects.update', $project), [
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'code' => 'SKY-PUN-UPD',
                'name' => 'Skyline Residency Updated',
                'project_type' => 'residential',
                'city' => 'Pimpri-Chinchwad',
                'state' => 'MH',
                'status' => 'active',
                'budget_amount' => '3450000000.00',
                'target_roi_percent' => '24.50',
                'starts_on' => '2026-08-01',
                'ends_on' => '2029-03-31',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Project master updated.')
            ->assertJsonPath('data.id', $project->id)
            ->assertJsonPath('data.code', 'SKY-PUN-UPD')
            ->assertJsonPath('data.name', 'Skyline Residency Updated')
            ->assertJsonPath('data.branch.code', 'PNQ-HO');

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'code' => 'SKY-PUN-UPD',
            'name' => 'Skyline Residency Updated',
            'city' => 'Pimpri-Chinchwad',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'projects.project.updated',
            'auditable_type' => Project::class,
            'auditable_id' => $project->id,
            'user_id' => $admin->id,
        ]);

        $this->assertDatabaseHas('user_notifications', [
            'recipient_user_id' => $admin->id,
            'notifiable_type' => Project::class,
            'notifiable_id' => $project->id,
            'category' => 'project_master',
            'status' => 'unread',
        ]);
    }

    public function test_project_master_update_enforces_role_company_and_branch_scope(): void
    {
        $this->seed();

        $salesHead = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $company = Company::where('code', 'B360D')->firstOrFail();
        $otherCompany = Company::where('code', 'B360P')->firstOrFail();
        $otherBranch = Branch::where('company_id', $otherCompany->id)->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();

        $payload = [
            'company_id' => $company->id,
            'branch_id' => $otherBranch->id,
            'code' => 'SKY-PUN',
            'name' => 'Skyline Residency',
            'project_type' => 'residential',
            'city' => 'Pune',
            'state' => 'MH',
            'status' => 'active',
        ];

        $this->actingAs($salesHead)
            ->patchJson(route('projects.update', $project), $payload)
            ->assertForbidden();

        $this->actingAs($admin)
            ->patchJson(route('projects.update', $project), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['branch_id']);
    }

    public function test_project_profile_bootstrap_exposes_persisted_tower_and_unit_rows(): void
    {
        $this->seed();

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();
        $unitCount = ProjectUnit::where('project_id', $project->id)->count();

        $payload = app(Builder360Bootstrap::class)->forUser($admin);
        $projectRow = collect($payload['dashboard']['projects'])
            ->firstWhere('db_id', $project->id);

        $this->assertIsArray($projectRow);
        $this->assertSame($unitCount, $projectRow['units']);
        $this->assertNotEmpty($projectRow['tower_rows']);
        $this->assertNotEmpty($projectRow['unit_rows']);
        $this->assertSame($unitCount, collect($projectRow['tower_rows'])->sum('units'));
        $this->assertArrayHasKey('available', $projectRow['unit_status_counts']);
        $this->assertContains('SKY-A-1205', collect($projectRow['unit_rows'])->pluck('unit_code')->all());
    }

    public function test_project_team_assignment_create_revoke_audit_notification_and_bootstrap(): void
    {
        $this->seed();

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $assignee = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();

        $response = $this->actingAs($admin)
            ->postJson(route('projects.team-assignments.store', $project), [
                'user_id' => $assignee->id,
                'employee_id' => $assignee->employee?->id,
                'role_label' => 'CRM Project Owner',
                'department' => 'Sales',
                'access_level' => 'manage',
                'starts_on' => '2026-07-10',
                'notes' => 'Owns project CRM coordination for test.',
            ])
            ->assertCreated()
            ->assertJsonPath('message', 'Project team member assigned.')
            ->assertJsonPath('data.project_id', $project->id)
            ->assertJsonPath('data.user_id', $assignee->id)
            ->assertJsonPath('data.role_label', 'CRM Project Owner')
            ->assertJsonPath('data.access_level', 'manage');

        $assignmentId = $response->json('data.id');

        $this->assertDatabaseHas('project_team_assignments', [
            'id' => $assignmentId,
            'company_id' => $project->company_id,
            'project_id' => $project->id,
            'user_id' => $assignee->id,
            'role_label' => 'CRM Project Owner',
            'access_level' => 'manage',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'projects.team_assignment.created',
            'auditable_type' => ProjectTeamAssignment::class,
            'auditable_id' => $assignmentId,
            'user_id' => $admin->id,
        ]);

        $this->assertDatabaseHas('user_notifications', [
            'recipient_user_id' => $assignee->id,
            'notifiable_type' => ProjectTeamAssignment::class,
            'notifiable_id' => $assignmentId,
            'category' => 'project_team',
            'status' => 'unread',
        ]);

        $payload = app(Builder360Bootstrap::class)->forUser($admin);
        $projectRow = collect($payload['dashboard']['projects'])->firstWhere('db_id', $project->id);

        $this->assertSame('/projects/__PROJECT__/team-assignments', $payload['project_master_options']['team_assignment_store_url_template']);
        $this->assertSame('/projects/__PROJECT__/team-assignments/__ASSIGNMENT__', $payload['project_master_options']['team_assignment_revoke_url_template']);
        $this->assertTrue($payload['project_master_options']['can_manage_project_team']);
        $this->assertContains($assignee->id, collect($payload['project_master_options']['assignable_users'])->pluck('id')->all());
        $this->assertContains('manage', collect($payload['project_master_options']['team_access_levels'])->pluck('value')->all());
        $this->assertContains('CRM Project Owner', collect($projectRow['team_rows'])->pluck('role_label')->all());

        $this->actingAs($admin)
            ->postJson(route('projects.team-assignments.store', $project), [
                'user_id' => $assignee->id,
                'role_label' => 'Duplicate Owner',
                'access_level' => 'read',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id']);

        $assignment = ProjectTeamAssignment::findOrFail($assignmentId);

        $this->actingAs($admin)
            ->deleteJson(route('projects.team-assignments.destroy', [$project, $assignment]))
            ->assertOk()
            ->assertJsonPath('message', 'Project team member assignment revoked.')
            ->assertJsonPath('data.status', 'revoked');

        $this->assertDatabaseHas('project_team_assignments', [
            'id' => $assignmentId,
            'status' => 'revoked',
            'revoked_by_user_id' => $admin->id,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'projects.team_assignment.revoked',
            'auditable_type' => ProjectTeamAssignment::class,
            'auditable_id' => $assignmentId,
            'user_id' => $admin->id,
        ]);
    }

    public function test_project_team_assignment_enforces_role_and_company_scope(): void
    {
        $this->seed();

        $salesHead = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();
        $otherCompany = Company::where('code', 'B360P')->firstOrFail();
        $otherCompanyUser = User::factory()->create([
            'role_id' => $salesHead->role_id,
            'company_id' => $otherCompany->id,
            'name' => 'Cross Company User',
            'email' => 'cross.company.user@example.test',
            'status' => 'active',
        ]);

        $this->actingAs($salesHead)
            ->postJson(route('projects.team-assignments.store', $project), [
                'user_id' => $admin->id,
                'role_label' => 'Unauthorized Assignment',
                'access_level' => 'read',
            ])
            ->assertForbidden();

        $this->actingAs($partner)
            ->postJson(route('projects.team-assignments.store', $project), [
                'user_id' => $admin->id,
                'role_label' => 'Partner Assignment',
                'access_level' => 'read',
            ])
            ->assertForbidden();

        $this->actingAs($admin)
            ->postJson(route('projects.team-assignments.store', $project), [
                'user_id' => $otherCompanyUser->id,
                'role_label' => 'Cross Company User',
                'access_level' => 'read',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id']);
    }

    public function test_authorized_users_can_export_scoped_project_cost_roi_csv_with_audit(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();
        $otherCompanyProject = Project::where('code', 'MTO-PUN')->firstOrFail();

        $response = $this->actingAs($sales)
            ->get(route('projects.cost-roi.export', [
                'project_id' => $project->id,
                'format' => 'csv',
            ]))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $csv = $response->getContent();

        $this->assertStringContainsString('project_code,project_name,company_code', $csv);
        $this->assertStringContainsString('SKY-PUN', $csv);
        $this->assertStringContainsString('budget_used_percent', $csv);
        $this->assertStringContainsString('revenue_to_spend_roi_percent', $csv);
        $this->assertStringNotContainsString('MTO-PUN', $csv);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'projects.cost_roi.exported',
            'auditable_type' => 'system',
            'user_id' => $sales->id,
        ]);

        $audit = AuditEvent::where('event_type', 'projects.cost_roi.exported')->latest('id')->firstOrFail();
        $this->assertSame('csv', $audit->metadata['format']);
        $this->assertSame($project->id, (int) $audit->metadata['filters']['project_id']);
        $this->assertSame(1, $audit->metadata['row_count']);

        $this->actingAs($sales)
            ->getJson(route('projects.cost-roi.export', [
                'project_id' => $otherCompanyProject->id,
                'format' => 'csv',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id']);

        $this->actingAs($sales)
            ->getJson(route('projects.cost-roi.export', ['format' => 'xlsx']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['format']);

        $this->actingAs($partner)
            ->get(route('projects.cost-roi.export', ['format' => 'csv']))
            ->assertForbidden();
    }
}
