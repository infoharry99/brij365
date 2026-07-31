<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeTaxDocument;
use App\Models\PayrollRun;
use App\Models\Company;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollTaxDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_payroll_can_generate_form_16_after_approved_payroll_and_verified_locked_tax_config(): void
    {
        $this->seed();

        $payroll = User::where('email', 'kavita.shah@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();
        $run = $this->approvedPayrollRun($payroll, $finance, 2026, 7);

        $documentId = $this->actingAs($payroll)
            ->postJson(route('payroll.tax-documents.store'), [
                'employee_id' => $employee->id,
                'financial_year' => '2026-2027',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'generated')
            ->assertJsonPath('data.document_type', 'form_16')
            ->assertJsonPath('data.financial_year', '2026-2027')
            ->assertJsonPath('data.assessment_year', '2027-2028')
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.employee.employee_code', 'EMP-0030')
            ->assertJsonPath('data.gross_salary', 90000)
            ->assertJsonPath('data.taxable_income', 40000)
            ->assertJsonPath('data.tds_deducted', 0)
            ->assertJsonPath('data.net_salary_paid', 80000)
            ->assertJsonPath('data.payroll_run_ids.0', $run->id)
            ->assertJsonPath('data.tax_configuration_snapshot.payroll_year_locked', true)
            ->assertJsonPath('data.tax_configuration_snapshot.verified', true)
            ->json('data.id');

        $document = EmployeeTaxDocument::findOrFail($documentId);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'payroll.tax_document.generated',
            'auditable_id' => $document->id,
            'user_id' => $payroll->id,
        ]);

        $this->actingAs($payroll)
            ->postJson(route('payroll.tax-documents.store'), [
                'employee_id' => $employee->id,
                'financial_year' => '2026-2027',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['financial_year']);

        $this->actingAs($payroll)
            ->postJson(route('payroll.tax-documents.store'), [
                'employee_id' => $employee->id,
                'financial_year' => '2026-2027',
                'force_new_version' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.version', 2);
    }

    public function test_generation_requires_locked_and_verified_tax_configuration(): void
    {
        $this->seed();

        $payroll = User::where('email', 'kavita.shah@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();
        $this->approvedPayrollRun($payroll, $finance, 2026, 8);

        $setting = SystemSetting::where('setting_key', 'payroll.tax_rules')->firstOrFail();
        $value = $setting->value;
        $value['payroll_year_locked'] = false;
        $setting->forceFill(['value' => $value])->save();

        $this->actingAs($payroll)
            ->postJson(route('payroll.tax-documents.store'), [
                'employee_id' => $employee->id,
                'financial_year' => '2026-2027',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['financial_year']);

        $value['payroll_year_locked'] = true;
        $value['verified'] = false;
        $setting->forceFill(['value' => $value])->save();

        $this->actingAs($payroll)
            ->postJson(route('payroll.tax-documents.store'), [
                'employee_id' => $employee->id,
                'financial_year' => '2026-2027',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['financial_year']);
    }

    public function test_finance_can_issue_and_employee_can_acknowledge_form_16(): void
    {
        $this->seed();

        $payroll = User::where('email', 'kavita.shah@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $employeeUser = User::where('email', 'amit.verma@builder360.test')->firstOrFail();
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();
        $this->approvedPayrollRun($payroll, $finance, 2026, 9);
        $document = $this->generateTaxDocument($payroll, $employee);

        $this->actingAs($payroll)
            ->patchJson(route('payroll.tax-documents.issue', $document), [
                'issue_reference' => 'FORM16-SELF-INVALID',
            ])
            ->assertForbidden();

        $this->actingAs($finance)
            ->patchJson(route('payroll.tax-documents.issue', $document), [
                'issue_reference' => 'FORM16-FY2627-EMP0030',
                'note' => 'Issued after finance and statutory review.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'issued')
            ->assertJsonPath('data.issue_reference', 'FORM16-FY2627-EMP0030')
            ->assertJsonPath('data.issued_by.email', 'suresh.iyer@builder360.test');

        $this->assertTrue(UserNotification::where('recipient_user_id', $employeeUser->id)
            ->where('category', 'payroll')
            ->where('status', 'unread')
            ->exists());

        $document->refresh();

        $this->actingAs($employeeUser)
            ->patchJson(route('payroll.tax-documents.acknowledge', $document), [
                'employee_acknowledgement_note' => 'Downloaded and acknowledged.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'acknowledged')
            ->assertJsonPath('data.employee_acknowledgement_note', 'Downloaded and acknowledged.')
            ->assertJsonPath('data.acknowledged_by.email', 'amit.verma@builder360.test');

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'payroll.tax_document.issued',
            'auditable_id' => $document->id,
            'user_id' => $finance->id,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'payroll.tax_document.acknowledged',
            'auditable_id' => $document->id,
            'user_id' => $employeeUser->id,
        ]);
    }

    public function test_employee_scope_and_partner_denial_are_enforced(): void
    {
        $this->seed();

        $payroll = User::where('email', 'kavita.shah@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $employeeUser = User::where('email', 'amit.verma@builder360.test')->firstOrFail();
        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();
        $amit = Employee::where('employee_code', 'EMP-0030')->firstOrFail();
        $priya = Employee::where('employee_code', 'EMP-0021')->firstOrFail();
        $otherCompanyEmployee = $this->createOtherCompanyEmployee();
        $this->approvedPayrollRun($payroll, $finance, 2026, 10);
        $amitDocument = $this->generateTaxDocument($payroll, $amit);
        $this->generateTaxDocument($payroll, $priya);

        $this->actingAs($employeeUser)
            ->getJson(route('payroll.tax-documents.index', ['financial_year' => '2026-2027']))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($payroll)
            ->getJson(route('payroll.tax-documents.index', ['page' => 1]))
            ->assertOk()
            ->assertJsonPath('meta.current_page', 1);

        $this->actingAs($payroll)
            ->getJson(route('payroll.tax-documents.index', ['period_year' => 2026]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['period_year'])
            ->assertJsonPath('errors.period_year.0', 'The selected filter is not available for this endpoint.');

        $this->actingAs($payroll)
            ->getJson(route('payroll.tax-documents.index', ['status' => 'invalid']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->actingAs($finance)
            ->patchJson(route('payroll.tax-documents.issue', $amitDocument), [
                'issue_reference' => 'FORM16-FY2627-EMP0030-SCOPE',
                'note' => 'Issued for employee self-service scope test.',
            ])
            ->assertOk();

        $this->actingAs($employeeUser)
            ->getJson(route('payroll.tax-documents.index', ['financial_year' => '2026-2027']))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.employee.employee_code', 'EMP-0030')
            ->assertJsonPath('data.0.document_payload.employee.employee_code', 'EMP-0030')
            ->assertJsonMissingPath('data.0.payroll_run_ids')
            ->assertJsonMissingPath('data.0.tax_configuration_snapshot')
            ->assertJsonMissingPath('data.0.document_payload.tax_setting')
            ->assertJsonMissingPath('data.0.workflow_history');

        $this->actingAs($employeeUser)
            ->getJson(route('payroll.tax-documents.index', ['employee_id' => $priya->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['employee_id']);

        $this->actingAs($payroll)
            ->getJson(route('payroll.tax-documents.index', ['employee_id' => $otherCompanyEmployee->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['employee_id']);

        $this->actingAs($partner)
            ->getJson(route('payroll.tax-documents.index'))
            ->assertForbidden();

        $this->actingAs($partner)
            ->postJson(route('payroll.tax-documents.store'), [
                'employee_id' => $amit->id,
                'financial_year' => '2026-2027',
            ])
            ->assertForbidden();
    }

    public function test_non_global_tax_document_users_without_company_assignment_fail_closed(): void
    {
        $this->seed();

        $payroll = User::where('email', 'kavita.shah@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();

        $this->approvedPayrollRun($payroll, $finance, 2026, 11);
        $document = $this->generateTaxDocument($payroll, $employee);

        $payroll->forceFill(['company_id' => null])->save();
        $finance->forceFill(['company_id' => null])->save();

        $this->actingAs($payroll)
            ->getJson(route('payroll.tax-documents.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($payroll)
            ->getJson(route('payroll.tax-documents.index', ['employee_id' => $employee->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('employee_id');

        $this->actingAs($payroll)
            ->postJson(route('payroll.tax-documents.store'), [
                'employee_id' => $employee->id,
                'financial_year' => '2026-2027',
                'force_new_version' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('employee_id');

        $this->actingAs($finance)
            ->patchJson(route('payroll.tax-documents.issue', $document), [
                'issue_reference' => 'FORM16-NO-COMPANY',
                'note' => 'Should fail without company scope.',
            ])
            ->assertForbidden();
    }

    private function approvedPayrollRun(User $payroll, User $finance, int $year, int $month): PayrollRun
    {
        $runNumber = $this->actingAs($payroll)
            ->postJson(route('payroll.runs.generate'), [
                'period_year' => $year,
                'period_month' => $month,
                'working_days' => 26,
            ])
            ->assertCreated()
            ->json('data.run_number');

        $run = PayrollRun::where('run_number', $runNumber)->firstOrFail();

        $this->actingAs($finance)
            ->patchJson(route('payroll.runs.approve', $run))
            ->assertOk();

        return $run->refresh();
    }

    private function generateTaxDocument(User $payroll, Employee $employee): EmployeeTaxDocument
    {
        $id = $this->actingAs($payroll)
            ->postJson(route('payroll.tax-documents.store'), [
                'employee_id' => $employee->id,
                'financial_year' => '2026-2027',
            ])
            ->assertCreated()
            ->json('data.id');

        return EmployeeTaxDocument::findOrFail($id);
    }

    private function createOtherCompanyEmployee(): Employee
    {
        $company = Company::where('code', 'B360P')->firstOrFail();

        return Employee::create([
            'company_id' => $company->id,
            'branch_id' => null,
            'project_id' => null,
            'user_id' => null,
            'manager_employee_id' => null,
            'employee_code' => 'EMP-EXT-TAX',
            'name' => 'External Tax Scope Employee',
            'designation' => 'Accounts Executive',
            'department' => 'Finance',
            'grade' => 'E2',
            'employment_type' => 'full_time',
            'status' => 'active',
            'joined_on' => now()->subYear()->toDateString(),
            'statutory_state' => 'MH',
            'monthly_ctc' => 50000,
            'sensitive_profile' => [],
        ]);
    }
}
