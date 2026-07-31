<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class Builder360VerificationCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_builder360_verify_outputs_safe_json_summary(): void
    {
        $this->seed();

        $exitCode = Artisan::call('builder360:verify', ['--json' => true]);
        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exitCode);
        $this->assertSame('ok', $payload['status']);
        $this->assertSame('sqlite', $payload['database']['connection']);
        $this->assertSame('ok', $payload['readiness']['status']);
        $this->assertSame('present', $payload['artifacts']['classic_assets']);
        $this->assertGreaterThan(0, $payload['artifacts']['route_count']);
        $this->assertTrue($payload['security']['app_key_configured']);
        $this->assertArrayNotHasKey('app_key', $payload['security']);
        $this->assertArrayNotHasKey('database_path', $payload['database']);
        $this->assertSame([], $payload['failures']);
    }

    public function test_builder360_verify_fails_when_required_security_configuration_is_missing(): void
    {
        $this->seed();

        Config::set('app.key', null);

        $exitCode = Artisan::call('builder360:verify', ['--json' => true]);
        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(1, $exitCode);
        $this->assertSame('degraded', $payload['status']);
        $this->assertFalse($payload['security']['app_key_configured']);
        $this->assertContains('app_key_configured', $payload['failures']);
        $this->assertContains('readiness', $payload['failures']);
    }

}
