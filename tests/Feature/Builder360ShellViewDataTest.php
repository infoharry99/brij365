<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Builder360ShellViewDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_shell_renders_from_immutable_view_data(): void
    {
        $this->seed();
        $director = User::query()->where('email', 'aditya.mehra@builder360.test')->firstOrFail();

        $this->actingAs($director)
            ->get(route('builder360.dashboard'))
            ->assertOk()
            ->assertSee('fa-table-cells-large', false)
            ->assertSee('Search projects, units, leads, vouchers')
            ->assertSee('Aditya Mehra')
            ->assertSee('Director')
            ->assertSee('All Projects');
    }

    public function test_shell_partials_do_not_read_the_legacy_bootstrap_array(): void
    {
        $sidebar = file_get_contents(resource_path('views/builder360/classic/partials/sidebar.blade.php'));
        $topbar = file_get_contents(resource_path('views/builder360/classic/partials/topbar.blade.php'));

        $this->assertIsString($sidebar);
        $this->assertIsString($topbar);
        $this->assertStringNotContainsString('$bootstrap[', $sidebar);
        $this->assertStringNotContainsString('$bootstrap[', $topbar);
        $this->assertStringContainsString('$shell->navigation', $sidebar);
        $this->assertStringContainsString('$shell->projects', $topbar);
    }
}
