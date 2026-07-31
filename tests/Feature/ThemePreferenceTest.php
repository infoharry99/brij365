<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemePreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_user_can_change_server_rendered_theme_preference(): void
    {
        $this->seed();
        $director = User::query()->where('email', 'aditya.mehra@builder360.test')->firstOrFail();

        $this->actingAs($director)
            ->from(route('builder360.dashboard'))
            ->post(route('builder360.theme.store'), ['theme' => 'dark'])
            ->assertRedirect(route('builder360.dashboard'))
            ->assertSessionHas('builder360.theme', 'dark');

        $this->get(route('builder360.dashboard'))
            ->assertOk()
            ->assertSee('data-theme="dark"', false)
            ->assertSee('aria-label="Switch to light theme"', false);
    }

    public function test_theme_preference_rejects_unknown_values(): void
    {
        $this->seed();
        $director = User::query()->where('email', 'aditya.mehra@builder360.test')->firstOrFail();

        $this->actingAs($director)
            ->from(route('builder360.dashboard'))
            ->post(route('builder360.theme.store'), ['theme' => 'system-script'])
            ->assertRedirect(route('builder360.dashboard'))
            ->assertSessionHasErrors('theme')
            ->assertSessionMissing('builder360.theme');
    }

    public function test_active_user_can_change_theme_through_progressive_shell_request(): void
    {
        $this->seed();
        $director = User::query()->where('email', 'aditya.mehra@builder360.test')->firstOrFail();

        $this->actingAs($director)
            ->postJson(route('builder360.theme.store'), ['theme' => 'dark'])
            ->assertNoContent()
            ->assertSessionHas('builder360.theme', 'dark');
    }

    public function test_theme_preference_requires_authentication(): void
    {
        $this->post(route('builder360.theme.store'), ['theme' => 'dark'])
            ->assertRedirect(route('login'));
    }
}
