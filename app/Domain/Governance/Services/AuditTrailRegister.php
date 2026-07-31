<?php

namespace App\Domain\Governance\Services;

use App\Models\AuditEvent;
use App\Models\User;
use App\Services\Governance\ManagementReportService;
use App\Services\Governance\ReportLimitPolicy;
use App\Support\PaginationPolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class AuditTrailRegister
{
    public function __construct(
        private readonly ManagementReportService $reports,
        private readonly PaginationPolicy $pagination,
        private readonly ReportLimitPolicy $limits,
    ) {}

    public function events(User $actor, array $filters): LengthAwarePaginator
    {
        return $this->filteredQuery($actor, $filters)
            ->orderByDesc('created_at')
            ->paginate($this->pagination->largePerPage())
            ->withQueryString();
    }

    public function exportEvents(User $actor, array $filters): Collection
    {
        return $this->filteredQuery($actor, $filters)
            ->orderByDesc('created_at')
            ->limit($this->limits->maxExportRows())
            ->get();
    }

    public function eventTypes(User $actor): Collection
    {
        return $this->reports->auditQuery($actor)->select('event_type')->distinct()->orderBy('event_type')->pluck('event_type');
    }

    public function auditableTypes(User $actor): Collection
    {
        return $this->reports->auditQuery($actor)->whereNotNull('auditable_type')->select('auditable_type')->distinct()->orderBy('auditable_type')->pluck('auditable_type');
    }

    public function users(User $actor): Collection
    {
        return User::query()->with('role')
            ->when(! $actor->hasPermission('*'), fn (Builder $query) => $query->where('company_id', $actor->company_id ?: 0))
            ->orderBy('name')->get();
    }

    private function filteredQuery(User $actor, array $filters): Builder
    {
        return $this->reports->auditQuery($actor)
            ->when($filters['event_type'] ?? null, fn (Builder $query, string $value) => $query->where('event_type', $value))
            ->when($filters['user_id'] ?? null, fn (Builder $query, int $value) => $query->where('user_id', $value))
            ->when($filters['auditable_type'] ?? null, fn (Builder $query, string $value) => $query->where('auditable_type', $value))
            ->when($filters['auditable_id'] ?? null, fn (Builder $query, int $value) => $query->where('auditable_id', $value))
            ->when($filters['request_method'] ?? null, fn (Builder $query, string $value) => $query->where('request_method', strtoupper($value)))
            ->when($filters['request_id'] ?? null, fn (Builder $query, string $value) => $query->where('request_id', $value))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $value) => $query->whereDate('created_at', '>=', $value))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $value) => $query->whereDate('created_at', '<=', $value))
            ->when($filters['search'] ?? null, function (Builder $query, string $term): void {
                $like = '%'.$term.'%';
                $query->where(fn (Builder $nested) => $nested->where('event_type', 'like', $like)->orWhere('action', 'like', $like));
            });
    }
}
