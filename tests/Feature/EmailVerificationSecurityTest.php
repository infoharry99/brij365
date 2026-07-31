<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_unverified_active_user_is_redirected_to_verification_notice(): void
    {
        $this->seed();

        $user = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $user->forceFill(['email_verified_at' => null])->save();

        $this->actingAs($user)
            ->get('/')
            ->assertRedirect(route('verification.notice', absolute: false));

        $this->actingAs($user)
            ->get(route('verification.notice'))
            ->assertOk()
            ->assertSee('Verify Your Email');
    }

    public function test_unverified_json_request_is_forbidden(): void
    {
        $this->seed();

        $user = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $user->forceFill(['email_verified_at' => null])->save();

        $this->actingAs($user)
            ->getJson(route('notifications.summary'))
            ->assertForbidden()
            ->assertJsonPath('message', 'Your email address is not verified.');
    }

    public function test_user_can_resend_and_complete_email_verification(): void
    {
        $this->seed();
        Notification::fake();

        $user = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $user->forceFill(['email_verified_at' => null])->save();

        $this->actingAs($user)
            ->post(route('verification.send'))
            ->assertRedirect()
            ->assertSessionHas('status', 'A fresh verification link has been sent to your email address.');

        Notification::assertSentTo($user, VerifyEmail::class);

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
            ->assertRedirect(route('builder360.dashboard', absolute: false))
            ->assertSessionHas('status', 'Your email address has been verified.');

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_admin_created_active_user_receives_verification_notification(): void
    {
        $this->seed();
        Notification::fake();

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $role = Role::where('slug', 'employee')->firstOrFail();

        $userId = $this->actingAs($admin)
            ->postJson(route('admin.users.store'), [
                'company_id' => $admin->company_id,
                'role_id' => $role->id,
                'name' => 'Verification Pending User',
                'email' => 'verification.pending@builder360.test',
                'password' => 'Builder360@123',
                'status' => 'active',
            ])
            ->assertCreated()
            ->assertJsonPath('data.email_verified_at', null)
            ->json('data.id');

        $created = User::findOrFail($userId);

        Notification::assertSentTo($created, VerifyEmail::class);
    }
}
