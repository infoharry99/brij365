<?php

namespace Tests\Feature;

use App\Application\Dashboard\Actions\ShowRoleDashboard;
use App\Application\Dashboard\Data\DashboardContextData;
use App\Application\Dashboard\Data\DashboardPageData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassicMvcDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_classic_dashboard_renders_without_react_or_vite_shell(): void
    {
        $this->seed();

        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();

        $this->actingAs($director)
            ->get(route('builder360.dashboard'))
            ->assertOk()
            ->assertSee('Management Dashboard')
            ->assertSee('Builder360')
            ->assertSee('Dashboard')
            ->assertDontSee('@vite', false)
            ->assertDontSee('id="root"', false)
            ->assertDontSee('builder360-bootstrap', false)
            ->assertDontSee('resources/js/app.jsx', false);
    }

    public function test_legacy_dashboard_route_redirects_to_the_classic_workspace(): void
    {
        $this->seed();

        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();

        $this->actingAs($director)
            ->get(route('builder360.legacy-app'))
            ->assertRedirect(route('builder360.dashboard'));
    }

    public function test_classic_dashboard_requires_authentication(): void
    {
        $this->get(route('builder360.dashboard'))
            ->assertRedirect(route('login', absolute: false));
    }

    public function test_dashboard_action_returns_minimal_immutable_page_data(): void
    {
        $this->seed();
        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();

        $page = app(ShowRoleDashboard::class)->execute(
            $director,
            new DashboardContextData('director', null, null),
        );

        $this->assertInstanceOf(DashboardPageData::class, $page);
        $this->assertSame('director', $page->dashboard['role_slug']);
        $this->assertArrayHasKey('active_role_context', $page->navigationContext);
        $this->assertArrayNotHasKey('crm_leads', $page->navigationContext);
        $this->assertArrayNotHasKey('collaboration_task_options', $page->navigationContext);
    }

    public function test_approval_center_renders_in_the_classic_shell_for_browser_requests(): void
    {
        $this->seed();

        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();

        $this->actingAs($director)
            ->get(route('builder360.approvals.index'))
            ->assertOk()
            ->assertSee('Approval Center')
            ->assertSee('records awaiting decision')
            ->assertSee('class="b360-shell"', false)
            ->assertDontSee('id="root"', false)
            ->assertDontSee('resources/js/app.jsx', false);
    }

    public function test_notifications_render_in_the_classic_shell_with_server_forms(): void
    {
        $this->seed();

        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();

        $this->actingAs($director)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Notifications')
            ->assertSee('Mark all read')
            ->assertSee('class="b360-shell"', false)
            ->assertSee('method="POST"', false)
            ->assertDontSee('@vite', false)
            ->assertDontSee('id="root"', false);
    }

    public function test_reports_navigation_renders_a_server_report_workspace(): void
    {
        $this->seed();
        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();

        $this->actingAs($director)
            ->get(route('governance.report-register.index'))
            ->assertOk()
            ->assertSee('Reports &amp; Analytics', false)
            ->assertSee('Report filters')
            ->assertSee('Export CSV')
            ->assertSee('class="b360-shell"', false)
            ->assertDontSee('id="root"', false);
    }
}
