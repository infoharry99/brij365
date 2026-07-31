<?php

namespace Tests\Feature;

use App\Application\Hr\Actions\ApproveEmployeeLoan;
use App\Application\Hr\Actions\AssignHrHelpdeskTicket;
use App\Application\Hr\Actions\CloseHrHelpdeskTicket;
use App\Application\Hr\Actions\CreateHrHelpdeskTicket;
use App\Application\Hr\Actions\DisburseEmployeeLoan;
use App\Application\Hr\Actions\RejectEmployeeLoan;
use App\Application\Hr\Data\HrCommandData;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\HrHelpdeskTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class HrLoansAndHelpdeskTest extends TestCase
{
    use RefreshDatabase;

    public function test_helpdesk_service_rejects_closing_an_unresolved_ticket(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $employee = Employee::where('company_id', $hr->company_id)->firstOrFail();
        $ticket = app(CreateHrHelpdeskTicket::class)->execute(new HrCommandData([
            'employee_id' => $employee->id,
            'category' => 'policy',
            'priority' => 'normal',
            'subject' => 'Verify unresolved ticket close guard',
            'description' => 'This open ticket must not be closed before resolution.',
        ], $hr));

        try {
            app(CloseHrHelpdeskTicket::class)->execute($ticket, new HrCommandData(['note' => 'Invalid early close.'], $hr));
            $this->fail('An unresolved HR helpdesk ticket was closed by the application service.');
        } catch (ValidationException $exception) {
            $this->assertSame('Only resolved HR helpdesk tickets can be closed.', $exception->errors()['ticket'][0]);
        }

        $this->assertSame('open', $ticket->fresh()->status);
    }

    public function test_loans_blade_workspace_loads_and_processes_loan_workflow(): void
    {
        $this->seed();

        $employeeUser = User::where('email', 'amit.verma@builder360.test')->firstOrFail();
        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();

        $this->actingAs($hr)
            ->get(route('hr.loans.index'))
            ->assertOk()
            ->assertSee('Employee Operations Workspace')
            ->assertSee('Request employee loan')
            ->assertSee('Employee loans')
            ->assertSee('Expense claims');

        $this->actingAs($employeeUser)
            ->post(route('hr.loans.store'), [
                'employee_id' => $employee->id,
                'loan_type' => 'emergency',
                'principal_amount' => 36000,
                'installment_months' => 9,
                'purpose' => 'Emergency loan requested from the native Blade workspace.',
            ])
            ->assertRedirect(route('hr.loans.index'));

        $loan = EmployeeLoan::query()
            ->where('employee_id', $employee->id)
            ->where('purpose', 'Emergency loan requested from the native Blade workspace.')
            ->firstOrFail();

        $this->assertSame('submitted', $loan->status);

        $this->actingAs($hr)
            ->patch(route('hr.loans.approve', $loan), [
                'approved_amount' => 27000,
                'repayment_starts_on' => now()->addMonthNoOverflow()->startOfMonth()->toDateString(),
                'decision_note' => 'Approved through Blade loans workspace.',
            ])
            ->assertRedirect(route('hr.loans.index'));

        $loan->refresh();
        $this->assertSame('approved', $loan->status);
        $this->assertSame('27000.00', (string) $loan->approved_amount);
        $this->assertSame('3000.00', (string) $loan->monthly_installment);

        $this->actingAs($finance)
            ->patch(route('hr.loans.disburse', $loan), [
                'payment_reference' => 'NEFT-LOAN-BLADE-1',
                'note' => 'Disbursed through Blade loans workspace.',
            ])
            ->assertRedirect(route('hr.loans.index'));

        $loan->refresh();
        $this->assertSame('disbursed', $loan->status);
        $this->assertSame($finance->id, $loan->disbursed_by_user_id);
        $this->assertSame('Disbursed through Blade loans workspace.', collect($loan->workflow_history)->last()['note']);
    }

    public function test_helpdesk_blade_workspace_loads_and_processes_ticket_workflow(): void
    {
        $this->seed();

        $employeeUser = User::where('email', 'amit.verma@builder360.test')->firstOrFail();
        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();

        $this->actingAs($hr)
            ->get(route('hr.helpdesk-tickets.index'))
            ->assertOk()
            ->assertSee('Employee Operations Workspace')
            ->assertSee('Raise HR helpdesk ticket')
            ->assertSee('HR helpdesk tickets')
            ->assertSee('Employee assets');

        $this->actingAs($employeeUser)
            ->post(route('hr.helpdesk-tickets.store'), [
                'employee_id' => $employee->id,
                'category' => 'payroll',
                'priority' => 'high',
                'subject' => 'Blade workspace payslip query',
                'description' => 'Please review my payslip reimbursement from the native Blade workspace.',
            ])
            ->assertRedirect(route('hr.helpdesk-tickets.index'));

        $ticket = HrHelpdeskTicket::where('subject', 'Blade workspace payslip query')->firstOrFail();
        $this->assertSame('open', $ticket->status);

        $this->actingAs($hr)
            ->patch(route('hr.helpdesk-tickets.assign', $ticket), [
                'assigned_to_user_id' => $hr->id,
                'note' => 'Assigned through Blade helpdesk workspace.',
            ])
            ->assertRedirect(route('hr.helpdesk-tickets.index'));

        $ticket->refresh();
        $this->assertSame('assigned', $ticket->status);
        $this->assertSame($hr->id, $ticket->assigned_to_user_id);

        $this->actingAs($hr)
            ->patch(route('hr.helpdesk-tickets.resolve', $ticket), [
                'resolution_summary' => 'Resolved through Blade helpdesk workspace.',
            ])
            ->assertRedirect(route('hr.helpdesk-tickets.index'));

        $ticket->refresh();
        $this->assertSame('resolved', $ticket->status);

        $this->actingAs($employeeUser)
            ->patch(route('hr.helpdesk-tickets.close', $ticket), [
                'note' => 'Closed through Blade helpdesk workspace.',
            ])
            ->assertRedirect(route('hr.helpdesk-tickets.index'));

        $ticket->refresh();
        $this->assertSame('closed', $ticket->status);
        $this->assertSame('Closed through Blade helpdesk workspace.', collect($ticket->workflow_history)->last()['note']);
    }

    public function test_employee_loan_submission_hr_approval_and_finance_disbursement(): void
    {
        $this->seed();

        $employeeUser = User::where('email', 'amit.verma@builder360.test')->firstOrFail();
        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();

        $loanId = $this->actingAs($employeeUser)
            ->postJson(route('hr.loans.store'), [
                'employee_id' => $employee->id,
                'loan_type' => 'emergency',
                'principal_amount' => 30000,
                'installment_months' => 6,
                'purpose' => 'Emergency advance for urgent family medical expense.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.employee.employee_code', 'EMP-0030')
            ->json('data.id');

        $loan = EmployeeLoan::findOrFail($loanId);

        $this->actingAs($employeeUser)
            ->patchJson(route('hr.loans.approve', $loan), [
                'approved_amount' => 30000,
                'repayment_starts_on' => now()->addMonth()->startOfMonth()->toDateString(),
            ])
            ->assertForbidden();

        $this->actingAs($hr)
            ->patchJson(route('hr.loans.approve', $loan), [
                'approved_amount' => 24000,
                'repayment_starts_on' => now()->addMonth()->startOfMonth()->toDateString(),
                'decision_note' => 'Approved within policy limit.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.approved_amount', 24000)
            ->assertJsonPath('data.monthly_installment', 4000);

        $loan->refresh();

        $this->actingAs($finance)
            ->patchJson(route('hr.loans.disburse', $loan), [
                'payment_reference' => 'NEFT-LOAN-3001',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'disbursed')
            ->assertJsonPath('data.disbursed_by.email', 'suresh.iyer@builder360.test');

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'hr.loan.submitted',
            'user_id' => $employeeUser->id,
            'auditable_id' => $loan->id,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'hr.loan.disbursed',
            'user_id' => $finance->id,
            'auditable_id' => $loan->id,
        ]);
    }

    public function test_loan_workflow_rejects_decisions_and_disbursements_from_invalid_source_statuses(): void
    {
        $this->seed();

        $employeeUser = User::where('email', 'amit.verma@builder360.test')->firstOrFail();
        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();

        $loanId = $this->actingAs($employeeUser)
            ->postJson(route('hr.loans.store'), [
                'employee_id' => $employee->id,
                'loan_type' => 'emergency',
                'principal_amount' => 20000,
                'installment_months' => 4,
                'purpose' => 'Validate the canonical loan transition contract.',
            ])
            ->assertCreated()
            ->json('data.id');

        $loan = EmployeeLoan::findOrFail($loanId);

        $this->assertValidationErrorFor('loan', fn () => app(DisburseEmployeeLoan::class)->execute(
            $loan,
            new HrCommandData(['payment_reference' => 'INVALID-INTERNAL-EARLY'], $finance),
        ));

        $this->actingAs($finance)
            ->patchJson(route('hr.loans.disburse', $loan), ['payment_reference' => 'INVALID-EARLY'])
            ->assertForbidden();

        $this->actingAs($hr)
            ->patchJson(route('hr.loans.approve', $loan), [
                'approved_amount' => 16000,
                'repayment_starts_on' => now()->addMonthNoOverflow()->startOfMonth()->toDateString(),
            ])
            ->assertOk();

        $loan->refresh();

        $this->assertValidationErrorFor('loan', fn () => app(ApproveEmployeeLoan::class)->execute(
            $loan,
            new HrCommandData([
                'approved_amount' => 15000,
                'repayment_starts_on' => now()->addMonthNoOverflow()->startOfMonth()->toDateString(),
            ], $hr),
        ));
        $this->assertValidationErrorFor('loan', fn () => app(RejectEmployeeLoan::class)->execute(
            $loan,
            new HrCommandData(['decision_note' => 'Too late to reject internally.'], $hr),
        ));

        $this->actingAs($hr)
            ->patchJson(route('hr.loans.approve', $loan), [
                'approved_amount' => 15000,
                'repayment_starts_on' => now()->addMonthNoOverflow()->startOfMonth()->toDateString(),
            ])
            ->assertForbidden();

        $this->actingAs($hr)
            ->patchJson(route('hr.loans.reject', $loan), ['decision_note' => 'Too late to reject.'])
            ->assertForbidden();

        $this->actingAs($finance)
            ->patchJson(route('hr.loans.disburse', $loan), ['payment_reference' => 'VALID-DISBURSEMENT'])
            ->assertOk();

        $this->actingAs($finance)
            ->patchJson(route('hr.loans.disburse', $loan->fresh()), ['payment_reference' => 'DUPLICATE'])
            ->assertForbidden();

        $this->assertValidationErrorFor('loan', fn () => app(DisburseEmployeeLoan::class)->execute(
            $loan->fresh(),
            new HrCommandData(['payment_reference' => 'DUPLICATE-INTERNAL'], $finance),
        ));

        $this->assertSame('disbursed', $loan->fresh()->status);
        $this->assertSame(1, collect($loan->fresh()->workflow_history)->where('status', 'disbursed')->count());

        $rejectedLoanId = $this->actingAs($employeeUser)
            ->postJson(route('hr.loans.store'), [
                'employee_id' => $employee->id,
                'loan_type' => 'salary_advance',
                'principal_amount' => 12000,
                'installment_months' => 3,
                'purpose' => 'Validate the permitted submitted-to-rejected transition.',
            ])
            ->assertCreated()
            ->json('data.id');

        $rejectedLoan = EmployeeLoan::findOrFail($rejectedLoanId);

        $this->actingAs($hr)
            ->patchJson(route('hr.loans.reject', $rejectedLoan), [
                'decision_note' => 'Rejected through the valid decision workflow.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $this->assertValidationErrorFor('loan', fn () => app(RejectEmployeeLoan::class)->execute(
            $rejectedLoan->fresh(),
            new HrCommandData(['decision_note' => 'Duplicate rejection.'], $hr),
        ));
        $this->assertSame(1, collect($rejectedLoan->fresh()->workflow_history)->where('status', 'rejected')->count());
    }

    public function test_employee_loan_scope_and_partner_denial(): void
    {
        $this->seed();

        $employeeUser = User::where('email', 'amit.verma@builder360.test')->firstOrFail();
        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();
        $otherEmployee = Employee::where('employee_code', 'EMP-0012')->firstOrFail();

        $this->actingAs($employeeUser)
            ->getJson(route('hr.loans.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.loan_number', 'LOAN-1001');

        $this->actingAs($employeeUser)
            ->getJson(route('hr.loans.index', ['employee_id' => $otherEmployee->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['employee_id']);

        $this->actingAs($employeeUser)
            ->postJson(route('hr.loans.store'), [
                'employee_id' => $otherEmployee->id,
                'loan_type' => 'salary_advance',
                'principal_amount' => 10000,
                'installment_months' => 3,
                'purpose' => 'Invalid loan for another employee.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('employee_id');

        $this->actingAs($partner)
            ->getJson(route('hr.loans.index'))
            ->assertForbidden();
    }

    public function test_hr_helpdesk_ticket_workflow(): void
    {
        $this->seed();

        $employeeUser = User::where('email', 'amit.verma@builder360.test')->firstOrFail();
        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();

        $ticketId = $this->actingAs($employeeUser)
            ->postJson(route('hr.helpdesk-tickets.store'), [
                'employee_id' => $employee->id,
                'category' => 'payroll',
                'priority' => 'high',
                'subject' => 'Payslip correction request',
                'description' => 'Please review and correct the reimbursement line in my payslip.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'open')
            ->json('data.id');

        $ticket = HrHelpdeskTicket::findOrFail($ticketId);

        $this->actingAs($hr)
            ->patchJson(route('hr.helpdesk-tickets.assign', $ticket), [
                'assigned_to_user_id' => $hr->id,
                'note' => 'HR payroll desk will review.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'assigned');

        $ticket->refresh();

        $this->actingAs($hr)
            ->patchJson(route('hr.helpdesk-tickets.resolve', $ticket), [
                'resolution_summary' => 'Payslip reimbursement line reviewed and corrected.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'resolved');

        $ticket->refresh();

        $this->actingAs($employeeUser)
            ->patchJson(route('hr.helpdesk-tickets.close', $ticket), [
                'note' => 'Confirmed corrected.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.closed_by.email', 'amit.verma@builder360.test');

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'hr.helpdesk.closed',
            'user_id' => $employeeUser->id,
            'auditable_id' => $ticket->id,
        ]);
    }

    public function test_helpdesk_assignee_candidates_exclude_external_portal_and_inactive_users(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $inactiveInternal = User::where('email', 'amit.verma@builder360.test')->firstOrFail();
        $inactiveInternal->forceFill(['status' => 'inactive'])->save();

        $this->actingAs($hr)
            ->get(route('hr.helpdesk-tickets.index'))
            ->assertOk()
            ->assertSee($hr->email)
            ->assertDontSee('sameer.bafna@partners.builder360.test')
            ->assertDontSee('farhan.shaikh@partners.builder360.test')
            ->assertDontSee('rohan.shah@example.test')
            ->assertDontSee($inactiveInternal->email);
    }

    public function test_helpdesk_assignment_rejects_external_portal_and_inactive_users_at_request_and_domain_boundaries(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();
        $inactiveInternal = User::where('email', 'amit.verma@builder360.test')->firstOrFail();
        $inactiveInternal->forceFill(['status' => 'inactive'])->save();
        $crossCompanyInternal = User::factory()->create([
            'company_id' => Company::where('code', 'B360P')->firstOrFail()->id,
            'role_id' => $hr->role_id,
            'name' => 'Outside HR User',
            'email' => 'outside.hr@builder360.test',
            'status' => 'active',
        ]);
        $employee = Employee::where('company_id', $hr->company_id)->firstOrFail();
        $ticket = app(CreateHrHelpdeskTicket::class)->execute(new HrCommandData([
            'employee_id' => $employee->id,
            'category' => 'policy',
            'priority' => 'normal',
            'subject' => 'Verify internal helpdesk assignee boundary',
            'description' => 'Only active internal users may be assigned to this ticket.',
        ], $hr));

        foreach ([$partner, $inactiveInternal, $crossCompanyInternal] as $invalidAssignee) {
            $this->actingAs($hr)
                ->patchJson(route('hr.helpdesk-tickets.assign', $ticket), [
                    'assigned_to_user_id' => $invalidAssignee->id,
                    'note' => 'This assignment must be rejected.',
                ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['assigned_to_user_id'])
                ->assertJsonPath('errors.assigned_to_user_id.0', 'The selected assignee must be an active internal user in the ticket company.');
        }

        foreach ([$partner, $crossCompanyInternal] as $invalidAssignee) {
            try {
                app(AssignHrHelpdeskTicket::class)->execute($ticket, new HrCommandData([
                    'assigned_to_user_id' => $invalidAssignee->id,
                    'note' => 'Domain bypass attempt must fail.',
                ], $hr));
                $this->fail('An unavailable user was assigned through the HR helpdesk domain action.');
            } catch (ValidationException $exception) {
                $this->assertSame(
                    'The selected assignee must be an active internal user in the ticket company.',
                    $exception->errors()['assigned_to_user_id'][0],
                );
            }
        }

        $ticket->refresh();
        $this->assertSame('open', $ticket->status);
        $this->assertNull($ticket->assigned_to_user_id);
    }

    public function test_helpdesk_workflow_rejects_assignment_and_resolution_after_resolution(): void
    {
        $this->seed();

        $employeeUser = User::where('email', 'amit.verma@builder360.test')->firstOrFail();
        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();

        $ticketId = $this->actingAs($employeeUser)
            ->postJson(route('hr.helpdesk-tickets.store'), [
                'employee_id' => $employee->id,
                'category' => 'policy',
                'priority' => 'medium',
                'subject' => 'Validate terminal helpdesk transitions',
                'description' => 'This ticket verifies that resolved work cannot be reassigned or resolved twice.',
            ])
            ->assertCreated()
            ->json('data.id');

        $ticket = HrHelpdeskTicket::findOrFail($ticketId);

        $this->actingAs($hr)
            ->patchJson(route('hr.helpdesk-tickets.assign', $ticket), [
                'assigned_to_user_id' => $hr->id,
                'note' => 'Valid initial assignment.',
            ])
            ->assertOk();

        $this->actingAs($hr)
            ->patchJson(route('hr.helpdesk-tickets.resolve', $ticket->fresh()), [
                'resolution_summary' => 'The policy question was reviewed and answered completely.',
            ])
            ->assertOk();

        $resolved = $ticket->fresh();
        $historyCount = count($resolved->workflow_history ?? []);

        $this->actingAs($hr)
            ->patchJson(route('hr.helpdesk-tickets.assign', $resolved), [
                'assigned_to_user_id' => $hr->id,
                'note' => 'Invalid reassignment after resolution.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ticket');

        $this->actingAs($hr)
            ->patchJson(route('hr.helpdesk-tickets.resolve', $resolved), [
                'resolution_summary' => 'This duplicate resolution must not be persisted.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ticket');

        $resolved->refresh();
        $this->assertSame('resolved', $resolved->status);
        $this->assertSame($historyCount, count($resolved->workflow_history ?? []));
    }

    public function test_helpdesk_scope_and_partner_denial(): void
    {
        $this->seed();

        $employeeUser = User::where('email', 'amit.verma@builder360.test')->firstOrFail();
        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();
        $otherEmployee = Employee::where('employee_code', 'EMP-0012')->firstOrFail();

        $this->actingAs($employeeUser)
            ->getJson(route('hr.helpdesk-tickets.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.ticket_number', 'HRT-1001');

        $this->actingAs($employeeUser)
            ->getJson(route('hr.helpdesk-tickets.index', ['employee_id' => $otherEmployee->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['employee_id']);

        $this->actingAs($partner)
            ->getJson(route('hr.helpdesk-tickets.index'))
            ->assertForbidden();
    }

    public function test_hr_loan_and_helpdesk_indexes_reject_unsupported_filters_and_accept_page(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();

        $this->actingAs($hr)
            ->getJson(route('hr.loans.index', ['page' => 1]))
            ->assertOk()
            ->assertJsonPath('meta.current_page', 1);

        $this->actingAs($hr)
            ->getJson(route('hr.loans.index', ['claim_type' => 'fuel']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['claim_type'])
            ->assertJsonPath('errors.claim_type.0', 'The selected filter is not available for this endpoint.');

        $this->actingAs($hr)
            ->getJson(route('hr.helpdesk-tickets.index', ['page' => 1]))
            ->assertOk()
            ->assertJsonPath('meta.current_page', 1);

        $this->actingAs($hr)
            ->getJson(route('hr.helpdesk-tickets.index', ['loan_type' => 'emergency']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['loan_type'])
            ->assertJsonPath('errors.loan_type.0', 'The selected filter is not available for this endpoint.');
    }

    private function assertValidationErrorFor(string $field, callable $callback): void
    {
        try {
            $callback();
            $this->fail("Expected a validation error for [{$field}].");
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }
}
