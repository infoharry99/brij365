<?php

namespace App\Domain\Payroll\Services;

use App\Application\Payroll\Data\PayrollBankBatchRowData;
use App\Application\Payroll\Data\PayrollComponentRowData;
use App\Application\Payroll\Data\PayrollRunItemRowData;
use App\Application\Payroll\Data\PayrollRunRowData;
use App\Application\Payroll\Data\PayrollWorkspaceSummaryData;
use App\Application\Payroll\Data\SalaryStructureRowData;
use App\Application\Payroll\Data\CommissionRuleRowData;
use App\Application\Payroll\Data\CommissionRunRowData;
use App\Domain\Hr\Services\EmployeeFieldVisibility;
use App\Models\CommissionRule;
use App\Models\CommissionRun;
use App\Models\EmployeeTaxDocument;
use App\Models\PayrollBankTransferBatch;
use App\Models\PayrollComponent;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\Project;
use App\Models\SalaryStructure;
use App\Models\User;
use App\Services\Payroll\CommissionService;
use App\Services\Payroll\PayrollBankTransferService;
use App\Services\Payroll\TaxDocumentService;
use App\Services\Security\CompanyScopeService;
use App\Support\PaginationPolicy;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

final class PayrollWorkspaceRegister
{
    public function __construct(
        private readonly CompanyScopeService $scope,
        private readonly PaginationPolicy $pagination,
        private readonly PayrollBankTransferService $batches,
        private readonly CommissionService $commissions,
        private readonly TaxDocumentService $taxDocuments,
        private readonly EmployeeFieldVisibility $fieldVisibility,
    ) {}

    /** @param array<string,mixed> $filters */
    public function components(User $user, array $filters = [], string $pageName = 'page'): LengthAwarePaginator
    {
        return $this->scope->apply(PayrollComponent::query(), $user)->where('is_active', true)
            ->when($filters['component_type'] ?? null, fn ($query, $value) => $query->where('component_type', $value))
            ->orderBy('component_type')->orderBy('name')->paginate($this->pagination->largePerPage($filters['per_page'] ?? null), ['*'], $pageName);
    }

    /** @param array<string,mixed> $filters */
    public function structures(User $user, array $filters = [], string $pageName = 'page'): LengthAwarePaginator
    {
        return $this->scope->apply(SalaryStructure::query()->with('components.payrollComponent'), $user)
            ->where('status', 'active')->latest('effective_from')->paginate($this->pagination->defaultPerPage($filters['per_page'] ?? null), ['*'], $pageName);
    }

