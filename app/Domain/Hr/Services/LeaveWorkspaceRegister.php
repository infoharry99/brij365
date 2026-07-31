<?php

namespace App\Domain\Hr\Services;

use App\Application\Hr\Data\LeaveBalanceRowData;
use App\Application\Hr\Data\LeaveEncashmentRowData;
use App\Application\Hr\Data\LeavePolicyRowData;
use App\Application\Hr\Data\LeaveProcessingLeaveTypeRuleData;
use App\Application\Hr\Data\LeaveProcessingLineItemData;
use App\Application\Hr\Data\LeaveProcessingRunRowData;
use App\Application\Hr\Data\LeaveProcessingRuleSnapshotData;
use App\Application\Hr\Data\LeaveRequestRowData;
use App\Application\Hr\Data\LeaveSummaryData;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeLeaveBalance;
use App\Models\LeaveEncashment;
use App\Models\LeaveProcessingRun;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\Hr\LeaveProcessingService;
use App\Services\Security\CompanyScopeService;
use App\Support\PaginationPolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class LeaveWorkspaceRegister
{
    public function __construct(private readonly CompanyScopeService $scope, private readonly PaginationPolicy $pagination, private readonly LeaveProcessingService $processing) {}

    public function types(User $u, array $f = [], string $page = 'page'): LengthAwarePaginator
    {
        return $this->scope->apply(LeaveType::query(), $u)->where('is_active', true)->orderBy('name')->paginate($this->pagination->largePerPage($f['per_page'] ?? null), ['*'], $page);
    }

    public function balances(User $u, array $f = [], string $page = 'page'): LengthAwarePaginator
    {
        return $this->scope->apply(EmployeeLeaveBalance::query()->with(['employee', 'leaveType']), $u)->when(! $this->canManage($u), fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('user_id', $u->id)))->when($f['employee_id'] ?? null, fn ($q, $v) => $q->where('employee_id', $v))->when($f['period_year'] ?? null, fn ($q, $v) => $q->where('period_year', $v))->orderBy('employee_id')->orderBy('leave_type_id')->paginate($this->pagination->defaultPerPage($f['per_page'] ?? null), ['*'], $page);
    }

    public function requests(User $u, array $f = [], string $page = 'page'): LengthAwarePaginator
    {
        return $this->requestQuery($u)
            ->with(['employee', 'leaveType', 'requestedBy', 'decidedBy', 'supportingDocument'])
            ->when($f['employee_id'] ?? null, fn ($q, $v) => $q->where('employee_id', $v))
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->latest()
            ->paginate($this->pagination->defaultPerPage($f['per_page'] ?? null), ['*'], $page);
    }

    public function processingRuns(User $u, array $f = [], string $page = 'page'): LengthAwarePaginator
    {
        return $this->scope->apply(LeaveProcessingRun::query()->with($this->processing->processingRelations()), $u)->when($f['period_year'] ?? null, fn ($q, $v) => $q->where('period_year', $v))->when($f['processing_type'] ?? null, fn ($q, $v) => $q->where('processing_type', $v))->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))->latest()->paginate($this->pagination->defaultPerPage($f['per_page'] ?? null), ['*'], $page);
    }

    public function encashments(User $u, array $f = [], string $page = 'page'): LengthAwarePaginator
    {
        return $this->encashmentQuery($u)
            ->with($this->processing->encashmentRelations())
            ->when($f['employee_id'] ?? null, fn ($q, $v) => $q->where('employee_id', $v))
            ->when($f['period_year'] ?? null, fn ($q, $v) => $q->where('period_year', $v))
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->latest()
            ->paginate($this->pagination->defaultPerPage($f['per_page'] ?? null), ['*'], $page);
    }

    public function summary(User $u): LeaveSummaryData
    {
        $today = now()->toDateString();
        $requestQuery = $this->requestQuery($u);

        return new LeaveSummaryData(
            pendingRequests: (clone $requestQuery)->where('status', 'submitted')->count(),
            onLeaveToday: (clone $requestQuery)
                ->where('status', 'approved')
                ->whereDate('starts_on', '<=', $today)
                ->whereDate('ends_on', '>=', $today)
                ->count(),
            upcoming: (clone $requestQuery)
                ->whereIn('status', ['submitted', 'approved'])
                ->whereDate('starts_on', '>', $today)
                ->count(),
            pendingEncashments: $u->can('viewAny', LeaveEncashment::class)
                ? $this->encashmentQuery($u)->where('status', 'submitted')->count()
                : 0,
        );
    }

    public function leaveTypeOptions(User $u): Collection
    {
        return $this->scope->apply(LeaveType::query(), $u)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'company_id', 'code', 'name']);
    }

    public function presentRequests(User $actor, LengthAwarePaginator $paginator): LengthAwarePaginator
    {
        return $paginator->through(fn (LeaveRequest $request) => new LeaveRequestRowData(
            id: $request->id,
            requestNumber: $request->request_number,
            employeeCode: $request->employee?->employee_code ?? 'Not available',
            employeeName: $request->employee?->name ?? 'Unknown employee',
            leaveTypeCode: $request->leaveType?->code ?? 'Not available',
            leaveTypeName: $request->leaveType?->name ?? 'Unknown leave type',
            dateRange: ($request->starts_on?->format('d M Y') ?? 'Not available').' to '.($request->ends_on?->format('d M Y') ?? 'Not available'),
            requestedDays: $this->decimalLabel($request->requested_days),
            duration: Str::headline($request->duration_unit),
            status: $request->status,
            statusLabel: Str::headline($request->status),
            reason: $request->reason ?: 'No reason provided',
            decisionNote: $request->decision_note,
            canApprove: $actor->can('approve', $request),
            canReject: $actor->can('reject', $request),
        ));
    }

    public function presentBalances(LengthAwarePaginator $paginator): LengthAwarePaginator
    {
        return $paginator->through(fn (EmployeeLeaveBalance $balance) => new LeaveBalanceRowData(
            employeeCode: $balance->employee?->employee_code ?? 'Not available',
            employeeName: $balance->employee?->name ?? 'Unknown employee',
            leaveTypeCode: $balance->leaveType?->code ?? 'Not available',
            leaveTypeName: $balance->leaveType?->name ?? 'Unknown leave type',
            periodYear: (int) $balance->period_year,
            opening: $this->decimalLabel($balance->opening_balance_days),
            accrued: $this->decimalLabel($balance->accrued_days),
            used: $this->decimalLabel($balance->used_days),
            pending: $this->decimalLabel($balance->pending_days),
            adjusted: $this->decimalLabel($balance->adjusted_days),
            available: $this->decimalLabel($balance->available_days),
        ));
    }

    public function presentProcessingRuns(User $actor, LengthAwarePaginator $paginator): LengthAwarePaginator
    {
        return $paginator->through(function (LeaveProcessingRun $run) use ($actor): LeaveProcessingRunRowData {
            $summary = is_array($run->summary) ? $run->summary : [];

            return new LeaveProcessingRunRowData(
                id: $run->id,
                runNumber: $run->run_number,
                periodYear: (int) $run->period_year,
                processingType: $run->processing_type,
                processingTypeLabel: Str::headline($run->processing_type),
                status: $run->status,
                statusLabel: Str::headline($run->status),
                employeeCount: (int) ($summary['employee_count'] ?? 0),
                lineCount: (int) ($summary['line_count'] ?? 0),
                accrualDays: $this->decimalLabel($summary['total_accrual_days'] ?? 0),
                carryForwardDays: $this->decimalLabel($summary['total_carry_forward_days'] ?? 0),
                lapseDays: $this->decimalLabel($summary['total_lapse_days'] ?? 0),
                createdBy: $run->createdBy?->name ?? 'Unknown user',
                postedBy: $run->postedBy?->name,
                createdAt: $run->created_at?->timezone(config('app.timezone'))->format('d M Y, h:i A') ?? 'Not available',
                postedAt: $run->posted_at?->timezone(config('app.timezone'))->format('d M Y, h:i A'),
                canPost: $actor->can('post', $run),
                lineItems: $this->presentProcessingLineItems($run->line_items),
                rulesSnapshot: $this->presentProcessingRuleSnapshot($run->rules_snapshot),
            );
        });
    }

    public function presentEncashments(User $actor, LengthAwarePaginator $paginator): LengthAwarePaginator
    {
        return $paginator->through(fn (LeaveEncashment $encashment) => new LeaveEncashmentRowData(
            id: $encashment->id,
            encashmentNumber: $encashment->encashment_number,
            employeeCode: $encashment->employee?->employee_code ?? 'Not available',
            employeeName: $encashment->employee?->name ?? 'Unknown employee',
            leaveTypeCode: $encashment->leaveType?->code ?? 'Not available',
            periodYear: (int) $encashment->period_year,
            requestedDays: $this->decimalLabel($encashment->requested_days),
            approvedDays: $this->decimalLabel($encashment->approved_days),
            grossAmount: $this->moneyLabel($encashment->gross_amount),
            netAmount: $this->moneyLabel($encashment->net_amount),
            status: $encashment->status,
            statusLabel: Str::headline($encashment->status),
            requestNote: $encashment->request_note ?: 'No request note',
            decisionNote: $encashment->decision_note,
            payrollReference: $encashment->payroll_reference,
            canApprove: $actor->can('approve', $encashment),
            canReject: $actor->can('reject', $encashment),
            canMarkPayroll: $actor->can('markPayroll', $encashment),
        ));
    }

    public function presentPolicies(LengthAwarePaginator $paginator): LengthAwarePaginator
    {
        return $paginator->through(fn (LeaveType $type) => new LeavePolicyRowData(
            id: $type->id,
            code: $type->code,
            name: $type->name,
            annualEntitlement: $this->decimalLabel($type->annual_entitlement_days).' days annually',
            paidLabel: $type->is_paid ? 'Paid leave' : 'Unpaid leave',
            documentLabel: $type->requires_document ? 'Supporting document required' : 'No supporting document required',
            halfDayLabel: $type->allows_half_day ? 'Half-day requests allowed' : 'Half-day requests not allowed',
            negativeBalanceLabel: $type->allow_negative_balance ? 'Negative balance allowed' : 'Negative balance blocked',
            carryForwardLabel: $type->carry_forward_enabled
                ? 'Carry forward up to '.$this->decimalLabel($type->max_carry_forward_days).' days'
                : 'Carry forward disabled',
            encashmentLabel: $type->encashment_enabled ? 'Encashment enabled' : 'Encashment disabled',
            approvalChain: collect($type->approval_chain ?? [])->map(fn ($step) => Str::headline((string) $step))->join(' -> ') ?: 'No approval chain configured',
        ));
    }

    public function companies(User $u): Collection
    {
        $q = Company::query();
        $this->scope->apply($q, $u, 'id');

        return $q->orderBy('name')->get(['id', 'code', 'name']);
    }

    public function employees(User $u): Collection
    {
        return $this->scope->apply(Employee::query(), $u)->when($this->selfOnly($u), fn ($q) => $q->where('user_id', $u->id))->orderBy('employee_code')->get(['id', 'employee_code', 'name', 'department', 'company_id', 'user_id']);
    }

    public function canManage(User $u): bool
    {
        return $u->hasPermission('leave.manage') || $u->hasPermission('leave.approve');
    }

    public function selfOnly(User $u): bool
    {
        return ! $this->canManage($u) && ! $u->hasPermission('payroll.view') && ! $u->hasPermission('payroll.manage');
    }

    private function requestQuery(User $u): Builder
    {
        return $this->scope->apply(LeaveRequest::query(), $u)
            ->when(! $this->canManage($u), fn ($q) => $q->whereHas('employee', fn ($employee) => $employee->where('user_id', $u->id)));
    }

    private function encashmentQuery(User $u): Builder
    {
        return $this->scope->apply(LeaveEncashment::query(), $u)
            ->when($this->selfOnly($u), fn ($q) => $q->whereHas('employee', fn ($employee) => $employee->where('user_id', $u->id)));
    }

    private function decimalLabel(mixed $value): string
    {
        $formatted = number_format((float) $value, 2, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }

    /**
     * @return array<int, LeaveProcessingLineItemData>
     */
    private function presentProcessingLineItems(mixed $lineItems): array
    {
        if (! is_array($lineItems)) {
            return [];
        }

        return collect($lineItems)
            ->filter(fn (mixed $line): bool => is_array($line))
            ->map(fn (array $line): LeaveProcessingLineItemData => new LeaveProcessingLineItemData(
                employeeCode: (string) ($line['employee_code'] ?? 'Not available'),
                employeeName: (string) ($line['employee_name'] ?? 'Unknown employee'),
                leaveTypeCode: (string) ($line['leave_type_code'] ?? 'Not available'),
                openingBalanceDays: $this->decimalLabel($line['opening_balance_days'] ?? 0),
                availableBeforeDays: $this->decimalLabel($line['available_before'] ?? 0),
                accrualDays: $this->decimalLabel($line['accrual_days'] ?? 0),
                carryForwardDays: $this->decimalLabel($line['carry_forward_days'] ?? 0),
                lapseDays: $this->decimalLabel($line['lapse_days'] ?? 0),
            ))
            ->values()
            ->all();
    }

    private function presentProcessingRuleSnapshot(mixed $snapshot): ?LeaveProcessingRuleSnapshotData
    {
        if (! is_array($snapshot) || $snapshot === []) {
            return null;
        }

        $settings = is_array($snapshot['rules_setting'] ?? null) ? $snapshot['rules_setting'] : [];
        $leaveTypes = collect(is_array($snapshot['leave_types'] ?? null) ? $snapshot['leave_types'] : [])
            ->filter(fn (mixed $rule): bool => is_array($rule))
            ->map(fn (array $rule): LeaveProcessingLeaveTypeRuleData => new LeaveProcessingLeaveTypeRuleData(
                code: (string) ($rule['code'] ?? 'Not available'),
                annualEntitlementDays: $this->decimalLabel($rule['annual_entitlement_days'] ?? 0),
                carryForwardLabel: (bool) ($rule['carry_forward_enabled'] ?? false) ? 'Enabled' : 'Disabled',
                maxCarryForwardDays: $this->decimalLabel($rule['max_carry_forward_days'] ?? 0),
                encashmentLabel: (bool) ($rule['encashment_enabled'] ?? false) ? 'Enabled' : 'Disabled',
            ))
            ->values()
            ->all();

        $taxRate = $settings['encashment_tax_rate'] ?? $snapshot['encashment_tax_rate'] ?? null;

        return new LeaveProcessingRuleSnapshotData(
            leaveTypes: $leaveTypes,
            settingKey: (string) ($settings['setting_key'] ?? 'Not recorded'),
            encashmentTaxRate: is_numeric($taxRate) ? $this->decimalLabel((float) $taxRate * 100).'%' : 'Not recorded',
            monthlyAccrualLabel: (bool) ($settings['monthly_accrual_enabled'] ?? false) ? 'Enabled' : 'Disabled',
            yearEndLabel: (bool) ($settings['year_end_processing_enabled'] ?? false) ? 'Enabled' : 'Disabled',
            encashmentFormula: (string) ($settings['encashment_formula'] ?? 'Not recorded'),
        );
    }

    private function moneyLabel(mixed $value): string
    {
        return 'INR '.number_format((float) $value, 2);
    }
}
