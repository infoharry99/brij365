<?php

namespace App\Services\Hr;

use App\Domain\Hr\Services\HrHelpdeskAssigneeCandidates;
use App\Models\Employee;
use App\Models\EmployeeAsset;
use App\Models\EmployeeLoan;
use App\Models\ExpenseClaim;
use App\Models\HrHelpdeskTicket;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Security\CompanyScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmployeeOperationsService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly CompanyScopeService $companyScope,
        private readonly HrHelpdeskAssigneeCandidates $helpdeskAssignees,
    )
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createAsset(array $data, User $actor, ?Request $request = null): EmployeeAsset
    {
        return DB::transaction(function () use ($data, $actor, $request): EmployeeAsset {
            $companyId = $this->companyIdForAssetCreation($data, $actor);

            $asset = EmployeeAsset::create([
                'company_id' => $companyId,
                'asset_code' => $data['asset_code'],
                'category' => $data['category'],
                'name' => $data['name'],
                'serial_number' => $data['serial_number'] ?? null,
                'status' => 'available',
                'condition' => $data['condition'] ?? 'good',
                'estimated_value' => $data['estimated_value'] ?? 0,
                'metadata' => $data['metadata'] ?? [],
                'workflow_history' => [$this->workflowEvent('available', $actor, 'Asset registered')],
            ]);

            $this->auditLogger->record(
                $actor,
                'hr.asset.created',
                'Registered employee asset',
                $asset,
                ['asset_code' => $asset->asset_code, 'category' => $asset->category],
                $request,
            );

            return $asset->load($this->assetRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function assignAsset(EmployeeAsset $employeeAsset, array $data, User $actor, ?Request $request = null): EmployeeAsset
    {
        return DB::transaction(function () use ($employeeAsset, $data, $actor, $request): EmployeeAsset {
            $asset = EmployeeAsset::query()->whereKey($employeeAsset->id)->lockForUpdate()->firstOrFail();
            $employee = Employee::query()->whereKey($data['employee_id'])->firstOrFail();
            $this->assertCompanyScope($actor, $asset->company_id, 'asset');

            if ($asset->status !== 'available') {
                throw ValidationException::withMessages(['asset' => 'Only available assets can be assigned.']);
            }

            if ((int) $asset->company_id !== (int) $employee->company_id) {
                throw ValidationException::withMessages(['employee_id' => 'The employee must belong to the same company as the asset.']);
            }

            $asset->forceFill([
                'employee_id' => $employee->id,
                'assigned_by_user_id' => $actor->id,
                'recovered_by_user_id' => null,
                'status' => 'assigned',
                'assigned_on' => $data['assigned_on'] ?? now()->toDateString(),
                'recovered_on' => null,
                'workflow_history' => $this->appendWorkflow($asset, 'assigned', $actor, $data['note'] ?? 'Asset assigned to employee'),
            ])->save();

            $this->auditLogger->record(
                $actor,
                'hr.asset.assigned',
                'Assigned employee asset',
                $asset,
                ['asset_code' => $asset->asset_code, 'employee_code' => $employee->employee_code],
                $request,
            );

            return $asset->load($this->assetRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function recoverAsset(EmployeeAsset $employeeAsset, array $data, User $actor, ?Request $request = null): EmployeeAsset
    {
        return DB::transaction(function () use ($employeeAsset, $data, $actor, $request): EmployeeAsset {
            $asset = EmployeeAsset::query()->whereKey($employeeAsset->id)->lockForUpdate()->firstOrFail();
            $this->assertCompanyScope($actor, $asset->company_id, 'asset');

            if ($asset->status !== 'assigned') {
                throw ValidationException::withMessages(['asset' => 'Only assigned assets can be recovered.']);
            }

            $status = $data['status'] ?? 'recovered';

            $asset->forceFill([
                'recovered_by_user_id' => $actor->id,
                'status' => $status,
                'condition' => $data['condition'],
                'recovered_on' => $data['recovered_on'] ?? now()->toDateString(),
                'workflow_history' => $this->appendWorkflow($asset, $status, $actor, $data['note'] ?? 'Asset recovered from employee'),
            ])->save();

            $this->auditLogger->record(
                $actor,
                'hr.asset.recovered',
                'Recovered employee asset',
                $asset,
                ['asset_code' => $asset->asset_code, 'status' => $asset->status, 'condition' => $asset->condition],
                $request,
            );

            return $asset->load($this->assetRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function submitClaim(array $data, User $actor, ?Request $request = null): ExpenseClaim
    {
        return DB::transaction(function () use ($data, $actor, $request): ExpenseClaim {
            $employee = Employee::query()->whereKey($data['employee_id'])->firstOrFail();
            $this->assertCompanyScope($actor, $employee->company_id, 'employee_id');

            $claim = ExpenseClaim::create([
                'company_id' => $employee->company_id,
                'employee_id' => $employee->id,
                'requested_by_user_id' => $actor->id,
                'claim_number' => $this->nextClaimNumber(),
                'claim_type' => $data['claim_type'],
                'status' => 'submitted',
                'claim_date' => $data['claim_date'],
                'amount' => $data['amount'],
                'approved_amount' => 0,
                'currency' => $data['currency'] ?? 'INR',
                'description' => $data['description'],
                'attachments' => $data['attachments'] ?? [],
                'workflow_history' => [$this->workflowEvent('submitted', $actor, 'Expense claim submitted')],
            ]);

            $this->auditLogger->record(
                $actor,
                'hr.claim.submitted',
                'Submitted expense claim',
                $claim,
                ['claim_number' => $claim->claim_number, 'employee_code' => $employee->employee_code, 'amount' => $claim->amount],
                $request,
            );

            return $claim->load($this->claimRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function approveClaim(ExpenseClaim $expenseClaim, array $data, User $actor, ?Request $request = null): ExpenseClaim
    {
        return DB::transaction(function () use ($expenseClaim, $data, $actor, $request): ExpenseClaim {
            $claim = ExpenseClaim::query()->whereKey($expenseClaim->id)->lockForUpdate()->firstOrFail();
            $this->assertCompanyScope($actor, $claim->company_id, 'claim');

            if ($claim->status !== 'submitted') {
                throw ValidationException::withMessages(['claim' => 'Only submitted expense claims can be approved.']);
            }

            if ((float) $data['approved_amount'] > (float) $claim->amount) {
                throw ValidationException::withMessages(['approved_amount' => 'Approved amount cannot exceed claimed amount.']);
            }

            $claim->forceFill([
                'approved_by_user_id' => $actor->id,
                'status' => 'approved',
                'approved_amount' => $data['approved_amount'],
                'decision_note' => $data['decision_note'] ?? null,
                'approved_at' => now(),
                'workflow_history' => $this->appendWorkflow($claim, 'approved', $actor, $data['decision_note'] ?? 'Expense claim approved'),
            ])->save();

            $this->auditLogger->record(
                $actor,
                'hr.claim.approved',
                'Approved expense claim',
                $claim,
                ['claim_number' => $claim->claim_number, 'approved_amount' => $claim->approved_amount],
                $request,
            );

            return $claim->load($this->claimRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function rejectClaim(ExpenseClaim $expenseClaim, array $data, User $actor, ?Request $request = null): ExpenseClaim
    {
        return DB::transaction(function () use ($expenseClaim, $data, $actor, $request): ExpenseClaim {
            $claim = ExpenseClaim::query()->whereKey($expenseClaim->id)->lockForUpdate()->firstOrFail();
            $this->assertCompanyScope($actor, $claim->company_id, 'claim');

            if ($claim->status !== 'submitted') {
                throw ValidationException::withMessages(['claim' => 'Only submitted expense claims can be rejected.']);
            }

            $claim->forceFill([
                'approved_by_user_id' => $actor->id,
                'status' => 'rejected',
                'approved_amount' => 0,
                'decision_note' => $data['decision_note'],
                'approved_at' => now(),
                'workflow_history' => $this->appendWorkflow($claim, 'rejected', $actor, $data['decision_note']),
            ])->save();

            $this->auditLogger->record(
                $actor,
                'hr.claim.rejected',
                'Rejected expense claim',
                $claim,
                ['claim_number' => $claim->claim_number],
                $request,
            );

            return $claim->load($this->claimRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function payClaim(ExpenseClaim $expenseClaim, array $data, User $actor, ?Request $request = null): ExpenseClaim
    {
        return DB::transaction(function () use ($expenseClaim, $data, $actor, $request): ExpenseClaim {
            $claim = ExpenseClaim::query()->whereKey($expenseClaim->id)->lockForUpdate()->firstOrFail();
            $this->assertCompanyScope($actor, $claim->company_id, 'claim');

            if ($claim->status !== 'approved') {
                throw ValidationException::withMessages(['claim' => 'Only approved expense claims can be marked paid.']);
            }

            $claim->forceFill([
                'paid_by_user_id' => $actor->id,
                'status' => 'paid',
                'paid_at' => now(),
                'workflow_history' => $this->appendWorkflow($claim, 'paid', $actor, $data['note'] ?? 'Expense claim paid'),
            ])->save();

            $this->auditLogger->record(
                $actor,
                'hr.claim.paid',
                'Marked expense claim as paid',
                $claim,
                ['claim_number' => $claim->claim_number, 'payment_reference' => $data['payment_reference'] ?? null],
                $request,
            );

            return $claim->load($this->claimRelations());
        });
    }

    public function submitLoan(array $data, User $actor, ?Request $request = null): EmployeeLoan
    {
        return DB::transaction(function () use ($data, $actor, $request): EmployeeLoan {
            $employee = Employee::query()->whereKey($data['employee_id'])->firstOrFail();
            $this->assertCompanyScope($actor, $employee->company_id, 'employee_id');

            $loan = EmployeeLoan::create([
                'company_id' => $employee->company_id,
                'employee_id' => $employee->id,
                'requested_by_user_id' => $actor->id,
                'loan_number' => sprintf('LOAN-%04d', EmployeeLoan::query()->withTrashed()->count() + 1001),
                'loan_type' => $data['loan_type'],
                'status' => 'submitted',
                'principal_amount' => $data['principal_amount'],
                'installment_months' => $data['installment_months'],
                'requested_on' => $data['requested_on'] ?? now()->toDateString(),
                'purpose' => $data['purpose'],
                'workflow_history' => [$this->workflowEvent('submitted', $actor, 'Loan request submitted')],
            ]);
            $this->auditLogger->record($actor, 'hr.loan.submitted', 'Submitted employee loan request', $loan, ['loan_number' => $loan->loan_number], $request);
            return $loan->load($this->loanRelations());
        });
    }

    public function approveLoan(EmployeeLoan $employeeLoan, array $data, User $actor, ?Request $request = null): EmployeeLoan
    {
        return DB::transaction(function () use ($employeeLoan, $data, $actor, $request): EmployeeLoan {
            $loan = EmployeeLoan::query()->whereKey($employeeLoan->id)->lockForUpdate()->firstOrFail();
            $this->assertCompanyScope($actor, $loan->company_id, 'loan');

            if ($loan->status !== 'submitted') {
                throw ValidationException::withMessages(['loan' => 'Only submitted employee loans can be approved.']);
            }

            if ((float) $data['approved_amount'] > (float) $loan->principal_amount) {
                throw ValidationException::withMessages(['approved_amount' => 'Approved amount cannot exceed requested principal.']);
            }
            $loan->forceFill([
                'approved_by_user_id' => $actor->id,
                'status' => 'approved',
                'approved_amount' => $data['approved_amount'],
                'monthly_installment' => round((float) $data['approved_amount'] / max((int) $loan->installment_months, 1), 2),
                'repayment_starts_on' => $data['repayment_starts_on'],
                'decision_note' => $data['decision_note'] ?? null,
                'approved_at' => now(),
                'workflow_history' => $this->appendWorkflow($loan, 'approved', $actor, $data['decision_note'] ?? 'Loan approved'),
            ])->save();
            $this->auditLogger->record($actor, 'hr.loan.approved', 'Approved employee loan request', $loan, ['loan_number' => $loan->loan_number], $request);
            return $loan->load($this->loanRelations());
        });
    }

    public function rejectLoan(EmployeeLoan $employeeLoan, array $data, User $actor, ?Request $request = null): EmployeeLoan
    {
        return DB::transaction(function () use ($employeeLoan, $data, $actor, $request): EmployeeLoan {
            $loan = EmployeeLoan::query()->whereKey($employeeLoan->id)->lockForUpdate()->firstOrFail();
            $this->assertCompanyScope($actor, $loan->company_id, 'loan');

            if ($loan->status !== 'submitted') {
                throw ValidationException::withMessages(['loan' => 'Only submitted employee loans can be rejected.']);
            }

            $loan->forceFill([
                'approved_by_user_id' => $actor->id,
                'status' => 'rejected',
                'decision_note' => $data['decision_note'],
                'approved_at' => now(),
                'workflow_history' => $this->appendWorkflow($loan, 'rejected', $actor, $data['decision_note']),
            ])->save();
            $this->auditLogger->record($actor, 'hr.loan.rejected', 'Rejected employee loan request', $loan, ['loan_number' => $loan->loan_number], $request);
            return $loan->load($this->loanRelations());
        });
    }

    public function disburseLoan(EmployeeLoan $employeeLoan, array $data, User $actor, ?Request $request = null): EmployeeLoan
    {
        return DB::transaction(function () use ($employeeLoan, $data, $actor, $request): EmployeeLoan {
            $loan = EmployeeLoan::query()->whereKey($employeeLoan->id)->lockForUpdate()->firstOrFail();
            $this->assertCompanyScope($actor, $loan->company_id, 'loan');

            if ($loan->status !== 'approved') {
                throw ValidationException::withMessages(['loan' => 'Only approved employee loans can be disbursed.']);
            }

            $loan->forceFill([
                'disbursed_by_user_id' => $actor->id,
                'status' => 'disbursed',
                'disbursed_at' => now(),
                'workflow_history' => $this->appendWorkflow($loan, 'disbursed', $actor, $data['note'] ?? 'Loan disbursed'),
            ])->save();
            $this->auditLogger->record($actor, 'hr.loan.disbursed', 'Disbursed employee loan', $loan, ['loan_number' => $loan->loan_number, 'payment_reference' => $data['payment_reference'] ?? null], $request);
            return $loan->load($this->loanRelations());
        });
    }

    public function createHelpdeskTicket(array $data, User $actor, ?Request $request = null): HrHelpdeskTicket
    {
        return DB::transaction(function () use ($data, $actor, $request): HrHelpdeskTicket {
            $employee = Employee::query()->whereKey($data['employee_id'])->firstOrFail();
            $this->assertCompanyScope($actor, $employee->company_id, 'employee_id');

            $ticket = HrHelpdeskTicket::create([
                'company_id' => $employee->company_id,
                'employee_id' => $employee->id,
                'raised_by_user_id' => $actor->id,
                'ticket_number' => sprintf('HRT-%04d', HrHelpdeskTicket::query()->withTrashed()->count() + 1001),
                'category' => $data['category'],
                'priority' => $data['priority'],
                'status' => 'open',
                'subject' => $data['subject'],
                'description' => $data['description'],
                'attachments' => $data['attachments'] ?? [],
                'workflow_history' => [$this->workflowEvent('open', $actor, 'HR helpdesk ticket raised')],
            ]);
            $this->auditLogger->record($actor, 'hr.helpdesk.created', 'Created HR helpdesk ticket', $ticket, ['ticket_number' => $ticket->ticket_number], $request);
            return $ticket->load($this->helpdeskRelations());
        });
    }

    public function assignHelpdeskTicket(HrHelpdeskTicket $hrHelpdeskTicket, array $data, User $actor, ?Request $request = null): HrHelpdeskTicket
    {
        return DB::transaction(function () use ($hrHelpdeskTicket, $data, $actor, $request): HrHelpdeskTicket {
            $ticket = HrHelpdeskTicket::query()->whereKey($hrHelpdeskTicket->id)->lockForUpdate()->firstOrFail();
            $this->assertCompanyScope($actor, $ticket->company_id, 'ticket');
            $assignee = User::query()->find($data['assigned_to_user_id']);

            if (! $assignee) {
                throw ValidationException::withMessages([
                    'assigned_to_user_id' => 'The selected assignee is not available.',
                ]);
            }

            $this->helpdeskAssignees->assertEligible($actor, $ticket, $assignee);

            if (! in_array($ticket->status, ['open', 'assigned'], true)) {
                throw ValidationException::withMessages(['ticket' => 'Only open or assigned HR helpdesk tickets can be assigned.']);
            }

            $ticket->forceFill([
                'assigned_to_user_id' => $assignee->id,
                'status' => 'assigned',
                'workflow_history' => $this->appendWorkflow($ticket, 'assigned', $actor, $data['note'] ?? 'Ticket assigned'),
            ])->save();
            $this->auditLogger->record($actor, 'hr.helpdesk.assigned', 'Assigned HR helpdesk ticket', $ticket, ['ticket_number' => $ticket->ticket_number], $request);
            return $ticket->load($this->helpdeskRelations());
        });
    }

    public function resolveHelpdeskTicket(HrHelpdeskTicket $hrHelpdeskTicket, array $data, User $actor, ?Request $request = null): HrHelpdeskTicket
    {
        return DB::transaction(function () use ($hrHelpdeskTicket, $data, $actor, $request): HrHelpdeskTicket {
            $ticket = HrHelpdeskTicket::query()->whereKey($hrHelpdeskTicket->id)->lockForUpdate()->firstOrFail();
            $this->assertCompanyScope($actor, $ticket->company_id, 'ticket');

            if (! in_array($ticket->status, ['open', 'assigned'], true)) {
                throw ValidationException::withMessages(['ticket' => 'Only open or assigned HR helpdesk tickets can be resolved.']);
            }

            $ticket->forceFill([
                'status' => 'resolved',
                'resolution_summary' => $data['resolution_summary'],
                'resolved_at' => now(),
                'workflow_history' => $this->appendWorkflow($ticket, 'resolved', $actor, $data['resolution_summary']),
            ])->save();
            $this->auditLogger->record($actor, 'hr.helpdesk.resolved', 'Resolved HR helpdesk ticket', $ticket, ['ticket_number' => $ticket->ticket_number], $request);
            return $ticket->load($this->helpdeskRelations());
        });
    }

    public function closeHelpdeskTicket(HrHelpdeskTicket $hrHelpdeskTicket, array $data, User $actor, ?Request $request = null): HrHelpdeskTicket
    {
        return DB::transaction(function () use ($hrHelpdeskTicket, $data, $actor, $request): HrHelpdeskTicket {
            $ticket = HrHelpdeskTicket::query()->whereKey($hrHelpdeskTicket->id)->lockForUpdate()->firstOrFail();
            $this->assertCompanyScope($actor, $ticket->company_id, 'ticket');

            if ($ticket->status !== 'resolved') {
                throw ValidationException::withMessages(['ticket' => 'Only resolved HR helpdesk tickets can be closed.']);
            }

            $ticket->forceFill([
                'closed_by_user_id' => $actor->id,
                'status' => 'closed',
                'closed_at' => now(),
                'workflow_history' => $this->appendWorkflow($ticket, 'closed', $actor, $data['note'] ?? 'Ticket closed'),
            ])->save();
            $this->auditLogger->record($actor, 'hr.helpdesk.closed', 'Closed HR helpdesk ticket', $ticket, ['ticket_number' => $ticket->ticket_number], $request);
            return $ticket->load($this->helpdeskRelations());
        });
    }

    /**
     * @return array<int, string>
     */
    public function assetRelations(): array
    {
        return ['company', 'employee', 'assignedBy', 'recoveredBy'];
    }

    /**
     * @return array<int, string>
     */
    public function claimRelations(): array
    {
        return ['company', 'employee', 'requestedBy', 'approvedBy', 'paidBy'];
    }

    public function loanRelations(): array
    {
        return ['company', 'employee', 'requestedBy', 'approvedBy', 'disbursedBy'];
    }

    public function helpdeskRelations(): array
    {
        return ['company', 'employee', 'raisedBy', 'assignedTo', 'closedBy'];
    }

    /**
     * @return array<string, mixed>
     */
    private function workflowEvent(string $status, User $actor, string $note): array
    {
        return [
            'status' => $status,
            'actor_user_id' => $actor->id,
            'actor' => $actor->name,
            'note' => $note,
            'at' => now()->toISOString(),
        ];
    }

    private function appendWorkflow(EmployeeAsset|ExpenseClaim|EmployeeLoan|HrHelpdeskTicket $record, string $status, User $actor, string $note): array
    {
        $history = $record->workflow_history ?? [];
        $history[] = $this->workflowEvent($status, $actor, $note);

        return $history;
    }

    private function nextClaimNumber(): string
    {
        return sprintf('CLM-%04d', ExpenseClaim::query()->withTrashed()->count() + 1001);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function companyIdForAssetCreation(array $data, User $actor): int
    {
        $companyId = $data['company_id'] ?? $this->companyScope->companyIdFor($actor);

        if ($companyId === null || $companyId === 0) {
            throw ValidationException::withMessages([
                'company_id' => 'A company assignment is required before creating employee assets.',
            ]);
        }

        if (! $this->companyScope->allows($actor, $companyId)) {
            throw ValidationException::withMessages([
                'company_id' => 'The selected company is outside your company scope.',
            ]);
        }

        return (int) $companyId;
    }

    private function assertCompanyScope(User $actor, int|string|null $companyId, string $field): void
    {
        if ($this->companyScope->allows($actor, $companyId)) {
            return;
        }

        throw ValidationException::withMessages([
            $field => 'The selected record is outside your company scope.',
        ]);
    }
}
