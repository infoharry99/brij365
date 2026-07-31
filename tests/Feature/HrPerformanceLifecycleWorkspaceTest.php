<?php

namespace Tests\Feature;

use App\Application\Hr\Data\DepartmentPerformanceRowData;
use App\Application\Hr\Data\LifecycleTrackerRowData;
use App\Application\Hr\Data\PerformanceReviewRowData;
use App\Models\PerformanceCycle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrPerformanceLifecycleWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_performance_dashboard_uses_scoped_persisted_review_aggregates(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();

        $this->actingAs($hr)
            ->get(route('hr.performance-dashboard.index'))
            ->assertOk()
            ->assertViewIs('hr.performance.workspace')
            ->assertViewHas('activeRegister', 'dashboard')
            ->assertViewHas('departmentRows', function ($rows): bool {
                return $rows->isNotEmpty()
                    && $rows->every(fn ($row): bool => $row instanceof DepartmentPerformanceRowData);
            })
            ->assertSee('Department performance dashboard')
            ->assertSee('Factual review completion and final scores');
    }

    public function test_performance_dashboard_rejects_a_deleted_cycle_filter(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $cycle = PerformanceCycle::where('cycle_code', 'PFC-10001')->firstOrFail();
        $cycle->delete();

        $this->actingAs($hr)
            ->getJson(route('hr.performance-dashboard.index', ['cycle_id' => $cycle->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cycle_id');
    }

    public function test_performance_review_inputs_use_the_persisted_cycle_rating_scale(): void
    {
        $this->seed();

        $employee = User::where('email', 'amit.verma@builder360.test')->firstOrFail();
        $cycle = PerformanceCycle::where('cycle_code', 'PFC-10001')->firstOrFail();
        $cycle->forceFill([
            'rating_scale_min' => 2,
            'rating_scale_max' => 7,
        ])->save();

        $this->actingAs($employee)
            ->get(route('hr.performance-reviews.index'))
            ->assertOk()
            ->assertViewHas('reviews', function ($reviews): bool {
                $row = $reviews->getCollection()->first();

                return $row instanceof PerformanceReviewRowData
                    && $row->ratingScaleMin === 2
                    && $row->ratingScaleMax === 7;
            })
            ->assertSee('Self score (2 to 7)')
            ->assertSee('min="2" max="7"', false);
    }

    public function test_lifecycle_tracker_renders_immutable_rows_and_enforces_access(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();

        $this->actingAs($hr)
            ->get(route('hr.lifecycle.index'))
            ->assertOk()
            ->assertViewIs('hr.lifecycle.index')
            ->assertViewHas('lifecycleEvents', function ($events): bool {
                return $events->isNotEmpty()
                    && $events->every(fn ($row): bool => $row instanceof LifecycleTrackerRowData);
            })
            ->assertSee('Lifecycle tracker')
            ->assertSee('Every row is derived from an existing authorized HR record.');

        $this->actingAs($hr)
            ->get(route('hr.lifecycle.index', ['stage' => 'confirmation']))
            ->assertOk()
            ->assertViewHas('lifecycleEvents', fn ($events): bool => $events->every(
                fn (LifecycleTrackerRowData $row): bool => $row->eventType === 'confirmation'
            ));

        $this->actingAs($partner)
            ->get(route('hr.lifecycle.index'))
            ->assertForbidden();
    }

    public function test_performance_confirmation_and_separation_actions_keep_mobile_parity(): void
    {
        $views = [
            base_path('resources/views/hr/performance/partials/reviews.blade.php'),
            base_path('resources/views/hr/confirmation/index.blade.php'),
            base_path('resources/views/hr/separation/index.blade.php'),
        ];

        foreach ($views as $view) {
            $source = file_get_contents($view);
            $this->assertStringContainsString("'mobile' => false", $source);
            $this->assertStringContainsString("'mobile' => true", $source);
        }

        foreach ([
            base_path('resources/views/hr/performance/partials/review-actions.blade.php'),
            base_path('resources/views/hr/confirmation/partials/case-actions.blade.php'),
            base_path('resources/views/hr/separation/partials/settlement-actions.blade.php'),
        ] as $partial) {
            $source = file_get_contents($partial);
            $this->assertStringContainsString("x-data=\"serverFormState\"", $source);
            $this->assertStringContainsString('x-bind:disabled="busy"', $source);
            $this->assertStringContainsString('x-text="submitLabel"', $source);
            $this->assertStringNotContainsString('submitting', $source);
        }
    }
}
