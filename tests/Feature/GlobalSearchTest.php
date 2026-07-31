<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_search_company_scoped_business_records(): void
    {
        $this->seed();

        $director = User::query()->where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $visible = Project::query()->where('company_id', $director->company_id)->firstOrFail();

        $this->actingAs($director)
            ->get(route('builder360.search', ['q' => $visible->code]))
            ->assertOk()
            ->assertSee('Search')
            ->assertSee($visible->code)
            ->assertSee('Projects')
            ->assertSee('id="b360-global-search"', false)
            ->assertSee('id="b360-sidebar-global-search"', false)
            ->assertDontSee('id="root"', false);
    }

    public function test_search_never_returns_records_from_another_company(): void
    {
        $this->seed();

        $director = User::query()->where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $otherCompany = Company::create([
            'code' => 'OTHER',
            'name' => 'Other Company',
            'legal_name' => 'Other Company Pvt Ltd',
            'state' => 'MH',
            'status' => 'active',
        ]);
        Project::create([
            'company_id' => $otherCompany->id,
            'code' => 'SECRET-PROJECT',
            'name' => 'Secret Project',
            'project_type' => 'residential',
            'city' => 'Mumbai',
            'state' => 'MH',
            'status' => 'active',
            'budget_amount' => 1000000,
            'target_roi_percent' => 12,
        ]);

        $this->actingAs($director)
            ->get(route('builder360.search', ['q' => 'SECRET-PROJECT']))
            ->assertOk()
            ->assertSee('No matching records')
            ->assertDontSee('Secret Project');
    }

    public function test_search_validates_query_and_rejects_unexpected_filters(): void
    {
        $this->seed();
        $director = User::query()->where('email', 'aditya.mehra@builder360.test')->firstOrFail();

        $this->actingAs($director)
            ->from(route('builder360.dashboard'))
            ->get(route('builder360.search', ['q' => 'A', 'company_id' => 999]))
            ->assertRedirect(route('builder360.dashboard'))
            ->assertSessionHasErrors(['q', 'company_id']);
    }

    public function test_search_requires_authentication(): void
    {
        $this->get(route('builder360.search', ['q' => 'project']))
            ->assertRedirect(route('login'));
    }
}
