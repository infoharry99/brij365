<?php

namespace App\Services\Audit;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogger
{
    private const REDACTED_VALUE = '[redacted]';

    /**
     * @var array<int, string>
     */
    private const SENSITIVE_CONTAINS_KEY_PATTERNS = [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'token',
        'remember_token',
        'secret',
        'api_key',
        'apikey',
        'access_key',
        'private_key',
        'client_secret',
        'otp',
    ];

    /**
     * @var array<int, string>
     */
    private const SENSITIVE_TOKEN_KEY_PATTERNS = [
        'pin',
        'aadhaar',
        'aadhar',
        'pan',
        'bank_account',
        'account_number',
        'account_no',
        'iban',
        'ifsc',
        'upi',
    ];

    /**
     * @param array<string, mixed> $metadata
     */
    public function record(
        ?User $user,
        string $eventType,
        string $action,
        ?Model $auditable = null,
        array $metadata = [],
        ?Request $request = null,
    ): AuditEvent {
        return AuditEvent::create([
            'user_id' => $user?->id,
            'event_type' => $eventType,
            'auditable_type' => $auditable ? $auditable::class : 'system',
            'auditable_id' => $auditable?->getKey(),
            'action' => $action,
            'metadata' => $this->sanitizeMetadata($metadata),
            'ip_address' => $request?->ip(),
            'request_method' => $request ? strtoupper($request->method()) : null,
            'request_path' => $request ? substr($request->path(), 0, 255) : null,
            'request_id' => $request ? $this->requestId($request) : null,
            'user_agent' => $request?->userAgent() ? substr((string) $request->userAgent(), 0, 512) : null,
        ]);
    }

    public function redactionSelfTestPasses(): bool
    {
        $sanitized = $this->sanitizeMetadata([
            'password' => 'secret-password',
            'api_key' => 'secret-api-key',
            'employee' => [
                'pan_number' => 'ABCDE1234F',
                'aadhaar_number' => '123456789012',
                'bank_account_number' => '001122334455',
                'ifsc_code' => 'TEST0000001',
            ],
            'history' => [
                ['client_secret' => 'secret-client-value'],
            ],
            'safe_key' => 'safe-value',
        ]);

        return ($sanitized['password'] ?? null) === self::REDACTED_VALUE
            && ($sanitized['api_key'] ?? null) === self::REDACTED_VALUE
            && ($sanitized['employee']['pan_number'] ?? null) === self::REDACTED_VALUE
            && ($sanitized['employee']['aadhaar_number'] ?? null) === self::REDACTED_VALUE
            && ($sanitized['employee']['bank_account_number'] ?? null) === self::REDACTED_VALUE
            && ($sanitized['employee']['ifsc_code'] ?? null) === self::REDACTED_VALUE
            && ($sanitized['history'][0]['client_secret'] ?? null) === self::REDACTED_VALUE
            && ($sanitized['safe_key'] ?? null) === 'safe-value';
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private function sanitizeMetadata(array $metadata): array
    {
        return $this->sanitizeArray($metadata);
    }

    /**
     * @param array<array-key, mixed> $items
     * @return array<array-key, mixed>
     */
    private function sanitizeArray(array $items): array
    {
        foreach ($items as $key => $value) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                $items[$key] = self::REDACTED_VALUE;

                continue;
            }

            if (is_array($value)) {
                $items[$key] = $this->sanitizeArray($value);
            }
        }

        return $items;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower(trim($key));
        $normalized = str_replace(['-', ' '], '_', $normalized);

        foreach (self::SENSITIVE_CONTAINS_KEY_PATTERNS as $pattern) {
            if ($normalized === $pattern || str_contains($normalized, $pattern)) {
                return true;
            }
        }

        foreach (self::SENSITIVE_TOKEN_KEY_PATTERNS as $pattern) {
            if (
                $normalized === $pattern
                || str_starts_with($normalized, $pattern.'_')
                || str_ends_with($normalized, '_'.$pattern)
                || str_contains($normalized, '_'.$pattern.'_')
                || str_contains($normalized, $pattern.'_')
            ) {
                return true;
            }
        }

        return false;
    }

    private function requestId(Request $request): ?string
    {
        $requestId = $request->attributes->get('request_id')
            ?: $request->headers->get('X-Request-Id')
            ?: $request->headers->get('X-Correlation-Id')
            ?: $request->headers->get('Traceparent');

        if (! is_string($requestId) || trim($requestId) === '') {
            return null;
        }

        return substr(trim($requestId), 0, 120);
    }
}