    /** @param array<string,mixed> $filters */
    public function runs(User $user, array $filters = [], string $pageName = 'page'): LengthAwarePaginator
    {
        return $this->scope->apply(PayrollRun::query()->with(['generatedBy', 'approvedBy', 'items.employee']), $user)
            ->when($filters['period_year'] ?? null, fn ($query, $value) => $query->where('period_year', $value))
            ->when($filters['period_month'] ?? null, fn ($query, $value) => $query->where('period_month', $value))
            ->when($filters['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->latest()->paginate($this->pagination->defaultPerPage($filters['per_page'] ?? null), ['*'], $pageName);
    }

    /** @param array<string,mixed> $filters */
    public function bankBatches(User $user, array $filters = [], string $pageName = 'page'): LengthAwarePaginator
    {
        return $this->scope->apply(PayrollBankTransferBatch::query()->with($this->batches->relations()), $user)
            ->when($filters['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->when($filters['payroll_run_id'] ?? null, fn ($query, $value) => $query->where('payroll_run_id', $value))
            ->when($filters['bank_name'] ?? null, fn ($query, $value) => $query->where('bank_name', $value))
            ->when($filters['from'] ?? null, fn ($query, $value) => $query->whereDate('payment_date', '>=', $value))
            ->when($filters['to'] ?? null, fn ($query, $value) => $query->whereDate('payment_date', '<=', $value))
            ->latest()->paginate($this->pagination->defaultPerPage($filters['per_page'] ?? null), ['*'], $pageName);
    }

    public function summary(User $user): PayrollWorkspaceSummaryData
    {
        $runs = $this->scope->apply(PayrollRun::query(), $user);
        $batches = $this->scope->apply(PayrollBankTransferBatch::query(), $user);
        $structures = $this->scope->apply(SalaryStructure::query(), $user);
        $components = $this->scope->apply(PayrollComponent::query(), $user);
        $commissionRules = $this->scope->apply(CommissionRule::query(), $user);
        $commissionRuns = $this->scope->apply(CommissionRun::query(), $user);

        return new PayrollWorkspaceSummaryData(
            totalRuns: (clone $runs)->count(),
            generatedRuns: (clone $runs)->where('status', 'generated')->count(),
            approvedRuns: (clone $runs)->where('status', 'approved')->count(),
            approvedNetPayable: $this->moneyLabel((clone $runs)->where('status', 'approved')->sum('net_payable')),
            preparedBatches: (clone $batches)->where('status', 'prepared')->count(),
            releasedBatches: (clone $batches)->where('status', 'released')->count(),
            activeStructures: (clone $structures)->where('status', 'active')->count(),
            activeComponents: (clone $components)->where('is_active', true)->count(),
            activeCommissionRules: (clone $commissionRules)->where('status', 'active')->count(),
            generatedCommissionRuns: (clone $commissionRuns)->where('status', 'generated')->count(),
            approvedCommissionRuns: (clone $commissionRuns)->where('status', 'approved')->count(),
            approvedCommissionTotal: $this->moneyLabel((clone $commissionRuns)->where('status', 'approved')->sum('commission_total')),
        );
    }

    public function presentRuns(User $actor, LengthAwarePaginator $paginator): LengthAwarePaginator
    {
        $canViewCompensation = $this->fieldVisibility->canViewCompensation($actor);

        return $paginator->through(function (PayrollRun $run) use ($actor, $canViewCompensation): PayrollRunRowData {
            $items = $canViewCompensation
                ? $run->items->map(fn (PayrollRunItem $item): PayrollRunItemRowData => new PayrollRunItemRowData(
                    id: $item->id,
                    employeeCode: $item->employee?->employee_code ?? 'Unavailable',
                    employeeName: $item->employee?->name ?? 'Unavailable employee',
                    designation: $item->employee?->designation ?? 'Not assigned',
                    department: $item->employee?->department ?? 'Not assigned',
                    payableDays: (int) $item->payable_days,
                    grossEarnings: $this->moneyLabel($item->gross_earnings),
                    deductions: $this->moneyLabel($item->total_deductions),
                    netPayable: $this->moneyLabel($item->net_payable),
                    status: $item->status,
                    statusLabel: Str::headline($item->status),
                ))->values()->all()
                : [];

            return new PayrollRunRowData(
                id: $run->id,
                runNumber: $run->run_number,
                period: sprintf('%04d-%02d', $run->period_year, $run->period_month),
                dateRange: ($run->period_start?->format('d M Y') ?? 'Not available').' to '.($run->period_end?->format('d M Y') ?? 'Not available'),
                status: $run->status,
                statusLabel: Str::headline($run->status),
                employeeCount: $run->items->count(),
                grossEarnings: $canViewCompensation ? $this->moneyLabel($run->gross_earnings) : 'Restricted',
                deductions: $canViewCompensation ? $this->moneyLabel($run->total_deductions) : 'Restricted',
                netPayable: $canViewCompensation ? $this->moneyLabel($run->net_payable) : 'Restricted',
                generatedBy: $run->generatedBy?->name ?? 'Unknown user',
                approvedBy: $run->approvedBy?->name,
                canApprove: $actor->can('approve', $run),
                canPrepareBatch: $actor->can('create', [PayrollBankTransferBatch::class, $run]),
                canViewCompensation: $canViewCompensation,
                items: $items,
            );
        });
    }

    public function presentBankBatches(User $actor, LengthAwarePaginator $paginator, bool $includePayload): LengthAwarePaginator
    {
        $canViewPayload = $actor->can('viewPayload', PayrollBankTransferBatch::class);

        return $paginator->through(fn (PayrollBankTransferBatch $batch) => new PayrollBankBatchRowData(
            id: $batch->id,
            batchNumber: $batch->batch_number,
            runNumber: $batch->payrollRun?->run_number ?? 'Not available',
            period: $batch->payrollRun
                ? sprintf('%04d-%02d', $batch->payrollRun->period_year, $batch->payrollRun->period_month)
                : 'Not available',
            bankName: $batch->bank_name,
            paymentDate: $batch->payment_date?->format('d M Y') ?? 'Not available',
            status: $batch->status,
            statusLabel: Str::headline($batch->status),
            itemCount: (int) $batch->item_count,
            controlTotal: $this->moneyLabel($batch->control_total),
            checksum: Str::limit((string) $batch->checksum, 20, '...'),
            preparedBy: $batch->preparedBy?->name ?? 'Unknown user',
            releasedBy: $batch->releasedBy?->name,
            canRelease: $actor->can('release', $batch),
            payload: $includePayload && $canViewPayload ? $batch->csv_payload : null,
        ));
    }

    public function presentStructures(LengthAwarePaginator $paginator): LengthAwarePaginator
    {
        return $paginator->through(fn (SalaryStructure $structure) => new SalaryStructureRowData(
            id: $structure->id,
            code: $structure->code,
            name: $structure->name,
            version: (int) $structure->version,
            effectiveRange: ($structure->effective_from?->format('d M Y') ?? 'Not available').' to '.($structure->effective_to?->format('d M Y') ?? 'Open ended'),
            monthlyCtc: $this->moneyLabel($structure->monthly_ctc),
            components: $structure->components->map(fn ($component): string => sprintf(
                '%s: %s',
                $component->payrollComponent?->code ?? 'Unknown component',
                $this->moneyLabel($component->amount),
            ))->values()->all(),
        ));
    }

    public function presentComponents(LengthAwarePaginator $paginator): LengthAwarePaginator
    {
        return $paginator->through(fn (PayrollComponent $component) => new PayrollComponentRowData(
            id: $component->id,
            code: $component->code,
            name: $component->name,
            type: $component->component_type,
            typeLabel: Str::headline($component->component_type),
            calculationLabel: Str::headline($component->calculation_type),
            taxableLabel: $component->is_taxable ? 'Taxable' : 'Not taxable',
            statutoryLabel: $component->is_statutory ? 'Statutory' : 'Non-statutory',
            rules: collect($component->rules ?? [])->map(function ($value, $key): string {
                $label = Str::headline((string) $key);
                $display = is_bool($value) ? ($value ? 'Yes' : 'No') : (is_scalar($value) ? (string) $value : 'Configured');

                return $label.': '.$display;
            })->values()->all(),
        ));
    }

    /** @param array<string,mixed> $filters */
    public function commissionRules(User $user, array $filters): LengthAwarePaginator
    {
        $query = CommissionRule::query()->with(['project', 'createdBy']);
        $this->scope->apply($query, $user);

        return $query->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))->when($filters['rule_type'] ?? null, fn ($q, $v) => $q->where('rule_type', $v))->when($filters['basis'] ?? null, fn ($q, $v) => $q->where('basis', $v))->when($filters['project_id'] ?? null, fn ($q, $v) => $q->where('project_id', $v))->when($filters['search'] ?? null, fn ($q, $v) => $q->where(fn ($n) => $n->where('rule_code', 'like', "%{$v}%")->orWhere('name', 'like', "%{$v}%")))->latest()->paginate($this->pagination->defaultPerPage($filters['per_page'] ?? null));
    }

    /** @param array<string,mixed> $filters */
    public function commissionRuns(User $user, array $filters): LengthAwarePaginator
    {
        $query = CommissionRun::query()->with($this->commissions->relations());
        $this->scope->apply($query, $user);

        return $query->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))->when($filters['commission_rule_id'] ?? null, fn ($q, $v) => $q->where('commission_rule_id', $v))->when($filters['period_year'] ?? null, fn ($q, $v) => $q->where('period_year', $v))->when($filters['period_month'] ?? null, fn ($q, $v) => $q->where('period_month', $v))->latest()->paginate($this->pagination->defaultPerPage($filters['per_page'] ?? null));
    }

