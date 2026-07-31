<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RouteProtectionRegressionTest extends TestCase
{
    public function test_business_routes_require_authenticated_verified_active_erp_middleware(): void
    {
        $publicRoutes = [
            'health',
            'login',
            'login.store',
            'password.request',
            'password.email',
            'password.reset',
            'password.store',
        ];

        $publicStateChangingRoutes = [
            'prospect-inquiries.store',
        ];

        $authenticatedButNotVerifiedRoutes = [
            'logout',
            'verification.notice',
            'verification.verify',
            'verification.send',
        ];

        $externalRouteMiddleware = [
            'finance.payment-gateway.webhook' => ['web', 'throttle:60,1'],
            'calendar.guest-invitations.show' => ['web', 'signed'],
            'calendar.guest-invitations.respond' => ['web', 'signed', 'throttle:20,1'],
        ];

        $requiredBusinessMiddleware = [
            'web',
            'auth',
            'account.active',
            'verified',
            'throttle:erp-read',
            'erp.write_limit',
        ];

        $requiredAuthLifecycleMiddleware = [
            'web',
            'auth',
            'account.active',
        ];

        $requiredPublicStateChangingMiddleware = [
            'web',
            'throttle:30,1',
        ];

        $failures = [];

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();

            if ($name === null) {
                continue;
            }

            $middleware = array_values(array_unique($route->gatherMiddleware()));

            if (in_array($name, $publicRoutes, true)) {
                continue;
            }

            if (in_array($name, $publicStateChangingRoutes, true)) {
                $missing = array_values(array_diff($requiredPublicStateChangingMiddleware, $middleware));

                if ($missing !== []) {
                    $failures[] = $name.' is missing public intake middleware: '.implode(', ', $missing);
                }

                if (in_array('auth', $middleware, true)) {
                    $failures[] = $name.' must remain public intake callable and must not require browser auth middleware.';
                }

                continue;
            }

            if (in_array($name, $authenticatedButNotVerifiedRoutes, true)) {
                $missing = array_values(array_diff($requiredAuthLifecycleMiddleware, $middleware));

                if ($missing !== []) {
                    $failures[] = $name.' is missing auth lifecycle middleware: '.implode(', ', $missing);
                }

                continue;
            }

            if (array_key_exists($name, $externalRouteMiddleware)) {
                $missing = array_values(array_diff($externalRouteMiddleware[$name], $middleware));

                if ($missing !== []) {
                    $failures[] = $name.' is missing external-route middleware: '.implode(', ', $missing);
                }

                if (in_array('auth', $middleware, true)) {
                    $failures[] = $name.' must remain externally callable and must not require browser auth middleware.';
                }

                continue;
            }

            $missing = array_values(array_diff($requiredBusinessMiddleware, $middleware));

            if ($missing !== []) {
                $failures[] = $name.' ['.$route->uri().'] is missing ERP middleware: '.implode(', ', $missing);
            }
        }

        $this->assertEmpty($failures, implode(PHP_EOL, $failures));
    }

    public function test_raw_private_local_storage_routes_are_not_registered_by_default(): void
    {
        $routeNames = collect(Route::getRoutes())
            ->map(fn ($route): ?string => $route->getName())
            ->filter()
            ->values();

        $this->assertFalse($routeNames->contains('storage.local'));
        $this->assertFalse($routeNames->contains('storage.local.upload'));
    }

    public function test_only_signed_payment_webhook_is_csrf_exempt(): void
    {
        $allowedExemptRoutes = [
            'finance.payment-gateway.webhook',
        ];

        $unexpected = [];
        $missingAllowed = [];

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();

            if ($name === null) {
                continue;
            }

            $excluded = array_values(array_unique($route->excludedMiddleware()));

            if (in_array(ValidateCsrfToken::class, $excluded, true)
                && ! in_array($name, $allowedExemptRoutes, true)) {
                $unexpected[] = $name.' ['.$route->uri().']';
            }
        }

        foreach ($allowedExemptRoutes as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);

            if ($route === null || ! in_array(ValidateCsrfToken::class, $route->excludedMiddleware(), true)) {
                $missingAllowed[] = $routeName;
            }
        }

        $this->assertEmpty($unexpected, 'Unexpected CSRF-exempt routes: '.implode(', ', $unexpected));
        $this->assertEmpty($missingAllowed, 'Allowed CSRF-exempt routes not configured: '.implode(', ', $missingAllowed));
    }
}
