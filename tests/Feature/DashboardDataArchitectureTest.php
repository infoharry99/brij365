<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardDataArchitectureTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_role_receives_complete_dashboard_data_architecture(): void
    {
        $this->seed();

        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();

        foreach ($this->roleSlugs() as $roleSlug) {
            $dashboard = $this->actingAs($director)
                ->postJson(route('builder360.role-context.store'), ['role_slug' => $roleSlug])
                ->assertOk()
                ->json('role_dashboard');

            $this->assertIsArray($dashboard, "Dashboard missing for {$roleSlug}");
            $this->assertSame($roleSlug, $dashboard['role_slug']);
            $this->assertNotEmpty($dashboard['title']);
            $this->assertIsArray($dashboard['context']);
            $this->assertIsArray($dashboard['period']);
            $this->assertIsArray($dashboard['stats']);
            $this->assertIsArray($dashboard['charts']);
            $this->assertIsArray($dashboard['alerts']);
            $this->assertIsArray($dashboard['tables']);
            $this->assertIsArray($dashboard['quick_actions']);

            $this->assertNotEmpty($dashboard['stats'], "Stats missing for {$roleSlug}");
            $this->assertNotEmpty($dashboard['charts'], "Charts missing for {$roleSlug}");
            $this->assertNotEmpty($dashboard['tables'], "Tables missing for {$roleSlug}");
            $this->assertNotEmpty($dashboard['quick_actions'], "Quick actions missing for {$roleSlug}");

            foreach ($dashboard['stats'] as $stat) {
                $this->assertDashboardItemContract($stat);
            }

            foreach ($dashboard['charts'] as $chart) {
                $this->assertArrayHasKey('rows', $chart);
                $this->assertIsArray($chart['rows']);
                foreach ($chart['rows'] as $row) {
                    $this->assertDashboardItemContract($row);
                }
            }

            foreach ($dashboard['tables'] as $table) {
                $this->assertArrayHasKey('rows', $table);
                $this->assertIsArray($table['rows']);
                foreach ($table['rows'] as $row) {
                    $this->assertDashboardItemContract($row);
                }
            }
        }
    }

    public function test_external_role_dashboard_routes_are_restricted_to_portal_destinations(): void
    {
        $this->seed();

        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();

        $allowedByRole = [
            'buyer' => ['dashboard', 'buyer', 'complaints', 'documents', 'notifications', 'profile'],
            'employee' => ['dashboard', 'ess', 'tasks', 'calendar', 'notifications', 'profile'],
            'channel_partner' => ['dashboard', 'partner', 'leads', 'qualification', 'sitevisits', 'sales', 'collections', 'funnel', 'performance', 'documents', 'notifications', 'profile'],
            'executive_partner_broker' => ['dashboard', 'partner', 'leads', 'qualification', 'sitevisits', 'sales', 'collections', 'funnel', 'performance', 'documents', 'notifications', 'profile'],
        ];

        foreach ($allowedByRole as $roleSlug => $allowedRoutes) {
            $dashboard = $this->actingAs($director)
                ->postJson(route('builder360.role-context.store'), ['role_slug' => $roleSlug])
                ->assertOk()
                ->json('role_dashboard');

            foreach ($this->dashboardRoutes($dashboard) as $route) {
                $baseRoute = strtok($route, '?') ?: $route;
                $this->assertContains($baseRoute, $allowedRoutes, "{$roleSlug} received restricted dashboard route [{$route}]");
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function roleSlugs(): array
    {
        return [
            'director',
            'sales_head',
            'construction_head',
            'finance_head',
            'hr_manager',
            'payroll',
            'recruiter',
            'auditor',
            'compliance',
            'system_admin',
            'employee',
            'buyer',
            'channel_partner',
            'executive_partner_broker',
        ];
    }

    /**
     * @param array<string, mixed> $item
     */
    private function assertDashboardItemContract(array $item): void
    {
        foreach (['key', 'label', 'value', 'value_type', 'source', 'route', 'route_filter', 'empty', 'is_actionable'] as $key) {
            $this->assertArrayHasKey($key, $item);
        }

        $this->assertIsArray($item['route_filter']);
        $this->assertIsBool($item['is_actionable']);
    }

    /**
     * @param array<string, mixed> $dashboard
     * @return array<int, string>
     */
    private function dashboardRoutes(array $dashboard): array
    {
        $routes = [];
        $groups = ['stats', 'alerts', 'quick_actions', 'sections', 'charts', 'tables'];

        foreach ($groups as $group) {
            foreach (($dashboard[$group] ?? []) as $item) {
                if (is_array($item) && ! empty($item['route']) && $item['is_actionable'] !== false) {
                    $routes[] = $item['route'];
                }

                foreach (($item['rows'] ?? []) as $row) {
                    if (is_array($row) && ! empty($row['route']) && $row['is_actionable'] !== false) {
                        $routes[] = $row['route'];
                    }
                }
            }
        }

        return array_values(array_unique($routes));
    }
}
