<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeExitInterview;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrExitInterviewTest extends TestCase
{
    public function test_exit_interview_register_renders_as_blade_workspace_for_browser_requests(): void
    {
        $this->seed();
        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $this->actingAs($hr)->get(route('hr.exit-interviews.index'))->assertOk()->assertViewIs('hr.exit-interviews.index')->assertSee('Exit Interviews');
    }

    use RefreshDatabase;

    public function test_hr_can_list_seeded_exit_interview_with_confidential_details_and_summary(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();

        $this->actingAs($hr)
            ->getJson(route('hr.exit-interviews.index', ['status' => 'submitted']))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.interview_number', 'EXI-10001')
            ->assertJsonPath('data.0.employee.employee_code', 'EMP-0030')
            ->assertJsonPath('data.0.confidential_responses_visible', true)
            ->assertJsonPath('data.0.confidential_responses.primary_reason', 'Accepted a role with broader site planning responsibility.');

        $this->actingAs($hr)
            ->getJson(route('hr.exit-interviews.summary'))
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.status_counts.submitted', 1)
            ->assertJsonPath('data.reason_counts.career_growth', 1)
            ->assertJsonPath('data.rehire_recommendation_counts.yes', 1)
            ->assertJsonPath('data.risk_flag_counts.retention_risk', 1)
            ->assertJsonPath('data.average_ratings.overall_experience', 4);

        $this->actingAs($director)
            ->getJson(route('hr.exit-interviews.summary'))
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.status_counts.submitted', 1);

        $this->actingAs($hr)
            ->getJson(route('hr.exit-interviews.summary', ['employee_id' => 1]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['employee_id'])
            ->assertJsonPath('errors.employee_id.0', 'The selected filter is not available for this endpoint.');
    }

    public function test_hr_can_schedule_employee_can_submit_and_hr_can_review_exit_interview(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $employeeUser = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $employee = Employee::where('employee_code', 'EMP-0021')->firstOrFail();

        $interviewId = $this->actingAs($hr)
            ->postJson(route('hr.exit-interviews.store'), [
                'employee_id' => $employee->id,
                'interview_due_on' => '2026-07-30',
                'questionnaire_template' => [
                    ['key' => 'primary_reason', 'label' => 'Primary reason', 'type' => 'text'],
                    ['key' => 'rehire_context', 'label' => 'Rehire context', 'type' => 'choice'],
                ],
                'note' => 'Schedule before final handover.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'scheduled')
            ->assertJsonPath('data.employee.employee_code', 'EMP-0021')
            ->assertJsonPath('data.scheduled_by.email', 'deepa.rao@builder360.test')
            ->json('data.id');

        $interview = EmployeeExitInterview::findOrFail($interviewId);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'hr.exit_interview.scheduled',
            'auditable_id' => $interview->id,
            'user_id' => $hr->id,
        ]);

        $this->assertTrue(UserNotification::where('recipient_user_id', $employeeUser->id)
            ->where('category', 'hr')
            ->where('status', 'unread')
            ->exists());

        $this->actingAs($employeeUser)
            ->patchJson(route('hr.exit-interviews.submit', $interview), [
                'separation_reason' => 'compensation',
                'rehire_recommendation' => 'conditional',
                'overall_experience_rating' => 3,
                'manager_relationship_rating' => 4,
                'workload_rating' => 3,
                'compensation_rating' => 2,
                'public_feedback' => 'Team culture was strong.',
                'improvement_suggestions' => 'Compensation bands should be reviewed for market alignment.',
                'confidential_responses' => [
                    'primary_reason' => 'Compensation gap versus market.',
                    'manager_feedback' => 'Manager was supportive.',
                ],
                'risk_flags' => ['pay_dispute', 'retention_risk'],
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.confidential_responses_visible', false)
            ->assertJsonMissingPath('data.confidential_responses')
            ->assertJsonPath('data.submitted_by.email', 'priya.nair@builder360.test');

        $interview->refresh();

        $this->actingAs($hr)
            ->patchJson(route('hr.exit-interviews.review', $interview), [
                'hr_review_notes' => 'Discuss compensation benchmarks with leadership and review sales-team bands.',
                'action_items' => [
                    ['owner' => 'HR Manager', 'action' => 'Prepare compensation benchmarking note.', 'due_on' => '2026-08-10'],
                    ['owner' => 'Sales Head', 'action' => 'Document retention risk indicators.', 'status' => 'open'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'reviewed')
            ->assertJsonPath('data.reviewed_by.email', 'deepa.rao@builder360.test')
            ->assertJsonPath('data.hr_review_notes', 'Discuss compensation benchmarks with leadership and review sales-team bands.')
            ->assertJsonCount(2, 'data.action_items');

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'hr.exit_interview.submitted',
            'auditable_id' => $interview->id,
            'user_id' => $employeeUser->id,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'hr.exit_interview.reviewed',
            'auditable_id' => $interview->id,
            'user_id' => $hr->id,
        ]);
    }

    public function test_employee_scope_confidential_masking_and_partner_denial_are_enforced(): void
    {
        $this->seed();

        $employeeUser = User::where('email', 'amit.verma@builder360.test')->firstOrFail();
        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();
        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $interview = EmployeeExitInterview::where('interview_number', 'EXI-10001')->firstOrFail();
        $otherEmployee = Employee::where('employee_code', 'EMP-0012')->firstOrFail();

        $this->actingAs($employeeUser)
            ->getJson(route('hr.exit-interviews.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.interview_number', 'EXI-10001')
            ->assertJsonPath('data.0.confidential_responses_visible', false)
            ->assertJsonMissingPath('data.0.confidential_responses')
            ->assertJsonMissingPath('data.0.hr_review_notes');

        $this->actingAs($employeeUser)
            ->patchJson(route('hr.exit-interviews.review', $interview), [
                'hr_review_notes' => 'Invalid employee review attempt.',
            ])
            ->assertForbidden();

        $this->actingAs($hr)
            ->postJson(route('hr.exit-interviews.store'), [
                'employee_id' => $otherEmployee->id,
                'interview_due_on' => '2026-07-31',
            ])
            ->assertCreated();

        $this->actingAs($employeeUser)
            ->getJson(route('hr.exit-interviews.index', ['employee_id' => $otherEmployee->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['employee_id']);

        $this->actingAs($partner)
            ->getJson(route('hr.exit-interviews.index'))
            ->assertForbidden();

        $this->actingAs($partner)
            ->getJson(route('hr.exit-interviews.summary'))
            ->assertForbidden();
    }

    public function test_exit_interview_index_rejects_unsupported_filters_and_accepts_page(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();

        $this->actingAs($hr)
            ->getJson(route('hr.exit-interviews.index', ['page' => 1]))
            ->assertOk()
            ->assertJsonPath('meta.current_page', 1);

        $this->actingAs($hr)
            ->getJson(route('hr.exit-interviews.index', ['separation_type' => 'resignation']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['separation_type'])
            ->assertJsonPath('errors.separation_type.0', 'The selected filter is not available for this endpoint.');
    }

    public function test_non_global_hr_user_without_company_assignment_fails_closed_for_exit_interviews(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $interview = EmployeeExitInterview::where('interview_number', 'EXI-10001')->firstOrFail();
        $employee = Employee::where('employee_code', 'EMP-0021')->firstOrFail();

        $hr->forceFill(['company_id' => null])->save();

        $this->actingAs($hr)
            ->getJson(route('hr.exit-interviews.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($hr)
            ->getJson(route('hr.exit-interviews.summary'))
            ->assertOk()
            ->assertJsonPath('data.total', 0);

        $this->actingAs($hr)
            ->postJson(route('hr.exit-interviews.store'), [
                'employee_id' => $employee->id,
                'interview_due_on' => '2026-07-31',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['employee_id']);

        $this->actingAs($hr)
            ->patchJson(route('hr.exit-interviews.review', $interview), [
                'hr_review_notes' => 'Invalid no-company review.',
            ])
            ->assertForbidden();
    }
}
