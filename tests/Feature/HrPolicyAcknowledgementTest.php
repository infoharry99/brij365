<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Employee;
use App\Models\EmployeePolicyAcknowledgement;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrPolicyAcknowledgementTest extends TestCase
{
    public function test_policy_acknowledgements_render_as_blade_workspace_for_browser_requests(): void
    {
        $this->seed();
        $employee = User::where('email', 'amit.verma@builder360.test')->firstOrFail();
        $this->actingAs($employee)->get(route('hr.policy-acknowledgements.index'))->assertOk()->assertViewIs('hr.policies.index')->assertSee('Policy Acknowledgements');
    }

    use RefreshDatabase;

    public function test_employee_can_list_configured_policy_and_acknowledge_own_profile(): void
    {
        $this->seed();

        $employeeUser = User::where('email', 'amit.verma@builder360.test')->firstOrFail();
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();

        SystemSetting::create([
            'company_id' => $employee->company_id,
            'created_by_user_id' => $employeeUser->id,
            'scope_key' => 'company:'.$employee->company_id,
            'setting_group' => 'HR',
            'setting_key' => 'hr.attendance_geofence_policy',
            'label' => 'Attendance & Geofence Policy',
            'description' => 'Self-service policy acknowledgement source.',
            'value_type' => 'json',
            'value' => [
                'policy_key' => 'hr.attendance_geofence_policy',
                'policy_title' => 'Attendance & Geofence Policy',
                'policy_version' => 3,
                'summary' => 'Configured attendance and geofence rules for ESS acknowledgement.',
                'required_for_self_service' => true,
                'effective_from' => '2026-04-01',
            ],
            'status' => 'active',
            'version' => 3,
            'effective_from' => '2026-04-01',
            'workflow_history' => [],
        ]);

        $this->actingAs($employeeUser)
            ->getJson(route('hr.policy-acknowledgements.index', ['employee_id' => $employee->id]))
            ->assertOk()
            ->assertJsonPath('policies.0.policy_key', 'hr.attendance_geofence_policy')
            ->assertJsonPath('policies.0.policy_version', 3)
            ->assertJsonPath('policies.0.status', 'pending')
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($employeeUser)
            ->postJson(route('hr.policy-acknowledgements.store'), [
                'employee_id' => $employee->id,
                'policy_key' => 'hr.attendance_geofence_policy',
                'policy_version' => 3,
                'acknowledgement_note' => 'Reviewed and understood.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'acknowledged')
            ->assertJsonPath('data.policy_version', 3)
            ->assertJsonPath('data.employee.employee_code', 'EMP-0030');

        $this->assertDatabaseHas('employee_policy_acknowledgements', [
            'employee_id' => $employee->id,
            'policy_key' => 'hr.attendance_geofence_policy',
            'policy_version' => 3,
            'status' => 'acknowledged',
        ]);

        $this->assertTrue(AuditEvent::where('event_type', 'hr.policy_acknowledgement.acknowledged')
            ->where('auditable_type', EmployeePolicyAcknowledgement::class)
            ->exists());

        $this->actingAs($employeeUser)
            ->getJson(route('hr.policy-acknowledgements.index', ['employee_id' => $employee->id]))
            ->assertOk()
            ->assertJsonPath('policies.0.status', 'acknowledged')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_policy_acknowledgement_scope_version_and_partner_restrictions_are_enforced(): void
    {
        $this->seed();

        $employeeUser = User::where('email', 'amit.verma@builder360.test')->firstOrFail();
        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();
        $otherEmployee = Employee::where('employee_code', 'EMP-0012')->firstOrFail();

        $this->actingAs($employeeUser)
            ->postJson(route('hr.policy-acknowledgements.store'), [
                'employee_id' => $otherEmployee->id,
                'policy_key' => 'hr.attendance_geofence_policy',
                'policy_version' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['employee_id']);

        $this->actingAs($employeeUser)
            ->postJson(route('hr.policy-acknowledgements.store'), [
                'employee_id' => $employee->id,
                'policy_key' => 'hr.attendance_geofence_policy',
                'policy_version' => 999,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['policy_version']);

        $this->actingAs($partner)
            ->getJson(route('hr.policy-acknowledgements.index'))
            ->assertForbidden();

        $this->actingAs($partner)
            ->postJson(route('hr.policy-acknowledgements.store'), [
                'employee_id' => $employee->id,
                'policy_key' => 'hr.attendance_geofence_policy',
                'policy_version' => 1,
            ])
            ->assertForbidden();
    }
}
