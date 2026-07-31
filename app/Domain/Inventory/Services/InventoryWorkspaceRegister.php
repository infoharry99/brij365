<?php

namespace App\Domain\Inventory\Services;

use App\Models\Project;
use App\Models\ProjectUnit;
use App\Models\UnitPriceVersion;
use App\Models\User;
use App\Services\Security\CompanyScopeService;
use App\Support\PaginationPolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class InventoryWorkspaceRegister
{
    public function __construct(
        private readonly CompanyScopeService $companyScope,
        private readonly PaginationPolicy $pagination,
    ) {}

    /** @param array<string, mixed> $filters */
    public function units(User $user, array $filters): LengthAwarePaginator
    {
        return $this->unitQuery($user, $filters)
            ->orderBy('project_id')->orderBy('tower')->orderBy('floor')->orderBy('unit_number')
            ->paginate($this->pagination->defaultPerPage($filters['per_page'] ?? null));
    }

    /** @param array<string, mixed> $filters @return Builder<ProjectUnit> */
    public function unitQuery(User $user, array $filters): Builder
    {
        $query = ProjectUnit::query()->with(['company', 'project', 'activeBooking']);
        $this->companyScope->apply($query, $user);

        return $query
            ->when($filters['project_id'] ?? null, fn (Builder $query, $value) => $query->where('project_id', $value))
            ->when($filters['status'] ?? null, fn (Builder $query, $value) => $query->where('status', $value))
            ->when($filters['unit_type'] ?? null, fn (Builder $query, $value) => $query->where('unit_type', $value));
    }

    /** @param array<string, mixed> $filters */
    public function priceVersions(User $user, array $filters): LengthAwarePaginator
    {
        $query = UnitPriceVersion::query()->with(['company', 'project', 'unit', 'createdBy', 'approvedBy']);
        $this->companyScope->apply($query, $user);

        return $query
            ->when($filters['project_id'] ?? null, fn ($query, $value) => $query->where('project_id', $value))
            ->when($filters['project_unit_id'] ?? null, fn ($query, $value) => $query->where('project_unit_id', $value))
            ->when($filters['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->when($filters['effective_on'] ?? null, fn ($query, $value) => $query
                ->whereDate('effective_from', '<=', $value)
                ->where(fn ($inner) => $inner->whereNull('effective_to')->orWhereDate('effective_to', '>=', $value)))
            ->orderByDesc('effective_from')->orderByDesc('version_number')
            ->paginate($this->pagination->defaultPerPage($filters['per_page'] ?? null));
    }

    public function projects(User $user): Collection
    {
        $query = Project::query()->select(['id', 'company_id', 'code', 'name', 'city', 'status']);
        $this->companyScope->apply($query, $user);

        return $query->orderBy('code')->get();
    }

    public function allUnits(User $user): Collection
    {
        $query = ProjectUnit::query()->with('project:id,code,name');
        $this->companyScope->apply($query, $user);

        return $query->orderBy('unit_code')->get();
    }

    public function unitTypes(User $user): array
    {
        $query = ProjectUnit::query()->select('unit_type')->distinct()->whereNotNull('unit_type');
        $this->companyScope->apply($query, $user);

        return $query->orderBy('unit_type')->pluck('unit_type')->all();
    }

    public function unitSummary(User $user): array
    {
        $query = ProjectUnit::query()->selectRaw('status, count(*) as aggregate_count')->groupBy('status');
        $this->companyScope->apply($query, $user);

        return $query->orderBy('status')->pluck('aggregate_count', 'status')->all();
    }
}
