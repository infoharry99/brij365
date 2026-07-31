<?php

namespace App\Domain\Buyer\Services;

use App\Models\Booking;
use App\Models\CollectionReceipt;
use App\Models\Customer;
use App\Models\ManagedDocument;
use App\Models\PaymentRequest;
use App\Models\ServiceTicket;
use App\Models\User;
use App\Support\PaginationPolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final class BuyerPortalRegister
{
    public function __construct(private readonly PaginationPolicy $pagination) {}

    public function customer(User $actor): ?Customer
    {
        return $actor->customer()->first();
    }

    public function bookings(User $actor, array $filters): LengthAwarePaginator
    {
        $customer = $this->customer($actor);

        return Booking::query()
            ->with(['company', 'project', 'unit', 'customer', 'lead', 'partner', 'bookedBy', 'paymentSchedules'])
            ->where('customer_id', $customer?->id ?? 0)
            ->when($filters['booking_id'] ?? null, fn (Builder $query, int $id) => $query->whereKey($id))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->latest('booked_on')
            ->paginate($this->pagination->defaultPerPage($filters['per_page'] ?? null))
            ->withQueryString();
    }

    public function receipts(User $actor, array $filters): LengthAwarePaginator
    {
        $customer = $this->customer($actor);

        return CollectionReceipt::query()
            ->with(['company', 'project', 'booking', 'paymentSchedule', 'customer', 'collectedBy', 'approvedBy'])
            ->where('customer_id', $customer?->id ?? 0)
            ->where('status', 'approved')
            ->when($filters['booking_id'] ?? null, fn (Builder $query, int $id) => $query->where('booking_id', $id))
            ->latest('receipt_date')
            ->latest()
            ->paginate($this->pagination->defaultPerPage($filters['per_page'] ?? null))
            ->withQueryString();
    }

    public function paymentRequests(User $actor, array $filters): LengthAwarePaginator
    {
        $customer = $this->customer($actor);

        return PaymentRequest::query()
            ->with(['company', 'project', 'booking', 'paymentSchedule', 'customer', 'collectionReceipt', 'createdBy', 'paidBy'])
            ->where('customer_id', $customer?->id ?? 0)
            ->when($filters['booking_id'] ?? null, fn (Builder $query, int $id) => $query->where('booking_id', $id))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->latest()
            ->paginate($this->pagination->defaultPerPage($filters['per_page'] ?? null))
            ->withQueryString();
    }

    public function documents(User $actor, array $filters): LengthAwarePaginator
    {
        $customer = $this->customer($actor);
        $bookingIds = $customer
            ? Booking::query()->where('customer_id', $customer->id)->pluck('id')->map(fn ($id): int => (int) $id)->all()
            : [];

        return $this->documentQuery($customer, $bookingIds)
            ->with(['company', 'category', 'uploadedBy', 'approvedBy'])
            ->when($filters['owner_type'] ?? null, fn (Builder $query, string $type) => $query->where('owner_type', $type))
            ->latest('approved_at')
            ->latest()
            ->paginate($this->pagination->defaultPerPage($filters['per_page'] ?? null))
            ->withQueryString();
    }

    public function tickets(User $actor, array $filters): LengthAwarePaginator
    {
        $customer = $this->customer($actor);

        return ServiceTicket::query()
            ->with(['booking', 'project', 'unit', 'customer', 'raisedBy', 'assignedTo', 'closedBy', 'workOrders.assignedTo', 'workOrders.vendor'])
            ->where('customer_id', $customer?->id ?? 0)
            ->when($filters['booking_id'] ?? null, fn (Builder $query, int $id) => $query->where('booking_id', $id))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['category'] ?? null, fn (Builder $query, string $category) => $query->where('category', $category))
            ->when($filters['priority'] ?? null, fn (Builder $query, string $priority) => $query->where('priority', $priority))
            ->orderByRaw("case when status in ('open', 'assigned', 'in_progress') then 0 else 1 end")
            ->orderBy('sla_due_at')
            ->paginate($this->pagination->defaultPerPage($filters['per_page'] ?? null))
            ->withQueryString();
    }

    private function documentQuery(?Customer $customer, array $bookingIds): Builder
    {
        return ManagedDocument::query()
            ->where('status', 'approved')
            ->where('is_current', true)
            ->where(function (Builder $query) use ($customer, $bookingIds): void {
                if ($customer) {
                    $query->where(fn (Builder $customerQuery) => $customerQuery
                        ->where('owner_type', 'customer')
                        ->where('owner_id', $customer->id));
                } else {
                    $query->whereRaw('1 = 0');
                }

                if ($bookingIds !== []) {
                    $query->orWhere(fn (Builder $bookingQuery) => $bookingQuery
                        ->where('owner_type', 'booking')
                        ->whereIn('owner_id', $bookingIds));
                }
            });
    }
}
