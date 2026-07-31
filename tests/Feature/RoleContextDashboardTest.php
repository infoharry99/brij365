<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Company;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleContextDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_director_can_switch_dashboard_context_to_every_seeded_role(): void
    {
        $this->seed();

        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();

        foreach ($this->expectedDashboardTitles() as $roleSlug => $expectedTitle) {
            $response = $this->actingAs($director)
                ->postJson(route('builder360.role-context.store'), ['role_slug' => $roleSlug])
                ->assertOk()
                ->assertJsonPath('active_role_context.role_slug', $roleSlug)
                ->assertJsonPath('user.role', $roleSlug)
                ->assertJsonPath('role_dashboard.role_slug', $roleSlug)
                ->assertJsonPath('role_dashboard.title', $expectedTitle)
                ->assertJsonStructure([
                    'active_role_context',
                    'role_dashboard' => [
                        'role_slug',
                        'title',
                        'subtitle',
                        'period',
                        'context',
                        'primary_route',
                        'primary_label',
                        'stats',
                        'charts',
                        'alerts',
                        'tables',
                        'quick_actions',
                        'sections',
                    ],
                    'dashboard',
                    'roles',
                    'modules',
                ]);

            $this->assertNormalizedRoleDashboard($response->json('role_dashboard'));
        }
    }

    public function test_system_admin_can_switch_dashboard_context(): void
    {
        $this->seed();

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();

        $this->actingAs($admin)
            ->postJson(route('builder360.role-context.store'), ['role_slug' => 'auditor'])
            ->assertOk()
            ->assertJsonPath('active_role_context.role_slug', 'auditor')
            ->assertJsonPath('active_role_context.actor_role_slug', 'system_admin')
            ->assertJsonPath('active_role_context.is_impersonated_preview', true);
    }

    public function test_role_preview_does_not_advertise_routes_the_authenticated_actor_cannot_open(): void
    {
        $this->seed();

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();

        $response = $this->actingAs($admin)
            ->postJson(route('builder360.role-context.store'), ['role_slug' => 'director'])
            ->assertOk();

        $moduleRoutes = collect($response->json('modules'))
            ->flatMap(fn (array $group) => collect($group['items'] ?? [])->pluck('route'));
        $leadMetric = collect($response->json('role_dashboard.stats'))->firstWhere('label', 'Leads');

        $this->assertNotContains('leads', $moduleRoutes);
        $this->assertIsArray($leadMetric);
        $this->assertNull($leadMetric['route']);
        $this->assertFalse($leadMetric['is_actionable']);
    }

    public function test_employee_buyer_and_partner_cannot_switch_to_internal_roles(): void
    {
        $this->seed();

        foreach ([
            'amit.verma@builder360.test',
            'rohan.shah@example.test',
            'sameer.bafna@partners.builder360.test',
        ] as $email) {
            $user = User::where('email', $email)->firstOrFail();

            $this->actingAs($user)
                ->postJson(route('builder360.role-context.store'), ['role_slug' => 'director'])
                ->assertForbidden();
        }
    }

    public function test_role_context_switch_is_audited(): void
    {
        $this->seed();

        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();

        $this->actingAs($director)
            ->postJson(route('builder360.role-context.store'), ['role_slug' => 'payroll'])
            ->assertOk();

        $this->assertDatabaseHas('audit_events', [
            'user_id' => $director->id,
            'event_type' => 'dashboard.role_context.changed',
            'auditable_type' => 'system',
        ]);

        $event = AuditEvent::where('event_type', 'dashboard.role_context.changed')->latest('id')->firstOrFail();

        $this->assertSame('payroll', $event->metadata['selected_role_slug'] ?? null);
        $this->assertSame($director->id, $event->metadata['actor_user_id'] ?? null);
    }

    public function test_role_scoped_bootstrap_endpoint_uses_selected_session_role(): void
    {
        $this->seed();

        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();

        $this->actingAs($director)
            ->withSession(['builder360.selected_role_slug' => 'channel_partner'])
            ->getJson(route('builder360.bootstrap'))
            ->assertOk()
            ->assertJsonPath('active_role_context.role_slug', 'channel_partner')
            ->assertJsonPath('user.role', 'channel_partner')
            ->assertJsonPath('role_dashboard.role_slug', 'channel_partner')
            ->assertJsonPath('role_dashboard.title', 'Channel Partner Dashboard')
            ->assertJsonPath('partner_dashboard.title', 'Channel Partner Dashboard');
    }

    public function test_project_context_can_be_selected_reset_and_persisted_in_bootstrap(): void
    {
        $this->seed();

        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $project = Project::query()->orderBy('id')->firstOrFail();

        $this->actingAs($director)
            ->postJson(route('builder360.project-context.store'), ['project_id' => $project->id])
            ->assertOk()
            ->assertJsonPath('active_project_context.mode', 'selected_project')
            ->assertJsonPath('active_project_context.project_id', $project->id)
            ->assertJsonPath('selected_project_id', $project->id)
            ->assertJsonPath('dashboard.scope.selected_project_id', $project->id);

        $this->actingAs($director)
            ->withSession(['builder360.selected_project_id' => $project->id])
            ->getJson(route('builder360.bootstrap'))
            ->assertOk()
            ->assertJsonPath('active_project_context.project_id', $project->id);

        $this->actingAs($director)
            ->postJson(route('builder360.project-context.store'), ['project_id' => null])
            ->assertOk()
            ->assertJsonPath('active_project_context.mode', 'all_projects')
            ->assertJsonPath('active_project_context.project_id', null)
            ->assertJsonPath('selected_project_id', null);
    }

    public function test_dashboard_period_context_can_be_changed_and_persisted(): void
    {
        $this->seed();

        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();

        $this->actingAs($director)
            ->postJson(route('builder360.dashboard-context.store'), ['period_key' => 'this_week'])
            ->assertOk()
            ->assertJsonPath('active_dashboard_period.key', 'this_week')
            ->assertJsonPath('role_dashboard.period.key', 'this_week')
            ->assertJsonStructure([
                'active_dashboard_period' => ['key', 'label', 'date_from', 'date_to', 'options'],
                'role_dashboard' => ['period'],
            ]);

        $this->actingAs($director)
            ->withSession(['builder360.dashboard_period' => ['key' => 'this_week']])
            ->getJson(route('builder360.bootstrap'))
            ->assertOk()
            ->assertJsonPath('active_dashboard_period.key', 'this_week')
            ->assertJsonPath('role_dashboard.period.key', 'this_week');

        $this->assertDatabaseHas('audit_events', [
            'user_id' => $director->id,
            'event_type' => 'dashboard.period_context.changed',
            'auditable_type' => 'system',
        ]);
    }

    public function test_dashboard_period_context_validates_invalid_and_custom_ranges(): void
    {
        $this->seed();

        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();

        $this->actingAs($director)
            ->postJson(route('builder360.dashboard-context.store'), ['period_key' => 'bad'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['period_key']);

        $this->actingAs($director)
            ->postJson(route('builder360.dashboard-context.store'), ['period_key' => 'custom'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date_from', 'date_to']);

        $this->actingAs($director)
            ->postJson(route('builder360.dashboard-context.store'), [
                'period_key' => 'custom',
                'date_from' => '2026-07-10',
                'date_to' => '2026-07-01',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date_to']);
    }

    public function test_project_context_rejects_out_of_scope_project_and_audits_valid_change(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $externalCompany = Company::create([
            'code' => 'EXTCO',
            'name' => 'External Company',
            'legal_name' => 'External Company Pvt Ltd',
            'state' => 'MH',
            'status' => 'active',
        ]);
        $externalProject = Project::create([
            'company_id' => $externalCompany->id,
            'code' => 'EXT-PROJ',
            'name' => 'External Project',
            'project_type' => 'residential',
            'city' => 'Mumbai',
            'state' => 'MH',
            'status' => 'active',
            'budget_amount' => 1000000,
            'target_roi_percent' => 12,
        ]);
        $visibleProject = Project::query()->where('company_id', $sales->company_id)->firstOrFail();

        $this->actingAs($sales)
            ->postJson(route('builder360.project-context.store'), ['project_id' => $externalProject->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id']);

        $this->actingAs($sales)
            ->postJson(route('builder360.project-context.store'), ['project_id' => $visibleProject->id])
            ->assertOk()
            ->assertJsonPath('active_project_context.project_id', $visibleProject->id);

        $this->assertDatabaseHas('audit_events', [
            'user_id' => $sales->id,
            'event_type' => 'dashboard.project_context.changed',
            'auditable_type' => 'system',
        ]);
    }

    public function test_invalid_project_context_is_cleared_after_role_switch(): void
    {
        $this->seed();

        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $project = Project::query()->whereDoesntHave('bookings')->firstOrFail();

        $this->actingAs($director)
            ->withSession(['builder360.selected_project_id' => $project->id])
            ->postJson(route('builder360.role-context.store'), ['role_slug' => 'buyer'])
            ->assertOk()
            ->assertJsonPath('active_role_context.role_slug', 'buyer')
            ->assertJsonPath('active_project_context.mode', 'all_projects')
            ->assertJsonPath('active_project_context.project_id', null);
    }

    /**
     * @return array<string, string>
     */
    private function expectedDashboardTitles(): array
    {
        return [
            'director' => 'Management Dashboard',
            'sales_head' => 'Sales Dashboard',
            'construction_head' => 'Construction Dashboard',
            'finance_head' => 'Finance Dashboard',
            'hr_manager' => 'HR Dashboard',
            'payroll' => 'Payroll Dashboard',
            'recruiter' => 'Recruitment Dashboard',
            'auditor' => 'Audit & Governance Dashboard',
            'compliance' => 'Compliance Dashboard',
            'system_admin' => 'System Administration Dashboard',
            'employee' => 'Employee Dashboard',
            'buyer' => 'Buyer Dashboard',
            'channel_partner' => 'Channel Partner Dashboard',
            'executive_partner_broker' => 'Executive Partner Broker Dashboard',
        ];
    }

    /**
     * @param  array<string, mixed>  $dashboard
     */
    private function assertNormalizedRoleDashboard(array $dashboard): void
    {
        $this->assertIsArray($dashboard['stats'] ?? null);
        $this->assertEquals(array_values($dashboard['stats']), $dashboard['stats']);
        $this->assertIsArray($dashboard['context'] ?? null);
        $this->assertIsArray($dashboard['period'] ?? null);
        $this->assertArrayHasKey('key', $dashboard['period']);
        $this->assertArrayHasKey('options', $dashboard['period']);
        $this->assertIsArray($dashboard['charts'] ?? null);
        $this->assertIsArray($dashboard['alerts'] ?? null);
        $this->assertIsArray($dashboard['tables'] ?? null);
        $this->assertIsArray($dashboard['quick_actions'] ?? null);
        $this->assertIsArray($dashboard['sections'] ?? null);
        $this->assertEquals(array_values($dashboard['sections']), $dashboard['sections']);

        foreach ($dashboard['stats'] as $stat) {
            foreach (['key', 'label', 'value', 'value_type', 'source', 'route', 'route_filter', 'is_actionable'] as $key) {
                $this->assertArrayHasKey($key, $stat);
            }
            $this->assertArrayHasKey('route', $stat);
            $this->assertIsArray($stat['route_filter'] ?? null);
        }

        foreach (['charts', 'tables'] as $group) {
            $this->assertEquals(array_values($dashboard[$group]), $dashboard[$group]);
            foreach ($dashboard[$group] as $item) {
                $this->assertArrayHasKey('rows', $item);
                $this->assertIsArray($item['rows']);
                $this->assertArrayHasKey('is_actionable', $item);
            }
        }

        foreach ($dashboard['alerts'] as $alert) {
            $this->assertArrayHasKey('key', $alert);
            $this->assertArrayHasKey('is_actionable', $alert);
        }

        foreach ($dashboard['quick_actions'] as $action) {
            $this->assertArrayHasKey('label', $action);
            $this->assertArrayHasKey('route', $action);
            $this->assertTrue((bool) $action['is_actionable']);
        }

        foreach ($dashboard['sections'] as $section) {
            $this->assertIsArray($section['rows'] ?? null);
            $this->assertEquals(array_values($section['rows']), $section['rows']);
            $this->assertArrayHasKey('route', $section);
            $this->assertIsArray($section['route_filter'] ?? null);

            foreach ($section['rows'] as $row) {
                $this->assertArrayHasKey('route', $row);
                $this->assertIsArray($row['route_filter'] ?? null);
            }
        }
    }
}
