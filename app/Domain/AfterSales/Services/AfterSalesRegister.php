<?php

namespace App\Domain\AfterSales\Services;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\MaintenanceWorkOrder;
use App\Models\Project;
use App\Models\ServiceTicket;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Security\CompanyScopeService;
use App\Support\PaginationPolicy;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class AfterSalesRegister
{
    public function __construct(
        private readonly CompanyScopeService $scope,
        private readonly PaginationPolicy $pagination,
    ) {}

    public function isBuyerPortalUser(User $actor): bool
    {
        return $actor->role?->slug === 'buyer' && $actor->hasPermission('buyer.view');
    }

    public function tickets(User $actor, array $filters): LengthAwarePaginator
    {
        $query = ServiceTicket::query()
            ->with(['booking', 'project', 'unit', 'customer', 'raisedBy', 'assignedTo', 'closedBy', 'workOrders.assignedTo', 'workOrders.vendor']);

        if ($this->isBuyerPortalUser($actor)) {
            $query->whereHas('customer', fn ($customerQuery) => $customerQuery->where('portal_user_id', $actor->id));
        } else {
            $this->scope->apply($query, $actor);
        }

        return $query
            ->when($filters['project_id'] ?? null, fn ($query, int $id) => $query->where('project_id', $id))
            ->when($filters['booking_id'] ?? null, fn ($query, int $id) => $query->where('booking_id', $id))
            ->when($filters['customer_id'] ?? null, fn ($query, int $id) => $query->where('customer_id', $id))
            ->when($filters['assigned_to_user_id'] ?? null, fn ($query, int $id) => $query->where('assigned_to_user_id', $id))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['priority'] ?? null, fn ($query, string $priority) => $query->where('priority', $priority))
            ->when($filters['category'] ?? null, fn ($query, string $category) => $query->where('category', $category))
            ->orderByRaw("case when status in ('open', 'assigned', 'in_progress') then 0 else 1 end")
            ->orderBy('sla_due_at')
            ->paginate($this->pagination->workspacePerPage($filters['per_page'] ?? null))
            ->withQueryString();
    }

    public function workOrders(User $actor, array $filters): LengthAwarePaginator
    {
        return $this->scope->apply(
            MaintenanceWorkOrder::query()->with(['serviceTicket', 'unit', 'assignedTo', 'vendor']),
            $actor,
        )
            ->when($filters['service_ticket_id'] ?? null, fn ($query, int $id) => $query->where('service_ticket_id', $id))
            ->when($filters['assigned_to_user_id'] ?? null, fn ($query, int $id) => $query->where('assigned_to_user_id', $id))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate($this->pagination->workspacePerPage($filters['per_page'] ?? null))
            ->withQueryString();
    }

    public function projects(User $actor): Collection
    {
        return $this->scope->apply(Project::query()->select(['id', 'company_id', 'code', 'name', 'status'])->where('status', 'active'), $actor)
            ->orderBy('code')->get();
    }

    public function bookings(User $actor): Collection
    {
        $query = Booking::query()->with(['project:id,code,name', 'unit:id,unit_code', 'customer:id,code,name,portal_user_id'])->where('status', 'confirmed');

        if ($this->isBuyerPortalUser($actor)) {
            $query->whereHas('customer', fn ($customerQuery) => $customerQuery->where('portal_user_id', $actor->id));
        } else {
            $this->scope->apply($query, $actor);
        }

        return $query->orderByDesc('booked_on')->orderBy('booking_code')->limit(150)->get();
    }

    public function customers(User $actor): Collection
    {
        $customerIds = $this->scope->apply(Booking::query()->select('customer_id')->whereNotNull('customer_id'), $actor);

        return Customer::query()->whereIn('id', $customerIds)->orderBy('name')->get(['id', 'code', 'name']);
    }

    public function assignees(User $actor): Collection
    {
        return $this->scope->apply(User::query()->select(['id', 'company_id', 'name', 'email', 'status'])->where('status', 'active'), $actor)
            ->orderBy('name')->get();
    }

    public function openTickets(User $actor): Collection
    {
        return $this->scope->apply(ServiceTicket::query()->with(['customer:id,code,name', 'unit:id,unit_code'])->whereIn('status', ['open', 'assigned', 'in_progress']), $actor)
            ->orderBy('ticket_number')->get();
    }

    public function vendors(User $actor): Collection
    {
        return $this->scope->apply(Vendor::query()->select(['id', 'company_id', 'vendor_code', 'name', 'vendor_type', 'status'])->where('status', 'active'), $actor)
            ->orderBy('name')->get();
    }
}
