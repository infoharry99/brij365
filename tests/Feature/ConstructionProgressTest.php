<?php

namespace Tests\Feature;

use App\Models\ConstructionMilestone;
use App\Models\Company;
use App\Models\DailyProgressReport;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConstructionProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_construction_user_can_list_milestones_and_daily_reports(): void
    {
        $this->seed();

        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();

        $this->actingAs($construction)
            ->getJson(route('construction.milestones.index'))
            ->assertOk()
            ->assertJsonPath('data.0.milestone_code', 'SKY-FDN');

        $this->actingAs($construction)
            ->getJson(route('construction.daily-progress-reports.index'))
            ->assertOk()
            ->assertJsonPath('data.0.report_number', 'DPR-1001')
            ->assertJsonPath('data.0.status', 'approved');
    }

    public function test_construction_user_can_open_native_blade_progress_workspace(): void
    {
        $this->seed();

        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();

        $this->actingAs($construction)
            ->get(route('construction.milestones.index'))
            ->assertOk()
            ->assertSee('Construction Progress Workspace')
            ->assertSee('New construction milestone')
            ->assertSee('Daily progress report')
            ->assertSee('Construction milestones')
            ->assertSee('Daily progress reports')
            ->assertSee('SKY-FDN');

        $this->actingAs($construction)
            ->get(route('construction.daily-progress-reports.index'))
            ->assertOk()
            ->assertSee('Construction Progress Workspace')
            ->assertSee('DPR-1001');
    }

    public function test_construction_user_can_submit_blade_milestone_and_daily_report(): void
    {
        $this->seed();

        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();
        $milestone = ConstructionMilestone::where('milestone_code', 'SKY-SLAB-03')->firstOrFail();

        $this->actingAs($construction)
            ->from(route('construction.milestones.index'))
            ->post(route('construction.milestones.store'), [
                'project_id' => $project->id,
                'milestone_code' => 'SKY-BLADE-01',
                'name' => 'Blade Workspace Milestone',
                'phase' => 'Finishing',
                'planned_start_on' => now()->addDays(2)->toDateString(),
                'planned_end_on' => now()->addDays(12)->toDateString(),
                'weight_percent' => 3.25,
            ])
            ->assertRedirect(route('construction.milestones.index', ['project_id' => $project->id]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('construction_milestones', [
            'project_id' => $project->id,
            'milestone_code' => 'SKY-BLADE-01',
            'status' => 'planned',
            'progress_percent' => 0,
        ]);

        $reportDate = now()->subDays(2)->toDateString();

        $this->actingAs($construction)
            ->from(route('construction.daily-progress-reports.index'))
            ->post(route('construction.daily-progress-reports.store'), [
                'project_id' => $project->id,
                'report_date' => $reportDate,
                'weather' => 'Clear',
                'manpower_count' => 12,
                'progress_items' => [
                    [
                        'milestone_id' => $milestone->id,
                        'work_done' => 'Blade workspace daily report submitted.',
                        'progress_percent' => 64,
                    ],
                ],
                'work_summary' => 'Blade workspace DPR created from browser form.',
                'safety_observations' => 'PPE checked.',
                'quality_observations' => 'Checklist reviewed.',
                'blockers' => 'No blockers.',
            ])
            ->assertRedirect(route('construction.daily-progress-reports.index', ['project_id' => $project->id, 'status' => 'submitted']))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('daily_progress_reports', [
            'project_id' => $project->id,
            'report_date' => $reportDate.' 00:00:00',
            'status' => 'submitted',
            'work_summary' => 'Blade workspace DPR created from browser form.',
        ]);
    }

    public function test_finance_user_can_approve_blade_daily_report(): void
    {
        $this->seed();

        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();
        $milestone = ConstructionMilestone::where('milestone_code', 'SKY-SLAB-03')->firstOrFail();

        $reportNumber = $this->actingAs($construction)
            ->postJson(route('construction.daily-progress-reports.store'), [
                'project_id' => $project->id,
                'report_date' => now()->toDateString(),
                'weather' => 'Cloudy',
                'manpower_count' => 6,
                'progress_items' => [
                    [
                        'milestone_id' => $milestone->id,
                        'work_done' => 'Approval test progress from browser.',
                        'progress_percent' => 72,
                    ],
                ],
                'work_summary' => 'Approval test DPR.',
            ])
            ->assertCreated()
            ->json('data.report_number');

        $report = DailyProgressReport::where('report_number', $reportNumber)->firstOrFail();

        $this->actingAs($finance)
            ->from(route('construction.daily-progress-reports.index'))
            ->patch(route('construction.daily-progress-reports.approve', $report), [
                'note' => 'Approved from Blade progress workspace.',
            ])
            ->assertRedirect(route('construction.daily-progress-reports.index', ['project_id' => $project->id, 'status' => 'approved']))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('daily_progress_reports', [
            'id' => $report->id,
            'status' => 'approved',
            'approved_by_user_id' => $finance->id,
        ]);

        $this->assertDatabaseHas('construction_milestones', [
            'id' => $milestone->id,
            'progress_percent' => 72,
        ]);
    }

    public function test_non_global_construction_users_without_company_assignment_fail_closed_for_progress(): void
    {
        $this->seed();

        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();
        $milestone = ConstructionMilestone::where('milestone_code', 'SKY-SLAB-03')->firstOrFail();

        $report = DailyProgressReport::create([
            'company_id' => $project->company_id,
            'project_id' => $project->id,
            'prepared_by_user_id' => $construction->id,
            'report_number' => 'DPR-SCOPE-001',
            'report_date' => now()->toDateString(),
            'manpower_count' => 1,
            'progress_items' => [
                [
                    'milestone_id' => $milestone->id,
                    'milestone_code' => $milestone->milestone_code,
                    'milestone_name' => $milestone->name,
                    'work_done' => 'Scope guard test.',
                    'progress_percent' => 60,
                ],
            ],
            'work_summary' => 'Scope guard test daily report.',
            'status' => 'submitted',
            'workflow_history' => [],
        ]);

        $construction->forceFill(['company_id' => null])->save();
        $finance->forceFill(['company_id' => null])->save();

        $this->actingAs($construction)
            ->getJson(route('construction.milestones.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($construction)
            ->getJson(route('construction.daily-progress-reports.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($construction)
            ->getJson(route('construction.milestones.index', ['project_id' => $project->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id']);

        $this->actingAs($construction)
            ->getJson(route('construction.daily-progress-reports.index', ['project_id' => $project->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id']);

        $this->actingAs($construction)
            ->postJson(route('construction.milestones.store'), [
                'project_id' => $project->id,
                'milestone_code' => 'SKY-SCOPE-01',
                'name' => 'Scope Guard Milestone',
                'phase' => 'Scope',
                'planned_start_on' => now()->addDay()->toDateString(),
                'planned_end_on' => now()->addDays(5)->toDateString(),
                'weight_percent' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id']);

        $this->actingAs($construction)
            ->postJson(route('construction.daily-progress-reports.store'), [
                'project_id' => $project->id,
                'report_date' => now()->toDateString(),
                'manpower_count' => 1,
                'progress_items' => [
                    [
                        'milestone_id' => $milestone->id,
                        'work_done' => 'Scope guard should reject this report.',
                        'progress_percent' => 61,
                    ],
                ],
                'work_summary' => 'Scope guard should reject this report.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id']);

        $this->actingAs($finance)
            ->patchJson(route('construction.daily-progress-reports.approve', $report), [
                'note' => 'Scope guard should reject approval.',
            ])
            ->assertForbidden();

        $this->actingAs($finance)
            ->patchJson(route('construction.daily-progress-reports.reject', $report), [
                'reason' => 'Scope guard should reject rejection.',
            ])
            ->assertForbidden();
    }

    public function test_construction_progress_indexes_validate_filters_and_project_scope(): void
    {
        $this->seed();

        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $otherCompany = Company::where('code', 'B360P')->firstOrFail();
        $otherProject = Project::where('company_id', $otherCompany->id)->firstOrFail();

        $this->actingAs($construction)
            ->getJson(route('construction.milestones.index', ['status' => 'invalid']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->actingAs($construction)
            ->getJson(route('construction.milestones.index', ['unexpected_filter' => 'ignored-before-hardening']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['unexpected_filter'])
            ->assertJsonPath('errors.unexpected_filter.0', 'The selected filter is not available for this endpoint.');

        $this->actingAs($construction)
            ->getJson(route('construction.milestones.index', ['project_id' => $otherProject->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id']);

        $this->actingAs($construction)
            ->getJson(route('construction.milestones.index', ['per_page' => 500]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);

        $this->actingAs($construction)
            ->getJson(route('construction.daily-progress-reports.index', ['status' => 'invalid']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->actingAs($construction)
            ->getJson(route('construction.daily-progress-reports.index', ['unexpected_filter' => 'ignored-before-hardening']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['unexpected_filter'])
            ->assertJsonPath('errors.unexpected_filter.0', 'The selected filter is not available for this endpoint.');

        $this->actingAs($construction)
            ->getJson(route('construction.daily-progress-reports.index', ['project_id' => $otherProject->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id']);

        $this->actingAs($construction)
            ->getJson(route('construction.daily-progress-reports.index', [
                'date_from' => now()->toDateString(),
                'date_to' => now()->subDay()->toDateString(),
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date_to']);

        $this->actingAs($construction)
            ->getJson(route('construction.daily-progress-reports.index', [
                'status' => 'approved',
                'date_from' => now()->subWeek()->toDateString(),
                'date_to' => now()->toDateString(),
                'per_page' => 10,
            ]))
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_construction_user_can_create_milestone(): void
    {
        $this->seed();

        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();

        $this->actingAs($construction)
            ->postJson(route('construction.milestones.store'), [
                'project_id' => $project->id,
                'milestone_code' => 'SKY-BRICK-01',
                'name' => 'First Floor Brickwork',
                'phase' => 'Masonry',
                'planned_start_on' => now()->addDays(3)->toDateString(),
                'planned_end_on' => now()->addDays(20)->toDateString(),
                'weight_percent' => 7.5,
                'dependencies' => ['SKY-SLAB-03'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.milestone_code', 'SKY-BRICK-01')
            ->assertJsonPath('data.status', 'planned')
            ->assertJsonPath('data.progress_percent', 0);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'construction.milestone.created',
            'user_id' => $construction->id,
        ]);
    }

    public function test_daily_report_approval_updates_milestone_progress(): void
    {
        $this->seed();

        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();
        $milestone = ConstructionMilestone::where('milestone_code', 'SKY-SLAB-03')->firstOrFail();

        $payload = [
            'project_id' => $project->id,
            'report_date' => now()->toDateString(),
            'weather' => 'Cloudy',
            'manpower_count' => 20,
            'manpower_breakup' => [
                ['category' => 'Mason', 'count' => 8],
                ['category' => 'Helper', 'count' => 12],
            ],
            'progress_items' => [
                [
                    'milestone_id' => $milestone->id,
                    'work_done' => 'Concrete pour completed for remaining slab area.',
                    'progress_percent' => 78,
                ],
            ],
            'materials_used' => [
                ['item_code' => 'CEMENT-OPC-53', 'description' => 'OPC 53 Grade Cement', 'unit' => 'bag', 'quantity' => 80],
            ],
            'equipment_used' => [
                ['name' => 'Concrete pump', 'hours' => 4],
            ],
            'work_summary' => 'Slab concrete pour progressed.',
            'safety_observations' => 'Barricading checked.',
            'quality_observations' => 'Cube samples taken.',
        ];

        $reportNumber = $this->actingAs($construction)
            ->postJson(route('construction.daily-progress-reports.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('data.status', 'submitted')
            ->json('data.report_number');

        $report = DailyProgressReport::where('report_number', $reportNumber)->firstOrFail();

        $this->actingAs($construction)
            ->patchJson(route('construction.daily-progress-reports.approve', $report))
            ->assertForbidden();

        $this->actingAs($finance)
            ->patchJson(route('construction.daily-progress-reports.approve', $report), [
                'note' => str_repeat('x', 1001),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('note');

        $this->actingAs($finance)
            ->patchJson(route('construction.daily-progress-reports.approve', $report), [
                'note' => 'Approved after matching DPR photos with site progress.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.approved_by.email', 'suresh.iyer@builder360.test');

        $report->refresh();

        $this->assertSame('Approved after matching DPR photos with site progress.', collect($report->workflow_history)->last()['note']);

        $this->assertDatabaseHas('construction_milestones', [
            'id' => $milestone->id,
            'status' => 'in_progress',
            'progress_percent' => 78,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'construction.daily_report.approved',
            'user_id' => $finance->id,
            'metadata->note' => 'Approved after matching DPR photos with site progress.',
        ]);
    }

    public function test_daily_report_rejection_does_not_update_milestone_progress(): void
    {
        $this->seed();

        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();
        $milestone = ConstructionMilestone::where('milestone_code', 'SKY-SLAB-03')->firstOrFail();
        $initialProgress = (float) $milestone->progress_percent;

        $reportNumber = $this->actingAs($construction)
            ->postJson(route('construction.daily-progress-reports.store'), [
                'project_id' => $project->id,
                'report_date' => now()->toDateString(),
                'weather' => 'Rainy',
                'manpower_count' => 2,
                'manpower_breakup' => [
                    ['category' => 'Supervisor', 'count' => 2],
                ],
                'progress_items' => [
                    [
                        'milestone_id' => $milestone->id,
                        'work_done' => 'Reported progress requires verification.',
                        'progress_percent' => 90,
                    ],
                ],
                'work_summary' => 'Progress claim submitted for review.',
            ])
            ->assertCreated()
            ->json('data.report_number');

        $report = DailyProgressReport::where('report_number', $reportNumber)->firstOrFail();

        $this->actingAs($construction)
            ->patchJson(route('construction.daily-progress-reports.reject', $report), [
                'reason' => 'Report preparer must not reject their own submitted daily report.',
            ])
            ->assertForbidden();

        $this->actingAs($finance)
            ->patchJson(route('construction.daily-progress-reports.reject', $report), [
                'reason' => str_repeat('x', 2001),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');

        $this->actingAs($finance)
            ->patchJson(route('construction.daily-progress-reports.reject', $report), [
                'reason' => 'Progress photographs and QA checklist missing.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $this->assertSame($initialProgress, (float) $milestone->fresh()->progress_percent);
    }

    public function test_daily_report_rejects_duplicate_project_date_and_invalid_manpower_total(): void
    {
        $this->seed();

        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();
        $milestone = ConstructionMilestone::where('milestone_code', 'SKY-SLAB-03')->firstOrFail();

        $payload = [
            'project_id' => $project->id,
            'report_date' => now()->subDay()->toDateString(),
            'weather' => 'Clear',
            'manpower_count' => 10,
            'manpower_breakup' => [
                ['category' => 'Mason', 'count' => 8],
            ],
            'progress_items' => [
                [
                    'milestone_id' => $milestone->id,
                    'work_done' => 'Duplicate date check.',
                    'progress_percent' => 55,
                ],
            ],
            'work_summary' => 'Duplicate report test.',
        ];

        $this->actingAs($construction)
            ->postJson(route('construction.daily-progress-reports.store'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['report_date', 'manpower_count']);
    }

    public function test_partner_cannot_access_internal_construction_routes(): void
    {
        $this->seed();

        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();

        $this->actingAs($partner)
            ->getJson(route('construction.milestones.index'))
            ->assertForbidden();

        $this->actingAs($partner)
            ->postJson(route('construction.daily-progress-reports.store'), [])
            ->assertForbidden();
    }
}
