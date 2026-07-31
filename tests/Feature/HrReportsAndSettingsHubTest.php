<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrReportsAndSettingsHubTest extends TestCase
{
    use RefreshDatabase;

    public function test_hr_manager_can_open_only_production_backed_hr_report_catalog(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();

        $response = $this->actingAs($hr)->get(route('hr.reports.index'));

        $response
            ->assertOk()
            ->assertViewIs('hr.reports.index')
            ->assertSee('Reports &amp; MIS', false)
            ->assertSee('Employee Master Register')
            ->assertSee('Governed exports only.')
            ->assertSee(route('hr.employees.export', [
                'format' => 'csv',
                'report_type' => 'Employee Master Register',
            ]))
            ->assertSee(route('hr.employees.export', [
                'format' => 'xls',
                'report_type' => 'Employee Master Register',
            ]))
            ->assertSee(route('hr.employees.export', [
                'format' => 'pdf',
                'report_type' => 'Employee Master Register',
            ]))
            ->assertDontSee('Payroll Run Register')
            ->assertDontSee('Recruitment Pipeline Export')
            ->assertDontSee('Attendance MIS')
            ->assertDontSee('Statutory payroll report');
    }

    public function test_finance_head_sees_only_the_existing_authorized_payroll_export_contract(): void
    {
        $this->seed();

        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();

        $response = $this->actingAs($finance)->get(route('hr.reports.index'));

        $response
            ->assertOk()
            ->assertSee('Payroll Run Register')
            ->assertSee(route('governance.report-register.index', [
                'format' => 'csv',
                'report' => 'payroll',
            ]))
            ->assertSee(route('governance.report-register.index', [
                'format' => 'excel',
                'report' => 'payroll',
            ]))
            ->assertSee(route('governance.report-register.index', [
                'format' => 'pdf',
                'report' => 'payroll',
            ]))
            ->assertDontSee('Employee Master Register')
            ->assertDontSee('Recruitment Pipeline Export');

        $this->actingAs($finance)
            ->get(route('governance.report-register.index', [
                'format' => 'csv',
                'report' => 'payroll',
            ]))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_payroll_admin_without_the_governance_export_permission_cannot_open_reports(): void
    {
        $this->seed();

        $payroll = User::where('email', 'kavita.shah@builder360.test')->firstOrFail();

        $this->assertTrue($payroll->can('viewAny', \App\Models\PayrollRun::class));
        $this->assertFalse($payroll->can('reports.view'));

        $this->actingAs($payroll)
            ->get(route('hr.reports.index'))
            ->assertForbidden();
    }

    public function test_recruiter_does_not_receive_an_unimplemented_recruitment_export(): void
    {
        $this->seed();

        $recruiter = User::where('email', 'ananya.sen@builder360.test')->firstOrFail();

        $this->actingAs($recruiter)
            ->get(route('hr.reports.index'))
            ->assertForbidden();
    }

    public function test_employee_without_hr_register_access_cannot_open_hr_reports(): void
    {
        $this->seed();

        $employee = User::where('email', 'amit.verma@builder360.test')->firstOrFail();

        $this->actingAs($employee)
            ->get(route('hr.reports.index'))
            ->assertForbidden();
    }

    public function test_hr_report_catalog_rejects_unsupported_query_state(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();

        $this->actingAs($hr)
            ->getJson(route('hr.reports.index', ['report' => 'prototype-only']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('report');
    }

    public function test_system_administrator_can_open_company_scoped_hr_settings_hub(): void
    {
        $this->seed();

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('hr.settings.index'));

        $response
            ->assertOk()
            ->assertViewIs('hr.settings.index')
            ->assertSee('HR Settings')
            ->assertSee('HR Attendance Rules')
            ->assertSee('HR Leave Processing and Encashment Rules')
            ->assertSee('Payroll Tax and Form 16 Rules')
            ->assertSee('Sales Commission Processing Rules')
            ->assertSee('Approval Chain Catalogue')
            ->assertSee('One-company governed configuration.')
            ->assertDontSee('After-Sales SLA Hours')
            ->assertDontSee('Collaboration Task Settings')
            ->assertDontSee('All Companies')
            ->assertDontSee('External integrations');
    }

    public function test_hr_settings_tabs_and_status_filters_are_server_authoritative(): void
    {
        $this->seed();

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('hr.settings.index', ['tab' => 'payroll', 'status' => 'active']))
            ->assertOk()
            ->assertSee('Payroll Tax and Form 16 Rules')
            ->assertSee('Sales Commission Processing Rules')
            ->assertDontSee('HR Attendance Rules')
            ->assertDontSee('Approval Chain Catalogue');

        $this->actingAs($admin)
            ->get(route('hr.settings.index', ['tab' => 'workflow']))
            ->assertOk()
            ->assertSee('Approval Chain Catalogue')
            ->assertDontSee('Payroll Tax and Form 16 Rules')
            ->assertDontSee('HR Leave Processing and Encashment Rules');
    }

    public function test_hr_settings_hub_excludes_other_company_records(): void
    {
        $this->seed();

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $globalDirector = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $otherCompany = Company::create([
            'name' => 'Other Company',
            'code' => 'OTHER',
            'state' => 'MH',
            'status' => 'active',
        ]);

        SystemSetting::create([
            'company_id' => $otherCompany->id,
            'created_by_user_id' => $admin->id,
            'scope_key' => 'company:'.$otherCompany->id,
            'setting_group' => 'hr',
            'setting_key' => 'hr.other_company_only',
            'label' => 'Other Company HR Rule',
            'description' => 'Must not be visible outside the owning company.',
            'value_type' => 'object',
            'value' => ['enabled' => true],
            'status' => 'active',
            'version' => 1,
        ]);

        $this->actingAs($admin)
            ->get(route('hr.settings.index'))
            ->assertOk()
            ->assertDontSee('Other Company HR Rule');

        $this->actingAs($globalDirector)
            ->get(route('hr.settings.index'))
            ->assertOk()
            ->assertDontSee('Other Company HR Rule');
    }

    public function test_user_without_settings_access_cannot_open_hr_settings_hub(): void
    {
        $this->seed();

        $company = Company::where('code', 'B360D')->firstOrFail();
        $role = Role::create([
            'slug' => 'hr_settings_denied_test',
            'name' => 'HR Settings Denied Test',
            'scope_level' => 'department',
            'permissions' => ['hr.view'],
            'is_active' => true,
        ]);
        $viewer = User::factory()->create([
            'role_id' => $role->id,
            'company_id' => $company->id,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($viewer)
            ->get(route('hr.settings.index'))
            ->assertForbidden();
    }

    public function test_hr_people_rail_shows_reports_and_settings_only_to_matching_roles(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();

        $this->actingAs($hr)
            ->get(route('hr.employees.index'))
            ->assertOk()
            ->assertSee('Reports &amp; MIS', false)
            ->assertDontSee(route('hr.settings.index'), false);

        $this->actingAs($admin)
            ->get(route('hr.settings.index'))
            ->assertOk()
            ->assertSee('HR Settings')
            ->assertSee(route('hr.settings.index'), false)
            ->assertDontSee(route('hr.reports.index'), false);
    }
}
