<?php

namespace App\Domain\Partner\Services;

use App\Models\Booking;
use App\Models\Lead;
use App\Models\User;
use App\Services\Partner\PartnerScopeService;
use App\Support\PaginationPolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final class PartnerPortalRegister
{
    public function __construct(
        private readonly PartnerScopeService $scope,
        private readonly PaginationPolicy $pagination,
    ) {}

    public function leads(User $actor, array $filters): LengthAwarePaginator
    {
        $partnerIds = $this->scope->activePartnerIdsForUser($actor);

        return Lead::query()
            ->with(['company', 'project', 'customer', 'partner', 'owner'])
            ->whereIn('partner_id', $partnerIds ?: [0])
            ->when($filters['stage'] ?? null, fn (Builder $query, string $stage) => $query->where('stage', $stage))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['project_id'] ?? null, fn (Builder $query, int $projectId) => $query->where('project_id', $projectId))
            ->latest()
            ->paginate($this->pagination->defaultPerPage($filters['per_page'] ?? null))
            ->withQueryString();
    }

    public function bookings(User $actor, array $filters): LengthAwarePaginator
    {
        $partnerIds = $this->scope->activePartnerIdsForUser($actor);

        return Booking::query()
            ->with(['company', 'project', 'unit', 'customer', 'lead', 'partner', 'bookedBy', 'paymentSchedules'])
            ->whereIn('partner_id', $partnerIds ?: [0])
            ->when($filters['project_id'] ?? null, fn (Builder $query, int $projectId) => $query->where('project_id', $projectId))
            ->when($filters['customer_id'] ?? null, fn (Builder $query, int $customerId) => $query->where('customer_id', $customerId))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->latest('booked_on')
            ->latest()
            ->paginate($this->pagination->defaultPerPage($filters['per_page'] ?? null))
            ->withQueryString();
    }
}
