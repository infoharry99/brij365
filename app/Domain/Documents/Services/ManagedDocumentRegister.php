<?php

namespace App\Domain\Documents\Services;

use App\Domain\Payroll\Services\EmployeeTaxInputAccess;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\DocumentCategory;
use App\Models\Employee;
use App\Models\ManagedDocument;
use App\Models\Project;
use App\Models\User;
use App\Services\Security\CompanyScopeService;
use App\Support\PaginationPolicy;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class ManagedDocumentRegister
{
    public function __construct(
        private readonly CompanyScopeService $companyScope,
        private readonly PaginationPolicy $pagination,
        private readonly EmployeeTaxInputAccess $taxInputAccess,
    ) {}

    public function documents(User $actor, array $filters): LengthAwarePaginator
    {
        $query = $this->companyScope->apply(
            ManagedDocument::query()->with([
                'company',
                'category',
                'uploadedBy',
                'approvedBy',
                'employeeOwner:id,company_id,user_id,employee_code,name,department',
            ]),
            $actor,
        );

        if (! $this->taxInputAccess->canReview($actor)) {
            $ownEmployeeId = $this->taxInputAccess->hasAnyExplicit($actor, ['employee.self_service'])
                ? Employee::query()->where('user_id', $actor->id)->value('id')
                : null;
            $query->where(function ($documents) use ($ownEmployeeId): void {
                $documents->whereDoesntHave('taxDeclarations');
                if ($ownEmployeeId !== null) {
                    $documents->orWhere(function ($own) use ($ownEmployeeId): void {
                        $own->where('owner_type', 'employee')->where('owner_id', $ownEmployeeId);
                    });
                }
            });
        }

        return $query
            ->when($filters['owner_type'] ?? null, fn ($query, string $ownerType) => $query->where('owner_type', $ownerType))
            ->when($filters['owner_id'] ?? null, fn ($query, int $ownerId) => $query->where('owner_id', $ownerId))
            ->when($filters['document_category_id'] ?? null, fn ($query, int $categoryId) => $query->where('document_category_id', $categoryId))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['current_only'] ?? true, fn ($query) => $query->where('is_current', true))
            ->when($filters['expires_within_days'] ?? null, function ($query, int $days): void {
                $query->whereNotNull('expires_on')
                    ->whereDate('expires_on', '>=', now()->toDateString())
                    ->whereDate('expires_on', '<=', now()->addDays($days)->toDateString());
            })
            ->latest()
            ->paginate($this->pagination->defaultPerPage($filters['per_page'] ?? null))
            ->withQueryString();
    }

    public function categories(User $actor): Collection
    {
        $companyId = $this->companyScope->companyIdFor($actor);

        return DocumentCategory::query()
            ->where('is_active', true)
            ->where(function ($query) use ($actor, $companyId): void {
                $query->whereNull('company_id');
                if ($this->companyScope->hasUnrestrictedCompanyScope($actor)) {
                    $query->orWhereNotNull('company_id');
                } elseif ($companyId !== null) {
                    $query->orWhere('company_id', $companyId);
                }
            })
            ->orderBy('owner_type')
            ->orderBy('name')
            ->get();
    }

    public function projects(User $actor): Collection
    {
        return $this->companyScope->apply(Project::query()->orderBy('code'), $actor)->get();
    }

    public function bookings(User $actor): Collection
    {
        return $this->companyScope->apply(
            Booking::query()->with(['customer', 'unit'])->orderByDesc('id'),
            $actor,
        )->limit(250)->get();
    }

    public function customers(User $actor): Collection
    {
        $companyId = $this->companyScope->companyIdFor($actor);

        return Customer::query()
            ->when($companyId !== null, fn ($query) => $query->whereHas('bookings', fn ($bookingQuery) => $bookingQuery->where('company_id', $companyId)))
            ->orderBy('name')
            ->limit(250)
            ->get();
    }

    public function employees(User $actor): Collection
    {
        return $this->companyScope->apply(Employee::query()->orderBy('name'), $actor)
            ->limit(250)
            ->get();
    }
}
