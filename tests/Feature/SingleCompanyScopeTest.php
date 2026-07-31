<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Project;
use App\Models\User;
use App\Services\Security\ActiveCompanyResolver;
use App\Services\Security\CompanyScopeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SingleCompanyScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_configured_company_is_resolved_by_stable_business_code(): void
    {
        $this->seed();
        config()->set('builder360.single_company.enabled', true);
        config()->set('builder360.single_company.code', 'B360D');

        $company = app(ActiveCompanyResolver::class)->resolve();

        $this->assertNotNull($company);
        $this->assertSame('B360D', $company->code);
        $this->assertSame('Builder360 Developers Pvt Ltd', $company->name);
    }

    public function test_global_users_are_restricted_to_the_configured_company(): void
    {
        $this->seed();
        config()->set('builder360.single_company.enabled', true);
        config()->set('builder360.single_company.code', 'B360D');

        $director = User::query()->where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $activeCompany = Company::query()->where('code', 'B360D')->firstOrFail();
        $otherCompany = Company::query()->where('code', 'B360P')->firstOrFail();
        $scope = app(CompanyScopeService::class);

        $this->assertSame($activeCompany->id, $scope->companyIdFor($director));
        $this->assertTrue($scope->allows($director, $activeCompany->id));
        $this->assertFalse($scope->allows($director, $otherCompany->id));

        $projectCompanyIds = $scope->apply(Project::query(), $director)
            ->pluck('company_id')
            ->unique()
            ->values()
            ->all();

        $this->assertSame([$activeCompany->id], $projectCompanyIds);
    }

    public function test_users_assigned_outside_the_configured_company_fail_closed(): void
    {
        $this->seed();
        config()->set('builder360.single_company.enabled', true);
        config()->set('builder360.single_company.code', 'B360D');

        $user = User::query()->where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $otherCompany = Company::query()->where('code', 'B360P')->firstOrFail();
        $user->forceFill(['company_id' => $otherCompany->id])->save();

        $scope = app(CompanyScopeService::class);

        $this->assertSame(0, $scope->companyIdFor($user));
        $this->assertFalse($scope->allows($user, $otherCompany->id));
        $this->assertSame(0, $scope->apply(Project::query(), $user)->count());
    }

    public function test_single_company_mode_blocks_creating_another_company(): void
    {
        $this->seed();
        config()->set('builder360.single_company.enabled', true);
        config()->set('builder360.single_company.code', 'B360D');

        $director = User::query()->where('email', 'aditya.mehra@builder360.test')->firstOrFail();

        $this->assertFalse($director->can('create', Company::class));
    }

    public function test_bootstrap_and_direct_model_authorization_stay_inside_the_configured_company(): void
    {
        $this->seed();
        config()->set('builder360.single_company.enabled', true);
        config()->set('builder360.single_company.code', 'B360D');

        $director = User::query()->where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $otherCompanyProject = Project::query()
            ->whereHas('company', fn ($query) => $query->where('code', 'B360P'))
            ->firstOrFail();

        $this->assertFalse($director->can('update', $otherCompanyProject));

        $bootstrap = app(\App\Services\Builder360\Builder360Bootstrap::class)->forUser($director);
        $companyCodes = collect($bootstrap['companies'] ?? [])->pluck('code')->unique()->values()->all();
        $projectCompanyIds = collect($bootstrap['projects'] ?? [])->pluck('company_id')->filter()->unique()->values()->all();
        $activeCompanyId = Company::query()->where('code', 'B360D')->value('id');

        $this->assertSame(['B360D'], $companyCodes);
        $this->assertSame([$activeCompanyId], $projectCompanyIds);
        $this->assertSame('company', data_get($bootstrap, 'sales_booking_options.scope.level'));
    }

    public function test_authenticated_write_request_is_bound_to_the_configured_company(): void
    {
        $this->seed();
        config()->set('builder360.single_company.enabled', true);
        config()->set('builder360.single_company.code', 'B360D');

        $director = User::query()->where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $activeCompany = Company::query()->where('code', 'B360D')->firstOrFail();

        $this->actingAs($director)
            ->postJson(route('collaboration.tasks.store'), [
                'title' => 'Single-company context task',
                'priority' => 'medium',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('work_tasks', [
            'company_id' => $activeCompany->id,
            'title' => 'Single-company context task',
        ]);
    }

    public function test_company_selector_is_hidden_in_single_company_workspace(): void
    {
        $this->seed();
        config()->set('builder360.single_company.enabled', true);
        config()->set('builder360.single_company.code', 'B360D');

        $director = User::query()->where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $activeCompany = Company::query()->where('code', 'B360D')->firstOrFail();

        $this->actingAs($director)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertSee('<input type="hidden" name="company_id" value="'.$activeCompany->id.'">', false)
            ->assertDontSee('<select name="company_id"', false);
    }

    public function test_collaboration_workspaces_load_company_options_in_single_company_mode(): void
    {
        $this->seed();
        config()->set('builder360.single_company.enabled', true);
        config()->set('builder360.single_company.code', 'B360D');

        $director = User::query()->where('email', 'aditya.mehra@builder360.test')->firstOrFail();

        $this->actingAs($director)
            ->get(route('collaboration.tasks.index'))
            ->assertOk();

        $this->actingAs($director)
            ->get(route('collaboration.calendar-events.index'))
            ->assertOk();
    }
}
