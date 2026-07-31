<?php

namespace App\Domain\Sales\Services;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Partner;
use App\Models\Project;
use App\Models\ProjectUnit;
use App\Models\User;
use App\Services\Security\CompanyScopeService;
use App\Support\PaginationPolicy;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class BookingRegister
{
    public function __construct(
        private readonly CompanyScopeService $companyScope,
        private readonly PaginationPolicy $pagination,
    ) {}

    /** @param array<string, mixed> $filters */
    public function bookings(User $user, array $filters): LengthAwarePaginator
    {
        $query = Booking::query()->with(['company', 'project', 'unit', 'unitPriceVersion', 'customer', 'lead', 'partner', 'bookedBy', 'paymentSchedules']);
        $this->companyScope->apply($query, $user);

        return $query
            ->when($filters['project_id'] ?? null, fn ($query, $value) => $query->where('project_id', $value))
            ->when($filters['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->when($filters['customer_id'] ?? null, fn ($query, $value) => $query->where('customer_id', $value))
            ->latest('booked_on')
            ->latest()
            ->paginate($this->pagination->defaultPerPage($filters['per_page'] ?? null));
    }

    public function bookableUnits(User $user): Collection
    {
        $query = ProjectUnit::query()
            ->with('project:id,code,name')
            ->where(fn ($query) => $query->where('status', 'available')->orWhere(fn ($inner) => $inner
                ->where('status', 'reserved')->whereNotNull('reserved_until')->where('reserved_until', '<', now())));
        $this->companyScope->apply($query, $user);

        return $query->orderBy('unit_code')->get();
    }

    public function leads(User $user): Collection
    {
        $query = Lead::query()
            ->with(['customer:id,code,name,email,phone', 'project:id,code,name', 'partner:id,code,name'])
            ->whereNotIn('status', ['won', 'lost']);
        $this->companyScope->apply($query, $user);

        return $query->orderBy('lead_code')->get();
    }

    public function projects(User $user): Collection
    {
        $query = Project::query()->select(['id', 'company_id', 'code', 'name']);
        $this->companyScope->apply($query, $user);

        return $query->orderBy('code')->get();
    }

    public function customers(User $user): Collection
    {
        return Customer::query()
            ->where(fn ($query) => $query
                ->whereHas('leads', fn ($leads) => $this->companyScope->apply($leads, $user))
                ->orWhereHas('bookings', fn ($bookings) => $this->companyScope->apply($bookings, $user)))
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'email', 'phone']);
    }

    public function partners(): Collection
    {
        return Partner::query()->where('status', 'active')->orderBy('name')->get(['id', 'code', 'name', 'partner_type']);
    }
}
