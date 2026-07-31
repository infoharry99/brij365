<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Services\Audit\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class AuditLoggerTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_logger_recursively_redacts_sensitive_metadata(): void
    {
        app(AuditLogger::class)->record(
            null,
            'security.metadata_sanitized',
            'Recorded audit metadata with central sanitization.',
            null,
            [
                'source' => 'audit_logger_test',
                'company_id' => 7,
                'amount' => 125000,
                'password' => 'plain-secret',
                'password_confirmation' => 'plain-secret',
                'api_key' => 'api-secret',
                'access_token' => 'token-secret',
                'employee' => [
                    'name' => 'Demo Employee',
                    'pan_number' => 'ABCDE1234F',
                    'aadhaar_number' => '123412341234',
                    'bank_account_number' => '1234567890',
                    'ifsc_code' => 'HDFC0001234',
                    'workflow' => [
                        'decision_note' => 'Approved by HR.',
                        'otp' => '456789',
                    ],
                ],
                'history' => [
                    [
                        'stage' => 'finance_review',
                        'client_secret' => 'nested-secret',
                    ],
                ],
            ],
        );

        $event = AuditEvent::query()->where('event_type', 'security.metadata_sanitized')->firstOrFail();

        $this->assertSame('audit_logger_test', $event->metadata['source']);
        $this->assertSame(7, $event->metadata['company_id']);
        $this->assertSame(125000, $event->metadata['amount']);
        $this->assertSame('Demo Employee', $event->metadata['employee']['name']);
        $this->assertSame('Approved by HR.', $event->metadata['employee']['workflow']['decision_note']);
        $this->assertSame('finance_review', $event->metadata['history'][0]['stage']);

        $this->assertSame('[redacted]', $event->metadata['password']);
        $this->assertSame('[redacted]', $event->metadata['password_confirmation']);
        $this->assertSame('[redacted]', $event->metadata['api_key']);
        $this->assertSame('[redacted]', $event->metadata['access_token']);
        $this->assertSame('[redacted]', $event->metadata['employee']['pan_number']);
        $this->assertSame('[redacted]', $event->metadata['employee']['aadhaar_number']);
        $this->assertSame('[redacted]', $event->metadata['employee']['bank_account_number']);
        $this->assertSame('[redacted]', $event->metadata['employee']['ifsc_code']);
        $this->assertSame('[redacted]', $event->metadata['employee']['workflow']['otp']);
        $this->assertSame('[redacted]', $event->metadata['history'][0]['client_secret']);
    }

    public function test_audit_logger_records_safe_request_context_without_query_string(): void
    {
        $request = Request::create(
            '/crm/leads?token=should-not-be-stored',
            'POST',
            server: [
                'HTTP_X_REQUEST_ID' => 'req-123456789',
                'HTTP_USER_AGENT' => 'Builder360 Test Browser/1.0',
                'REMOTE_ADDR' => '10.10.10.10',
            ],
        );

        app(AuditLogger::class)->record(
            null,
            'security.request_context',
            'Recorded audit request context.',
            null,
            ['source' => 'audit_logger_test'],
            $request,
        );

        $event = AuditEvent::query()->where('event_type', 'security.request_context')->firstOrFail();

        $this->assertSame('10.10.10.10', $event->ip_address);
        $this->assertSame('POST', $event->request_method);
        $this->assertSame('crm/leads', $event->request_path);
        $this->assertSame('req-123456789', $event->request_id);
        $this->assertSame('Builder360 Test Browser/1.0', $event->user_agent);
        $this->assertStringNotContainsString('token', $event->request_path);
    }
}
