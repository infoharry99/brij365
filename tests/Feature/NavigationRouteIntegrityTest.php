<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Builder360\Builder360Bootstrap;
use App\Support\Builder360ModuleNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class NavigationRouteIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_visible_role_navigation_destination_is_authorized_and_renderable(): void
    {
        $this->seed();

        $users = User::query()
            ->with('role')
            ->where('status', 'active')
            ->whereHas('role', fn ($query) => $query->whereIn('slug', [
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
            ]))
            ->get()
            ->unique(fn (User $user): string => (string) $user->role?->slug);

        $this->assertCount(14, $users);

        foreach ($users as $user) {
            $bootstrap = app(Builder360Bootstrap::class)->forUser($user, $user);
            $routes = collect($bootstrap['modules'])
                ->flatMap(fn (array $group) => $group['items'] ?? [])
                ->pluck('route')
                ->filter()
                ->unique()
                ->values();

            $this->assertNotEmpty($routes, "No navigation was generated for role [{$user->role?->slug}].");

            foreach ($routes as $route) {
                $url = Builder360ModuleNavigation::urlFor($route, $bootstrap);
                $this->assertNotNull($url, "Visible route [{$route}] has no named Laravel destination.");

                $response = $this->getWithoutLeakingOutputBuffers(
                    user: $user,
                    url: $url,
                    route: (string) $route,
                );

                // Mailbox is intentionally a resolver: it sends the user to the
                // assigned account workspace or to the account-connection screen.
                // Follow only that known, same-module canonical redirect so an
                // auth/login redirect can never make this integrity check pass.
                if ($route === 'mailbox' && $response->isRedirect()) {
                    $target = (string) $response->headers->get('Location');
                    $targetPath = (string) (parse_url($target, PHP_URL_PATH) ?: '');

                    $this->assertStringStartsWith(
                        '/mailbox/accounts',
                        $targetPath,
                        "Visible Mailbox route redirected outside its account workspace to [{$target}].",
                    );

                    $response = $this->getWithoutLeakingOutputBuffers(
                        user: $user,
                        url: $target,
                        route: (string) $route.' (canonical redirect)',
                    );
                }

                $this->assertSame(
                    200,
                    $response->getStatusCode(),
                    "Role [{$user->role?->slug}] cannot open visible route [{$route}] at [{$url}].",
                );
            }
        }
    }

    private function getWithoutLeakingOutputBuffers(User $user, string $url, string $route): TestResponse
    {
        $levelBeforeRequest = ob_get_level();
        $response = $this->actingAs($user)->get($url);
        $levelAfterRequest = ob_get_level();
        $bufferState = $levelAfterRequest === $levelBeforeRequest
            ? 'none'
            : json_encode(array_slice(ob_get_status(true), $levelBeforeRequest), JSON_THROW_ON_ERROR);

        if ($levelAfterRequest > $levelBeforeRequest) {
            while (ob_get_level() > $levelBeforeRequest) {
                ob_end_clean();
            }
        }

        $this->assertSame(
            $levelBeforeRequest,
            $levelAfterRequest,
            "Visible route [{$route}] at [{$url}] leaked an output buffer for role [{$user->role?->slug}]. Buffer state: {$bufferState}",
        );

        return $response;
    }
}
