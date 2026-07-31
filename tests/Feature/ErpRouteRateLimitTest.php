<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class ErpRouteRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_erp_read_routes_are_rate_limited_per_user_and_ip(): void
    {
        $this->seed();

        Config::set('security.rate_limits.erp_read_per_minute', 1);

        $user = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();

        $this->actingAs($user)
            ->withServerVariables(['REMOTE_ADDR' => '10.10.10.21'])
            ->getJson(route('notifications.summary'))
            ->assertOk();

        $this->actingAs($user)
            ->withServerVariables(['REMOTE_ADDR' => '10.10.10.21'])
            ->getJson(route('notifications.summary'))
            ->assertTooManyRequests()
            ->assertHeader('Retry-After');
    }

    public function test_state_changing_erp_routes_are_rate_limited_without_blocking_safe_reads(): void
    {
        $this->seed();

        Config::set('security.rate_limits.erp_read_per_minute', 10);
        Config::set('security.rate_limits.erp_write_per_minute', 1);

        $user = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();

        $this->actingAs($user)
            ->withServerVariables(['REMOTE_ADDR' => '10.10.10.22'])
            ->patchJson(route('notifications.read-all'))
            ->assertOk()
            ->assertHeader('X-RateLimit-Limit', '1')
            ->assertHeader('X-RateLimit-Remaining', '0');

        $this->actingAs($user)
            ->withServerVariables(['REMOTE_ADDR' => '10.10.10.22'])
            ->patchJson(route('notifications.read-all'))
            ->assertTooManyRequests()
            ->assertHeader('Retry-After')
            ->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'Too many state-changing ERP requests'));

        $this->actingAs($user)
            ->withServerVariables(['REMOTE_ADDR' => '10.10.10.22'])
            ->getJson(route('notifications.summary'))
            ->assertOk();
    }

    protected function tearDown(): void
    {
        RateLimiter::clear('erp-write:guest|10.10.10.21');
        RateLimiter::clear('erp-write:guest|10.10.10.22');

        parent::tearDown();
    }
}
