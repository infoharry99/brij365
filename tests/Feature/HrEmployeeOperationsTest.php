<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DocumentCategory;
use App\Models\Employee;
use App\Models\EmployeeAsset;
use App\Models\EmployeeLoan;
use App\Models\ExpenseClaim;
use App\Models\HrHelpdeskTicket;
use App\Models\ManagedDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrEmployeeOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_claims_blade_workspace_loads_and_processes_claim_workflow(): void
    {
        $this->seed();

        $employeeUser = User::where('email', 'amit.verma@builder360.test')->firstOrFail();
        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();

        $this->actingAs($hr)
            ->get(route('hr.expense-claims.index'))
            ->assertOk()
            ->assertSee('Expense Claims')
            ->assertSee('Submit expense claim')
            ->assertSee('Expense claims')
            ->assertSee('Employee loans');

        $this->actingAs($hr)
            ->get(route('hr.loans.index'))
            ->assertOk()
            ->assertSee('Loans &amp; Advances', false);

        $this->actingAs($hr)
            ->get(route('hr.helpdesk-tickets.index'))
            ->assertOk()
            ->assertSee('HR Helpdesk');

        $this->actingAs($employeeUser)
            ->post(route('hr.expense-claims.store'), [
                'employee_id' => $employee->id,
                'claim_type' => 'fuel',
                'claim_date' => now()->toDateString(),
                'amount' => 1800,
                'description' => 'Fuel reimbursement from the native Blade workspace.',
            ])
            ->assertRedirect(route('hr.expense-claims.index'));

        $claim = ExpenseClaim::query()
            ->where('employee_id', $employee->id)
            ->where('description', 'Fuel reimbursement from the native Blade workspace.')
            ->firstOrFail();

        $this->assertSame('submitted', $claim->status);

        $this->actingAs($hr)
            ->patch(route('hr.expense-claims.approve', $claim), [
                'approved_amount' => 1750,
                'decision_note' => 'Approved through Blade claims workspace.',
            ])
            ->assertRedirect(route('hr.expense-claims.index'));

        $claim->refresh();
        $this->assertSame('approved', $claim->status);
        $this->assertSame('1750.00', (string) $claim->approved_amount);

        $this->actingAs($finance)
            ->patch(route('hr.expense-claims.pay', $claim), [
                'payment_reference' => 'NEFT-CLAIM-BLADE-1',
                'note' => 'Paid through Blade claims workspace.',
            ])
            ->assertRedirect(route('hr.expense-claims.index'));

        $claim->refresh();
        $this->assertSame('paid', $claim->status);
        $this->assertSame($finance->id, $claim->paid_by_user_id);
        $this->assertSame('Paid through Blade claims workspace.', collect($claim->workflow_history)->last()['note']);
    }

    public function test_assets_blade_workspace_loads_and_processes_asset_assignment_workflow(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();

        $this->actingAs($hr)
            ->get(route('hr.assets.index'))
            ->assertOk()
            ->assertSee('Asset Management')
            ->assertSee('Register employee asset')
            ->assertSee('Employee assets')
            ->assertSee('HR helpdesk tickets');

        $this->actingAs($hr)
            ->post(route('hr.assets.store'), [
                'asset_code' => 'AST-BLADE-3001',
                'category' => 'Laptop',
                'name' => 'Blade Workflow Laptop',
                'serial_number' => 'BLADE-LAP-3001',
                'condition' => 'new',
                'estimated_value' => 72000,
            ])
            ->assertRedirect(route('hr.assets.index'));

        $asset = EmployeeAsset::where('asset_code', 'AST-BLADE-3001')->firstOrFail();
        $this->assertSame('available', $asset->status);

        $this->actingAs($hr)
            ->patch(route('hr.assets.assign', $asset), [
                'employee_id' => $employee->id,
                'assigned_on' => now()->toDateString(),
                'note' => 'Assigned through Blade asset workspace.',
            ])
            ->assertRedirect(route('hr.assets.index'));

        $asset->refresh();
        $this->assertSame('assigned', $asset->status);
        $this->assertSame($employee->id, $asset->employee_id);

        $this->actingAs($hr)
            ->patch(route('hr.assets.recover', $asset), [
                'condition' => 'good',
                'status' => 'recovered',
                'recovered_on' => now()->toDateString(),
                'note' => 'Recovered through Blade asset workspace.',
            ])
            ->assertRedirect(route('hr.assets.index'));

        $asset->refresh();
        $this->assertSame('recovered', $asset->status);
        $this->assertSame('Recovered through Blade asset workspace.', collect($asset->workflow_history)->last()['note']);
    }

    public function test_hr_can_register_assign_and_recover_employee_asset(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();

        $assetId = $this->actingAs($hr)
            ->postJson(route('hr.assets.store'), [
                'asset_code' => 'AST-TAB-2001',
                'category' => 'Tablet',
                'name' => 'Site Inspection Tablet',
                'serial_number' => 'TAB-B360-2001',
                'condition' => 'new',
                'estimated_value' => 32000,
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'available')
            ->json('data.id');

        $asset = EmployeeAsset::findOrFail($assetId);

        $this->actingAs($hr)
            ->patchJson(route('hr.assets.assign', $asset), [
                'employee_id' => $employee->id,
                'assigned_on' => now()->toDateString(),
                'note' => 'Issued for site inspection work.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'assigned')
            ->assertJsonPath('data.employee.employee_code', 'EMP-0030');

        $asset->refresh();

        $this->actingAs($hr)
            ->patchJson(route('hr.assets.recover', $asset), [
                'condition' => 'good',
                'status' => 'recovered',
                'note' => 'Returned after inspection.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'recovered')
            ->assertJsonPath('data.condition', 'good');

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'hr.asset.created',
            'user_id' => $hr->id,
            'auditable_id' => $asset->id,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'hr.asset.recovered',
            'user_id' => $hr->id,
            'auditable_id' => $asset->id,
        ]);
    }

    public function test_employee_sees_own_assets_and_cannot_manage_assets(): void
    {
        $this->seed();

        $employeeUser = User::where('email', 'amit.verma@builder360.test')->firstOrFail();

        $this->actingAs($employeeUser)
            ->getJson(route('hr.assets.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.asset_code', 'AST-LAP-1001');

        $this->actingAs($employeeUser)
            ->getJson(route('hr.assets.index', ['employee_id' => Employee::where('employee_code', 'EMP-0012')->value('id')]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['employee_id']);

        $this->actingAs($employeeUser)
            ->postJson(route('hr.assets.store'), [
                'asset_code' => 'AST-BAD-0001',
                'category' => 'Laptop',
                'name' => 'Invalid Asset',
            ])
            ->assertForbidden();
    }

    public function test_global_user_can_register_asset_with_explicit_company_scope(): void
    {
        $this->seed();

        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $company = Company::where('code', 'B360D')->firstOrFail();

        $response = $this->actingAs($director)
            ->postJson(route('hr.assets.store'), [
                'company_id' => $company->id,
                'asset_code' => 'AST-GLOBAL-2001',
                'category' => 'Laptop',
                'name' => 'Global Scope Laptop',
                'serial_number' => 'GLOBAL-LAP-2001',
                'condition' => 'new',
                'estimated_value' => 75000,
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.status', 'available')
            ->assertJsonPath('data.company.id', $company->id)
            ->assertJsonPath('data.company.code', 'B360D');

        $this->assertDatabaseHas('employee_assets', [
            'asset_code' => 'AST-GLOBAL-2001',
            'company_id' => $company->id,
            'status' => 'available',
        ]);

        $this->actingAs($director)
            ->postJson(route('hr.assets.store'), [
                'asset_code' => 'AST-GLOBAL-2002',
                'category' => 'Laptop',
                'name' => 'Active Company Context Laptop',
            ])
            ->assertCreated()
            ->assertJsonPath('data.company.id', $company->id);
    }

    public function test_hr_employee_document_submission_and_separate_approval_workflow(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();
        $category = DocumentCategory::where('code', 'EMPLOYEE_KYC')->firstOrFail();

        $documentId = $this->actingAs($hr)
            ->postJson(route('hr.employees.documents.store', $employee), [
                'document_category_id' => $category->id,
                'title' => 'Amit Verma KYC Proof',
                'storage_disk' => 'local',
                'storage_path' => 'documents/employees/amit-verma-kyc.pdf',
                'original_filename' => 'amit-verma-kyc.pdf',
                'mime_type' => 'application/pdf',
                'file_size_bytes' => 124000,
                'checksum_sha256' => str_repeat('a', 64),
                'issue_date' => now()->subDay()->toDateString(),
                'expires_on' => now()->addYear()->toDateString(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.employee.employee_code', 'EMP-0030')
            ->json('data.id');

        $document = ManagedDocument::findOrFail($documentId);

        $this->actingAs($hr)
            ->patchJson(route('hr.employees.documents.approve', [$employee, $document]), [
                'approval_note' => 'Uploader cannot approve this document.',
            ])
            ->assertForbidden();

        $this->actingAs($director)
            ->patchJson(route('hr.employees.documents.approve', [$employee, $document]), [
                'approval_note' => 'KYC proof verified against original.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.approved_by.email', 'aditya.mehra@builder360.test')
            ->assertJsonPath('data.metadata.approval_note', 'KYC proof verified against original.');

        $this->assertDatabaseHas('managed_documents', [
            'id' => $document->id,
            'owner_type' => 'employee',
            'owner_id' => $employee->id,
            'status' => 'approved',
            'approved_by_user_id' => $director->id,
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

    public function test_employee_claim_submission_hr_approval_and_finance_payment_workflow(): void
    {
        $this->seed();

        $employeeUser = User::where('email', 'amit.verma@builder360.test')->firstOrFail();
        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();

        $claimId = $this->actingAs($employeeUser)
            ->postJson(route('hr.expense-claims.store'), [
                'employee_id' => $employee->id,
                'claim_type' => 'fuel',
                'claim_date' => now()->toDateString(),
                'amount' => 2450,
                'description' => 'Fuel reimbursement for project site visit.',
                'attachments' => [['name' => 'fuel-bill.jpg', 'url' => 'documents/demo/fuel-bill.jpg']],
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.employee.employee_code', 'EMP-0030')
            ->json('data.id');

        $claim = ExpenseClaim::findOrFail($claimId);

        $this->actingAs($employeeUser)
            ->patchJson(route('hr.expense-claims.approve', $claim), [
                'approved_amount' => 2450,
            ])
            ->assertForbidden();

        $this->actingAs($hr)
            ->patchJson(route('hr.expense-claims.approve', $claim), [
                'approved_amount' => 2400,
                'decision_note' => 'Approved as per travel policy.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.approved_amount', 2400)
            ->assertJsonPath('data.approved_by.email', 'deepa.rao@builder360.test');

        $claim->refresh();

        $this->actingAs($finance)
            ->patchJson(route('hr.expense-claims.pay', $claim), [
                'payment_reference' => 'NEFT-CLM-2001',
                'note' => 'Paid in weekly reimbursement batch.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.paid_by.email', 'suresh.iyer@builder360.test');

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'hr.claim.submitted',
            'user_id' => $employeeUser->id,
            'auditable_id' => $claim->id,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'hr.claim.paid',
            'user_id' => $finance->id,
            'auditable_id' => $claim->id,
        ]);
    }

    public function test_employee_claim_scope_and_partner_denial_are_enforced(): void
    {
        $this->seed();

        $employeeUser = User::where('email', 'amit.verma@builder360.test')->firstOrFail();
        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();
        $otherEmployee = Employee::where('employee_code', 'EMP-0012')->firstOrFail();

        $this->actingAs($employeeUser)
            ->postJson(route('hr.expense-claims.store'), [
                'employee_id' => $otherEmployee->id,
                'claim_type' => 'travel',
                'claim_date' => now()->toDateString(),
                'amount' => 1200,
                'description' => 'Invalid claim for another employee.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('employee_id');

        $this->actingAs($employeeUser)
            ->getJson(route('hr.expense-claims.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.claim_number', 'CLM-1001');

        $this->actingAs($employeeUser)
            ->getJson(route('hr.expense-claims.index', ['employee_id' => $otherEmployee->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['employee_id']);

        $this->actingAs($partner)
            ->getJson(route('hr.expense-claims.index'))
            ->assertForbidden();

        $this->actingAs($partner)
            ->getJson(route('hr.assets.index'))
            ->assertForbidden();
    }

    public function test_non_global_hr_operations_users_without_company_assignment_fail_closed(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();

        $assetId = $this->actingAs($hr)
            ->postJson(route('hr.assets.store'), [
                'asset_code' => 'AST-SCOPE-0001',
                'category' => 'Laptop',
                'name' => 'Scope Test Laptop',
                'condition' => 'new',
                'estimated_value' => 45000,
            ])
            ->assertCreated()
            ->json('data.id');

        $claimId = $this->actingAs($hr)
            ->postJson(route('hr.expense-claims.store'), [
                'employee_id' => $employee->id,
                'claim_type' => 'office',
                'claim_date' => now()->toDateString(),
                'amount' => 1500,
                'description' => 'Office supplies claim for scope regression.',
            ])
            ->assertCreated()
            ->json('data.id');

        $loanId = $this->actingAs($hr)
            ->postJson(route('hr.loans.store'), [
                'employee_id' => $employee->id,
                'loan_type' => 'emergency',
                'principal_amount' => 25000,
                'installment_months' => 5,
                'purpose' => 'Emergency support scope regression.',
            ])
            ->assertCreated()
            ->json('data.id');

        $ticketId = $this->actingAs($hr)
            ->postJson(route('hr.helpdesk-tickets.store'), [
                'employee_id' => $employee->id,
                'category' => 'policy',
                'priority' => 'medium',
                'subject' => 'Scope regression HR helpdesk',
                'description' => 'Request created before company scope is removed.',
            ])
            ->assertCreated()
            ->json('data.id');

        $asset = EmployeeAsset::findOrFail($assetId);
        $claim = ExpenseClaim::findOrFail($claimId);
        $loan = EmployeeLoan::findOrFail($loanId);
        $ticket = HrHelpdeskTicket::findOrFail($ticketId);

        $hr->forceFill(['company_id' => null])->save();
        $finance->forceFill(['company_id' => null])->save();

        $this->actingAs($hr)
            ->getJson(route('hr.assets.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($hr)
            ->getJson(route('hr.expense-claims.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($hr)
            ->getJson(route('hr.loans.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($hr)
            ->getJson(route('hr.helpdesk-tickets.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($hr)
            ->postJson(route('hr.assets.store'), [
                'asset_code' => 'AST-SCOPE-0002',
                'category' => 'Laptop',
                'name' => 'Invalid No Company Asset',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('company_id');

        $this->actingAs($hr)
            ->postJson(route('hr.expense-claims.store'), [
                'employee_id' => $employee->id,
                'claim_type' => 'office',
                'claim_date' => now()->toDateString(),
                'amount' => 1200,
                'description' => 'Invalid no company claim.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('employee_id');

        $this->actingAs($hr)
            ->postJson(route('hr.loans.store'), [
                'employee_id' => $employee->id,
                'loan_type' => 'emergency',
                'principal_amount' => 25000,
                'installment_months' => 5,
                'purpose' => 'Invalid no company loan request.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('employee_id');

        $this->actingAs($hr)
            ->postJson(route('hr.helpdesk-tickets.store'), [
                'employee_id' => $employee->id,
                'category' => 'policy',
                'priority' => 'medium',
                'subject' => 'Invalid no company helpdesk',
                'description' => 'Invalid no company helpdesk request.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('employee_id');

        $this->actingAs($hr)
            ->patchJson(route('hr.assets.assign', $asset), [
                'employee_id' => $employee->id,
            ])
            ->assertForbidden();

        $this->actingAs($hr)
            ->patchJson(route('hr.expense-claims.approve', $claim), [
                'approved_amount' => 1500,
            ])
            ->assertForbidden();

        $this->actingAs($hr)
            ->patchJson(route('hr.loans.approve', $loan), [
                'approved_amount' => 20000,
                'repayment_starts_on' => now()->addMonthNoOverflow()->startOfMonth()->toDateString(),
            ])
            ->assertForbidden();

        $this->actingAs($hr)
            ->patchJson(route('hr.helpdesk-tickets.assign', $ticket), [
                'assigned_to_user_id' => $finance->id,
            ])
            ->assertForbidden();
    }

    public function test_hr_asset_and_claim_indexes_reject_unsupported_filters_and_accept_page(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();

        $this->actingAs($hr)
            ->getJson(route('hr.assets.index', ['page' => 1]))
            ->assertOk()
            ->assertJsonPath('meta.current_page', 1);

        $this->actingAs($hr)
            ->getJson(route('hr.assets.index', ['claim_type' => 'fuel']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['claim_type'])
            ->assertJsonPath('errors.claim_type.0', 'The selected filter is not available for this endpoint.');

        $this->actingAs($hr)
            ->getJson(route('hr.expense-claims.index', ['page' => 1]))
            ->assertOk()
            ->assertJsonPath('meta.current_page', 1);

        $this->actingAs($hr)
            ->getJson(route('hr.expense-claims.index', ['category' => 'Laptop']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category'])
            ->assertJsonPath('errors.category.0', 'The selected filter is not available for this endpoint.');
    }
}
