<?php

namespace App\Domain\Hr\Services;

use App\Application\Hr\Data\EmployeeAssetRowData;
use App\Application\Hr\Data\EmployeeAssetSummaryData;
use App\Application\Hr\Data\EmployeeLoanRowData;
use App\Application\Hr\Data\EmployeeLoanSummaryData;
use App\Application\Hr\Data\ExpenseClaimRowData;
use App\Application\Hr\Data\ExpenseClaimSummaryData;
use App\Application\Hr\Data\HrHelpdeskSummaryData;
use App\Application\Hr\Data\HrHelpdeskTicketRowData;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeAsset;
use App\Models\EmployeeLoan;
use App\Models\ExpenseClaim;
use App\Models\HrHelpdeskTicket;
use App\Models\User;
use App\Services\Hr\EmployeeOperationsService;
use App\Services\Security\CompanyScopeService;
use App\Support\PaginationPolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class EmployeeOperationsRegister
{
    public function __construct(
        private readonly CompanyScopeService $scope,
        private readonly PaginationPolicy $pagination,
        private readonly EmployeeOperationsService $operations,
        private readonly HrHelpdeskAssigneeCandidates $helpdeskAssignees,
    ) {}

    public function assets(User $actor, array $filters = [], string $page = 'page'): LengthAwarePaginator
    {
        return $this->assetQuery($actor, $filters)
            ->with($this->operations->assetRelations())
            ->orderBy('asset_code')
            ->paginate($this->perPage($filters), ['*'], $page);
    }

    public function claims(User $actor, array $filters = [], string $page = 'page'): LengthAwarePaginator
    {
        return $this->claimQuery($actor, $filters)
            ->with($this->operations->claimRelations())
            ->latest('claim_date')->latest()->paginate($this->perPage($filters), ['*'], $page);
    }

    public function loans(User $actor, array $filters = [], string $page = 'page'): LengthAwarePaginator
    {
        return $this->loanQuery($actor, $filters)
            ->with($this->operations->loanRelations())
            ->latest('requested_on')->paginate($this->perPage($filters), ['*'], $page);
    }

    public function assetSummary(User $actor, array $filters = []): EmployeeAssetSummaryData
    {
        unset($filters['status'], $filters['page'], $filters['per_page']);

        $summary = $this->assetQuery($actor, $filters)
            ->selectRaw('COUNT(*) AS aggregate_total')
            ->selectRaw("SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) AS aggregate_available")
            ->selectRaw("SUM(CASE WHEN status = 'assigned' THEN 1 ELSE 0 END) AS aggregate_assigned")
            ->selectRaw("SUM(CASE WHEN status = 'recovered' THEN 1 ELSE 0 END) AS aggregate_recovered")
            ->selectRaw("SUM(CASE WHEN status = 'retired' THEN 1 ELSE 0 END) AS aggregate_retired")
            ->selectRaw("SUM(CASE WHEN status = 'lost' THEN 1 ELSE 0 END) AS aggregate_lost")
            ->first();

        return new EmployeeAssetSummaryData(
            total: (int) ($summary?->aggregate_total ?? 0),
            available: (int) ($summary?->aggregate_available ?? 0),
            assigned: (int) ($summary?->aggregate_assigned ?? 0),
            recovered: (int) ($summary?->aggregate_recovered ?? 0),
            retired: (int) ($summary?->aggregate_retired ?? 0),
            lost: (int) ($summary?->aggregate_lost ?? 0),
        );
    }

    public function helpdeskSummary(User $actor, array $filters = []): HrHelpdeskSummaryData
    {
        unset($filters['status'], $filters['page'], $filters['per_page']);

        $summary = $this->helpdeskQuery($actor, $filters)
            ->selectRaw('COUNT(*) AS aggregate_total')
            ->selectRaw("SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) AS aggregate_open")
            ->selectRaw("SUM(CASE WHEN status = 'assigned' THEN 1 ELSE 0 END) AS aggregate_assigned")
            ->selectRaw("SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) AS aggregate_resolved")
            ->selectRaw("SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) AS aggregate_closed")
            ->selectRaw("SUM(CASE WHEN priority = 'critical' THEN 1 ELSE 0 END) AS aggregate_critical")
            ->first();

        return new HrHelpdeskSummaryData(
            total: (int) ($summary?->aggregate_total ?? 0),
            open: (int) ($summary?->aggregate_open ?? 0),
            assigned: (int) ($summary?->aggregate_assigned ?? 0),
            resolved: (int) ($summary?->aggregate_resolved ?? 0),
            closed: (int) ($summary?->aggregate_closed ?? 0),
            critical: (int) ($summary?->aggregate_critical ?? 0),
        );
    }

    public function claimSummary(User $actor, array $filters = []): ExpenseClaimSummaryData
    {
        unset($filters['status'], $filters['page'], $filters['per_page']);

        $summary = $this->claimQuery($actor, $filters)
            ->selectRaw('COUNT(*) AS aggregate_total')
            ->selectRaw("SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) AS aggregate_submitted")
            ->selectRaw("SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS aggregate_approved")
            ->selectRaw("SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS aggregate_rejected")
            ->selectRaw("SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) AS aggregate_paid")
            ->selectRaw('COALESCE(SUM(amount), 0) AS aggregate_claimed_amount')
            ->selectRaw('COALESCE(SUM(approved_amount), 0) AS aggregate_approved_amount')
            ->first();

        return new ExpenseClaimSummaryData(
            total: (int) ($summary?->aggregate_total ?? 0),
            submitted: (int) ($summary?->aggregate_submitted ?? 0),
            approved: (int) ($summary?->aggregate_approved ?? 0),
            rejected: (int) ($summary?->aggregate_rejected ?? 0),
            paid: (int) ($summary?->aggregate_paid ?? 0),
            claimedAmount: $this->money($summary?->aggregate_claimed_amount),
            approvedAmount: $this->money($summary?->aggregate_approved_amount),
        );
    }

    public function loanSummary(User $actor, array $filters = []): EmployeeLoanSummaryData
    {
        unset($filters['status'], $filters['page'], $filters['per_page']);

        $summary = $this->loanQuery($actor, $filters)
            ->selectRaw('COUNT(*) AS aggregate_total')
            ->selectRaw("SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) AS aggregate_submitted")
            ->selectRaw("SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS aggregate_approved")
            ->selectRaw("SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS aggregate_rejected")
            ->selectRaw("SUM(CASE WHEN status = 'disbursed' THEN 1 ELSE 0 END) AS aggregate_disbursed")
            ->selectRaw("SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) AS aggregate_closed")
            ->selectRaw('COALESCE(SUM(principal_amount), 0) AS aggregate_requested_amount')
            ->selectRaw('COALESCE(SUM(approved_amount), 0) AS aggregate_approved_amount')
            ->first();

        return new EmployeeLoanSummaryData(
            total: (int) ($summary?->aggregate_total ?? 0),
            submitted: (int) ($summary?->aggregate_submitted ?? 0),
            approved: (int) ($summary?->aggregate_approved ?? 0),
            rejected: (int) ($summary?->aggregate_rejected ?? 0),
            disbursed: (int) ($summary?->aggregate_disbursed ?? 0),
            closed: (int) ($summary?->aggregate_closed ?? 0),
            requestedAmount: $this->money($summary?->aggregate_requested_amount),
            approvedAmount: $this->money($summary?->aggregate_approved_amount),
        );
    }

    public function presentAssets(User $actor, LengthAwarePaginator $assets): LengthAwarePaginator
    {
        $timezone = (string) config('app.timezone', 'Asia/Kolkata');

        return $assets->through(function (EmployeeAsset $asset) use ($actor, $timezone): EmployeeAssetRowData {
            $workflow = collect($asset->workflow_history ?? [])->last();
            $employeeName = $asset->employee?->name ?? 'Unassigned';

            return new EmployeeAssetRowData(
                id: $asset->id,
                assetCode: $asset->asset_code,
                category: $asset->category,
                name: $asset->name,
                serialNumber: $asset->serial_number ?: 'Not recorded',
                status: $asset->status,
                statusLabel: str($asset->status)->replace('_', ' ')->title()->toString(),
                statusTone: $this->assetStatusTone($asset->status),
                condition: $asset->condition,
                conditionLabel: str($asset->condition)->replace('_', ' ')->title()->toString(),
                conditionTone: $this->assetConditionTone($asset->condition),
                employeeName: $employeeName,
                employeeCode: $asset->employee?->employee_code ?? 'No custodian',
                employeeInitial: $asset->employee ? $this->initial($employeeName) : '—',
                employeeContext: $asset->employee?->department ?: 'Available inventory',
                assignedOn: $asset->assigned_on?->format('d M Y') ?? 'Not assigned',
                recoveredOn: $asset->recovered_on?->format('d M Y') ?? 'Not recovered',
                estimatedValue: $this->money($asset->estimated_value),
                workflowNote: (string) data_get($workflow, 'note', 'No workflow note recorded.'),
                workflowActor: (string) data_get($workflow, 'actor', 'System'),
                workflowAt: $this->workflowTimestamp(data_get($workflow, 'at'), $timezone),
                canAssign: $actor->can('assign', $asset),
                canRecover: $actor->can('recover', $asset),
            );
        });
    }

    public function presentClaims(User $actor, LengthAwarePaginator $claims): LengthAwarePaginator
    {
        $timezone = (string) config('app.timezone', 'Asia/Kolkata');

        return $claims->through(function (ExpenseClaim $claim) use ($actor, $timezone): ExpenseClaimRowData {
            $workflow = collect($claim->workflow_history ?? [])->last();
            $attachments = $this->attachmentNames($claim->attachments ?? []);

            return new ExpenseClaimRowData(
                id: $claim->id,
                claimNumber: $claim->claim_number,
                employeeCode: $claim->employee?->employee_code ?? 'No employee code',
                employeeName: $claim->employee?->name ?? 'Employee unavailable',
                employeeInitial: $this->initial($claim->employee?->name),
                employeeContext: $claim->employee?->department ?: 'No department',
                claimType: $claim->claim_type,
                claimTypeLabel: $this->claimTypeLabel($claim->claim_type),
                claimDate: $claim->claim_date?->format('d M Y') ?? 'Date unavailable',
                description: $claim->description,
                claimedAmount: $this->money($claim->amount, $claim->currency),
                approvedAmount: $this->money($claim->approved_amount, $claim->currency),
                approvalAmountInput: number_format((float) $claim->amount, 2, '.', ''),
                status: $claim->status,
                statusLabel: ucfirst($claim->status),
                statusTone: $this->statusTone($claim->status),
                workflowNote: (string) data_get($workflow, 'note', 'No workflow note recorded.'),
                workflowActor: (string) data_get($workflow, 'actor', 'System'),
                workflowAt: $this->workflowTimestamp(data_get($workflow, 'at'), $timezone),
                attachmentCount: count($attachments),
                attachmentNames: $attachments,
                canApprove: $actor->can('approve', $claim),
                canReject: $actor->can('reject', $claim),
                canPay: $actor->can('pay', $claim),
            );
        });
    }

    public function presentLoans(User $actor, LengthAwarePaginator $loans): LengthAwarePaginator
    {
        $timezone = (string) config('app.timezone', 'Asia/Kolkata');

        return $loans->through(function (EmployeeLoan $loan) use ($actor, $timezone): EmployeeLoanRowData {
            $workflow = collect($loan->workflow_history ?? [])->last();

            return new EmployeeLoanRowData(
                id: $loan->id,
                loanNumber: $loan->loan_number,
                employeeCode: $loan->employee?->employee_code ?? 'No employee code',
                employeeName: $loan->employee?->name ?? 'Employee unavailable',
                employeeInitial: $this->initial($loan->employee?->name),
                employeeContext: $loan->employee?->department ?: 'No department',
                loanType: $loan->loan_type,
                loanTypeLabel: $this->loanTypeLabel($loan->loan_type),
                requestedOn: $loan->requested_on?->format('d M Y') ?? 'Date unavailable',
                purpose: $loan->purpose,
                principalAmount: $this->money($loan->principal_amount),
                approvedAmount: $this->money($loan->approved_amount),
                approvalAmountInput: number_format((float) $loan->principal_amount, 2, '.', ''),
                installmentMonths: (int) $loan->installment_months,
                monthlyInstallment: $this->money($loan->monthly_installment),
                repaymentStartsOn: $loan->repayment_starts_on?->format('d M Y') ?? 'Not scheduled',
                repaymentStartsOnInput: $loan->repayment_starts_on?->toDateString() ?? now()->addMonthNoOverflow()->startOfMonth()->toDateString(),
                status: $loan->status,
                statusLabel: ucfirst($loan->status),
                statusTone: $this->statusTone($loan->status),
                workflowNote: (string) data_get($workflow, 'note', 'No workflow note recorded.'),
                workflowActor: (string) data_get($workflow, 'actor', 'System'),
                workflowAt: $this->workflowTimestamp(data_get($workflow, 'at'), $timezone),
                canApprove: $actor->can('approve', $loan),
                canReject: $actor->can('reject', $loan),
                canDisburse: $actor->can('disburse', $loan),
            );
        });
    }

    public function presentHelpdesk(User $actor, LengthAwarePaginator $tickets): LengthAwarePaginator
    {
        $timezone = (string) config('app.timezone', 'Asia/Kolkata');

        return $tickets->through(function (HrHelpdeskTicket $ticket) use ($actor, $timezone): HrHelpdeskTicketRowData {
            $workflow = collect($ticket->workflow_history ?? [])->last();
            $attachments = $this->attachmentNames($ticket->attachments ?? []);

            return new HrHelpdeskTicketRowData(
                id: $ticket->id,
                ticketNumber: $ticket->ticket_number,
                subject: $ticket->subject,
                description: $ticket->description,
                employeeCode: $ticket->employee?->employee_code ?? 'No employee code',
                employeeName: $ticket->employee?->name ?? 'Employee unavailable',
                employeeInitial: $this->initial($ticket->employee?->name),
                employeeContext: $ticket->employee?->department ?: 'No department',
                category: $ticket->category,
                categoryLabel: str($ticket->category)->replace('_', ' ')->title()->toString(),
                priority: $ticket->priority,
                priorityLabel: str($ticket->priority)->title()->toString(),
                priorityTone: $this->helpdeskPriorityTone($ticket->priority),
                status: $ticket->status,
                statusLabel: str($ticket->status)->replace('_', ' ')->title()->toString(),
                statusTone: $this->helpdeskStatusTone($ticket->status),
                raisedBy: $ticket->raisedBy?->name ?? 'System',
                assignedTo: $ticket->assignedTo?->name ?? 'Unassigned',
                createdAt: $ticket->created_at?->timezone($timezone)->format('d M Y, h:i A') ?? 'Time unavailable',
                resolvedAt: $ticket->resolved_at?->timezone($timezone)->format('d M Y, h:i A') ?? 'Not resolved',
                closedAt: $ticket->closed_at?->timezone($timezone)->format('d M Y, h:i A') ?? 'Not closed',
                resolutionSummary: $ticket->resolution_summary ?: 'No resolution recorded.',
                attachmentCount: count($attachments),
                attachmentNames: $attachments,
                workflowNote: (string) data_get($workflow, 'note', 'No workflow note recorded.'),
                workflowActor: (string) data_get($workflow, 'actor', 'System'),
                workflowAt: $this->workflowTimestamp(data_get($workflow, 'at'), $timezone),
                canManage: in_array($ticket->status, ['open', 'assigned'], true) && $actor->can('manage', $ticket),
                canClose: $actor->can('close', $ticket),
            );
        });
    }

    public function tickets(User $actor, array $filters = [], string $page = 'page'): LengthAwarePaginator
    {
        return $this->helpdeskQuery($actor, $filters)
            ->with($this->operations->helpdeskRelations())
            ->orderByRaw("case when status in ('open', 'assigned') then 0 else 1 end")
            ->latest()
            ->paginate($this->perPage($filters), ['*'], $page);
    }

    public function employees(User $actor, string $operation): Collection
    {
        return $this->scope->apply(Employee::query(), $actor)
            ->when($this->isSelfServiceOnly($actor, $this->privilegedPermissions($operation)), fn ($query) => $query->where('user_id', $actor->id))
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'employee_code', 'name', 'department']);
    }

    public function assignees(User $actor): Collection
    {
        return $this->helpdeskAssignees->forActor($actor);
    }

    public function companies(User $actor): Collection
    {
        return $this->scope->apply(Company::query()->where('status', 'active')->orderBy('name'), $actor, 'id')->get(['id', 'code', 'name']);
    }

    public function abilities(User $actor): array
    {
        return [
            'canCreateAsset' => $actor->can('create', EmployeeAsset::class),
            'canCreateClaim' => $actor->can('create', ExpenseClaim::class),
            'canCreateLoan' => $actor->can('create', EmployeeLoan::class),
            'canCreateTicket' => $actor->can('create', HrHelpdeskTicket::class),
            'canApproveClaims' => $actor->hasPermission('claims.approve') || $actor->hasPermission('hr.manage'),
            'canPayClaims' => $actor->hasPermission('finance.approve'),
            'canApproveLoans' => $actor->hasPermission('loans.approve') || $actor->hasPermission('hr.manage'),
            'canDisburseLoans' => $actor->hasPermission('finance.approve'),
            'canManageHelpdesk' => $actor->hasPermission('helpdesk.manage') || $actor->hasPermission('hr.manage'),
        ];
    }

    private function assetQuery(User $actor, array $filters): Builder
    {
        return $this->scope->apply(EmployeeAsset::query(), $actor)
            ->when($this->isSelfServiceOnly($actor, $this->privilegedPermissions('assets')), fn (Builder $query) => $query->whereHas('employee', fn (Builder $employeeQuery) => $employeeQuery->where('user_id', $actor->id)))
            ->when(isset($filters['employee_id']), fn (Builder $query) => $query->where('employee_id', $filters['employee_id']))
            ->when(isset($filters['category']), fn (Builder $query) => $query->where('category', $filters['category']))
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(isset($filters['search']), function (Builder $query) use ($filters): void {
                $search = '%'.$filters['search'].'%';
                $query->where(fn (Builder $searchQuery) => $searchQuery
                    ->where('asset_code', 'like', $search)
                    ->orWhere('name', 'like', $search)
                    ->orWhere('serial_number', 'like', $search));
            });
    }

    private function claimQuery(User $actor, array $filters): Builder
    {
        return $this->scope->apply(ExpenseClaim::query(), $actor)
            ->when($this->isSelfServiceOnly($actor, $this->privilegedPermissions('claims')), fn (Builder $query) => $query->whereHas('employee', fn (Builder $employeeQuery) => $employeeQuery->where('user_id', $actor->id)))
            ->when(isset($filters['employee_id']), fn (Builder $query) => $query->where('employee_id', $filters['employee_id']))
            ->when(isset($filters['claim_type']), fn (Builder $query) => $query->where('claim_type', $filters['claim_type']))
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(isset($filters['date_from']), fn (Builder $query) => $query->whereDate('claim_date', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn (Builder $query) => $query->whereDate('claim_date', '<=', $filters['date_to']));
    }

    private function loanQuery(User $actor, array $filters): Builder
    {
        return $this->scope->apply(EmployeeLoan::query(), $actor)
            ->when($this->isSelfServiceOnly($actor, $this->privilegedPermissions('loans')), fn (Builder $query) => $query->whereHas('employee', fn (Builder $employeeQuery) => $employeeQuery->where('user_id', $actor->id)))
            ->when(isset($filters['employee_id']), fn (Builder $query) => $query->where('employee_id', $filters['employee_id']))
            ->when(isset($filters['loan_type']), fn (Builder $query) => $query->where('loan_type', $filters['loan_type']))
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']));
    }

    private function helpdeskQuery(User $actor, array $filters): Builder
    {
        return $this->scope->apply(HrHelpdeskTicket::query(), $actor)
            ->when($this->isSelfServiceOnly($actor, $this->privilegedPermissions('helpdesk')), fn (Builder $query) => $query->whereHas('employee', fn (Builder $employeeQuery) => $employeeQuery->where('user_id', $actor->id)))
            ->when(isset($filters['employee_id']), fn (Builder $query) => $query->where('employee_id', $filters['employee_id']))
            ->when(isset($filters['category']), fn (Builder $query) => $query->where('category', $filters['category']))
            ->when(isset($filters['priority']), fn (Builder $query) => $query->where('priority', $filters['priority']))
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']));
    }

    private function money(mixed $value, string $currency = 'INR'): string
    {
        return trim($currency).' '.number_format((float) ($value ?? 0), 2);
    }

    private function initial(?string $name): string
    {
        $name = trim((string) $name);

        return $name === '' ? '?' : mb_strtoupper(mb_substr($name, 0, 1));
    }

    private function claimTypeLabel(string $type): string
    {
        return match ($type) {
            'travel' => 'Travel',
            'food' => 'Food',
            'fuel' => 'Fuel',
            'mobile' => 'Mobile',
            'medical' => 'Medical',
            'office' => 'Office',
            'other' => 'Other',
            default => str($type)->replace('_', ' ')->title()->toString(),
        };
    }

    private function loanTypeLabel(string $type): string
    {
        return match ($type) {
            'salary_advance' => 'Salary Advance',
            'emergency' => 'Emergency',
            'welfare' => 'Welfare',
            'other' => 'Other',
            default => str($type)->replace('_', ' ')->title()->toString(),
        };
    }

    private function assetStatusTone(string $status): string
    {
        return match ($status) {
            'available', 'recovered' => 'success',
            'assigned' => 'info',
            'retired' => 'muted',
            'lost' => 'danger',
            default => 'muted',
        };
    }

    private function assetConditionTone(string $condition): string
    {
        return match ($condition) {
            'new' => 'success',
            'good' => 'info',
            'fair' => 'warning',
            'damaged' => 'danger',
            default => 'muted',
        };
    }

    private function helpdeskStatusTone(string $status): string
    {
        return match ($status) {
            'closed' => 'success',
            'resolved' => 'info',
            'assigned' => 'warning',
            default => 'muted',
        };
    }

    private function helpdeskPriorityTone(string $priority): string
    {
        return match ($priority) {
            'critical' => 'danger',
            'high' => 'warning',
            'medium' => 'info',
            default => 'muted',
        };
    }

    private function statusTone(string $status): string
    {
        return match ($status) {
            'paid', 'disbursed' => 'success',
            'approved' => 'info',
            'submitted' => 'warning',
            'rejected' => 'danger',
            default => 'muted',
        };
    }

    private function workflowTimestamp(mixed $value, string $timezone): string
    {
        if (! is_string($value) || trim($value) === '') {
            return 'Time unavailable';
        }

        try {
            return Carbon::parse($value)->timezone($timezone)->format('d M Y, h:i A');
        } catch (\Throwable) {
            return 'Time unavailable';
        }
    }

    /**
     * @param  array<int, mixed>  $attachments
     * @return array<int, string>
     */
    private function attachmentNames(array $attachments): array
    {
        return collect($attachments)
            ->map(function (mixed $attachment): ?string {
                $name = is_array($attachment)
                    ? data_get($attachment, 'name')
                    : (is_string($attachment) ? $attachment : null);

                if (! is_string($name) || trim($name) === '') {
                    return null;
                }

                $path = parse_url($name, PHP_URL_PATH);
                $label = basename(is_string($path) && $path !== '' ? $path : $name);

                return $label !== '' ? $label : 'Attachment available';
            })
            ->filter()
            ->values()
            ->all();
    }

    private function perPage(array $filters): int
    {
        return $this->pagination->defaultPerPage($filters['per_page'] ?? null);
    }

    /** @return array<int, string> */
    private function privilegedPermissions(string $operation): array
    {
        return match ($operation) {
            'assets' => ['assets.view', 'assets.manage', 'hr.manage'],
            'claims' => ['claims.view', 'claims.manage', 'claims.approve', 'finance.approve', 'hr.manage'],
            'loans' => ['loans.view', 'loans.manage', 'loans.approve', 'finance.approve', 'hr.manage'],
            'helpdesk' => ['helpdesk.view', 'helpdesk.manage', 'hr.manage'],
            default => [],
        };
    }

    /** @param array<int, string> $privilegedPermissions */
    private function isSelfServiceOnly(User $actor, array $privilegedPermissions): bool
    {
        if (! $actor->hasPermission('employee.self_service')) {
            return false;
        }

        foreach ($privilegedPermissions as $permission) {
            if ($actor->hasPermission($permission)) {
                return false;
            }
        }

        return true;
    }
}
