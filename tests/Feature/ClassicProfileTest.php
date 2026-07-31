<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassicProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_open_classic_profile_workspace(): void
    {
        $this->seed();

        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();

        $this->actingAs($director)
            ->get(route('builder360.profile'))
            ->assertOk()
            ->assertSee('My Profile')
            ->assertSee($director->email)
            ->assertSee('Account Access')
            ->assertSee('Recent Activity')
            ->assertSee('class="b360-shell"', false)
            ->assertDontSee('id="root"', false)
            ->assertDontSee('@vite', false);
    }

    public function test_profile_requires_authenticated_verified_active_user(): void
    {
        $this->get(route('builder360.profile'))->assertRedirect(route('login'));
    }
}
