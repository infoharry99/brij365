<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;

class SecurityAuditService
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    public function loginSucceeded(User $user, Request $request): void
    {
        $this->auditLogger->record(
            $user,
            'auth.login.succeeded',
            'User signed in successfully.',
            $user,
            $this->metadata($request, ['outcome' => 'success']),
            $request,
        );
    }

    public function loginFailed(Request $request, string $email, string $reason = 'invalid_credentials'): void
    {
        $this->auditLogger->record(
            null,
            'auth.login.failed',
            'User sign-in attempt failed.',
            null,
            $this->metadata($request, [
                'outcome' => 'failed',
                'reason' => $reason,
                'email_hash' => $this->emailHash($email),
            ]),
            $request,
        );
    }

    public function logout(?User $user, Request $request): void
    {
        $this->auditLogger->record(
            $user,
            'auth.logout',
            'User signed out.',
            $user,
            $this->metadata($request),
            $request,
        );
    }

    public function inactiveSessionRevoked(User $user, Request $request): void
    {
        $this->auditLogger->record(
            $user,
            'auth.session.revoked_inactive_account',
            'Authenticated session revoked because the account is not active.',
            $user,
            $this->metadata($request, [
                'account_status' => $user->status,
            ]),
            $request,
        );
    }

    public function passwordResetRequested(?User $user, Request $request, string $email, string $outcome): void
    {
        $this->auditLogger->record(
            $user,
            'auth.password_reset.requested',
            'Password reset link requested.',
            $user,
            $this->metadata($request, [
                'outcome' => $outcome,
                'email_hash' => $this->emailHash($email),
            ]),
            $request,
        );
    }

    public function passwordResetCompleted(User $user, Request $request): void
    {
        $this->auditLogger->record(
            $user,
            'auth.password_reset.completed',
            'User password reset completed.',
            $user,
            $this->metadata($request),
            $request,
        );
    }

    public function emailVerificationNotificationSent(User $user, Request $request): void
    {
        $this->auditLogger->record(
            $user,
            'auth.email_verification.notification_sent',
            'Email verification notification requested.',
            $user,
            $this->metadata($request),
            $request,
        );
    }

    public function emailVerified(User $user, Request $request): void
    {
        $this->auditLogger->record(
            $user,
            'auth.email_verification.completed',
            'User email address verified.',
            $user,
            $this->metadata($request),
            $request,
        );
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function metadata(Request $request, array $extra = []): array
    {
        return array_merge([
            'route' => $request->route()?->getName(),
            'method' => $request->method(),
            'user_agent_hash' => $this->hashNullable($request->userAgent()),
        ], $extra);
    }

    private function emailHash(string $email): string
    {
        return hash('sha256', mb_strtolower(trim($email)));
    }

    private function hashNullable(?string $value): ?string
    {
        return $value === null || $value === ''
            ? null
            : hash('sha256', $value);
    }
}
