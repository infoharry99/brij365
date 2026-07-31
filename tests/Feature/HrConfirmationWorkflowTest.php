<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeConfirmationCase;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrConfirmationWorkflowTest extends TestCase
{
    public function test_confirmation_register_renders_as_blade_workspace_for_browser_requests(): void
    {
        $this->seed();
        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $this->actingAs($hr)->get(route('hr.confirmation-cases.index'))->assertOk()->assertViewIs('hr.confirmation.index')->assertSee('Employee Confirmation');
    }

    use RefreshDatabase;

    public function test_hr_and_manager_can_list_scoped_confirmation_due_cases(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $manager = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $employee = User::where('email', 'amit.verma@builder360.test')->firstOrFail();
        $otherEmployee = Employee::where('employee_code', 'EMP-0012')->firstOrFail();

        $this->actingAs($hr)
            ->getJson(route('hr.confirmation-cases.index', ['status' => 'due']))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.case_number', 'CNF-10001')
            ->assertJsonPath('data.0.employee.employee_code', 'EMP-0030')
            ->assertJsonPath('data.0.manager.employee_code', 'EMP-0012');

        $this->actingAs($manager)
            ->getJson(route('hr.confirmation-cases.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.case_number', 'CNF-10001');

        $this->actingAs($employee)
            ->getJson(route('hr.confirmation-cases.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.case_number', 'CNF-10001');

        $this->actingAs($employee)
            ->getJson(route('hr.confirmation-cases.index', ['employee_id' => $otherEmployee->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['employee_id']);

        $this->actingAs($employee)
            ->getJson(route('hr.confirmation-cases.index', ['manager_employee_id' => $otherEmployee->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['manager_employee_id']);

        $this->actingAs($hr)
            ->getJson(route('hr.confirmation-cases.index', ['page' => 1]))
            ->assertOk()
            ->assertJsonPath('meta.current_page', 1);

        $this->actingAs($hr)
            ->getJson(route('hr.confirmation-cases.index', ['separation_type' => 'resignation']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['separation_type'])
            ->assertJsonPath('errors.separation_type.0', 'The selected filter is not available for this endpoint.');
    }

    public function test_hr_can_create_confirmation_case_and_duplicate_is_rejected(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $employee = Employee::where('employee_code', 'EMP-0012')->firstOrFail();
        $manager = Employee::where('employee_code', 'EMP-0018')->firstOrFail();

        $caseId = $this->actingAs($hr)
            ->postJson(route('hr.confirmation-cases.store'), [
                'employee_id' => $employee->id,
                'manager_employee_id' => $manager->id,
                'probation_starts_on' => now()->subMonths(6)->toDateString(),
                'probation_ends_on' => now()->addDays(10)->toDateString(),
                'review_due_on' => now()->addDays(7)->toDateString(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'due')
            ->assertJsonPath('data.employee.employee_code', 'EMP-0012')
            ->json('data.id');

        $case = EmployeeConfirmationCase::findOrFail($caseId);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'hr.confirmation.created',
            'auditable_id' => $case->id,
            'user_id' => $hr->id,
        ]);

        $this->actingAs($hr)
            ->postJson(route('hr.confirmation-cases.store'), [
                'employee_id' => $employee->id,
                'manager_employee_id' => $manager->id,
                'probation_starts_on' => now()->subMonths(6)->toDateString(),
                'probation_ends_on' => now()->addDays(10)->toDateString(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['employee_id']);
    }

    public function test_manager_recommendation_and_hr_confirmation_updates_employee_record(): void
    {
        $this->seed();

        $manager = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $employeeUser = User::where('email', 'amit.verma@builder360.test')->firstOrFail();
        $case = EmployeeConfirmationCase::where('case_number', 'CNF-10001')->firstOrFail();

        $this->actingAs($manager)
            ->patchJson(route('hr.confirmation-cases.recommend', $case), [
                'manager_recommendation' => 'confirm',
                'manager_comments' => 'Amit has completed probation with consistent site execution and ownership.',
                'review_scores' => [
                    'performance' => 4.2,
                    'behaviour' => 4.0,
                    'attendance' => 4.5,
                    'culture_fit' => 4.1,
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'manager_recommended')
            ->assertJsonPath('data.manager_recommendation', 'confirm')
            ->assertJsonPath('data.manager_reviewer.email', 'rajesh.kulkarni@builder360.test');

        $this->assertTrue(UserNotification::where('recipient_user_id', $hr->id)
            ->where('category', 'hr')
            ->where('status', 'unread')
            ->exists());

        $case->refresh();

        $this->actingAs($hr)
            ->patchJson(route('hr.confirmation-cases.decide', $case), [
                'hr_decision' => 'confirm',
                'hr_comments' => 'Confirmed after manager recommendation and HR record review.',
                'confirmation_effective_on' => now()->toDateString(),
                'confirmation_letter_reference' => 'CNF-LETTER-EMP-0030',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.hr_decision', 'confirm')
            ->assertJsonPath('data.confirmation_letter_reference', 'CNF-LETTER-EMP-0030')
            ->assertJsonPath('data.hr_reviewer.email', 'deepa.rao@builder360.test');

        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();
        $profile = $employee->sensitive_profile;

        $this->assertSame('confirmed', $profile['confirmation']['status']);
        $this->assertSame('CNF-LETTER-EMP-0030', $profile['confirmation']['letter_reference']);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'hr.confirmation.manager_recommended',
            'auditable_id' => $case->id,
            'user_id' => $manager->id,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'hr.confirmation.decided',
            'auditable_id' => $case->id,
            'user_id' => $hr->id,
        ]);

        $this->assertTrue(UserNotification::where('recipient_user_id', $employeeUser->id)
            ->where('category', 'hr')
            ->where('severity', 'success')
            ->exists());
    }

    public function test_hr_extension_requires_date_and_changes_case_status(): void
    {
        $this->seed();

        $manager = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $case = EmployeeConfirmationCase::where('case_number', 'CNF-10001')->firstOrFail();

        $this->actingAs($manager)
            ->patchJson(route('hr.confirmation-cases.recommend', $case), [
                'manager_recommendation' => 'extend',
                'manager_comments' => 'Extend probation for additional site safety follow-through review.',
            ])
            ->assertOk();

        $case->refresh();

        $this->actingAs($hr)
            ->patchJson(route('hr.confirmation-cases.decide', $case), [
                'hr_decision' => 'extend',
                'hr_comments' => 'Extension requires measurable safety and reporting milestones.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['extended_until']);

        $this->actingAs($hr)
            ->patchJson(route('hr.confirmation-cases.decide', $case), [
                'hr_decision' => 'extend',
                'hr_comments' => 'Extended with manager checkpoint every two weeks.',
                'extended_until' => now()->addDays(45)->toDateString(),
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'extended')
            ->assertJsonPath('data.hr_decision', 'extend');
    }

    public function test_employee_and_partner_restrictions_are_enforced(): void
    {
        $this->seed();

        $employee = User::where('email', 'amit.verma@builder360.test')->firstOrFail();
        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();
        $case = EmployeeConfirmationCase::where('case_number', 'CNF-10001')->firstOrFail();

        $this->actingAs($employee)
            ->patchJson(route('hr.confirmation-cases.recommend', $case), [
                'manager_recommendation' => 'confirm',
                'manager_comments' => 'Invalid self recommendation.',
            ])
            ->assertForbidden();

        $this->actingAs($partner)
            ->getJson(route('hr.confirmation-cases.index'))
            ->assertForbidden();

        $this->actingAs($partner)
            ->postJson(route('hr.confirmation-cases.store'), [
                'employee_id' => $case->employee_id,
                'probation_ends_on' => now()->addMonth()->toDateString(),
            ])
            ->assertForbidden();
    }

    public function test_non_global_hr_user_without_company_assignment_fails_closed_for_confirmation_cases(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $case = EmployeeConfirmationCase::where('case_number', 'CNF-10001')->firstOrFail();
        $employee = Employee::where('employee_code', 'EMP-0012')->firstOrFail();

        $hr->forceFill(['company_id' => null])->save();

        $this->actingAs($hr)
            ->getJson(route('hr.confirmation-cases.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($hr)
            ->postJson(route('hr.confirmation-cases.store'), [
                'employee_id' => $employee->id,
                'probation_ends_on' => now()->addMonth()->toDateString(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['employee_id']);

        $this->actingAs($hr)
            ->patchJson(route('hr.confirmation-cases.decide', $case), [
                'hr_decision' => 'confirm',
                'hr_comments' => 'Invalid no-company decision.',
                'confirmation_effective_on' => now()->toDateString(),
            ])
            ->assertForbidden();
    }
}
