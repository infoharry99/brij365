<?php

namespace Tests\Feature;

use App\Application\Recruitment\Data\RecruitmentPipelineColumnData;
use App\Models\AuditEvent;
use App\Models\Branch;
use App\Models\CalendarEvent;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Interview;
use App\Models\JobOffer;
use App\Models\JobOpening;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class RecruitmentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_recruitment_users_can_open_native_blade_workspace(): void
    {
        $this->seed();

        $recruiter = User::where('email', 'ananya.sen@builder360.test')->firstOrFail();

        $this->actingAs($recruiter)
            ->get(route('recruitment.job-openings.index'))
            ->assertOk()
            ->assertSee('Recruitment')
            ->assertSee('Pipeline')
            ->assertSee('Job openings')
            ->assertSee('Candidates')
            ->assertSee('Interviews')
            ->assertSee('Offers')
            ->assertSee('JOB-1001')
            ->assertSee('data-recruitment-surface="openings"', false)
            ->assertDontSee('data-recruitment-surface="candidates"', false);

        $this->actingAs($recruiter)
            ->get(route('recruitment.pipeline.index'))
            ->assertOk()
            ->assertSee('Candidate pipeline')
            ->assertSee('Screening')
            ->assertSee('Interview scheduled')
            ->assertSee('Selected')
            ->assertSee('CAN-1001')
            ->assertSee('data-recruitment-surface="pipeline"', false)
            ->assertDontSee('data-recruitment-surface="candidates"', false);

        $this->actingAs($recruiter)
            ->get(route('recruitment.candidates.index'))
            ->assertOk()
            ->assertSee('CAN-1001')
            ->assertSee('data-recruitment-surface="candidates"', false)
            ->assertDontSee('data-recruitment-surface="openings"', false);

        $this->actingAs($recruiter)
            ->get(route('recruitment.interviews.index'))
            ->assertOk()
            ->assertSee('INT-1001')
            ->assertSee('data-recruitment-surface="interviews"', false)
            ->assertDontSee('data-recruitment-surface="candidates"', false);

        $this->actingAs($recruiter)
            ->get(route('recruitment.offers.index'))
            ->assertOk()
            ->assertSee('OFF-1001')
            ->assertSee('data-recruitment-surface="offers"', false)
            ->assertDontSee('data-recruitment-surface="interviews"', false);
    }

    public function test_pipeline_uses_independent_bounded_stage_queries_while_candidate_register_remains_paginated(): void
    {
        $this->seed();

        $recruiter = User::where('email', 'ananya.sen@builder360.test')->firstOrFail();
        $template = Candidate::where('candidate_code', 'CAN-1001')->firstOrFail();
        $source = 'Pipeline regression';

        $selected = $template->replicate(['candidate_code', 'email', 'phone', 'stage_history']);
        $selected->forceFill([
            'candidate_code' => 'CAN-PIPE-SELECTED',
            'email' => 'pipeline.selected@example.test',
            'phone' => '+91 98111 23001',
            'source' => $source,
            'stage' => 'selected',
            'status' => 'active',
            'stage_history' => [],
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ])->save();

        $screening = $template->replicate(['candidate_code', 'email', 'phone', 'stage_history']);
        $screening->forceFill([
            'candidate_code' => 'CAN-PIPE-SCREENING',
            'email' => 'pipeline.screening@example.test',
            'phone' => '+91 98111 23002',
            'source' => $source,
            'stage' => 'screening',
            'status' => 'active',
            'stage_history' => [],
            'created_at' => now(),
            'updated_at' => now(),
        ])->save();

        $this->actingAs($recruiter)
            ->get(route('recruitment.pipeline.index', ['source' => $source, 'per_page' => 1]))
            ->assertOk()
            ->assertViewHas('pipelineColumns', function (array $columns): bool {
                $columns = collect($columns)->keyBy(fn (RecruitmentPipelineColumnData $column): string => $column->stage);
                $screening = $columns->get('screening');
                $selected = $columns->get('selected');

                return $screening instanceof RecruitmentPipelineColumnData
                    && $selected instanceof RecruitmentPipelineColumnData
                    && $screening->total === 1
                    && $selected->total === 1
                    && $screening->limit === 1
                    && $selected->limit === 1
                    && $screening->candidates->sole()->code === 'CAN-PIPE-SCREENING'
                    && $selected->candidates->sole()->code === 'CAN-PIPE-SELECTED';
            })
            ->assertSee('2 matching candidates')
            ->assertSee('CAN-PIPE-SCREENING')
            ->assertSee('CAN-PIPE-SELECTED');

        $this->actingAs($recruiter)
            ->get(route('recruitment.candidates.index', ['source' => $source, 'per_page' => 1]))
            ->assertOk()
            ->assertViewHas('candidates', fn ($candidates): bool => $candidates instanceof LengthAwarePaginator
                && $candidates->perPage() === 1
                && $candidates->count() === 1
                && $candidates->total() === 2);
    }

    public function test_recruiter_can_submit_blade_opening_and_hr_can_approve(): void
    {
        $this->seed();

        $recruiter = User::where('email', 'ananya.sen@builder360.test')->firstOrFail();
        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $company = Company::where('code', 'B360D')->firstOrFail();
        $branch = Branch::where('code', 'PNQ-HO')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();

        $this->actingAs($recruiter)
            ->from(route('recruitment.job-openings.index'))
            ->post(route('recruitment.job-openings.store'), [
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'project_id' => $project->id,
                'title' => 'Blade Site Engineer',
                'department' => 'Construction',
                'designation' => 'Site Engineer',
                'positions' => 2,
                'employment_type' => 'full_time',
                'work_location' => 'Pune',
                'budget_min_ctc' => 540000,
                'budget_max_ctc' => 720000,
                'target_hiring_date' => now()->addDays(25)->toDateString(),
                'required_skills' => ['Site execution'],
                'business_justification' => 'Blade workspace recruitment requisition.',
            ])
            ->assertRedirect(route('recruitment.job-openings.index', ['status' => 'pending_approval']))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $opening = JobOpening::where('title', 'Blade Site Engineer')->firstOrFail();

        $this->assertDatabaseHas('job_openings', [
            'id' => $opening->id,
            'status' => 'pending_approval',
            'created_by_user_id' => $recruiter->id,
        ]);

        $this->actingAs($hr)
            ->from(route('recruitment.job-openings.index'))
            ->patch(route('recruitment.job-openings.approve', $opening), [
                'review_note' => 'Approved from Blade recruitment workspace.',
            ])
            ->assertRedirect(route('recruitment.job-openings.index', ['status' => 'open']))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('job_openings', [
            'id' => $opening->id,
            'status' => 'open',
            'reviewed_by_user_id' => $hr->id,
        ]);
    }

    public function test_recruiter_can_create_blade_candidate_schedule_interview_and_create_offer_then_hr_releases(): void
    {
        $this->seed();

        $recruiter = User::where('email', 'ananya.sen@builder360.test')->firstOrFail();
        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $opening = JobOpening::where('opening_code', 'JOB-1001')->firstOrFail();
        JobOffer::whereHas('candidate', fn ($query) => $query->where('email', 'blade.candidate@example.test'))->delete();

        $this->actingAs($recruiter)
            ->from(route('recruitment.candidates.index'))
            ->post(route('recruitment.candidates.store'), [
                'job_opening_id' => $opening->id,
                'name' => 'Blade Candidate',
                'email' => 'blade.candidate@example.test',
                'phone' => '+91 98111 23001',
                'source' => 'LinkedIn',
                'current_company' => 'Prior Employer',
                'experience_years' => 3.5,
                'current_ctc' => 520000,
                'expected_ctc' => 700000,
                'notice_period_days' => 30,
                'skills' => ['CRM'],
                'notes' => 'Created from Blade recruitment workspace.',
            ])
            ->assertRedirect(route('recruitment.candidates.index', ['stage' => 'screening']))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $candidate = Candidate::where('email', 'blade.candidate@example.test')->firstOrFail();

        $this->actingAs($recruiter)
            ->from(route('recruitment.interviews.index'))
            ->post(route('recruitment.interviews.store'), [
                'candidate_id' => $candidate->id,
                'round_name' => 'Blade HR Round',
                'scheduled_at' => now()->addDays(20)->setTime(10, 30)->format('Y-m-d\TH:i'),
                'duration_minutes' => 45,
                'mode' => 'video',
                'venue_or_link' => 'https://meet.example.test/blade-candidate',
                'panel_user_ids' => [$hr->id],
            ])
            ->assertRedirect(route('recruitment.interviews.index', ['status' => 'scheduled']))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $interview = Interview::where('candidate_id', $candidate->id)->firstOrFail();

        $this->actingAs($hr)
            ->from(route('recruitment.interviews.index'))
            ->patch(route('recruitment.interviews.feedback', $interview), [
                'rating' => 4,
                'recommendation' => 'selected',
                'feedback_note' => 'Good fit from Blade workflow.',
            ])
            ->assertRedirect(route('recruitment.interviews.index', ['status' => 'completed']))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $candidate->refresh();
        $joiningDate = now()->addDays(35)->toDateString();

        $this->actingAs($recruiter)
            ->from(route('recruitment.offers.index'))
            ->post(route('recruitment.offers.store'), [
                'candidate_id' => $candidate->id,
                'template_code' => 'BLADE_APPOINTMENT',
                'offered_ctc' => 700000,
                'joining_date' => $joiningDate,
                'placeholders' => [
                    'candidate_name' => 'Blade Candidate',
                    'designation' => 'Senior Executive',
                    'department' => 'Sales',
                    'joining_date' => $joiningDate,
                    'offered_ctc' => 700000,
                ],
            ])
            ->assertRedirect(route('recruitment.offers.index', ['status' => 'draft']))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $offer = JobOffer::where('candidate_id', $candidate->id)->firstOrFail();

        $this->actingAs($hr)
            ->from(route('recruitment.offers.index'))
            ->patch(route('recruitment.offers.release', $offer), [
                'release_note' => 'Released from Blade recruitment workspace.',
            ])
            ->assertRedirect(route('recruitment.offers.index', ['status' => 'released']))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('candidates', [
            'id' => $candidate->id,
            'stage' => 'offer_released',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('job_offers', [
            'id' => $offer->id,
            'status' => 'released',
            'released_by_user_id' => $hr->id,
        ]);
    }

    public function test_recruiter_can_list_recruitment_master_data(): void
    {
        $this->seed();

        $recruiter = User::where('email', 'ananya.sen@builder360.test')->firstOrFail();

        $this->actingAs($recruiter)
            ->getJson(route('recruitment.job-openings.index'))
            ->assertOk()
            ->assertJsonPath('data.0.opening_code', 'JOB-1001')
            ->assertJsonPath('data.0.status', 'open');

        $this->actingAs($recruiter)
            ->getJson(route('recruitment.candidates.index'))
            ->assertOk()
            ->assertJsonPath('data.0.candidate_code', 'CAN-1001')
            ->assertJsonPath('data.0.stage', 'offer_released')
            ->assertJsonPath('data.0.interviews.0.panel.0.email', 'deepa.rao@builder360.test');

        $this->actingAs($recruiter)
            ->getJson(route('recruitment.interviews.index'))
            ->assertOk()
            ->assertJsonPath('data.0.panel.0.email', 'deepa.rao@builder360.test')
            ->assertJsonPath('data.0.panel.1.email', 'priya.nair@builder360.test');
    }

    public function test_non_global_recruitment_users_without_company_assignment_fail_closed(): void
    {
        $this->seed();

        $recruiter = User::where('email', 'ananya.sen@builder360.test')->firstOrFail();
        $hrManager = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $opening = JobOpening::where('opening_code', 'JOB-1001')->firstOrFail();
        $candidate = Candidate::where('candidate_code', 'CAN-1001')->firstOrFail();
        $panelUser = User::where('email', 'priya.nair@builder360.test')->firstOrFail();

        $candidate->forceFill(['stage' => 'selected'])->save();
        JobOffer::where('candidate_id', $candidate->id)->delete();

        $offer = JobOffer::create([
            'company_id' => $candidate->company_id,
            'candidate_id' => $candidate->id,
            'created_by_user_id' => $recruiter->id,
            'offer_number' => 'OFF-SCOPE-0001',
            'template_code' => 'SALES_EXECUTIVE_APPOINTMENT',
            'offered_ctc' => 820000,
            'joining_date' => now()->addDays(40)->toDateString(),
            'placeholders' => [
                'candidate_name' => $candidate->name,
                'designation' => 'Senior Executive',
                'department' => 'Sales',
                'joining_date' => now()->addDays(40)->toDateString(),
                'offered_ctc' => 820000,
            ],
            'status' => 'draft',
            'document_history' => [],
        ]);

        $recruiter->forceFill(['company_id' => null])->save();
        $hrManager->forceFill(['company_id' => null])->save();

        $this->actingAs($recruiter)
            ->getJson(route('recruitment.job-openings.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($recruiter)
            ->getJson(route('recruitment.candidates.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($recruiter)
            ->getJson(route('recruitment.interviews.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($recruiter)
            ->getJson(route('recruitment.offers.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($recruiter)
            ->getJson(route('recruitment.source-summary'))
            ->assertOk()
            ->assertJsonPath('data.scope.company_id', 0)
            ->assertJsonPath('data.totals.candidates', 0);

        $this->actingAs($recruiter)
            ->postJson(route('recruitment.candidates.store'), [
                'job_opening_id' => $opening->id,
                'name' => 'Scope Guard Candidate',
                'email' => 'scope.guard.candidate@example.test',
                'phone' => '+91 98111 33001',
                'source' => 'Referral',
                'experience_years' => 4,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['job_opening_id']);

        $this->actingAs($recruiter)
            ->postJson(route('recruitment.interviews.store'), [
                'candidate_id' => $candidate->id,
                'round_name' => 'Scope Guard Round',
                'scheduled_at' => now()->addDays(7)->setTime(11, 0)->toDateTimeString(),
                'duration_minutes' => 60,
                'mode' => 'video',
                'panel_user_ids' => [$panelUser->id],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['candidate_id']);

        $this->actingAs($recruiter)
            ->postJson(route('recruitment.offers.store'), [
                'candidate_id' => $candidate->id,
                'template_code' => 'SALES_EXECUTIVE_APPOINTMENT',
                'offered_ctc' => 820000,
                'joining_date' => now()->addDays(40)->toDateString(),
                'placeholders' => [
                    'candidate_name' => $candidate->name,
                    'designation' => 'Senior Executive',
                    'department' => 'Sales',
                    'joining_date' => now()->addDays(40)->toDateString(),
                    'offered_ctc' => 820000,
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['candidate_id']);

        $this->actingAs($hrManager)
            ->patchJson(route('recruitment.offers.release', $offer), [
                'release_note' => 'Scope guard should reject release.',
            ])
            ->assertForbidden();
    }

    public function test_recruitment_indexes_validate_filters(): void
    {
        $this->seed();

        $recruiter = User::where('email', 'ananya.sen@builder360.test')->firstOrFail();

        $this->actingAs($recruiter)
            ->getJson(route('recruitment.job-openings.index', ['status' => 'invalid']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->actingAs($recruiter)
            ->getJson(route('recruitment.job-openings.index', ['unexpected_filter' => 'ignored-before-hardening']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['unexpected_filter'])
            ->assertJsonPath('errors.unexpected_filter.0', 'The selected filter is not available for this endpoint.');

        $this->actingAs($recruiter)
            ->getJson(route('recruitment.job-openings.index', ['department' => str_repeat('x', 121)]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['department']);

        $this->actingAs($recruiter)
            ->getJson(route('recruitment.candidates.index', ['stage' => 'invalid']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['stage']);

        $this->actingAs($recruiter)
            ->getJson(route('recruitment.candidates.index', ['unexpected_filter' => 'ignored-before-hardening']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['unexpected_filter'])
            ->assertJsonPath('errors.unexpected_filter.0', 'The selected filter is not available for this endpoint.');

        $this->actingAs($recruiter)
            ->getJson(route('recruitment.candidates.index', ['search' => str_repeat('x', 121)]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['search']);

        $this->actingAs($recruiter)
            ->getJson(route('recruitment.interviews.index', ['status' => 'invalid']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->actingAs($recruiter)
            ->getJson(route('recruitment.interviews.index', ['unexpected_filter' => 'ignored-before-hardening']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['unexpected_filter'])
            ->assertJsonPath('errors.unexpected_filter.0', 'The selected filter is not available for this endpoint.');

        $this->actingAs($recruiter)
            ->getJson(route('recruitment.interviews.index', ['date' => 'not-a-date']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date']);

        $this->actingAs($recruiter)
            ->getJson(route('recruitment.offers.index', ['status' => 'invalid']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->actingAs($recruiter)
            ->getJson(route('recruitment.offers.index', ['unexpected_filter' => 'ignored-before-hardening']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['unexpected_filter'])
            ->assertJsonPath('errors.unexpected_filter.0', 'The selected filter is not available for this endpoint.');

        $this->actingAs($recruiter)
            ->getJson(route('recruitment.offers.index', ['per_page' => 500]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);

        $this->actingAs($recruiter)
            ->getJson(route('recruitment.source-summary', ['unexpected_filter' => 'ignored-before-hardening']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['unexpected_filter'])
            ->assertJsonPath('errors.unexpected_filter.0', 'The selected filter is not available for this endpoint.');

        $this->actingAs($recruiter)
            ->getJson(route('recruitment.source-summary', ['date_to' => now()->subDay()->toDateString(), 'date_from' => now()->toDateString()]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date_to']);

        $this->actingAs($recruiter)
            ->getJson(route('recruitment.candidates.index', [
                'stage' => 'offer_released',
                'source' => 'Naukri',
                'search' => 'CAN-1001',
                'per_page' => 10,
            ]))
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_recruitment_source_summary_reports_real_source_metrics(): void
    {
        $this->seed();

        $recruiter = User::where('email', 'ananya.sen@builder360.test')->firstOrFail();

        $this->actingAs($recruiter)
            ->getJson(route('recruitment.source-summary', [
                'source' => 'Naukri',
                'department' => 'Sales',
            ]))
            ->assertOk()
            ->assertJsonPath('data.scope.company_id', $recruiter->company_id)
            ->assertJsonPath('data.filters.source', 'Naukri')
            ->assertJsonPath('data.filters.department', 'Sales')
            ->assertJsonPath('data.totals.sources', 1)
            ->assertJsonPath('data.totals.candidates', 1)
            ->assertJsonPath('data.totals.offers', 1)
            ->assertJsonPath('data.totals.converted', 0)
            ->assertJsonPath('data.rows.0.source', 'Naukri')
            ->assertJsonPath('data.rows.0.total_candidates', 1)
            ->assertJsonPath('data.rows.0.offer_count', 1)
            ->assertJsonPath('data.rows.0.offer_rate', 100)
            ->assertJsonPath('data.rows.0.conversion_rate', 0);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'recruitment.source_summary.viewed',
            'user_id' => $recruiter->id,
        ]);
    }

    public function test_recruiter_can_create_candidate_and_duplicate_email_is_rejected(): void
    {
        $this->seed();

        $recruiter = User::where('email', 'ananya.sen@builder360.test')->firstOrFail();
        $opening = JobOpening::where('opening_code', 'JOB-1001')->firstOrFail();

        $payload = [
            'job_opening_id' => $opening->id,
            'name' => 'Karan Deshmukh',
            'email' => 'karan.deshmukh@example.test',
            'phone' => '+91 98111 22001',
            'source' => 'Referral',
            'current_company' => 'City Square Realty',
            'experience_years' => 3.25,
            'current_ctc' => 540000,
            'expected_ctc' => 700000,
            'notice_period_days' => 45,
            'skills' => ['CRM', 'Inside Sales'],
            'documents' => [['type' => 'resume', 'name' => 'karan-resume.pdf']],
            'notes' => 'Strong CRM background.',
        ];

        $this->actingAs($recruiter)
            ->postJson(route('recruitment.candidates.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('data.name', 'Karan Deshmukh')
            ->assertJsonPath('data.stage', 'screening');

        $this->assertDatabaseHas('candidates', [
            'email' => 'karan.deshmukh@example.test',
            'stage' => 'screening',
            'owner_user_id' => $recruiter->id,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'recruitment.candidate.created',
            'action' => 'Created recruitment candidate',
            'user_id' => $recruiter->id,
        ]);

        $this->actingAs($recruiter)
            ->postJson(route('recruitment.candidates.store'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'phone']);
    }

    public function test_recruiter_can_update_candidate_stage_with_audit_and_guards(): void
    {
        $this->seed();

        $recruiter = User::where('email', 'ananya.sen@builder360.test')->firstOrFail();
        $hrManager = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $opening = JobOpening::where('opening_code', 'JOB-1001')->firstOrFail();

        $candidateCode = $this->actingAs($recruiter)
            ->postJson(route('recruitment.candidates.store'), [
                'job_opening_id' => $opening->id,
                'name' => 'Stage Update Candidate',
                'email' => 'stage.update.candidate@example.test',
                'phone' => '+91 98111 22031',
                'source' => 'Referral',
                'experience_years' => 2.5,
                'skills' => ['Sales'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.stage', 'screening')
            ->assertJsonPath('data.permissions.update', true)
            ->assertJsonPath('data.can_transition_stage', true)
            ->json('data.candidate_code');

        $candidate = Candidate::where('candidate_code', $candidateCode)->firstOrFail();

        $this->actingAs($hrManager)
            ->patchJson(route('recruitment.candidates.stage', $candidate), [
                'stage' => 'selected',
            ])
            ->assertForbidden();

        $this->actingAs($recruiter)
            ->patchJson(route('recruitment.candidates.stage', $candidate), [
                'stage' => 'offer_released',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('stage');

        $this->actingAs($recruiter)
            ->patchJson(route('recruitment.candidates.stage', $candidate), [
                'stage' => 'selected',
                'transition_note' => 'Selected after recruiter screening.',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Candidate stage updated.')
            ->assertJsonPath('data.stage', 'selected')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.can_transition_stage', false);

        $this->assertDatabaseHas('candidates', [
            'id' => $candidate->id,
            'stage' => 'selected',
            'status' => 'active',
        ]);

        $candidate->refresh();
        $this->assertSame('selected', collect($candidate->stage_history)->last()['stage']);
        $this->assertSame('Selected after recruiter screening.', collect($candidate->stage_history)->last()['note']);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'recruitment.candidate.stage_updated',
            'user_id' => $recruiter->id,
        ]);
    }

    public function test_job_requisition_submission_approval_and_candidate_gating(): void
    {
        $this->seed();

        $recruiter = User::where('email', 'ananya.sen@builder360.test')->firstOrFail();
        $hrManager = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $company = Company::where('code', 'B360D')->firstOrFail();
        $branch = Branch::where('code', 'PNQ-HO')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();

        $payload = [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'project_id' => $project->id,
            'title' => 'Channel Sales Manager',
            'department' => 'Sales',
            'designation' => 'Manager',
            'positions' => 2,
            'employment_type' => 'full_time',
            'work_location' => 'Pune',
            'budget_min_ctc' => 900000,
            'budget_max_ctc' => 1200000,
            'target_hiring_date' => now()->addDays(45)->toDateString(),
            'required_skills' => ['Channel Sales', 'Broker Network'],
            'business_justification' => 'Additional channel coverage for new launch.',
        ];

        $openingCode = $this->actingAs($recruiter)
            ->postJson(route('recruitment.job-openings.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('message', 'Job requisition submitted for approval.')
            ->assertJsonPath('data.status', 'pending_approval')
            ->assertJsonPath('data.created_by.email', 'ananya.sen@builder360.test')
            ->assertJsonPath('data.business_justification', 'Additional channel coverage for new launch.')
            ->json('data.opening_code');

        $opening = JobOpening::where('opening_code', $openingCode)->firstOrFail();

        $this->actingAs($recruiter)
            ->postJson(route('recruitment.candidates.store'), [
                'job_opening_id' => $opening->id,
                'name' => 'Pending Requisition Candidate',
                'email' => 'pending.requisition.candidate@example.test',
                'phone' => '+91 98111 22101',
                'source' => 'Referral',
                'experience_years' => 5,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['job_opening_id']);

        $this->actingAs($recruiter)
            ->patchJson(route('recruitment.job-openings.approve', $opening), [
                'review_note' => 'Creator should not approve.',
            ])
            ->assertForbidden();

        $this->actingAs($hrManager)
            ->patchJson(route('recruitment.job-openings.approve', $opening), [
                'review_note' => 'Approved for immediate sourcing.',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Job requisition approved.')
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.reviewed_by.email', 'deepa.rao@builder360.test');

        $this->assertDatabaseHas('job_openings', [
            'id' => $opening->id,
            'status' => 'open',
            'reviewed_by_user_id' => $hrManager->id,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'recruitment.job_opening.created',
            'user_id' => $recruiter->id,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'recruitment.job_opening.approved',
            'user_id' => $hrManager->id,
        ]);

        $this->actingAs($recruiter)
            ->postJson(route('recruitment.candidates.store'), [
                'job_opening_id' => $opening->id,
                'name' => 'Approved Requisition Candidate',
                'email' => 'approved.requisition.candidate@example.test',
                'phone' => '+91 98111 22102',
                'source' => 'Referral',
                'experience_years' => 5,
            ])
            ->assertCreated()
            ->assertJsonPath('data.stage', 'screening');
    }

    public function test_hr_can_reject_pending_job_requisition(): void
    {
        $this->seed();

        $recruiter = User::where('email', 'ananya.sen@builder360.test')->firstOrFail();
        $hrManager = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $company = Company::where('code', 'B360D')->firstOrFail();

        $openingCode = $this->actingAs($recruiter)
            ->postJson(route('recruitment.job-openings.store'), [
                'company_id' => $company->id,
                'title' => 'Temporary Hiring Request',
                'department' => 'Sales',
                'designation' => 'Executive',
                'positions' => 1,
                'employment_type' => 'contract',
                'budget_max_ctc' => 500000,
                'target_hiring_date' => now()->addDays(20)->toDateString(),
            ])
            ->assertCreated()
            ->json('data.opening_code');

        $opening = JobOpening::where('opening_code', $openingCode)->firstOrFail();

        $this->actingAs($hrManager)
            ->patchJson(route('recruitment.job-openings.reject', $opening), [
                'review_note' => 'Role to be covered through internal transfer.',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Job requisition rejected.')
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.reviewed_by.email', 'deepa.rao@builder360.test');

        $this->assertDatabaseHas('job_openings', [
            'id' => $opening->id,
            'status' => 'rejected',
            'reviewed_by_user_id' => $hrManager->id,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'recruitment.job_opening.rejected',
            'user_id' => $hrManager->id,
        ]);
    }

    public function test_interview_scheduling_rejects_panel_conflict(): void
    {
        $this->seed();

        $recruiter = User::where('email', 'ananya.sen@builder360.test')->firstOrFail();
        $candidate = Candidate::where('candidate_code', 'CAN-1001')->firstOrFail();
        $panelUser = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $slot = now()->addDays(8)->setTime(14, 0)->toDateTimeString();

        $payload = [
            'candidate_id' => $candidate->id,
            'round_name' => 'Sales Manager Round',
            'scheduled_at' => $slot,
            'duration_minutes' => 60,
            'mode' => 'video',
            'venue_or_link' => 'https://meet.example.test/recruitment-round',
            'panel_user_ids' => [$panelUser->id],
        ];

        $this->actingAs($recruiter)
            ->postJson(route('recruitment.interviews.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('data.status', 'scheduled')
            ->assertJsonPath('data.candidate.stage', 'interview_scheduled')
            ->assertJsonPath('data.panel.0.email', 'deepa.rao@builder360.test');

        $this->assertDatabaseHas('interviews', [
            'candidate_id' => $candidate->id,
            'scheduled_at' => $slot,
            'status' => 'scheduled',
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'recruitment.interview.scheduled',
            'user_id' => $recruiter->id,
        ]);

        $otherCandidate = Candidate::where('candidate_code', 'CAN-1001')->firstOrFail()->replicate([
            'candidate_code',
            'email',
            'phone',
        ]);
        $otherCandidate->forceFill([
            'candidate_code' => 'CAN-1999',
            'email' => 'conflict.panel@example.test',
            'phone' => '+91 98111 22999',
            'stage' => 'screening',
        ])->save();

        $payload['candidate_id'] = $otherCandidate->id;

        $this->actingAs($recruiter)
            ->postJson(route('recruitment.interviews.store'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('panel_user_ids');
    }

    public function test_interview_scheduling_uses_half_open_intervals_and_allows_adjacent_slots(): void
    {
        $this->seed();

        $recruiter = User::where('email', 'ananya.sen@builder360.test')->firstOrFail();
        $candidate = Candidate::where('candidate_code', 'CAN-1001')->firstOrFail();
        $panelUser = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $otherPanelUser = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $startsAt = now()->addDays(30)->setTime(10, 0, 0);

        $basePayload = [
            'round_name' => 'Availability Round',
            'duration_minutes' => 60,
            'mode' => 'video',
            'venue_or_link' => 'https://meet.example.test/availability-round',
        ];

        $this->actingAs($recruiter)
            ->postJson(route('recruitment.interviews.store'), $basePayload + [
                'candidate_id' => $candidate->id,
                'scheduled_at' => $startsAt->toDateTimeString(),
                'panel_user_ids' => [$panelUser->id],
            ])
            ->assertCreated();

        $this->actingAs($recruiter)
            ->postJson(route('recruitment.interviews.store'), $basePayload + [
                'candidate_id' => $candidate->id,
                'scheduled_at' => $startsAt->copy()->addMinutes(30)->toDateTimeString(),
                'panel_user_ids' => [$otherPanelUser->id],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('scheduled_at');

        $secondCandidate = $candidate->replicate(['candidate_code', 'email', 'phone', 'stage_history']);
        $secondCandidate->forceFill([
            'candidate_code' => 'CAN-INTERVAL-2',
            'email' => 'interval.second@example.test',
            'phone' => '+91 98111 22002',
            'stage' => 'screening',
            'status' => 'active',
            'stage_history' => [],
        ])->save();

        $this->actingAs($recruiter)
            ->postJson(route('recruitment.interviews.store'), $basePayload + [
                'candidate_id' => $secondCandidate->id,
                'scheduled_at' => $startsAt->copy()->addMinutes(30)->toDateTimeString(),
                'panel_user_ids' => [$panelUser->id],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('panel_user_ids');

        $this->actingAs($recruiter)
            ->postJson(route('recruitment.interviews.store'), $basePayload + [
                'candidate_id' => $secondCandidate->id,
                'scheduled_at' => $startsAt->copy()->addHour()->toDateTimeString(),
                'panel_user_ids' => [$panelUser->id],
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'scheduled');
    }

    public function test_interview_scheduling_rejects_overlapping_builder360_calendar_events(): void
    {
        $this->seed();

        $recruiter = User::where('email', 'ananya.sen@builder360.test')->firstOrFail();
        $candidate = Candidate::where('candidate_code', 'CAN-1001')->firstOrFail();
        $panelUser = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $startsAt = now()->addDays(35)->setTime(13, 0, 0);

        CalendarEvent::create([
            'company_id' => $candidate->company_id,
            'project_id' => $candidate->jobOpening?->project_id,
            'organizer_user_id' => $panelUser->id,
            'event_number' => 'CAL-RECRUITMENT-CONFLICT',
            'title' => 'Panel member project review',
            'event_type' => 'meeting',
            'status' => 'scheduled',
            'starts_at' => $startsAt->copy()->subMinutes(30),
            'ends_at' => $startsAt->copy()->addMinutes(30),
            'timezone' => 'Asia/Kolkata',
            'visibility' => 'internal',
            'attendees' => [],
            'reminders' => [],
            'workflow_history' => [],
            'metadata' => [],
        ]);

        $payload = [
            'candidate_id' => $candidate->id,
            'round_name' => 'Calendar Conflict Round',
            'scheduled_at' => $startsAt->toDateTimeString(),
            'duration_minutes' => 60,
            'mode' => 'video',
            'venue_or_link' => 'https://meet.example.test/calendar-conflict-round',
            'panel_user_ids' => [$panelUser->id],
        ];

        $this->actingAs($recruiter)
            ->postJson(route('recruitment.interviews.store'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('panel_user_ids');

        $payload['scheduled_at'] = $startsAt->copy()->addMinutes(30)->toDateTimeString();

        $this->actingAs($recruiter)
            ->postJson(route('recruitment.interviews.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('data.status', 'scheduled');
    }

    public function test_panel_member_can_submit_interview_feedback_and_complete_interview(): void
    {
        $this->seed();

        $recruiter = User::where('email', 'ananya.sen@builder360.test')->firstOrFail();
        $panelUser = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $opening = JobOpening::where('opening_code', 'JOB-1001')->firstOrFail();

        $candidateId = $this->actingAs($recruiter)
            ->postJson(route('recruitment.candidates.store'), [
                'job_opening_id' => $opening->id,
                'name' => 'Feedback Candidate',
                'email' => 'feedback.candidate@example.test',
                'phone' => '+91 98111 22301',
                'source' => 'Referral',
                'experience_years' => 4,
            ])
            ->assertCreated()
            ->json('data.id');

        $interviewId = $this->actingAs($recruiter)
            ->postJson(route('recruitment.interviews.store'), [
                'candidate_id' => $candidateId,
                'round_name' => 'Panel Feedback Round',
                'scheduled_at' => now()->addDays(12)->setTime(10, 0)->toDateTimeString(),
                'duration_minutes' => 60,
                'mode' => 'video',
                'venue_or_link' => 'https://meet.example.test/panel-feedback-round',
                'panel_user_ids' => [$panelUser->id],
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'scheduled')
            ->json('data.id');

        $this->actingAs($panelUser)
            ->patchJson(route('recruitment.interviews.feedback', Interview::findOrFail($interviewId)), [
                'rating' => 4,
                'recommendation' => 'selected',
                'strengths' => 'Strong channel partner handling and follow-up discipline.',
                'concerns' => 'Needs induction on Builder360 pricing controls.',
                'feedback_note' => 'Recommended for offer after compensation discussion.',
                'next_action' => 'Move to offer discussion.',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Interview feedback submitted.')
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.candidate.stage', 'interviewed')
            ->assertJsonPath('data.feedback.summary.average_rating', 4)
            ->assertJsonPath('data.feedback.summary.completed', true)
            ->assertJsonPath('data.feedback.entries.0.reviewer_email', 'deepa.rao@builder360.test')
            ->assertJsonPath('data.feedback.entries.0.recommendation', 'selected');

        $this->assertDatabaseHas('interviews', [
            'id' => $interviewId,
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('candidates', [
            'id' => $candidateId,
            'stage' => 'interviewed',
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'recruitment.interview.feedback_submitted',
            'user_id' => $panelUser->id,
        ]);
    }

    public function test_only_panel_members_can_submit_feedback_and_duplicates_are_rejected(): void
    {
        $this->seed();

        $recruiter = User::where('email', 'ananya.sen@builder360.test')->firstOrFail();
        $panelUser = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $interview = Interview::where('interview_code', 'INT-1001')->firstOrFail();

        $this->actingAs($recruiter)
            ->patchJson(route('recruitment.interviews.feedback', $interview), [
                'rating' => 3,
                'recommendation' => 'hold',
            ])
            ->assertForbidden();

        $this->actingAs($panelUser)
            ->patchJson(route('recruitment.interviews.feedback', $interview), [
                'rating' => 5,
                'recommendation' => 'second_round',
                'feedback_note' => 'Proceed to business head discussion.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'scheduled')
            ->assertJsonPath('data.feedback.summary.submitted_count', 1)
            ->assertJsonPath('data.feedback.summary.panel_count', 2);

        $this->actingAs($panelUser)
            ->patchJson(route('recruitment.interviews.feedback', $interview->fresh()), [
                'rating' => 4,
                'recommendation' => 'selected',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['interview']);
    }

    public function test_offer_creator_cannot_release_and_hr_can_release(): void
    {
        $this->seed();

        $recruiter = User::where('email', 'ananya.sen@builder360.test')->firstOrFail();
        $hrManager = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $candidate = Candidate::where('candidate_code', 'CAN-1001')->firstOrFail();

        $candidate->forceFill(['stage' => 'selected'])->save();
        JobOffer::where('candidate_id', $candidate->id)->delete();

        $payload = [
            'candidate_id' => $candidate->id,
            'template_code' => 'SALES_EXECUTIVE_APPOINTMENT',
            'offered_ctc' => 820000,
            'joining_date' => now()->addDays(40)->toDateString(),
            'placeholders' => [
                'candidate_name' => $candidate->name,
                'designation' => 'Senior Executive',
                'department' => 'Sales',
                'joining_date' => now()->addDays(40)->toDateString(),
                'offered_ctc' => 820000,
            ],
        ];

        $offerNumber = $this->actingAs($recruiter)
            ->postJson(route('recruitment.offers.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.permissions.release', false)
            ->assertJsonPath('data.candidate.stage', 'offer_draft')
            ->json('data.offer_number');

        $offer = JobOffer::where('offer_number', $offerNumber)->firstOrFail();

        $this->actingAs($recruiter)
            ->patchJson(route('recruitment.offers.release', $offer))
            ->assertForbidden();

        $this->actingAs($hrManager)
            ->getJson(route('recruitment.offers.index', ['status' => 'draft']))
            ->assertOk()
            ->assertJsonFragment([
                'offer_number' => $offerNumber,
            ])
            ->assertJsonPath('data.0.permissions.release', true);

        $this->actingAs($hrManager)
            ->patchJson(route('recruitment.offers.release', $offer), [
                'release_note' => str_repeat('x', 2001),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('release_note');

        $this->actingAs($hrManager)
            ->patchJson(route('recruitment.offers.release', $offer), [
                'release_note' => 'Approved for candidate release after HR review.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'released')
            ->assertJsonPath('data.released_by.email', 'deepa.rao@builder360.test')
            ->assertJsonPath('data.candidate.stage', 'offer_released');

        $this->assertDatabaseHas('job_offers', [
            'id' => $offer->id,
            'status' => 'released',
            'released_by_user_id' => $hrManager->id,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'recruitment.offer.released',
            'user_id' => $hrManager->id,
        ]);

        $offer->refresh();
        $this->assertSame('Approved for candidate release after HR review.', collect($offer->document_history)->last()['note']);

        $audit = AuditEvent::query()
            ->where('event_type', 'recruitment.offer.released')
            ->latest()
            ->firstOrFail();
        $this->assertSame('Approved for candidate release after HR review.', $audit->metadata['release_note']);
    }

    public function test_hr_can_convert_released_candidate_offer_to_employee(): void
    {
        $this->seed();

        $hrManager = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $candidate = Candidate::where('candidate_code', 'CAN-1001')->firstOrFail();
        $offer = JobOffer::where('candidate_id', $candidate->id)->firstOrFail();

        $this->assertSame('released', $offer->status);

        $candidateRegisterRow = collect($this->actingAs($hrManager)
            ->getJson(route('recruitment.candidates.index', ['stage' => 'offer_released']))
            ->assertOk()
            ->json('data'))->firstWhere('candidate_code', $candidate->candidate_code);

        $this->assertNotNull($candidateRegisterRow);
        $this->assertTrue($candidateRegisterRow['permissions']['convert']);
        $this->assertTrue($candidateRegisterRow['can_convert_to_employee']);

        $response = $this->actingAs($hrManager)
            ->postJson(route('recruitment.candidates.convert-to-employee', $candidate), [
                'employee_code' => 'EMP-CONV-1',
                'grade' => 'S1',
                'joined_on' => now()->toDateString(),
                'statutory_state' => 'MH',
                'acceptance_note' => 'Candidate accepted offer and joined HR records.',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Candidate converted to employee.')
            ->assertJsonPath('data.employee.employee_code', 'EMP-CONV-1')
            ->assertJsonPath('data.stage', 'employee_created')
            ->assertJsonPath('data.status', 'converted')
            ->assertJsonPath('data.permissions.convert', true)
            ->assertJsonPath('data.can_convert_to_employee', false)
            ->assertJsonPath('data.offer.status', 'accepted')
            ->assertJsonPath('data.offer.accepted_by.email', 'deepa.rao@builder360.test');

        $employee = Employee::where('employee_code', 'EMP-CONV-1')->firstOrFail();

        $this->assertSame($candidate->name, $employee->name);
        $this->assertSame('Sales', $employee->department);
        $this->assertSame('Senior Executive', $employee->designation);
        $this->assertSame('full_time', $employee->employment_type);
        $this->assertSame('MH', $employee->statutory_state);
        $this->assertSame($candidate->candidate_code, $employee->sensitive_profile['candidate_code']);
        $this->assertSame($offer->offer_number, $employee->sensitive_profile['offer_number']);
        $this->assertSame('65000.00', (string) $employee->monthly_ctc);

        $this->assertDatabaseHas('candidates', [
            'id' => $candidate->id,
            'employee_id' => $employee->id,
            'stage' => 'employee_created',
            'status' => 'converted',
        ]);

        $this->assertDatabaseHas('job_offers', [
            'id' => $offer->id,
            'status' => 'accepted',
            'accepted_by_user_id' => $hrManager->id,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'recruitment.candidate.converted_to_employee',
            'action' => 'Converted candidate to employee',
            'user_id' => $hrManager->id,
        ]);

        $this->assertSame('EMP-CONV-1', $response->json('data.employee.employee_code'));
    }

    public function test_candidate_conversion_blocks_duplicate_employee_creation(): void
    {
        $this->seed();

        $hrManager = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $candidate = Candidate::where('candidate_code', 'CAN-1001')->firstOrFail();

        $this->actingAs($hrManager)
            ->postJson(route('recruitment.candidates.convert-to-employee', $candidate), [
                'employee_code' => 'EMP-CONV-2',
                'joined_on' => now()->toDateString(),
            ])
            ->assertOk();

        $this->actingAs($hrManager)
            ->postJson(route('recruitment.candidates.convert-to-employee', $candidate->fresh()), [
                'employee_code' => 'EMP-CONV-3',
                'joined_on' => now()->toDateString(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['candidate']);

        $this->assertSame(1, Employee::whereIn('employee_code', ['EMP-CONV-2', 'EMP-CONV-3'])->count());
    }

    public function test_candidate_conversion_requires_released_offer(): void
    {
        $this->seed();

        $hrManager = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $candidate = Candidate::where('candidate_code', 'CAN-1001')->firstOrFail();

        JobOffer::where('candidate_id', $candidate->id)->update([
            'status' => 'draft',
            'released_by_user_id' => null,
            'released_at' => null,
        ]);

        $candidate->forceFill(['stage' => 'offer_draft'])->save();

        $this->actingAs($hrManager)
            ->postJson(route('recruitment.candidates.convert-to-employee', $candidate), [
                'employee_code' => 'EMP-CONV-4',
                'joined_on' => now()->toDateString(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['candidate']);
    }

    public function test_recruiter_cannot_convert_candidate_to_employee(): void
    {
        $this->seed();

        $recruiter = User::where('email', 'ananya.sen@builder360.test')->firstOrFail();
        $candidate = Candidate::where('candidate_code', 'CAN-1001')->firstOrFail();

        $this->actingAs($recruiter)
            ->postJson(route('recruitment.candidates.convert-to-employee', $candidate), [
                'employee_code' => 'EMP-CONV-5',
                'joined_on' => now()->toDateString(),
            ])
            ->assertForbidden();
    }

    public function test_partner_cannot_access_internal_recruitment_routes(): void
    {
        $this->seed();

        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();

        $this->actingAs($partner)
            ->getJson(route('recruitment.candidates.index'))
            ->assertForbidden();

        $this->actingAs($partner)
            ->getJson(route('recruitment.source-summary'))
            ->assertForbidden();

        $this->actingAs($partner)
            ->postJson(route('recruitment.candidates.store'), [])
            ->assertForbidden();
    }
}
