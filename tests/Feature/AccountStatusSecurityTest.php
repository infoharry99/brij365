<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AccountStatusSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_or_suspended_users_cannot_login(): void
    {
        $this->seed();

        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $director->forceFill(['status' => 'suspended'])->save();

        $this->from(route('login'))
            ->post(route('login.store'), [
                'email' => $director->email,
                'password' => 'Builder360@123',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();

        $director->forceFill(['status' => 'inactive'])->save();

        $this->from(route('login'))
            ->post(route('login.store'), [
                'email' => $director->email,
                'password' => 'Builder360@123',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_deactivated_authenticated_session_is_invalidated_on_next_request(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $sales->forceFill(['status' => 'suspended'])->save();

        $this->actingAs($sales)
            ->getJson(route('notifications.summary'))
            ->assertForbidden()
            ->assertJsonPath('message', 'This account is not active. Please contact your Builder360 administrator.');

        $this->assertGuest();
    }

    public function test_inactive_users_do_not_receive_password_reset_links(): void
    {
        $this->seed();
        Notification::fake();

        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $director->forceFill(['status' => 'inactive'])->save();

        $this->post(route('password.email'), [
            'email' => $director->email,
        ])
            ->assertRedirect()
            ->assertSessionHas('status', 'If the email belongs to an active Builder360 account, a secure password reset link will be sent.');

        Notification::assertNotSentTo($director, ResetPassword::class);
    }
}
