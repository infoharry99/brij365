<?php

namespace App\Domain\Construction\Services;

use App\Models\BoqItem;
use App\Models\ConstructionMilestone;
use App\Models\ContractorBill;
use App\Models\ContractorMeasurement;
use App\Models\DailyProgressReport;
use App\Models\Project;
use App\Models\User;
use App\Services\Construction\ConstructionService;
use App\Services\Security\CompanyScopeService;
use App\Support\PaginationPolicy;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class ConstructionWorkspaceRegister
{
    public function __construct(
        private readonly CompanyScopeService $companyScope,
        private readonly PaginationPolicy $pagination,
        private readonly ConstructionService $construction,
    ) {}

    /** @param array<string, mixed> $filters */
    public function milestones(User $user, array $filters, string $pageName = 'page'): LengthAwarePaginator
    {
        $query = ConstructionMilestone::query()->with(['project', 'createdBy']);
        $this->companyScope->apply($query, $user);

        return $query
            ->when($filters['project_id'] ?? null, fn ($query, $value) => $query->where('project_id', $value))
            ->when($filters['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->when($filters['phase'] ?? null, fn ($query, $value) => $query->where('phase', $value))
            ->orderBy('planned_start_on')
            ->paginate($this->pagination->workspacePerPage($filters['per_page'] ?? null), ['*'], $pageName);
    }

    /** @param array<string, mixed> $filters */
    public function dailyReports(User $user, array $filters, string $pageName = 'page'): LengthAwarePaginator
    {
        $query = DailyProgressReport::query()->with(['project', 'preparedBy', 'approvedBy']);
        $this->companyScope->apply($query, $user);

        return $query
            ->when($filters['project_id'] ?? null, fn ($query, $value) => $query->where('project_id', $value))
            ->when($filters['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->when($filters['date_from'] ?? null, fn ($query, $value) => $query->whereDate('report_date', '>=', $value))
            ->when($filters['date_to'] ?? null, fn ($query, $value) => $query->whereDate('report_date', '<=', $value))
            ->orderByDesc('report_date')
            ->paginate($this->pagination->workspacePerPage($filters['per_page'] ?? null), ['*'], $pageName);
    }

    /** @param array<string, mixed> $filters */
    public function boqItems(User $user, array $filters): LengthAwarePaginator
    {
        $query = BoqItem::query()->with($this->construction->boqItemRelations());
        $this->companyScope->apply($query, $user);

        return $query
            ->when($filters['project_id'] ?? null, fn ($query, $value) => $query->where('project_id', $value))
            ->when($filters['vendor_id'] ?? null, fn ($query, $value) => $query->where('vendor_id', $value))
            ->when($filters['trade'] ?? null, fn ($query, $value) => $query->where('trade', $value))
            ->when($filters['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->orderBy('boq_code')->paginate($this->pagination->workspacePerPage($filters['per_page'] ?? null));
    }

    /** @param array<string, mixed> $filters */
    public function measurements(User $user, array $filters): LengthAwarePaginator
    {
        $query = ContractorMeasurement::query()->with($this->construction->measurementRelations());
        $this->companyScope->apply($query, $user);

        return $query
            ->when($filters['project_id'] ?? null, fn ($query, $value) => $query->where('project_id', $value))
            ->when($filters['vendor_id'] ?? null, fn ($query, $value) => $query->where('vendor_id', $value))
            ->when($filters['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->when($filters['date_from'] ?? null, fn ($query, $value) => $query->whereDate('measurement_date', '>=', $value))
            ->when($filters['date_to'] ?? null, fn ($query, $value) => $query->whereDate('measurement_date', '<=', $value))
            ->latest()->paginate($this->pagination->workspacePerPage($filters['per_page'] ?? null));
    }

    /** @param array<string, mixed> $filters */
    public function bills(User $user, array $filters): LengthAwarePaginator
    {
        $query = ContractorBill::query()->with($this->construction->contractorBillRelations());
        $this->companyScope->apply($query, $user);

        return $query
            ->when($filters['project_id'] ?? null, fn ($query, $value) => $query->where('project_id', $value))
            ->when($filters['vendor_id'] ?? null, fn ($query, $value) => $query->where('vendor_id', $value))
            ->when($filters['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->when($filters['date_from'] ?? null, fn ($query, $value) => $query->whereDate('bill_date', '>=', $value))
            ->when($filters['date_to'] ?? null, fn ($query, $value) => $query->whereDate('bill_date', '<=', $value))
            ->latest()->paginate($this->pagination->workspacePerPage($filters['per_page'] ?? null));
    }

    public function projects(User $user): Collection
    {
        $query = Project::query()->where('status', 'active')->orderBy('code');
        $this->companyScope->apply($query, $user);

        return $query->get(['id', 'company_id', 'code', 'name']);
    }

    public function milestoneOptions(User $user): Collection
    {
        $query = ConstructionMilestone::query()->whereIn('status', ['planned', 'in_progress', 'delayed'])->orderBy('milestone_code');
        $this->companyScope->apply($query, $user);

        return $query->get(['id', 'project_id', 'milestone_code', 'name', 'progress_percent', 'status']);
    }

    public function phases(User $user): Collection
    {
        $query = ConstructionMilestone::query()->select('phase')->whereNotNull('phase')->distinct()->orderBy('phase');
        $this->companyScope->apply($query, $user);

        return $query->pluck('phase')->filter()->values();
    }

    public function milestoneMetrics(User $user): Collection
    {
        $query = ConstructionMilestone::query()->selectRaw('status, count(*) as aggregate')->groupBy('status');
        $this->companyScope->apply($query, $user);

        return $query->pluck('aggregate', 'status');
    }

    public function reportMetrics(User $user): Collection
    {
        $query = DailyProgressReport::query()->selectRaw('status, count(*) as aggregate')->groupBy('status');
        $this->companyScope->apply($query, $user);

        return $query->pluck('aggregate', 'status');
    }
}
