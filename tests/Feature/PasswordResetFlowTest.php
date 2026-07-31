<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_request_password_reset_link_without_account_enumeration(): void
    {
        $this->seed();
        Notification::fake();

        $response = $this->post(route('password.email'), [
            'email' => 'not.registered@example.test',
        ]);

        $response
            ->assertRedirect()
            ->assertSessionHas('status', 'If the email belongs to an active Builder360 account, a secure password reset link will be sent.');

        Notification::assertNothingSent();
    }

    public function test_active_user_can_reset_password_and_login_with_new_password(): void
    {
        $this->seed();
        Notification::fake();

        $user = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $token = null;

        $this->post(route('password.email'), [
            'email' => $user->email,
        ])->assertRedirect()
            ->assertSessionHas('status');

        Notification::assertSentTo(
            $user,
            ResetPassword::class,
            function (ResetPassword $notification) use (&$token): bool {
                $token = $notification->token;

                return is_string($token) && $token !== '';
            }
        );

        $this->get(route('password.reset', [
            'token' => $token,
            'email' => $user->email,
        ]))
            ->assertOk()
            ->assertSee('Set New Password');

        $newPassword = 'Builder360@456';

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => strtoupper($user->email),
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ])
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Your password has been reset. You can sign in with the new password.');

        $user->refresh();
        $this->assertTrue(Hash::check($newPassword, $user->password));

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => $newPassword,
        ])->assertRedirect(route('builder360.dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    public function test_password_reset_requires_strong_confirmed_password(): void
    {
        $this->seed();
        Notification::fake();

        $user = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $token = null;

        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo(
            $user,
            ResetPassword::class,
            function (ResetPassword $notification) use (&$token): bool {
                $token = $notification->token;

                return true;
            }
        );

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ])
            ->assertSessionHasErrors('password');
    }

    public function test_invalid_reset_token_is_rejected(): void
    {
        $this->seed();

        $user = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();

        $this->post(route('password.store'), [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'Builder360@789',
            'password_confirmation' => 'Builder360@789',
        ])
            ->assertRedirect()
            ->assertSessionHasErrors('email');

        $user->refresh();
        $this->assertTrue(Hash::check('Builder360@123', $user->password));
    }
}
