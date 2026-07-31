<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\User;
use App\Services\Builder360\Builder360Bootstrap;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuthSecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_success_failure_and_logout_are_audited_without_secret_metadata(): void
    {
        $this->seed();

        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();

        $this->from(route('login'))
            ->post(route('login.store'), [
                'email' => $director->email,
                'password' => 'wrong-password',
            ])
            ->assertRedirect(route('login'));

        $failed = AuditEvent::where('event_type', 'auth.login.failed')->latest('id')->firstOrFail();

        $this->assertNull($failed->user_id);
        $this->assertSame('failed', $failed->metadata['outcome']);
        $this->assertSame(hash('sha256', $director->email), $failed->metadata['email_hash']);
        $this->assertArrayNotHasKey('password', $failed->metadata);
        $this->assertArrayNotHasKey('email', $failed->metadata);

        $this->post(route('login.store'), [
            'email' => $director->email,
            'password' => 'Builder360@123',
        ])->assertRedirect(route('builder360.dashboard', absolute: false));

        $this->assertDatabaseHas('audit_events', [
            'user_id' => $director->id,
            'event_type' => 'auth.login.succeeded',
            'auditable_type' => User::class,
            'auditable_id' => $director->id,
        ]);

        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->assertDatabaseHas('audit_events', [
            'user_id' => $director->id,
            'event_type' => 'auth.logout',
            'auditable_type' => User::class,
            'auditable_id' => $director->id,
        ]);
    }

    public function test_guest_cannot_execute_authenticated_auth_state_changes(): void
    {
        $this->seed();

        $this->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->post(route('verification.send'))
            ->assertRedirect(route('login'));

        $this->assertDatabaseMissing('audit_events', [
            'event_type' => 'auth.logout',
        ]);

        $this->assertDatabaseMissing('audit_events', [
            'event_type' => 'auth.email_verification.notification_sent',
        ]);
    }

    public function test_dashboard_bootstrap_exposes_laravel_auth_security_status_without_secret_payloads(): void
    {
        $this->seed();

        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();

        AuditEvent::create([
            'user_id' => $director->id,
            'event_type' => 'auth.login.succeeded',
            'auditable_type' => User::class,
            'auditable_id' => $director->id,
            'action' => 'User authenticated successfully.',
            'metadata' => [
                'outcome' => 'succeeded',
                'password' => 'must-not-leak',
                'token' => 'must-not-leak',
            ],
        ]);

        $options = app(Builder360Bootstrap::class)->forUser($director)['auth_security_options'];

        $this->assertSame('laravel-auth', $options['source']);
        $this->assertSame(route('login', [], false), $options['login_route']);
        $this->assertSame(route('password.request', [], false), $options['forgot_password_route']);
        $this->assertSame(route('verification.notice', [], false), $options['verification_notice_route']);
        $this->assertSame(route('logout', [], false), $options['logout_route']);
        $this->assertContains('auth.login.succeeded', collect($options['event_counts'])->pluck('event_type')->all());

        $otpControl = collect($options['controls'])->firstWhere('key', 'otp_mfa');

        $this->assertSame('not_implemented', $otpControl['status']);

        $encoded = json_encode($options, JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('must-not-leak', $encoded);
    }

    public function test_dashboard_bootstrap_exposes_scoped_account_profile_without_secret_payloads(): void
    {
        $this->seed();

        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $buyer = User::where('email', 'rohan.shah@example.test')->firstOrFail();
        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();

        AuditEvent::create([
            'user_id' => $director->id,
            'event_type' => 'auth.login.succeeded',
            'auditable_type' => User::class,
            'auditable_id' => $director->id,
            'action' => 'User authenticated successfully.',
            'metadata' => [
                'outcome' => 'succeeded',
                'password' => 'must-not-leak',
                'remember_token' => 'must-not-leak',
                'reset_token' => 'must-not-leak',
            ],
        ]);

        $directorProfile = app(Builder360Bootstrap::class)->forUser($director)['account_profile_options'];
        $buyerProfile = app(Builder360Bootstrap::class)->forUser($buyer)['account_profile_options'];
        $partnerProfile = app(Builder360Bootstrap::class)->forUser($partner)['account_profile_options'];

        $this->assertSame('laravel-auth', $directorProfile['source']);
        $this->assertSame($director->name, $directorProfile['user']['name']);
        $this->assertSame($director->email, $directorProfile['user']['email']);
        $this->assertSame($director->role?->slug, $directorProfile['role']['slug']);
        $this->assertSame($director->company?->code, $directorProfile['company']['code']);
        $this->assertSame($director->role?->slug, $directorProfile['active_role_context']['role_slug']);
        $this->assertSame(route('logout', [], false), $directorProfile['security']['logout_route']);
        $this->assertSame(route('password.request', [], false), $directorProfile['security']['forgot_password_route']);
        $this->assertSame($buyer->email, $buyerProfile['user']['email']);
        $this->assertSame('buyer', $buyerProfile['role']['slug']);
        $this->assertSame($partner->email, $partnerProfile['user']['email']);
        $this->assertSame('channel_partner', $partnerProfile['role']['slug']);

        $encoded = json_encode([$directorProfile, $buyerProfile, $partnerProfile], JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('must-not-leak', $encoded);
        $this->assertStringNotContainsString('remember_token', $encoded);
        $this->assertStringNotContainsString('reset_token', $encoded);
        $this->assertArrayNotHasKey('password', $directorProfile['user']);
        $this->assertArrayNotHasKey('remember_token', $directorProfile['user']);
        $this->assertArrayNotHasKey('reset_token', $directorProfile['user']);
    }

    public function test_inactive_session_revocation_is_audited(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $sales->forceFill(['status' => 'suspended'])->save();

        $this->actingAs($sales)
            ->getJson(route('notifications.summary'))
            ->assertForbidden();

        $event = AuditEvent::where('event_type', 'auth.session.revoked_inactive_account')
            ->where('user_id', $sales->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('suspended', $event->metadata['account_status']);
    }

    public function test_password_reset_request_and_completion_are_audited_without_tokens(): void
    {
        $this->seed();
        Notification::fake();

        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $token = null;

        $this->post(route('password.email'), [
            'email' => $director->email,
        ])->assertRedirect();

        $requested = AuditEvent::where('event_type', 'auth.password_reset.requested')
            ->where('user_id', $director->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('sent', $requested->metadata['outcome']);
        $this->assertArrayNotHasKey('token', $requested->metadata);
        $this->assertArrayNotHasKey('password', $requested->metadata);

        Notification::assertSentTo(
            $director,
            ResetPassword::class,
            function (ResetPassword $notification) use (&$token): bool {
                $token = $notification->token;

                return true;
            }
        );

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => $director->email,
            'password' => 'Builder360@456',
            'password_confirmation' => 'Builder360@456',
        ])->assertRedirect(route('login'));

        $completed = AuditEvent::where('event_type', 'auth.password_reset.completed')
            ->where('user_id', $director->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertArrayNotHasKey('token', $completed->metadata);
        $this->assertArrayNotHasKey('password', $completed->metadata);
    }

    public function test_unknown_password_reset_request_is_audited_without_raw_email(): void
    {
        $this->seed();
        Notification::fake();

        $email = 'unknown.person@example.test';

        $this->post(route('password.email'), [
            'email' => $email,
        ])->assertRedirect();

        $event = AuditEvent::where('event_type', 'auth.password_reset.requested')
            ->latest('id')
            ->firstOrFail();

        $this->assertNull($event->user_id);
        $this->assertSame('no_active_account', $event->metadata['outcome']);
        $this->assertSame(hash('sha256', $email), $event->metadata['email_hash']);
        $this->assertArrayNotHasKey('email', $event->metadata);

        Notification::assertNothingSent();
    }

    public function test_email_verification_resend_and_completion_are_audited(): void
    {
        $this->seed();
        Notification::fake();

        $user = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $user->forceFill(['email_verified_at' => null])->save();

        $this->actingAs($user)
            ->post(route('verification.send'))
            ->assertRedirect();

        Notification::assertSentTo($user, VerifyEmail::class);

        $this->assertDatabaseHas('audit_events', [
            'user_id' => $user->id,
            'event_type' => 'auth.email_verification.notification_sent',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
        ]);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => sha1($user->email),
            ],
        );

        $this->actingAs($user)
            ->get($verificationUrl)
            ->assertRedirect(route('builder360.dashboard', absolute: false));

        $this->assertDatabaseHas('audit_events', [
            'user_id' => $user->id,
            'event_type' => 'auth.email_verification.completed',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
        ]);
    }
}
