<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Company;
use App\Models\Employee;
use App\Models\PerformanceCycle;
use App\Models\PerformanceReview;
use App\Models\Project;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrPerformanceManagementTest extends TestCase
{
    public function test_performance_cycles_and_reviews_render_as_blade_workspaces_for_browser_requests(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();

        $this->actingAs($hr)->get(route('hr.performance-cycles.index'))->assertOk()->assertViewIs('hr.performance.workspace')->assertSee('Performance Management')->assertSee('Performance cycles');
        $this->actingAs($hr)->get(route('hr.performance-reviews.index'))->assertOk()->assertViewIs('hr.performance.workspace')->assertSee('Employee reviews');
    }

    use RefreshDatabase;

    public function test_hr_manager_can_list_seeded_performance_cycles_and_reviews(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();

        $this->actingAs($hr)
            ->getJson(route('hr.performance-cycles.index', ['frequency' => 'quarterly']))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.cycle_code', 'PFC-10001')
            ->assertJsonPath('data.0.department', 'Construction')
            ->assertJsonPath('data.0.reviews_count', 1);

        $this->actingAs($hr)
            ->getJson(route('hr.performance-reviews.index', ['department' => 'Construction']))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.review_number', 'PFR-10001')
            ->assertJsonPath('data.0.employee.employee_code', 'EMP-0030')
            ->assertJsonPath('data.0.manager.employee_code', 'EMP-0012');
    }

    public function test_performance_indexes_validate_filters_and_scope(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $employeeUser = User::where('email', 'amit.verma@builder360.test')->firstOrFail();
        $cycle = PerformanceCycle::where('cycle_code', 'PFC-10001')->firstOrFail();
        $review = PerformanceReview::where('review_number', 'PFR-10001')->firstOrFail();
        $otherEmployee = Employee::where('employee_code', 'EMP-0012')->firstOrFail();
        $externalCycle = $this->createExternalPerformanceCycle();

        $this->actingAs($hr)
            ->getJson(route('hr.performance-cycles.index', ['status' => 'invalid']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->actingAs($hr)
            ->getJson(route('hr.performance-cycles.index', ['frequency' => 'weekly']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('frequency');

        $this->actingAs($hr)
            ->getJson(route('hr.performance-cycles.index', ['per_page' => 500]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');

        $this->actingAs($hr)
            ->getJson(route('hr.performance-cycles.index', ['employee_id' => $review->employee_id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('employee_id')
            ->assertJsonPath('errors.employee_id.0', 'The selected filter is not available for this endpoint.');

        $this->actingAs($hr)
            ->getJson(route('hr.performance-cycles.index', ['project_id' => $externalCycle->project_id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('project_id');

        $this->actingAs($hr)
            ->getJson(route('hr.performance-reviews.index', ['status' => 'invalid']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->actingAs($hr)
            ->getJson(route('hr.performance-reviews.index', ['frequency' => 'quarterly']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('frequency')
            ->assertJsonPath('errors.frequency.0', 'The selected filter is not available for this endpoint.');

        $this->actingAs($hr)
            ->getJson(route('hr.performance-reviews.index', [
                'from' => now()->toDateString(),
                'to' => now()->subDay()->toDateString(),
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('to');

        $this->actingAs($hr)
            ->getJson(route('hr.performance-reviews.index', ['cycle_id' => $externalCycle->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cycle_id');

        $this->actingAs($employeeUser)
            ->getJson(route('hr.performance-reviews.index', ['employee_id' => $otherEmployee->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('employee_id');

        $this->actingAs($hr)
            ->getJson(route('hr.performance-cycles.index', [
                'status' => 'active',
                'frequency' => 'quarterly',
                'department' => 'Construction',
                'project_id' => $cycle->project_id,
                'current' => true,
                'per_page' => 10,
                'page' => 1,
            ]))
            ->assertOk()
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.cycle_code', 'PFC-10001');

        $this->actingAs($hr)
            ->getJson(route('hr.performance-reviews.index', [
                'cycle_id' => $cycle->id,
                'employee_id' => $review->employee_id,
                'department' => 'Construction',
                'status' => 'draft',
                'pip_required' => false,
                'from' => now()->startOfQuarter()->toDateString(),
                'to' => now()->endOfQuarter()->toDateString(),
                'per_page' => 10,
                'page' => 1,
            ]))
            ->assertOk()
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.review_number', 'PFR-10001');
    }

    public function test_cycle_creation_prevents_overlapping_same_population(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();

        $this->actingAs($hr)
            ->postJson(route('hr.performance-cycles.store'), [
                'name' => 'Construction Monthly July Review',
                'frequency' => 'monthly',
                'status' => 'active',
                'starts_on' => '2026-07-01',
                'ends_on' => '2026-07-31',
                'review_due_on' => '2026-08-05',
                'department' => 'Construction',
                'rating_scale_min' => 1,
                'rating_scale_max' => 5,
                'passing_score' => 3,
            ])
            ->assertCreated()
            ->assertJsonPath('data.frequency', 'monthly')
            ->assertJsonPath('data.status', 'active');

        $this->actingAs($hr)
            ->postJson(route('hr.performance-cycles.store'), [
                'name' => 'Overlapping Construction Monthly Review',
                'frequency' => 'monthly',
                'starts_on' => '2026-07-15',
                'ends_on' => '2026-08-14',
                'department' => 'Construction',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['starts_on']);
    }

    public function test_hr_can_create_review_and_duplicate_review_is_rejected(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $cycle = PerformanceCycle::where('cycle_code', 'PFC-10001')->firstOrFail();
        $employee = Employee::where('employee_code', 'EMP-0012')->firstOrFail();
        $manager = Employee::where('employee_code', 'EMP-0018')->firstOrFail();

        $reviewId = $this->actingAs($hr)
            ->postJson(route('hr.performance-reviews.store'), [
                'performance_cycle_id' => $cycle->id,
                'employee_id' => $employee->id,
                'manager_employee_id' => $manager->id,
                'kpis' => [
                    ['name' => 'Milestone progress governance', 'target' => 'Weekly plan variance under control', 'weight' => 60, 'metric' => 'progress'],
                    ['name' => 'Safety and quality closure', 'target' => 'No overdue critical observations', 'weight' => 40, 'metric' => 'quality'],
                ],
                'kra_summary' => ['role_expectation' => 'Project manager quarterly delivery review.'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.employee.employee_code', 'EMP-0012')
            ->assertJsonPath('data.status', 'draft')
            ->json('data.id');

        $review = PerformanceReview::findOrFail($reviewId);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'hr.performance_review.created',
            'auditable_id' => $review->id,
            'user_id' => $hr->id,
        ]);

        $this->actingAs($hr)
            ->postJson(route('hr.performance-reviews.store'), [
                'performance_cycle_id' => $cycle->id,
                'employee_id' => $employee->id,
                'manager_employee_id' => $manager->id,
                'kpis' => [
                    ['name' => 'Duplicate KPI', 'target' => 'Invalid duplicate', 'weight' => 100],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['employee_id']);
    }

    public function test_global_user_can_create_company_level_cycle_and_review_with_explicit_company_scope(): void
    {
        $this->seed();

        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $company = Company::where('code', 'B360D')->firstOrFail();
        $employee = Employee::where('employee_code', 'EMP-0021')->firstOrFail();

        $cycleId = $this->actingAs($director)
            ->postJson(route('hr.performance-cycles.store'), [
                'company_id' => $company->id,
                'name' => 'Sales Monthly Global Scope Review',
                'frequency' => 'monthly',
                'status' => 'active',
                'starts_on' => '2026-09-01',
                'ends_on' => '2026-09-30',
                'review_due_on' => '2026-10-05',
                'department' => 'Sales',
                'rating_scale_min' => 1,
                'rating_scale_max' => 5,
                'passing_score' => 3,
            ])
            ->assertCreated()
            ->assertJsonPath('data.company.id', $company->id)
            ->assertJsonPath('data.department', 'Sales')
            ->json('data.id');

        $cycle = PerformanceCycle::findOrFail($cycleId);

        $this->assertDatabaseHas('performance_cycles', [
            'id' => $cycle->id,
            'company_id' => $company->id,
            'created_by_user_id' => $director->id,
        ]);

        $reviewId = $this->actingAs($director)
            ->postJson(route('hr.performance-reviews.store'), [
                'performance_cycle_id' => $cycle->id,
                'employee_id' => $employee->id,
                'kpis' => [
                    ['name' => 'Lead conversion governance', 'target' => 'Improve qualified-to-booked conversion', 'weight' => 60, 'metric' => 'conversion'],
                    ['name' => 'Collection follow-up discipline', 'target' => 'Weekly follow-up cadence maintained', 'weight' => 40, 'metric' => 'follow_up'],
                ],
                'kra_summary' => ['role_expectation' => 'Monthly sales operating review.'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.employee.employee_code', 'EMP-0021')
            ->assertJsonPath('data.cycle.id', $cycle->id)
            ->json('data.id');

        $this->assertDatabaseHas('performance_reviews', [
            'id' => $reviewId,
            'company_id' => $company->id,
            'performance_cycle_id' => $cycle->id,
            'employee_id' => $employee->id,
        ]);
    }

    public function test_self_manager_and_hr_close_workflow_with_pip_and_audit(): void
    {
        $this->seed();

        $employeeUser = User::where('email', 'amit.verma@builder360.test')->firstOrFail();
        $managerUser = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $review = PerformanceReview::where('review_number', 'PFR-10001')->firstOrFail();

        $this->actingAs($employeeUser)
            ->patchJson(route('hr.performance-reviews.self-submit', $review), [
                'self_score' => 3.6,
                'kra_summary' => [
                    'achievements' => 'Improved DPR accuracy and contractor follow-up cadence.',
                    'challenges' => 'Material delivery delays affected one milestone.',
                    'support_needed' => 'Need clearer material ETA escalation.',
                ],
                'strengths' => 'Execution discipline and field coordination.',
                'improvement_areas' => 'Earlier escalation on procurement blockers.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'self_submitted')
            ->assertJsonPath('data.self_score', 3.6);

        $this->assertDatabaseHas('user_notifications', [
            'recipient_user_id' => $managerUser->id,
            'category' => 'performance',
            'status' => 'unread',
        ]);

        $this->actingAs($managerUser)
            ->patchJson(route('hr.performance-reviews.manager-submit', $review), [
                'manager_score' => 2.4,
                'manager_comments' => 'Good ownership, but blocker escalation needs measurable improvement.',
                'kpis' => [
                    ['name' => 'Daily progress reporting quality', 'target' => 'Submit accurate DPR inputs within cut-off time', 'weight' => 40, 'metric' => 'timeliness_quality', 'actual' => 'Mostly on time', 'score' => 3],
                    ['name' => 'Site safety observations', 'target' => 'Zero unresolved high-risk safety observations', 'weight' => 30, 'metric' => 'safety', 'actual' => 'One delayed closure', 'score' => 2],
                    ['name' => 'Contractor coordination', 'target' => 'Resolve assigned contractor blockers within SLA', 'weight' => 30, 'metric' => 'sla', 'actual' => 'Two delayed escalations', 'score' => 2],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'manager_submitted')
            ->assertJsonPath('data.manager_score', 2.4);

        $this->actingAs($hr)
            ->patchJson(route('hr.performance-reviews.close', $review), [
                'lock_version' => $review->fresh()->lock_version,
                'final_score' => 2.4,
                'final_rating' => 'Needs Improvement',
                'hr_comments' => 'PIP opened for escalation discipline and structured weekly progress governance.',
                'pip_required' => true,
                'pip_plan' => [
                    'objectives' => [
                        'Escalate material blockers within 24 hours.',
                        'Submit weekly corrective action tracker to reporting manager.',
                    ],
                    'starts_on' => now()->addDay()->toDateString(),
                    'ends_on' => now()->addDays(45)->toDateString(),
                    'review_frequency' => 'weekly',
                    'owner' => 'Rajesh Kulkarni',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.final_rating', 'Needs Improvement')
            ->assertJsonPath('data.pip_required', true)
            ->assertJsonPath('data.pip_status', 'open');

        $this->assertSame(3, AuditEvent::where('auditable_id', $review->id)
            ->whereIn('event_type', [
                'hr.performance_review.self_submitted',
                'hr.performance_review.manager_submitted',
                'hr.performance_review.closed',
            ])
            ->count());

        $this->assertTrue(UserNotification::where('recipient_user_id', $employeeUser->id)
            ->where('category', 'performance')
            ->where('severity', 'warning')
            ->exists());
    }

    public function test_cycle_pip_threshold_forces_pip_plan_on_low_final_score(): void
    {
        $this->seed();

        $managerUser = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $review = PerformanceReview::where('review_number', 'PFR-10001')->firstOrFail();

        $review->cycle->forceFill([
            'rules' => array_replace($review->cycle->rules ?? [], ['pip_threshold' => 3.0]),
        ])->save();

        $this->actingAs($managerUser)
            ->patchJson(route('hr.performance-reviews.manager-submit', $review), [
                'manager_score' => 2.9,
                'manager_comments' => 'Below the configured PIP threshold.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'manager_submitted');

        $this->actingAs($hr)
            ->patchJson(route('hr.performance-reviews.close', $review), [
                'lock_version' => $review->fresh()->lock_version,
                'final_score' => 2.9,
                'final_rating' => 'Needs Improvement',
                'hr_comments' => 'Low score should require a PIP plan.',
                'pip_required' => false,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('pip_plan');

        $this->actingAs($hr)
            ->patchJson(route('hr.performance-reviews.close', $review), [
                'lock_version' => $review->fresh()->lock_version,
                'final_score' => 2.9,
                'final_rating' => 'Needs Improvement',
                'hr_comments' => 'PIP required by configured cycle threshold.',
                'pip_required' => false,
                'pip_plan' => [
                    'objectives' => ['Improve blocker escalation within one business day.'],
                    'starts_on' => now()->addDay()->toDateString(),
                    'ends_on' => now()->addDays(30)->toDateString(),
                    'review_frequency' => 'weekly',
                    'owner' => 'Rajesh Kulkarni',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.pip_required', true)
            ->assertJsonPath('data.pip_status', 'open');
    }

    public function test_manager_submission_rejects_hr_owned_and_unknown_scoring_inputs(): void
    {
        $this->seed();

        $review = PerformanceReview::where('review_number', 'PFR-10001')->firstOrFail();
        $manager = $review->managerEmployee?->user;
        $this->assertNotNull($manager);

        $review->forceFill([
            'scoring_inputs' => [
                'self_review' => 3.5,
                'hr_calibration' => 4.25,
            ],
        ])->save();

        $this->actingAs($manager)
            ->patchJson(route('hr.performance-reviews.manager-submit', $review), [
                'manager_score' => 4,
                'manager_comments' => 'Attempted to submit fields owned by HR and the server.',
                'scoring_inputs' => [
                    'kpi_achievement' => 4,
                    'hr_calibration' => 5,
                    'unapproved_override' => 5,
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('scoring_inputs');

        $review->refresh();
        $this->assertSame('draft', $review->status);
        $this->assertSame(4.25, (float) data_get($review->scoring_inputs, 'hr_calibration'));

        $this->actingAs($manager)
            ->patchJson(route('hr.performance-reviews.manager-submit', $review), [
                'manager_score' => 4,
                'manager_comments' => 'Submitted only evidence explicitly owned by the manager workflow.',
                'scoring_inputs' => [
                    'kpi_achievement' => 4,
                    'kra_achievement' => 4.5,
                    'competencies' => 4,
                    'behaviour' => 4,
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'manager_submitted');

        $review->refresh();
        $this->assertSame(4.25, (float) data_get($review->scoring_inputs, 'hr_calibration'));
        $this->assertArrayNotHasKey('unapproved_override', $review->scoring_inputs ?? []);
        $this->assertArrayNotHasKey('attendance', $review->scoring_inputs ?? []);
    }

    public function test_employee_scope_and_partner_restrictions_are_enforced(): void
    {
        $this->seed();

        $employeeUser = User::where('email', 'amit.verma@builder360.test')->firstOrFail();
        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();
        $review = PerformanceReview::where('review_number', 'PFR-10001')->firstOrFail();

        $this->actingAs($employeeUser)
            ->getJson(route('hr.performance-reviews.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.review_number', 'PFR-10001');

        $this->actingAs($employeeUser)
            ->patchJson(route('hr.performance-reviews.manager-submit', $review), [
                'manager_score' => 4,
                'manager_comments' => 'Invalid self-manager submission.',
            ])
            ->assertForbidden();

        $this->actingAs($partner)
            ->getJson(route('hr.performance-cycles.index'))
            ->assertForbidden();

        $this->actingAs($partner)
            ->getJson(route('hr.performance-reviews.index'))
            ->assertForbidden();
    }

    public function test_non_global_hr_user_without_company_assignment_fails_closed_for_performance_records(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $cycle = PerformanceCycle::where('cycle_code', 'PFC-10001')->firstOrFail();
        $review = PerformanceReview::where('review_number', 'PFR-10001')->firstOrFail();
        $employee = Employee::where('employee_code', 'EMP-0012')->firstOrFail();

        $hr->forceFill(['company_id' => null])->save();

        $this->actingAs($hr)
            ->getJson(route('hr.performance-cycles.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($hr)
            ->getJson(route('hr.performance-reviews.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($hr)
            ->getJson(route('hr.performance-reviews.index', ['cycle_id' => $cycle->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cycle_id']);

        $this->actingAs($hr)
            ->postJson(route('hr.performance-cycles.store'), [
                'name' => 'Invalid No Company Cycle',
                'frequency' => 'monthly',
                'starts_on' => '2026-07-01',
                'ends_on' => '2026-07-31',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['company_id']);

        $this->actingAs($hr)
            ->postJson(route('hr.performance-reviews.store'), [
                'performance_cycle_id' => $cycle->id,
                'employee_id' => $employee->id,
                'kpis' => [
                    ['name' => 'Invalid no-company KPI', 'weight' => 100],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['performance_cycle_id', 'employee_id']);

        $this->actingAs($hr)
            ->patchJson(route('hr.performance-reviews.close', $review), [
                'lock_version' => $review->fresh()->lock_version,
                'final_score' => 4,
                'final_rating' => 'Good',
                'hr_comments' => 'Invalid no-company closure.',
            ])
            ->assertForbidden();
    }

    private function createExternalPerformanceCycle(): PerformanceCycle
    {
        $company = Company::create([
            'code' => 'EXTPERF',
            'name' => 'External Performance Co',
            'legal_name' => 'External Performance Co Private Limited',
            'state' => 'MH',
            'status' => 'active',
        ]);
        $project = Project::create([
            'company_id' => $company->id,
            'code' => 'EXT-PERF',
            'name' => 'External Performance Project',
            'project_type' => 'residential',
            'city' => 'Mumbai',
            'state' => 'MH',
            'status' => 'active',
            'budget_amount' => 1000000,
            'target_roi_percent' => 10,
        ]);

        return PerformanceCycle::create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'created_by_user_id' => null,
            'activated_by_user_id' => null,
            'cycle_code' => 'PFC-EXT-10001',
            'name' => 'External Performance Cycle',
            'frequency' => 'quarterly',
            'status' => 'active',
            'starts_on' => now()->startOfQuarter()->toDateString(),
            'ends_on' => now()->endOfQuarter()->toDateString(),
            'review_due_on' => now()->endOfQuarter()->addDays(10)->toDateString(),
            'department' => 'Construction',
            'rating_scale_min' => 1,
            'rating_scale_max' => 5,
            'passing_score' => 3,
            'rules' => ['pip_threshold' => 2.5],
            'workflow_history' => [['status' => 'active', 'actor' => 'Scope Test', 'note' => 'External cycle', 'at' => now()->toISOString()]],
            'activated_at' => now(),
        ]);
    }
}
