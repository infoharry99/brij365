<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Lead;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmSalesAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_user_can_view_native_sales_funnel_and_performance_workspace(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();

        $this->actingAs($sales)
            ->get(route('crm.analytics.index'))
            ->assertOk()
            ->assertSee('Sales Funnel &amp; Performance', false)
            ->assertSee('Source conversion')
            ->assertSee('Team Performance')
            ->assertSee('Campaign conversion');
    }

    public function test_sales_analytics_metrics_are_derived_from_scoped_records(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();
        $expectedLeads = Lead::where('company_id', $sales->company_id)->where('project_id', $project->id)->count();

        $this->actingAs($sales)
            ->get(route('crm.analytics.index', ['project_id' => $project->id]))
            ->assertOk()
            ->assertViewHas('report', fn (array $report) => $report['summary']['leads'] === $expectedLeads)
            ->assertSee('SKY-PUN');
    }

    public function test_sales_analytics_rejects_unavailable_project_and_unknown_filters(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $otherCompany = Company::where('code', 'B360P')->firstOrFail();
        $otherProject = Project::where('company_id', $otherCompany->id)->firstOrFail();

        $this->actingAs($sales)
            ->get(route('crm.analytics.index', ['project_id' => $otherProject->id]))
            ->assertSessionHasErrors('project_id');

        $this->actingAs($sales)
            ->get(route('crm.analytics.index', ['owner_id' => 1]))
            ->assertSessionHasErrors('owner_id');
    }

    public function test_partner_cannot_open_internal_sales_analytics(): void
    {
        $this->seed();

        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();

        $this->actingAs($partner)
            ->get(route('crm.analytics.index'))
            ->assertForbidden();
    }
}