    public function presentCommissionRules(LengthAwarePaginator $paginator): LengthAwarePaginator
    {
        return $paginator->through(fn (CommissionRule $rule) => new CommissionRuleRowData(
            id: $rule->id,
            code: $rule->rule_code,
            name: $rule->name,
            type: $rule->rule_type,
            typeLabel: Str::headline($rule->rule_type),
            basisLabel: Str::headline($rule->basis),
            valueLabel: match ($rule->rule_type) {
                'fixed' => $this->moneyLabel($rule->fixed_amount),
                'percentage' => number_format((float) $rule->rate_percent, 2).'%',
                'target' => number_format((float) $rule->rate_percent, 2).'% after '.$this->moneyLabel($rule->target_amount),
                'slab' => count($rule->slab_rules ?? []).' configured slab'.(count($rule->slab_rules ?? []) === 1 ? '' : 's'),
                default => 'Configured',
            },
            effectiveRange: ($rule->effective_from?->format('d M Y') ?? 'Not available').' to '.($rule->effective_to?->format('d M Y') ?? 'Open ended'),
            status: $rule->status,
            statusLabel: Str::headline($rule->status),
            projectLabel: $rule->project ? $rule->project->code.' - '.$rule->project->name : 'All projects',
            createdBy: $rule->createdBy?->name ?? 'Unknown user',
        ));
    }

