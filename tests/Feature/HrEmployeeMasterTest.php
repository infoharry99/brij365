<?php

namespace Tests\Feature;

use App\Domain\Hr\Services\EmployeeRegister;
use App\Models\AuditEvent;
use App\Models\Branch;
use App\Models\Company;
use App\Models\DocumentCategory;
use App\Models\Employee;
use App\Models\EmployeeMovement;
use App\Models\EmployeeProfileSection;
use App\Models\EmployeeTaxDocument;
use App\Models\ManagedDocument;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\Project;
use App\Models\Role;
use App\Models\SalaryAssignment;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrEmployeeMasterTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_master_filter_chips_use_server_derived_business_labels(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $employee = Employee::query()
            ->with(['branch', 'project'])
            ->where('company_id', $hr->company_id)
            ->whereNotNull('branch_id')
            ->whereNotNull('project_id')
            ->firstOrFail();

        $this->actingAs($hr)
            ->get(route('hr.employees.index', [
                'branch_id' => $employee->branch_id,
                'project_id' => $employee->project_id,
                'status' => $employee->status,
            ]))
            ->assertOk()
            ->assertSee('Branch: '.$employee->branch->name)
            ->assertSee('Project: '.$employee->project->name)
            ->assertSee('Status: '.(['active' => 'Active', 'inactive' => 'Inactive', 'on_notice' => 'On notice', 'separated' => 'Separated'][$employee->status]));
    }

    public function test_employee_register_does_not_select_compensation_for_unauthorized_roles(): void
    {
        $this->seed();

        $company = Company::where('code', 'B360D')->firstOrFail();
        $viewer = $this->createUserWithPermissions($company, 'employee_register_no_compensation', ['hr.view']);
        $hrManager = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $register = app(EmployeeRegister::class);

        $restrictedQuery = $register->query($viewer, [], true);

        $this->assertNotContains('employees.monthly_ctc', $restrictedQuery->getQuery()->columns ?? []);
        $this->assertNotContains('employees.sensitive_profile', $restrictedQuery->getQuery()->columns ?? []);
        $this->assertStringNotContainsString('payroll_run_items', $restrictedQuery->toSql());

        $authorizedQuery = $register->query($hrManager, [], true);

        $this->assertContains('employees.monthly_ctc', $authorizedQuery->getQuery()->columns ?? []);
        $this->assertNotContains('employees.sensitive_profile', $authorizedQuery->getQuery()->columns ?? []);
        $this->assertStringContainsString('payroll_run_items', $authorizedQuery->toSql());
    }

    public function test_hr_employee_master_and_profile_render_as_blade_workspaces_for_browser_requests(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();

        $this->actingAs($hr)
            ->get(route('hr.employees.index'))
            ->assertOk()
            ->assertViewIs('hr.employees.index')
            ->assertSee('Employee Master')
            ->assertSee('EMP-0030')
            ->assertSee('Create employee record')
            ->assertSee('people-workspace', false)
            ->assertSee('Employee Directory')
            ->assertSee('Employees matching the current directory filters')
            ->assertSee('people-mobile-cards', false)
            ->assertSee('All projects');

        $this->actingAs($hr)
            ->get(route('hr.employees.show', $employee))
            ->assertOk()
            ->assertViewIs('hr.employees.show')
            ->assertSee($employee->name)
            ->assertSee('Employee 360')
            ->assertSee('people-profile-nav', false)
            ->assertSee('Current placement')
            ->assertSee('Update employee record');
    }

    public function test_employee_payroll_summary_and_activity_history_render_as_blade_workspaces(): void
    {
        $this->seed();
        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();

        $this->actingAs($hr)->get(route('hr.employees.payroll-summary.show', $employee))->assertOk()->assertViewIs('hr.employees.payroll-summary')->assertSee('Payroll Summary');
        $this->actingAs($hr)->get(route('hr.employees.audit-events.index', $employee))->assertOk()->assertViewIs('hr.employees.audit')->assertSee('Employee Activity History');
    }

    public function test_employee_document_registers_render_as_blade_workspaces(): void
    {
        $this->seed();
        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();

        $this->actingAs($hr)->get(route('hr.employee-documents.index'))->assertOk()->assertViewIs('hr.documents.index')->assertSee('Employee Documents');
        $this->actingAs($hr)->get(route('hr.employees.documents.index', $employee))->assertOk()->assertViewIs('hr.documents.index')->assertSee($employee->name);
    }

    public function test_employee_movements_and_profile_sections_render_as_blade_workspaces(): void
    {
        $this->seed();
        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();

        $this->actingAs($hr)->get(route('hr.employees.movements.index', $employee))->assertOk()->assertViewIs('hr.employees.movements')->assertSee('Employee Movements');
        $this->actingAs($hr)->get(route('hr.employees.profile-sections.show', $employee))->assertOk()->assertViewIs('hr.employees.profile-sections')->assertSee('Employee Profile Details');
    }

    public function test_hr_manager_can_list_and_view_company_employee_master_records(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();

        $this->actingAs($hr)
            ->getJson(route('hr.employees.index', ['department' => 'Construction']))
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonFragment(['employee_code' => 'EMP-0012'])
            ->assertJsonFragment(['employee_code' => 'EMP-0030'])
            ->assertJsonMissingPath('data.0.sensitive_profile')
            ->assertJsonMissingPath('data.0.sensitive_profile_masked')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['employee_code', 'name', 'designation', 'department', 'company', 'branch', 'project', 'user'],
                ],
            ]);

        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();

        $this->actingAs($hr)
            ->getJson(route('hr.employees.show', $employee))
            ->assertOk()
            ->assertJsonPath('data.employee_code', 'EMP-0030')
            ->assertJsonPath('data.monthly_ctc', 62000)
            ->assertJsonPath('data.sensitive_profile.pan_masked', 'ABCDE••••F')
            ->assertJsonStructure([
                'data' => [
                    'direct_reports_count',
                    'documents_count',
                    'assets_count',
                    'leave_requests_count',
                    'attendance_records_count',
                    'payroll_items_count',
                    'tax_documents_count',
                    'confirmation_cases_count',
                    'separation_settlements_count',
                    'expense_claims_count',
                    'loans_count',
                    'performance_reviews_count',
                ],
            ]);
    }

    public function test_read_only_hr_viewer_cannot_see_salary_or_raw_sensitive_profile_data(): void
    {
        $this->seed();

        $company = Company::where('code', 'B360D')->firstOrFail();
        $role = Role::create([
            'slug' => 'hr_viewer_test',
            'name' => 'HR Viewer Test',
            'scope_level' => 'department',
            'permissions' => ['hr.view'],
            'is_active' => true,
        ]);
        $viewer = User::factory()->create([
            'role_id' => $role->id,
            'company_id' => $company->id,
            'email' => 'hr.viewer@example.test',
            'status' => 'active',
        ]);
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();

        $this->actingAs($viewer)
            ->getJson(route('hr.employees.index'))
            ->assertOk()
            ->assertJsonMissingPath('data.0.monthly_ctc')
            ->assertJsonMissingPath('data.0.sensitive_profile')
            ->assertJsonMissingPath('data.0.sensitive_profile_masked');

        $this->actingAs($viewer)
            ->getJson(route('hr.employees.show', $employee))
            ->assertOk()
            ->assertJsonPath('data.employee_code', 'EMP-0030')
            ->assertJsonMissingPath('data.monthly_ctc')
            ->assertJsonMissingPath('data.sensitive_profile')
            ->assertJsonPath('data.sensitive_profile_masked.pan_masked', 'ABCDE'.str_repeat("\u{2022}", 4).'F');
    }

    public function test_sensitive_profile_sections_deny_read_only_hr_and_payroll_roles_but_allow_own_self_service(): void
    {
        $this->seed();

        $company = Company::where('code', 'B360D')->firstOrFail();
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();
        $selfServiceUser = User::where('email', 'amit.verma@builder360.test')->firstOrFail();
        $hrManager = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();

        EmployeeProfileSection::updateOrCreate(
            ['employee_id' => $employee->id, 'section' => 'personal'],
            [
                'company_id' => $company->id,
                'data' => ['email' => 'private.profile@example.test', 'blood' => 'O+'],
                'created_by_user_id' => $hrManager->id,
                'updated_by_user_id' => $hrManager->id,
            ],
        );

        foreach ([
            'sensitive_profile_hr_viewer' => ['hr.view'],
            'sensitive_profile_payroll_viewer' => ['payroll.view'],
        ] as $key => $permissions) {
            $viewer = $this->createUserWithPermissions($company, $key, $permissions);

            $this->actingAs($viewer)
                ->getJson(route('hr.employees.profile-sections.show', $employee))
                ->assertForbidden();

            $this->actingAs($viewer)
                ->get(route('hr.employees.show', $employee))
                ->assertOk()
                ->assertDontSee('Work profile')
                ->assertDontSee(route('hr.employees.profile-sections.show', $employee), false);
        }

        $this->actingAs($selfServiceUser)
            ->getJson(route('hr.employees.profile-sections.show', $employee))
            ->assertOk()
            ->assertJsonPath('data.sections.personal.email', 'private.profile@example.test');

        $this->actingAs($selfServiceUser)
            ->get(route('hr.employees.me.profile'))
            ->assertOk()
            ->assertSee('Work profile')
            ->assertSee(route('hr.employees.profile-sections.show', $employee), false);
    }

    public function test_hr_manager_can_export_employee_mis_with_scope_and_audit(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();

        $response = $this->actingAs($hr)
            ->get(route('hr.employees.export', [
                'format' => 'csv',
                'department' => 'Construction',
                'report_type' => 'Employee Master Register',
            ]));

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertSee('employee_code,name,designation,department', false)
            ->assertSee('EMP-0012', false)
            ->assertSee('EMP-0030', false)
            ->assertDontSee('sensitive_profile', false)
            ->assertDontSee('ABCDE1234F', false);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'hr.employee_report.exported',
            'user_id' => $hr->id,
            'action' => 'Exported HR employee MIS report',
        ]);

        $audit = AuditEvent::where('event_type', 'hr.employee_report.exported')->latest('id')->firstOrFail();

        $this->assertSame('csv', $audit->metadata['format']);
        $this->assertSame('Construction', $audit->metadata['filters']['department']);
        $this->assertSame(2, $audit->metadata['row_count']);
        $this->assertTrue($audit->metadata['compensation_visible']);
    }

    public function test_hr_employee_mis_export_masks_compensation_for_read_only_hr_viewers(): void
    {
        $this->seed();

        $company = Company::where('code', 'B360D')->firstOrFail();
        $role = Role::create([
            'slug' => 'hr_export_viewer_test',
            'name' => 'HR Export Viewer Test',
            'scope_level' => 'department',
            'permissions' => ['hr.view'],
            'is_active' => true,
        ]);
        $viewer = User::factory()->create([
            'role_id' => $role->id,
            'company_id' => $company->id,
            'email' => 'hr.export.viewer@example.test',
            'status' => 'active',
        ]);

        $this->actingAs($viewer)
            ->get(route('hr.employees.export', [
                'format' => 'csv',
                'search' => 'EMP-0030',
            ]))
            ->assertOk()
            ->assertSee('EMP-0030', false)
            ->assertSee('restricted', false)
            ->assertDontSee('62000', false)
            ->assertDontSee('ABCDE1234F', false);
    }

    public function test_employee_self_service_can_view_own_profile_but_not_employee_register(): void
    {
        $this->seed();

        $employeeUser = User::where('email', 'amit.verma@builder360.test')->firstOrFail();
        $ownEmployee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();
        $anotherEmployee = Employee::where('employee_code', 'EMP-0012')->firstOrFail();

        $this->actingAs($employeeUser)
            ->getJson(route('hr.employees.me'))
            ->assertOk()
            ->assertJsonPath('data.employee_code', 'EMP-0030')
            ->assertJsonPath('data.monthly_ctc', 62000)
            ->assertJsonPath('data.sensitive_profile_masked.pan_masked', 'ABCDE••••F')
            ->assertJsonMissingPath('data.sensitive_profile');

        $this->actingAs($employeeUser)
            ->getJson(route('hr.employees.index'))
            ->assertForbidden();

        $this->actingAs($employeeUser)
            ->getJson(route('hr.employees.profile-sections.show', $ownEmployee))
            ->assertOk()
            ->assertJsonPath('data.employee_code', 'EMP-0030');

        $this->actingAs($employeeUser)
            ->patchJson(route('hr.employees.profile-sections.update', $ownEmployee), [
                'sections' => ['personal' => ['blood' => 'A+']],
            ])
            ->assertForbidden();

        $this->actingAs($employeeUser)
            ->getJson(route('hr.employees.show', $anotherEmployee))
            ->assertForbidden();
    }

    public function test_employee_payroll_summary_is_employee_scoped_and_permission_protected(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $employeeUser = User::where('email', 'amit.verma@builder360.test')->firstOrFail();
        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();
        $otherEmployee = Employee::where('employee_code', 'EMP-0012')->firstOrFail();
        $assignment = SalaryAssignment::where('employee_id', $employee->id)->firstOrFail();

        $approvedRun = PayrollRun::create([
            'company_id' => $employee->company_id,
            'generated_by_user_id' => $hr->id,
            'approved_by_user_id' => $hr->id,
            'run_number' => 'PAY-HR-EMP-0030-01',
            'period_year' => 2026,
            'period_month' => 5,
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'working_days' => 26,
            'status' => 'approved',
            'gross_earnings' => 62000,
            'total_deductions' => 7000,
            'net_payable' => 55000,
            'approved_at' => now(),
        ]);

        PayrollRunItem::create([
            'payroll_run_id' => $approvedRun->id,
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'salary_structure_id' => $assignment->salary_structure_id,
            'monthly_ctc' => 62000,
            'payable_days' => 26,
            'gross_earnings' => 62000,
            'total_deductions' => 7000,
            'net_payable' => 55000,
            'component_breakup' => ['basic' => 31000, 'hra' => 12400, 'pf' => 1800],
            'status' => 'approved',
        ]);

        $draftRun = PayrollRun::create([
            'company_id' => $employee->company_id,
            'generated_by_user_id' => $hr->id,
            'run_number' => 'PAY-HR-EMP-0030-DRAFT',
            'period_year' => 2026,
            'period_month' => 6,
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'working_days' => 26,
            'status' => 'draft',
            'gross_earnings' => 63000,
            'total_deductions' => 7100,
            'net_payable' => 55900,
        ]);

        PayrollRunItem::create([
            'payroll_run_id' => $draftRun->id,
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'salary_structure_id' => $assignment->salary_structure_id,
            'monthly_ctc' => 63000,
            'payable_days' => 26,
            'gross_earnings' => 63000,
            'total_deductions' => 7100,
            'net_payable' => 55900,
            'component_breakup' => ['basic' => 31500],
            'status' => 'generated',
        ]);

        EmployeeTaxDocument::create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'generated_by_user_id' => $hr->id,
            'issued_by_user_id' => $hr->id,
            'document_number' => 'TAX-HR-EMP-0030-01',
            'document_type' => 'form_16',
            'financial_year' => '2025-2026',
            'assessment_year' => '2026-2027',
            'version' => 1,
            'status' => 'issued',
            'gross_salary' => 62000,
            'taxable_income' => 12000,
            'tds_deducted' => 1200,
            'net_salary_paid' => 55000,
            'payroll_run_ids' => [$approvedRun->id],
            'component_summary' => ['basic' => 31000],
            'tax_configuration_snapshot' => ['source' => 'test'],
            'document_payload' => ['summary' => 'issued employee tax document'],
            'workflow_history' => [['status' => 'issued', 'actor' => $hr->name, 'at' => now()->toISOString()]],
            'generated_at' => now()->subDay(),
            'issued_at' => now(),
        ]);

        $this->actingAs($hr)
            ->getJson(route('hr.employees.payroll-summary.show', $employee))
            ->assertOk()
            ->assertJsonPath('data.access_mode', 'internal_payroll')
            ->assertJsonPath('data.current_assignment.structure.version', 1)
            ->assertJsonPath('data.payroll_items.0.run_number', 'PAY-HR-EMP-0030-DRAFT')
            ->assertJsonPath('data.payroll_items.1.run_number', 'PAY-HR-EMP-0030-01')
            ->assertJsonPath('data.payroll_items.1.component_breakup.pf', 1800)
            ->assertJsonPath('data.tax_documents.0.document_number', 'TAX-HR-EMP-0030-01');

        $this->actingAs($employeeUser)
            ->getJson(route('hr.employees.payroll-summary.show', $employee))
            ->assertOk()
            ->assertJsonPath('data.access_mode', 'self_service')
            ->assertJsonPath('data.payroll_items.0.run_number', 'PAY-HR-EMP-0030-01')
            ->assertJsonCount(1, 'data.payroll_items')
            ->assertJsonPath('data.payroll_items.0.component_breakup', [])
            ->assertJsonPath('data.tax_documents.0.payroll_run_ids', []);

        $this->actingAs($employeeUser)
            ->getJson(route('hr.employees.payroll-summary.show', $otherEmployee))
            ->assertForbidden();

        $this->actingAs($partner)
            ->getJson(route('hr.employees.payroll-summary.show', $employee))
            ->assertForbidden();
    }

    public function test_employee_audit_timeline_is_scoped_to_employee_related_records_and_restricted(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $employeeUser = User::where('email', 'amit.verma@builder360.test')->firstOrFail();
        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();
        $otherEmployee = Employee::where('employee_code', 'EMP-0012')->firstOrFail();

        $movement = EmployeeMovement::create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'movement_number' => 'MOV-AUD-EMP-0030',
            'movement_type' => 'department_change',
            'effective_on' => now()->toDateString(),
            'status' => 'approved',
            'previous_values' => ['department' => 'Construction'],
            'new_values' => ['department' => 'Projects'],
            'created_by_user_id' => $hr->id,
            'approved_by_user_id' => $hr->id,
            'approved_at' => now(),
        ]);

        AuditEvent::create([
            'user_id' => $hr->id,
            'event_type' => 'hr.employee.updated',
            'auditable_type' => Employee::class,
            'auditable_id' => $employee->id,
            'action' => 'Updated employee audit test record',
            'metadata' => ['field' => 'designation'],
        ]);

        AuditEvent::create([
            'user_id' => $hr->id,
            'event_type' => 'hr.employee_movement.approved',
            'auditable_type' => EmployeeMovement::class,
            'auditable_id' => $movement->id,
            'action' => 'Approved employee movement audit test record',
            'metadata' => ['movement_number' => $movement->movement_number],
        ]);

        AuditEvent::create([
            'user_id' => $hr->id,
            'event_type' => 'hr.employee.updated',
            'auditable_type' => Employee::class,
            'auditable_id' => $otherEmployee->id,
            'action' => 'Unrelated employee audit event',
            'metadata' => [],
        ]);

        $this->actingAs($hr)
            ->getJson(route('hr.employees.audit-events.index', $employee))
            ->assertOk()
            ->assertJsonFragment(['action' => 'Updated employee audit test record'])
            ->assertJsonFragment(['action' => 'Approved employee movement audit test record'])
            ->assertJsonMissing(['action' => 'Unrelated employee audit event']);

        $this->actingAs($hr)
            ->getJson(route('hr.employees.audit-events.index', [$employee, 'unsupported' => 'x']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['unsupported']);

        $company = Company::where('code', 'B360D')->firstOrFail();
        $role = Role::create([
            'slug' => 'hr_audit_viewer_denied_test',
            'name' => 'HR Audit Viewer Denied Test',
            'scope_level' => 'department',
            'permissions' => ['hr.view'],
            'is_active' => true,
        ]);
        $viewer = User::factory()->create([
            'role_id' => $role->id,
            'company_id' => $company->id,
            'email' => 'hr.audit.viewer@example.test',
            'status' => 'active',
        ]);

        $this->actingAs($viewer)
            ->getJson(route('hr.employees.audit-events.index', $employee))
            ->assertForbidden();

        $this->actingAs($employeeUser)
            ->getJson(route('hr.employees.audit-events.index', $employee))
            ->assertForbidden();

        $this->actingAs($partner)
            ->getJson(route('hr.employees.audit-events.index', $employee))
            ->assertForbidden();
    }

    public function test_hr_manager_can_create_and_update_employee_with_audit(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $company = Company::where('code', 'B360D')->firstOrFail();
        $branch = Branch::where('code', 'PNQ-HO')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();
        $manager = Employee::where('employee_code', 'EMP-0012')->firstOrFail();

        $employeeId = $this->actingAs($hr)
            ->postJson(route('hr.employees.store'), [
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'project_id' => $project->id,
                'manager_employee_id' => $manager->id,
                'employee_code' => 'EMP-0044',
                'name' => 'Sonal Patil',
                'designation' => 'CRM Coordinator',
                'department' => 'Sales',
                'grade' => 'B2',
                'employment_type' => 'full_time',
                'joined_on' => now()->subMonth()->toDateString(),
                'statutory_state' => 'MH',
                'monthly_ctc' => 58000,
                'sensitive_profile' => [
                    'pan_masked' => 'PQRSX••••T',
                    'aadhaar_masked' => '•••• •••• 7788',
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.employee_code', 'EMP-0044')
            ->json('data.id');

        $employee = Employee::findOrFail($employeeId);

        $this->actingAs($hr)
            ->patchJson(route('hr.employees.update', $employee), [
                'designation' => 'Senior CRM Coordinator',
                'department' => 'Customer Success',
                'monthly_ctc' => 65000,
                'lock_version' => $employee->lock_version,
            ])
            ->assertOk()
            ->assertJsonPath('data.designation', 'Senior CRM Coordinator')
            ->assertJsonPath('data.department', 'Customer Success')
            ->assertJsonPath('data.monthly_ctc', 65000);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'hr.employee.created',
            'user_id' => $hr->id,
            'auditable_id' => $employee->id,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'hr.employee.updated',
            'user_id' => $hr->id,
            'auditable_id' => $employee->id,
        ]);
    }

    public function test_employee_updates_reject_stale_versions_without_overwriting_newer_data(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();
        $initialVersion = (int) $employee->lock_version;

        $this->actingAs($hr)
            ->patchJson(route('hr.employees.update', $employee), [
                'designation' => 'Missing version overwrite attempt',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lock_version');

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'designation' => $employee->designation,
            'lock_version' => $initialVersion,
        ]);

        $this->actingAs($hr)
            ->patchJson(route('hr.employees.update', $employee), [
                'designation' => 'Senior HR Manager',
                'lock_version' => $initialVersion,
            ])
            ->assertOk()
            ->assertJsonPath('data.lock_version', $initialVersion + 1);

        $this->actingAs($hr)
            ->patchJson(route('hr.employees.update', $employee), [
                'designation' => 'Stale overwrite attempt',
                'lock_version' => $initialVersion,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lock_version');

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'designation' => 'Senior HR Manager',
            'lock_version' => $initialVersion + 1,
        ]);
    }

    public function test_employee_updates_and_reporting_movements_reject_indirect_management_cycles(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $employees = Employee::query()
            ->where('company_id', $hr->company_id)
            ->orderBy('id')
            ->limit(3)
            ->get();

        $this->assertCount(3, $employees);

        [$root, $manager, $report] = $employees->all();
        $root->forceFill(['manager_employee_id' => null])->saveQuietly();
        $manager->forceFill(['manager_employee_id' => $root->id])->saveQuietly();
        $report->forceFill(['manager_employee_id' => $manager->id])->saveQuietly();
        $root->refresh();

        $this->actingAs($hr)
            ->patchJson(route('hr.employees.update', $root), [
                'manager_employee_id' => $report->id,
                'lock_version' => $root->lock_version,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('manager_employee_id');

        $this->actingAs($hr)
            ->postJson(route('hr.employees.movements.store', $root), [
                'movement_type' => 'reporting_change',
                'effective_on' => now()->toDateString(),
                'status' => 'pending',
                'new_values' => ['manager_employee_id' => $report->id],
                'reason' => 'Invalid reporting-chain change',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('new_values.manager_employee_id');

        $report->forceFill(['manager_employee_id' => null])->saveQuietly();

        $movementId = $this->actingAs($hr)
            ->postJson(route('hr.employees.movements.store', $root), [
                'movement_type' => 'reporting_change',
                'effective_on' => now()->toDateString(),
                'status' => 'pending',
                'new_values' => ['manager_employee_id' => $report->id],
                'reason' => 'Reporting-chain change pending approval',
            ])
            ->assertCreated()
            ->json('data.id');

        $report->forceFill(['manager_employee_id' => $root->id])->saveQuietly();
        $movement = EmployeeMovement::findOrFail($movementId);

        $this->actingAs($hr)
            ->patchJson(route('hr.employees.movements.approve', [$root, $movement]), [
                'remarks' => 'Hierarchy changed after submission.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('new_values.manager_employee_id');

        $this->assertSame('pending', $movement->fresh()->status);
        $this->assertNull($root->fresh()->manager_employee_id);
    }

    public function test_hr_manager_can_save_and_view_employee_profile_sections(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();

        $payload = [
            'sections' => [
                'personal' => [
                    'dob' => '1993-04-12',
                    'gender' => 'Male',
                    'marital' => 'Married',
                    'blood' => 'O+',
                    'mobile' => '+91 9876543210',
                    'email' => 'amit.personal@example.test',
                ],
                'emergency' => [
                    ['name' => 'Neha Verma', 'relation' => 'Spouse', 'phone' => '+91 9000000000'],
                ],
                'family' => [
                    ['name' => 'Neha Verma', 'relation' => 'Spouse', 'dependent' => true],
                ],
                'education' => [
                    ['qualification' => 'B.E. Civil', 'institute' => 'Pune University', 'year' => 2015],
                ],
                'experience' => [
                    ['company' => 'Buildwell Projects', 'role' => 'Engineer', 'from' => '2016-01-01', 'to' => '2021-12-31'],
                ],
            ],
        ];

        $this->actingAs($hr)
            ->patchJson(route('hr.employees.profile-sections.update', $employee), $payload)
            ->assertOk()
            ->assertJsonPath('data.employee_code', 'EMP-0030')
            ->assertJsonPath('data.sections.personal.dob', '1993-04-12')
            ->assertJsonPath('data.sections.emergency.0.name', 'Neha Verma')
            ->assertJsonPath('data.sections.education.0.year', 2015);

        $this->assertSame('O+', EmployeeProfileSection::where('employee_id', $employee->id)->where('section', 'personal')->firstOrFail()->data['blood']);

        $this->actingAs($hr)
            ->getJson(route('hr.employees.profile-sections.show', $employee))
            ->assertOk()
            ->assertJsonPath('data.sections.personal.email', 'amit.personal@example.test')
            ->assertJsonPath('data.sections.family.0.dependent', true);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'hr.employee_profile_sections.updated',
            'user_id' => $hr->id,
            'auditable_id' => $employee->id,
        ]);
    }

    public function test_hr_manager_can_record_and_approve_effective_dated_employee_movements(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();

        $movementId = $this->actingAs($hr)
            ->postJson(route('hr.employees.movements.store', $employee), [
                'movement_type' => 'promotion',
                'effective_on' => now()->toDateString(),
                'status' => 'pending',
                'new_values' => [
                    'designation' => 'Senior Site Engineer',
                    'grade' => 'B2',
                    'monthly_ctc' => 71000,
                ],
                'reason' => 'Quarterly promotion cycle',
            ])
            ->assertCreated()
            ->assertJsonPath('data.movement_type', 'promotion')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.new_values.designation', 'Senior Site Engineer')
            ->json('data.id');

        $employee->refresh();
        $this->assertSame('Site Engineer', $employee->designation);
        $this->assertSame('B1', $employee->grade);
        $this->assertSame('62000.00', (string) $employee->monthly_ctc);

        $movement = EmployeeMovement::findOrFail($movementId);

        $this->actingAs($hr)
            ->patchJson(route('hr.employees.movements.approve', [$employee, $movement]), [
                'remarks' => 'Approved by HR manager',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.approved_by.email', 'deepa.rao@builder360.test');

        $employee->refresh();
        $this->assertSame('Senior Site Engineer', $employee->designation);
        $this->assertSame('B2', $employee->grade);
        $this->assertSame('71000.00', (string) $employee->monthly_ctc);

        $this->actingAs($hr)
            ->getJson(route('hr.employees.movements.index', $employee))
            ->assertOk()
            ->assertJsonPath('data.0.status', 'approved')
            ->assertJsonPath('data.0.previous_values.designation', 'Site Engineer')
            ->assertJsonPath('data.0.new_values.monthly_ctc', '71000.00');

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'hr.employee_movement.created',
            'user_id' => $hr->id,
            'auditable_id' => $movement->id,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'hr.employee_movement.approved',
            'user_id' => $hr->id,
            'auditable_id' => $movement->id,
        ]);

        $this->assertTrue(UserNotification::query()
            ->where('notifiable_type', EmployeeMovement::class)
            ->where('notifiable_id', $movement->id)
            ->where('category', 'hr')
            ->exists());
    }

    public function test_employee_movements_enforce_scope_validation_and_sensitive_salary_masking(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();
        $otherCompanyBranch = Branch::where('code', 'AMD-BR')->firstOrFail();

        $this->actingAs($partner)
            ->getJson(route('hr.employees.movements.index', $employee))
            ->assertForbidden();

        $this->actingAs($hr)
            ->postJson(route('hr.employees.movements.store', $employee), [
                'movement_type' => 'transfer',
                'effective_on' => now()->toDateString(),
                'new_values' => [
                    'branch_id' => $otherCompanyBranch->id,
                    'department' => 'Construction',
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['new_values.branch_id']);

        $this->actingAs($hr)
            ->postJson(route('hr.employees.movements.store', $employee), [
                'movement_type' => 'salary_change',
                'effective_on' => now()->toDateString(),
                'status' => 'approved',
                'new_values' => [
                    'monthly_ctc' => 73500,
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'approved');

        $company = Company::where('code', 'B360D')->firstOrFail();
        $role = Role::create([
            'slug' => 'hr_movement_viewer_test',
            'name' => 'HR Movement Viewer Test',
            'scope_level' => 'department',
            'permissions' => ['hr.view'],
            'is_active' => true,
        ]);
        $viewer = User::factory()->create([
            'role_id' => $role->id,
            'company_id' => $company->id,
            'email' => 'hr.movement.viewer@example.test',
            'status' => 'active',
        ]);

        $this->actingAs($viewer)
            ->getJson(route('hr.employees.movements.index', $employee))
            ->assertOk()
            ->assertJsonPath('data.0.new_values.monthly_ctc', 'restricted')
            ->assertJsonPath('data.0.previous_values.monthly_ctc', 'restricted');
    }

    public function test_hr_employee_documents_are_scoped_versioned_and_approval_protected(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $employeeUser = User::where('email', 'amit.verma@builder360.test')->firstOrFail();
        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();
        $category = DocumentCategory::where('code', 'EMPLOYEE_KYC')->firstOrFail();

        $documentId = $this->actingAs($hr)
            ->postJson(route('hr.employees.documents.store', $employee), [
                'document_category_id' => $category->id,
                'title' => 'Amit Verma Employee KYC',
                'storage_path' => 'documents/employees/emp-0030-kyc.pdf',
                'original_filename' => 'emp-0030-kyc.pdf',
                'mime_type' => 'application/pdf',
                'file_size_bytes' => 2048,
                'checksum_sha256' => hash('sha256', 'emp-0030-kyc'),
                'issue_date' => now()->subDay()->toDateString(),
                'expires_on' => now()->addDays(20)->toDateString(),
                'metadata' => ['source' => 'feature_test'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.owner_type', 'employee')
            ->assertJsonPath('data.owner_id', $employee->id)
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.category.code', 'EMPLOYEE_KYC')
            ->assertJsonPath('data.is_expiring_within_30_days', true)
            ->json('data.id');

        $document = ManagedDocument::findOrFail($documentId);

        $this->actingAs($hr)
            ->patchJson(route('hr.employees.documents.approve', [$employee, $document]), [
                'approval_note' => 'Same uploader should not approve.',
            ])
            ->assertForbidden();

        $this->actingAs($director)
            ->patchJson(route('hr.employees.documents.approve', [$employee, $document]), [
                'approval_note' => 'Director approval for employee document.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.approved_by.email', 'aditya.mehra@builder360.test');

        $this->actingAs($employeeUser)
            ->getJson(route('hr.employees.documents.index', $employee))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Amit Verma Employee KYC')
            ->assertJsonPath('data.0.download_url', route('documents.download', $document, false));

        $this->actingAs($employeeUser)
            ->postJson(route('hr.employees.documents.store', $employee), [
                'document_category_id' => $category->id,
                'title' => 'Unauthorized self upload',
            ])
            ->assertForbidden();

        $this->actingAs($partner)
            ->getJson(route('hr.employees.documents.index', $employee))
            ->assertForbidden();

        $this->assertDatabaseHas('managed_documents', [
            'id' => $document->id,
            'owner_type' => 'employee',
            'owner_id' => $employee->id,
            'status' => 'approved',
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'documents.document.submitted',
            'user_id' => $hr->id,
            'auditable_id' => $document->id,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'documents.document.approved',
            'user_id' => $director->id,
            'auditable_id' => $document->id,
        ]);
    }

    public function test_hr_employee_document_register_lists_employee_owned_documents_with_scope_filters(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();
        $category = DocumentCategory::where('code', 'EMPLOYEE_KYC')->firstOrFail();
        $otherCompany = Company::where('code', 'B360P')->firstOrFail();

        $document = ManagedDocument::create([
            'company_id' => $employee->company_id,
            'document_category_id' => $category->id,
            'uploaded_by_user_id' => $hr->id,
            'document_number' => 'DOC-HR-EMP-REGISTER-01',
            'title' => 'Amit Verma Register KYC',
            'owner_type' => 'employee',
            'owner_id' => $employee->id,
            'status' => 'submitted',
            'storage_disk' => 'local',
            'storage_path' => 'documents/employees/emp-0030-register-kyc.pdf',
            'original_filename' => 'emp-0030-register-kyc.pdf',
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 4096,
            'checksum_sha256' => hash('sha256', 'emp-0030-register-kyc'),
            'issue_date' => now()->subDay(),
            'expires_on' => now()->addDays(10),
            'version' => 1,
            'is_current' => true,
            'metadata' => ['source' => 'register_test'],
        ]);

        ManagedDocument::create([
            'company_id' => $employee->company_id,
            'document_category_id' => $category->id,
            'uploaded_by_user_id' => $hr->id,
            'document_number' => 'DOC-HR-EMP-ARCHIVED-01',
            'title' => 'Archived Employee KYC',
            'owner_type' => 'employee',
            'owner_id' => $employee->id,
            'status' => 'archived',
            'storage_disk' => 'local',
            'storage_path' => 'documents/employees/emp-0030-archived-kyc.pdf',
            'original_filename' => 'emp-0030-archived-kyc.pdf',
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 2048,
            'checksum_sha256' => hash('sha256', 'emp-0030-archived-kyc'),
            'issue_date' => now()->subYear(),
            'expires_on' => now()->addYear(),
            'version' => 1,
            'is_current' => false,
            'metadata' => [],
        ]);

        $outsideEmployee = Employee::create([
            'company_id' => $otherCompany->id,
            'employee_code' => 'EMP-OUTSIDE-DOC',
            'name' => 'Outside Employee Document',
            'designation' => 'Tester',
            'department' => 'HR',
            'employment_type' => 'full_time',
            'status' => 'active',
            'joined_on' => now()->subYear(),
            'statutory_state' => 'MH',
        ]);

        $this->actingAs($hr)
            ->getJson(route('hr.employee-documents.index', ['search' => 'Register KYC', 'expires_within_days' => 30]))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $document->id)
            ->assertJsonPath('data.0.owner_type', 'employee')
            ->assertJsonPath('data.0.employee.employee_code', 'EMP-0030')
            ->assertJsonPath('data.0.is_expiring_within_30_days', true);

        $this->actingAs($hr)
            ->getJson(route('hr.employee-documents.index', ['current_only' => 0, 'status' => 'archived']))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.document_number', 'DOC-HR-EMP-ARCHIVED-01');

        $this->actingAs($hr)
            ->getJson(route('hr.employee-documents.index', ['unsupported' => 'x']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['unsupported']);

        $this->actingAs($hr)
            ->getJson(route('hr.employee-documents.index', ['employee_id' => $outsideEmployee->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['employee_id']);

        $this->actingAs($partner)
            ->getJson(route('hr.employee-documents.index'))
            ->assertForbidden();
    }

    public function test_hr_employee_document_registration_requires_expiry_and_safe_file_metadata(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();
        $category = DocumentCategory::where('code', 'EMPLOYEE_KYC')->firstOrFail();

        $this->actingAs($hr)
            ->postJson(route('hr.employees.documents.store', $employee), [
                'document_category_id' => $category->id,
                'title' => 'Invalid Employee KYC',
                'storage_path' => '../private/emp-0030-kyc.exe',
                'original_filename' => 'emp-0030-kyc.exe',
                'mime_type' => 'application/x-msdownload',
                'file_size_bytes' => 2048,
                'checksum_sha256' => str_repeat('z', 64),
                'issue_date' => now()->subDay()->toDateString(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['storage_path', 'original_filename', 'mime_type', 'checksum_sha256', 'expires_on']);
    }

    public function test_employee_master_rejects_cross_company_and_partner_access(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();
        $otherCompany = Company::where('code', 'B360P')->firstOrFail();
        $branch = Branch::where('code', 'PNQ-HO')->firstOrFail();
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();

        $this->actingAs($hr)
            ->postJson(route('hr.employees.store'), [
                'company_id' => $otherCompany->id,
                'branch_id' => $branch->id,
                'employee_code' => 'EMP-BAD',
                'name' => 'Invalid Employee',
                'designation' => 'Invalid',
                'department' => 'Invalid',
                'employment_type' => 'full_time',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['company_id', 'branch_id']);

        $this->actingAs($partner)
            ->getJson(route('hr.employees.index'))
            ->assertForbidden();

        $this->actingAs($partner)
            ->get(route('hr.employees.export', ['format' => 'csv']))
            ->assertForbidden();

        $this->actingAs($partner)
            ->getJson(route('hr.employees.show', $employee))
            ->assertForbidden();

        $this->actingAs($partner)
            ->patchJson(route('hr.employees.update', $employee), ['designation' => 'Invalid'])
            ->assertForbidden();
    }

    public function test_non_global_hr_user_without_company_assignment_fails_closed_for_employee_master(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $company = Company::where('code', 'B360D')->firstOrFail();
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();

        $hr->forceFill(['company_id' => null])->save();

        $this->actingAs($hr)
            ->getJson(route('hr.employees.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($hr)
            ->getJson(route('hr.employees.index', ['company_id' => $company->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['company_id']);

        $this->actingAs($hr)
            ->getJson(route('hr.employees.show', $employee))
            ->assertForbidden();

        $this->actingAs($hr)
            ->postJson(route('hr.employees.store'), [
                'company_id' => $company->id,
                'employee_code' => 'EMP-NOCOMP',
                'name' => 'No Company HR Attempt',
                'designation' => 'Invalid',
                'department' => 'Invalid',
                'employment_type' => 'full_time',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['company_id']);

        $this->actingAs($hr)
            ->patchJson(route('hr.employees.update', $employee), [
                'designation' => 'Invalid No Company Update',
            ])
            ->assertForbidden();
    }

    public function test_employee_master_index_rejects_unsupported_filters_and_accepts_page(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();

        $this->actingAs($hr)
            ->getJson(route('hr.employees.index', ['page' => 1]))
            ->assertOk()
            ->assertJsonPath('meta.current_page', 1);

        $this->actingAs($hr)
            ->getJson(route('hr.employees.index', ['grade' => 'B2']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['grade'])
            ->assertJsonPath('errors.grade.0', 'The selected filter is not available for this endpoint.');

        $this->actingAs($hr)
            ->getJson(route('hr.employees.export', ['format' => 'json']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['format']);
    }

    /** @param list<string> $permissions */
    private function createUserWithPermissions(Company $company, string $key, array $permissions): User
    {
        $role = Role::create([
            'slug' => $key,
            'name' => str($key)->replace('_', ' ')->title()->toString(),
            'scope_level' => 'company',
            'permissions' => $permissions,
            'is_active' => true,
        ]);

        return User::factory()->create([
            'role_id' => $role->id,
            'company_id' => $company->id,
            'email' => $key.'@example.test',
            'status' => 'active',
        ]);
    }
}
