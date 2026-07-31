<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Company;
use App\Models\Partner;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class RequestIdMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_response_gets_generated_request_id_header(): void
    {
        $response = $this->getJson(route('health'));

        $response->assertOk();

        $requestId = $response->headers->get('X-Request-Id');

        $this->assertIsString($requestId);
        $this->assertNotSame('', trim($requestId));
        $this->assertLessThanOrEqual(120, strlen($requestId));
        $response->assertJsonMissingPath('request_id');
    }

    public function test_existing_correlation_id_is_preserved_as_response_request_id(): void
    {
        $response = $this
            ->withHeaders(['X-Correlation-Id' => 'client-correlation-123'])
            ->getJson(route('health'));

        $response
            ->assertOk()
            ->assertHeader('X-Request-Id', 'client-correlation-123');
    }

    public function test_generated_request_id_is_recorded_on_audited_workflow(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $company = Company::where('code', 'B360D')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();
        $partner = Partner::where('code', 'CP-001')->firstOrFail();

        $response = $this
            ->actingAs($sales)
            ->postJson(route('crm.leads.store'), [
                'company_id' => $company->id,
                'project_id' => $project->id,
                'partner_id' => $partner->id,
                'customer_name' => 'Generated Request Id Buyer',
                'customer_email' => 'generated.request.id@example.test',
                'customer_phone' => '+91 98111 44077',
                'source' => 'Channel walk-in',
                'stage' => 'New',
                'expected_value' => 9700000,
                'follow_up_at' => now()->addDay()->toISOString(),
            ]);

        $response->assertCreated();

        $requestId = $response->headers->get('X-Request-Id');

        $this->assertIsString($requestId);
        $this->assertNotSame('', trim($requestId));

        $event = AuditEvent::query()
            ->where('event_type', 'crm.lead.created')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($requestId, $event->request_id);
        $this->assertSame('POST', $event->request_method);
        $this->assertSame('crm/leads', $event->request_path);
    }

    public function test_json_authentication_errors_include_request_id_in_body(): void
    {
        $response = $this
            ->withHeaders(['X-Request-Id' => 'auth-error-request-001'])
            ->getJson(route('notifications.summary'));

        $response
            ->assertUnauthorized()
            ->assertHeader('X-Request-Id', 'auth-error-request-001')
            ->assertJsonPath('request_id', 'auth-error-request-001');
    }

    public function test_json_validation_errors_include_request_id_in_body(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();

        $response = $this
            ->withHeaders(['X-Request-Id' => 'validation-error-request-001'])
            ->actingAs($sales)
            ->postJson(route('crm.leads.store'), []);

        $response
            ->assertUnprocessable()
            ->assertHeader('X-Request-Id', 'validation-error-request-001')
            ->assertJsonPath('request_id', 'validation-error-request-001')
            ->assertJsonValidationErrors(['customer_name']);
    }

    public function test_unhandled_json_server_errors_are_sanitized_and_correlated(): void
    {
        Config::set('app.debug', false);
        Config::set('security.exception_responses.include_debug_details', false);

        Route::get('/synthetic-json-exception', function (): void {
            throw new RuntimeException('Sensitive internal DSN leaked in exception message.');
        });

        $response = $this
            ->withHeaders(['X-Request-Id' => 'server-error-request-001'])
            ->getJson('/synthetic-json-exception');

        $response
            ->assertStatus(500)
            ->assertHeader('X-Request-Id', 'server-error-request-001')
            ->assertJsonPath('request_id', 'server-error-request-001')
            ->assertJsonPath('message', 'An unexpected server error occurred. Provide the request_id to support.')
            ->assertJsonMissingPath('exception')
            ->assertJsonMissingPath('file')
            ->assertJsonMissingPath('line');

        $this->assertStringNotContainsString('Sensitive internal DSN', $response->getContent());
    }

    public function test_local_json_server_errors_can_include_debug_details_when_explicitly_enabled(): void
    {
        Config::set('app.debug', true);
        Config::set('security.exception_responses.include_debug_details', true);

        Route::get('/synthetic-json-debug-exception', function (): void {
            throw new RuntimeException('Developer-visible synthetic failure.');
        });

        $response = $this
            ->withHeaders(['X-Request-Id' => 'debug-error-request-001'])
            ->getJson('/synthetic-json-debug-exception');

        $response
            ->assertStatus(500)
            ->assertHeader('X-Request-Id', 'debug-error-request-001')
            ->assertJsonPath('request_id', 'debug-error-request-001')
            ->assertJsonPath('message', 'Developer-visible synthetic failure.')
            ->assertJsonPath('exception', 'RuntimeException')
            ->assertJsonStructure(['file', 'line']);
    }
}