    public function presentCommissionRuns(User $actor, LengthAwarePaginator $paginator): LengthAwarePaginator
    {
        return $paginator->through(fn (CommissionRun $run) => new CommissionRunRowData(
            id: $run->id,
            runNumber: $run->run_number,
            ruleLabel: $run->rule ? $run->rule->rule_code.' - '.$run->rule->name : 'Unavailable rule',
            period: sprintf('%04d-%02d', $run->period_year, $run->period_month),
            dateRange: ($run->period_start?->format('d M Y') ?? 'Not available').' to '.($run->period_end?->format('d M Y') ?? 'Not available'),
            status: $run->status,
            statusLabel: Str::headline($run->status),
            itemCount: (int) $run->item_count,
            sourceTotal: $this->moneyLabel($run->source_total),
            eligibleTotal: $this->moneyLabel($run->eligible_total),
            commissionTotal: $this->moneyLabel($run->commission_total),
            generatedBy: $run->generatedBy?->name ?? 'Unknown user',
            approvedBy: $run->approvedBy?->name,
            canApprove: $actor->can('approve', $run),
            canReject: $actor->can('reject', $run),
        ));
    }

    /** @return array<int, array{id:int,label:string}> */
    public function commissionRuleOptions(User $actor): array
    {
        return $this->scope->apply(CommissionRule::query(), $actor)
            ->where('status', 'active')
            ->orderBy('rule_code')
            ->get(['id', 'company_id', 'rule_code', 'name'])
            ->map(fn (CommissionRule $rule): array => ['id' => $rule->id, 'label' => $rule->rule_code.' - '.$rule->name])
            ->all();
    }

    /** @return array<int, array{id:int,label:string}> */
    public function projectOptions(User $actor): array
    {
        return $this->scope->apply(Project::query(), $actor)
            ->where('status', 'active')
            ->orderBy('code')
            ->get(['id', 'company_id', 'code', 'name'])
            ->map(fn (Project $project): array => ['id' => $project->id, 'label' => $project->code.' - '.$project->name])
            ->all();
    }

    /** @param array<string,mixed> $filters */
    public function taxDocuments(User $actor, array $filters): LengthAwarePaginator
    {
        $query = EmployeeTaxDocument::query()->with($this->taxDocuments->relations());
        $this->scope->apply($query, $actor);

        return $query->when($actor->hasPermission('employee.self_service') && ! $actor->hasPermission('payroll.view') && ! $actor->hasPermission('payroll.manage') && ! $actor->hasPermission('payroll.approve') && ! $actor->hasPermission('compliance.view') && ! $actor->hasPermission('compliance.manage'), fn ($q) => $q->whereIn('status', ['issued', 'acknowledged'])->whereHas('employee', fn ($e) => $e->where('user_id', $actor->id)))->when($filters['employee_id'] ?? null, fn ($q, $v) => $q->where('employee_id', $v))->when($filters['financial_year'] ?? null, fn ($q, $v) => $q->where('financial_year', $v))->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))->when($filters['document_type'] ?? null, fn ($q, $v) => $q->where('document_type', $v))->latest()->paginate($this->pagination->defaultPerPage($filters['per_page'] ?? null));
    }

    private function moneyLabel(mixed $value): string
    {
        return 'INR '.number_format((float) $value, 2);
    }
}
