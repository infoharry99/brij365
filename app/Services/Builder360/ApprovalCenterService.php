<?php

namespace App\Services\Builder360;

use App\Models\AttendanceRegularizationRequest;
use App\Models\CollectionReceipt;
use App\Models\CommissionRun;
use App\Models\ContractorBill;
use App\Models\ContractorMeasurement;
use App\Models\DailyProgressReport;
use App\Models\EmployeeLoan;
use App\Models\ExpenseClaim;
use App\Models\FinancialVoucher;
use App\Models\GstEntry;
use App\Models\GstReturnPeriod;
use App\Models\JobOpening;
use App\Models\LeaveEncashment;
use App\Models\LeaveRequest;
use App\Models\ManagedDocument;
use App\Models\PayrollRun;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequisition;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\UnitPriceVersion;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

class ApprovalCenterService
{
    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>|null
     */
    public function payloadFor(User $actor, ?string $selectedRoleSlug = null, ?int $selectedProjectId = null, array $filters = []): ?array
    {
        $user = $this->effectiveUserForRoleContext($actor, $selectedRoleSlug);

        if (! $this->canUseApprovalCenter($user)) {
            return null;
        }

        $projectId = $this->visibleProjectForUser($user, $selectedProjectId)?->id;
        $rows = $this->allRows($user, $projectId);
        $baseRows = $this->filterRows($rows, $filters, false);
        $visibleRows = $this->filterRows($baseRows, $filters, true)->values();
        $perPage = max(1, min(100, (int) ($filters['per_page'] ?? 25)));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $offset = ($page - 1) * $perPage;

        return [
            'source' => 'business-records',
            'generated_at' => now()->toISOString(),
            'scope' => [
                'company_id' => $user->company_id,
                'project_id' => $projectId,
                'role_slug' => $user->role?->slug,
            ],
            'summary' => $this->summary($baseRows),
            'filters' => $this->availableFilters($rows),
            'rows' => $visibleRows->slice($offset, $perPage)->values()->all(),
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $visibleRows->count(),
                'last_page' => (int) max(1, ceil($visibleRows->count() / $perPage)),
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function bootstrapOptions(?User $user, ?int $selectedProjectId = null): ?array
    {
        if (! $user) {
            return null;
        }

        return $this->payloadFor($user, $user->role?->slug, $selectedProjectId, [
            'tab' => 'pending',
            'per_page' => 25,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function dashboardRows(?User $user, ?int $selectedProjectId = null): array
    {
        if (! $user) {
            return [];
        }

        $payload = $this->payloadFor($user, $user->role?->slug, $selectedProjectId, [
            'tab' => 'pending',
            'per_page' => 50,
        ]);

        return $payload['rows'] ?? [];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function exportRows(User $actor, ?string $selectedRoleSlug, ?int $selectedProjectId, array $filters): array
    {
        $payload = $this->payloadFor($actor, $selectedRoleSlug, $selectedProjectId, array_merge($filters, [
            'per_page' => 100,
            'page' => 1,
        ]));

        return $payload['rows'] ?? [];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function allRows(User $user, ?int $projectId): Collection
    {
        $rows = collect();
        $companyId = $user->company_id;

        $this->source($rows, $user, PurchaseRequisition::class, [
            'module' => 'Procurement',
            'type' => 'Purchase Requisition',
            'pending_statuses' => ['submitted'],
            'approved_statuses' => ['approved'],
            'number' => 'requisition_number',
            'description' => fn (Model $m) => $m->purpose ?: 'Purchase requisition approval',
            'amount' => 'estimated_total',
            'raised_by' => 'requestedBy',
            'approve_route' => 'procurement.requisitions.approve',
            'open_route' => 'construction',
            'priority' => 'med',
            'project_aware' => true,
        ], $companyId, $projectId);

        $this->source($rows, $user, PurchaseOrder::class, [
            'module' => 'Procurement',
            'type' => 'Purchase Order',
            'pending_statuses' => ['draft'],
            'approved_statuses' => ['approved'],
            'number' => 'po_number',
            'description' => fn (Model $m) => 'Purchase order approval',
            'amount' => 'total_amount',
            'raised_by' => 'createdBy',
            'approve_route' => 'procurement.purchase-orders.approve',
            'open_route' => 'construction',
            'priority' => 'high',
            'project_aware' => true,
        ], $companyId, $projectId);

        $this->source($rows, $user, FinancialVoucher::class, [
            'module' => 'Finance',
            'type' => 'Financial Voucher',
            'pending_statuses' => ['submitted'],
            'approved_statuses' => ['approved'],
            'number' => 'voucher_number',
            'description' => fn (Model $m) => $m->narration ?: 'Voucher approval',
            'amount' => 'total_debit',
            'raised_by' => 'createdBy',
            'approve_route' => 'finance.vouchers.approve',
            'reject_route' => 'finance.vouchers.reject',
            'open_route' => 'finance',
            'priority' => 'high',
        ], $companyId, $projectId);

        $this->source($rows, $user, CollectionReceipt::class, [
            'module' => 'Finance',
            'type' => 'Collection Receipt',
            'pending_statuses' => ['submitted'],
            'approved_statuses' => ['approved'],
            'number' => 'receipt_number',
            'description' => fn (Model $m) => 'Collection receipt approval',
            'amount' => 'amount',
            'raised_by' => 'collectedBy',
            'approve_route' => 'finance.collections.approve',
            'open_route' => 'collections',
            'priority' => 'med',
            'project_aware' => true,
        ], $companyId, $projectId);

        $this->source($rows, $user, GstEntry::class, [
            'module' => 'Finance',
            'type' => 'GST Entry',
            'pending_statuses' => ['submitted'],
            'approved_statuses' => ['approved'],
            'number' => 'entry_number',
            'description' => fn (Model $m) => $m->document_number ? "GST entry {$m->document_number}" : 'GST entry approval',
            'amount' => 'total_tax_amount',
            'raised_by' => 'createdBy',
            'approve_route' => 'finance.gst-entries.approve',
            'open_route' => 'finance',
            'priority' => 'med',
            'project_aware' => true,
        ], $companyId, $projectId);

        $this->source($rows, $user, GstReturnPeriod::class, [
            'module' => 'Finance',
            'type' => 'GST Return',
            'pending_statuses' => ['submitted'],
            'approved_statuses' => ['approved'],
            'number' => 'return_number',
            'description' => fn (Model $m) => 'GST return approval',
            'amount' => 'output_tax_total',
            'raised_by' => 'preparedBy',
            'approve_route' => 'finance.gst-return-periods.approve',
            'open_route' => 'finance',
            'priority' => 'high',
        ], $companyId, $projectId);

        $this->source($rows, $user, LeaveRequest::class, [
            'module' => 'HR',
            'type' => 'Leave Request',
            'pending_statuses' => ['submitted'],
            'approved_statuses' => ['approved'],
            'number' => 'request_number',
            'description' => fn (Model $m) => 'Leave request approval',
            'amount' => 'requested_days',
            'amount_suffix' => 'day(s)',
            'raised_by' => 'employee.user',
            'approve_route' => 'hr.leave-requests.approve',
            'reject_route' => 'hr.leave-requests.reject',
            'payload_key' => 'decision_note',
            'open_route' => 'hr',
            'priority' => 'med',
        ], $companyId, $projectId);

        $this->source($rows, $user, AttendanceRegularizationRequest::class, [
            'module' => 'HR',
            'type' => 'Attendance Regularization',
            'pending_statuses' => ['submitted'],
            'approved_statuses' => ['approved'],
            'number' => 'request_number',
            'description' => fn (Model $m) => $m->reason ?: 'Attendance correction approval',
            'raised_by' => 'requestedBy',
            'approve_route' => 'hr.attendance-regularizations.approve',
            'reject_route' => 'hr.attendance-regularizations.reject',
            'payload_key' => 'decision_note',
            'open_route' => 'hr',
            'priority' => 'med',
        ], $companyId, $projectId);

        $this->source($rows, $user, ExpenseClaim::class, [
            'module' => 'HR',
            'type' => 'Expense Claim',
            'pending_statuses' => ['submitted'],
            'approved_statuses' => ['approved'],
            'number' => 'claim_number',
            'description' => fn (Model $m) => $m->purpose ?: 'Expense claim approval',
            'amount' => 'amount',
            'raised_by' => 'requestedBy',
            'approve_route' => 'hr.expense-claims.approve',
            'reject_route' => 'hr.expense-claims.reject',
            'payload_key' => 'decision_note',
            'open_route' => 'hr',
            'priority' => 'med',
        ], $companyId, $projectId);

        $this->source($rows, $user, EmployeeLoan::class, [
            'module' => 'HR',
            'type' => 'Employee Loan',
            'pending_statuses' => ['submitted'],
            'approved_statuses' => ['approved'],
            'number' => 'loan_number',
            'description' => fn (Model $m) => $m->purpose ?: 'Employee loan approval',
            'amount' => 'principal_amount',
            'raised_by' => 'requestedBy',
            'approve_route' => 'hr.loans.approve',
            'reject_route' => 'hr.loans.reject',
            'payload_key' => 'decision_note',
            'open_route' => 'hr',
            'priority' => 'high',
        ], $companyId, $projectId);

        $this->source($rows, $user, LeaveEncashment::class, [
            'module' => 'HR',
            'type' => 'Leave Encashment',
            'pending_statuses' => ['submitted'],
            'approved_statuses' => ['approved'],
            'number' => 'encashment_number',
            'description' => fn (Model $m) => 'Leave encashment approval',
            'amount' => 'net_amount',
            'raised_by' => 'requestedBy',
            'approve_route' => 'hr.leave-encashments.approve',
            'reject_route' => 'hr.leave-encashments.reject',
            'payload_key' => 'decision_note',
            'open_route' => 'hr',
            'priority' => 'med',
        ], $companyId, $projectId);

        $this->source($rows, $user, PayrollRun::class, [
            'module' => 'Payroll',
            'type' => 'Payroll Run',
            'pending_statuses' => ['draft', 'generated'],
            'approved_statuses' => ['approved'],
            'number' => 'run_number',
            'description' => fn (Model $m) => "Payroll {$m->period_month}/{$m->period_year}",
            'amount' => 'net_payable',
            'raised_by' => 'generatedBy',
            'approve_route' => 'payroll.runs.approve',
            'open_route' => 'hr',
            'priority' => 'high',
        ], $companyId, $projectId);

        $this->source($rows, $user, CommissionRun::class, [
            'module' => 'Payroll',
            'type' => 'Commission Run',
            'pending_statuses' => ['generated'],
            'approved_statuses' => ['approved'],
            'number' => 'run_number',
            'description' => fn (Model $m) => "Commission {$m->period_month}/{$m->period_year}",
            'amount' => 'commission_total',
            'raised_by' => 'generatedBy',
            'approve_route' => 'payroll.commission-runs.approve',
            'reject_route' => 'payroll.commission-runs.reject',
            'payload_key' => 'decision_note',
            'open_route' => 'hr',
            'priority' => 'high',
        ], $companyId, $projectId);

        $this->source($rows, $user, JobOpening::class, [
            'module' => 'Recruitment',
            'type' => 'Job Opening',
            'pending_statuses' => ['pending_approval'],
            'approved_statuses' => ['open', 'approved'],
            'number' => 'opening_number',
            'description' => fn (Model $m) => $m->title ?: 'Job opening approval',
            'raised_by' => 'createdBy',
            'approve_route' => 'recruitment.job-openings.approve',
            'reject_route' => 'recruitment.job-openings.reject',
            'payload_key' => 'decision_note',
            'open_route' => 'hr',
            'priority' => 'med',
            'project_aware' => true,
        ], $companyId, $projectId);

        $this->source($rows, $user, DailyProgressReport::class, [
            'module' => 'Construction',
            'type' => 'Daily Progress Report',
            'pending_statuses' => ['submitted'],
            'approved_statuses' => ['approved'],
            'number' => 'report_number',
            'description' => fn (Model $m) => 'Daily progress approval',
            'raised_by' => 'preparedBy',
            'approve_route' => 'construction.daily-progress-reports.approve',
            'reject_route' => 'construction.daily-progress-reports.reject',
            'payload_key' => 'decision_note',
            'open_route' => 'construction',
            'priority' => 'med',
            'project_aware' => true,
        ], $companyId, $projectId);

        $this->source($rows, $user, ContractorMeasurement::class, [
            'module' => 'Construction',
            'type' => 'Contractor Measurement',
            'pending_statuses' => ['submitted'],
            'approved_statuses' => ['approved'],
            'number' => 'measurement_number',
            'description' => fn (Model $m) => 'Contractor measurement approval',
            'amount' => 'measured_total',
            'raised_by' => 'submittedBy',
            'approve_route' => 'construction.contractor-measurements.approve',
            'reject_route' => 'construction.contractor-measurements.reject',
            'payload_key' => 'decision_note',
            'open_route' => 'construction',
            'priority' => 'med',
            'project_aware' => true,
        ], $companyId, $projectId);

        $this->source($rows, $user, ContractorBill::class, [
            'module' => 'Construction',
            'type' => 'Contractor Bill',
            'pending_statuses' => ['submitted'],
            'approved_statuses' => ['approved'],
            'number' => 'bill_number',
            'description' => fn (Model $m) => 'Contractor bill approval',
            'amount' => 'payable_amount',
            'raised_by' => 'preparedBy',
            'approve_route' => 'construction.contractor-bills.approve',
            'open_route' => 'construction',
            'priority' => 'high',
            'project_aware' => true,
        ], $companyId, $projectId);

        $this->source($rows, $user, ManagedDocument::class, [
            'module' => 'Documents',
            'type' => 'Managed Document',
            'pending_statuses' => ['submitted'],
            'approved_statuses' => ['approved'],
            'number' => 'document_number',
            'description' => fn (Model $m) => $m->title ?: 'Document approval',
            'raised_by' => 'createdBy',
            'approve_route' => 'documents.approve',
            'open_route' => 'documents',
            'priority' => 'med',
            'project_aware' => true,
        ], $companyId, $projectId);

        $this->source($rows, $user, SystemSetting::class, [
            'module' => 'System',
            'type' => 'System Setting',
            'pending_statuses' => ['draft'],
            'approved_statuses' => ['active', 'approved'],
            'number' => 'setting_key',
            'description' => fn (Model $m) => $m->description ?: $m->setting_key,
            'raised_by' => 'createdBy',
            'approve_route' => 'settings.system-settings.approve',
            'open_route' => 'admin',
            'priority' => 'low',
        ], $companyId, $projectId);

        $this->source($rows, $user, UnitPriceVersion::class, [
            'module' => 'Inventory',
            'type' => 'Unit Price Version',
            'pending_statuses' => ['draft'],
            'approved_statuses' => ['approved'],
            'number' => 'version_number',
            'description' => fn (Model $m) => 'Unit price version approval',
            'amount' => 'total_price',
            'raised_by' => 'createdBy',
            'approve_route' => 'inventory.unit-price-versions.approve',
            'open_route' => 'inventory',
            'priority' => 'high',
            'project_aware' => true,
        ], $companyId, $projectId);

        return $rows->sortByDesc(fn (array $row) => $row['created_at'] ?? '')->values();
    }

    /**
     * @param Collection<int, array<string, mixed>> $rows
     * @param array<string, mixed> $config
     */
    private function source(Collection $rows, User $user, string $modelClass, array $config, ?int $companyId, ?int $projectId): void
    {
        if (! class_exists($modelClass) || ! $user->can('viewAny', $modelClass)) {
            return;
        }

        if ($projectId && empty($config['project_aware'])) {
            return;
        }

        $statuses = array_values(array_unique(array_merge($config['pending_statuses'], $config['approved_statuses'])));
        $query = $modelClass::query()->whereIn('status', $statuses)->latest();
        $this->applyCompanyScope($query, $companyId);

        if (! empty($config['project_aware']) && $projectId) {
            $query->where('project_id', $projectId);
        }

        $query->limit(80)->get()->each(function (Model $model) use ($rows, $user, $config): void {
            $rows->push($this->row($user, $model, $config));
        });
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function row(User $user, Model $model, array $config): array
    {
        $status = (string) data_get($model, 'status', 'pending');
        $amountField = $config['amount'] ?? null;
        $amount = $amountField ? (float) (data_get($model, $amountField) ?: 0) : 0.0;
        $canApprove = $this->routeExists($config['approve_route'] ?? null) && $user->can('approve', $model);
        $canReject = $this->routeExists($config['reject_route'] ?? null) && $user->can('reject', $model);
        $payloadKey = (string) ($config['payload_key'] ?? 'note');
        $projectId = data_get($model, 'project_id');
        $number = (string) data_get($model, $config['number'], sprintf('%s-%s', class_basename($model), $model->getKey()));
        $description = is_callable($config['description'] ?? null)
            ? (string) $config['description']($model)
            : (string) ($config['description'] ?? $number);

        return [
            'id' => str(class_basename($model))->snake()->toString().'-'.$model->getKey(),
            'record_id' => $model->getKey(),
            'number' => $number,
            'type' => $config['type'],
            'source_module' => $config['module'],
            'project_id' => $projectId,
            'project_label' => $this->projectLabel($model),
            'description' => $description,
            'raised_by' => $this->relationName($model, $config['raised_by'] ?? null),
            'amount_value' => $amount,
            'amount_display' => $this->amountDisplay($amount, $config['amount_suffix'] ?? null),
            'age' => $this->ageLabel($model->created_at),
            'priority' => $config['priority'] ?? 'med',
            'status' => $status,
            'created_at' => optional($model->created_at)->toISOString(),
            'decided_at' => optional(data_get($model, 'approved_at') ?: data_get($model, 'decided_at'))->toISOString(),
            'can_approve' => $this->isPendingStatus($status) && $canApprove,
            'can_reject' => $this->isPendingStatus($status) && $canReject,
            'approve_url' => $canApprove ? route($config['approve_route'], $model, false) : null,
            'reject_url' => $canReject ? route($config['reject_route'], $model, false) : null,
            'approve_payload_key' => $payloadKey,
            'reject_payload_key' => $payloadKey,
            'open_route' => $config['open_route'] ?? 'dashboard',
            'open_route_filter' => array_filter([
                'project_id' => $projectId,
                'tab' => str($config['type'])->lower()->replace(' ', '_')->toString(),
            ], fn ($value) => $value !== null && $value !== ''),
        ];
    }

    private function applyCompanyScope(Builder $query, ?int $companyId): void
    {
        if ($companyId) {
            $query->where('company_id', $companyId);
        }
    }

    private function routeExists(?string $name): bool
    {
        return $name ? Route::has($name) : false;
    }

    private function relationName(Model $model, ?string $path): string
    {
        if (! $path) {
            return '—';
        }

        $value = $model;
        foreach (explode('.', $path) as $part) {
            if ($value instanceof Model && method_exists($value, $part)) {
                $value = $value->{$part};
                continue;
            }

            $value = data_get($value, $part);
        }

        return (string) (data_get($value, 'name') ?: data_get($value, 'email') ?: '—');
    }

    private function projectLabel(Model $model): ?string
    {
        if (! data_get($model, 'project_id') || ! method_exists($model, 'project')) {
            return null;
        }

        $project = $model->project;

        return $project ? trim(($project->code ? "{$project->code} · " : '').$project->name) : null;
    }

    private function amountDisplay(float $amount, ?string $suffix = null): string
    {
        if ($suffix) {
            return $amount > 0 ? rtrim(rtrim(number_format($amount, 2), '0'), '.').' '.$suffix : '—';
        }

        return $amount > 0 ? '₹'.number_format($amount, 0) : '—';
    }

    private function ageLabel($date): string
    {
        if (! $date) {
            return '—';
        }

        $days = (int) $date->diffInDays(now());

        return $days === 0 ? 'Today' : "{$days} day(s)";
    }

    /**
     * @param Collection<int, array<string, mixed>> $rows
     * @param array<string, mixed> $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function filterRows(Collection $rows, array $filters, bool $applyTab): Collection
    {
        $filtered = $rows;

        if ($q = trim((string) ($filters['q'] ?? ''))) {
            $needle = mb_strtolower($q);
            $filtered = $filtered->filter(function (array $row) use ($needle): bool {
                return str_contains(mb_strtolower(implode(' ', [
                    $row['number'] ?? '',
                    $row['type'] ?? '',
                    $row['source_module'] ?? '',
                    $row['description'] ?? '',
                    $row['raised_by'] ?? '',
                    $row['project_label'] ?? '',
                    $row['status'] ?? '',
                ])), $needle);
            });
        }

        foreach (['module' => 'source_module', 'priority' => 'priority', 'status' => 'status'] as $filterKey => $rowKey) {
            if ($value = $filters[$filterKey] ?? null) {
                $filtered = $filtered->where($rowKey, $value);
            }
        }

        if (! $applyTab) {
            return $filtered->values();
        }

        return match ($filters['tab'] ?? 'pending') {
            'high_priority' => $filtered->filter(fn (array $row) => $this->isPendingStatus($row['status']) && $row['priority'] === 'high'),
            'actionable' => $filtered->filter(fn (array $row) => $this->isPendingStatus($row['status']) && (($row['can_approve'] ?? false) || ($row['can_reject'] ?? false))),
            'restricted' => $filtered->filter(fn (array $row) => $this->isPendingStatus($row['status']) && ! ($row['can_approve'] ?? false) && ! ($row['can_reject'] ?? false)),
            'approved' => $filtered->filter(fn (array $row) => $this->isApprovedStatus($row['status'])),
            default => $filtered->filter(fn (array $row) => $this->isPendingStatus($row['status'])),
        };
    }

    /**
     * @param Collection<int, array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    private function summary(Collection $rows): array
    {
        $pending = $rows->filter(fn (array $row) => $this->isPendingStatus($row['status']));
        $approved = $rows->filter(fn (array $row) => $this->isApprovedStatus($row['status']));

        return [
            'pending' => $pending->count(),
            'high_priority' => $pending->where('priority', 'high')->count(),
            'actionable' => $pending->filter(fn (array $row) => ($row['can_approve'] ?? false) || ($row['can_reject'] ?? false))->count(),
            'restricted' => $pending->filter(fn (array $row) => ! ($row['can_approve'] ?? false) && ! ($row['can_reject'] ?? false))->count(),
            'approved' => $approved->count(),
            'value_tagged' => $rows->filter(fn (array $row) => (float) ($row['amount_value'] ?? 0) > 0)->count(),
            'total_value' => round((float) $rows->sum('amount_value'), 2),
            'modules' => $rows->groupBy('source_module')->map->count()->sortKeys()->all(),
        ];
    }

    /**
     * @param Collection<int, array<string, mixed>> $rows
     * @return array<string, array<int, string>>
     */
    private function availableFilters(Collection $rows): array
    {
        return [
            'modules' => $rows->pluck('source_module')->filter()->unique()->sort()->values()->all(),
            'priorities' => $rows->pluck('priority')->filter()->unique()->sort()->values()->all(),
            'statuses' => $rows->pluck('status')->filter()->unique()->sort()->values()->all(),
        ];
    }

    private function isPendingStatus(string $status): bool
    {
        return in_array($status, ['submitted', 'draft', 'generated', 'pending_approval'], true);
    }

    private function isApprovedStatus(string $status): bool
    {
        return in_array($status, ['approved', 'active', 'open'], true);
    }

    private function canUseApprovalCenter(User $user): bool
    {
        if ($this->isExternalDashboardUser($user)) {
            return false;
        }

        if ($user->hasPermission('*')) {
            return true;
        }

        $allowed = [
            'approvals.view',
            'procurement.manage',
            'procurement.approve',
            'finance.manage',
            'finance.approve',
            'collections.approve',
            'hr.manage',
            'leave.approve',
            'attendance.approve',
            'claims.approve',
            'loans.approve',
            'documents.approve',
            'payroll.manage',
            'payroll.approve',
            'construction.manage',
            'construction.approve',
            'recruitment.manage',
            'recruitment.approve',
            'settings.manage',
            'settings.approve',
            'legal.approve',
            'reports.view',
            'audit.view',
        ];

        return collect($allowed)->contains(fn (string $permission) => $user->hasPermission($permission));
    }

    private function isExternalDashboardUser(?User $user): bool
    {
        return $this->isPartnerPortalUser($user) || $this->isBuyerPortalUser($user) || $user?->role?->slug === 'employee';
    }

    private function isPartnerPortalUser(?User $user): bool
    {
        return $user?->role?->scope_level === 'partner';
    }

    private function isBuyerPortalUser(?User $user): bool
    {
        return $user?->role?->slug === 'buyer';
    }

    private function effectiveUserForRoleContext(User $actor, ?string $selectedRoleSlug): User
    {
        $actor->loadMissing('role', 'company');
        $selectedRoleSlug = $selectedRoleSlug ?: $actor->role?->slug;

        if (! $selectedRoleSlug || $selectedRoleSlug === $actor->role?->slug) {
            return $actor;
        }

        if (! in_array($actor->role?->slug, ['director', 'system_admin'], true) && ! $actor->hasPermission('*')) {
            throw new AuthorizationException('This role view is not available.');
        }

        $role = Role::query()->where('slug', $selectedRoleSlug)->where('is_active', true)->first();

        if (! $role) {
            throw new AuthorizationException('The selected role is not available.');
        }

        $user = User::query()
            ->with('role', 'company')
            ->where('role_id', $role->id)
            ->where('status', 'active')
            ->when($actor->company_id, fn (Builder $query) => $query->where('company_id', $actor->company_id))
            ->first();

        if (! $user) {
            throw new AuthorizationException('No active user is available for the selected role.');
        }

        return $user;
    }

    private function visibleProjectForUser(User $user, ?int $projectId): ?Project
    {
        if (! $projectId) {
            return null;
        }

        return Project::query()
            ->when($user->company_id, fn (Builder $query) => $query->where('company_id', $user->company_id))
            ->where('id', $projectId)
            ->first();
    }
}
