<?php

namespace App\Domain\Projects\Services;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Project;
use App\Models\User;
use App\Services\Security\CompanyScopeService;
use App\Support\PaginationPolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class ProjectWorkspaceRegister
{
    public function __construct(
        private readonly CompanyScopeService $companyScope,
        private readonly PaginationPolicy $pagination,
    ) {}

    /** @param array<string, mixed> $filters */
    public function projects(User $user, array $filters): LengthAwarePaginator
    {
        $query = Project::query()
            ->with([
                'company:id,code,name,status',
                'branch:id,company_id,code,name,city,state',
                'teamAssignments' => fn ($query) => $query->with(['user:id,name,email', 'employee:id,employee_code,name'])->latest('id'),
            ])
            ->withCount(['units', 'bookings', 'collectionReceipts as approved_collections_count' => fn ($query) => $query->where('status', 'approved')])
            ->withSum(['bookings as booked_revenue_sum' => fn ($query) => $query->whereIn('status', ['confirmed', 'agreement_pending', 'registered'])], 'net_receivable')
            ->withSum(['collectionReceipts as approved_collections_sum' => fn ($query) => $query->where('status', 'approved')], 'amount');
        $this->companyScope->apply($query, $user);

        return $query
            ->when($filters['company_id'] ?? null, fn (Builder $query, $value) => $query->where('company_id', $value))
            ->when($filters['branch_id'] ?? null, fn (Builder $query, $value) => $query->where('branch_id', $value))
            ->when($filters['status'] ?? null, fn (Builder $query, $value) => $query->where('status', $value))
            ->when($filters['project_type'] ?? null, fn (Builder $query, $value) => $query->where('project_type', $value))
            ->when($filters['search'] ?? null, fn (Builder $query, $value) => $query->where(fn (Builder $inner) => $inner
                ->where('code', 'like', "%{$value}%")
                ->orWhere('name', 'like', "%{$value}%")
                ->orWhere('city', 'like', "%{$value}%")))
            ->orderBy('code')
            ->paginate($this->pagination->defaultPerPage($filters['per_page'] ?? null));
    }

    public function companies(User $user): Collection
    {
        $query = Company::query()->where('status', 'active')->orderBy('code');
        $this->companyScope->apply($query, $user, 'id');

        return $query->get(['id', 'code', 'name', 'state', 'status']);
    }

    public function branches(User $user): Collection
    {
        $query = Branch::query()->where('status', 'active')->orderBy('code');
        $this->companyScope->apply($query, $user);

        return $query->get(['id', 'company_id', 'code', 'name', 'city', 'state']);
    }

    public function users(User $user): Collection
    {
        $query = User::query()->with('employee:id,user_id,employee_code,name')->where('status', 'active')->orderBy('name');
        $this->companyScope->apply($query, $user);

        return $query->get(['id', 'company_id', 'name', 'email']);
    }

    public function employees(User $user): Collection
    {
        $query = Employee::query()->orderBy('employee_code');
        $this->companyScope->apply($query, $user);

        return $query->get(['id', 'company_id', 'user_id', 'employee_code', 'name']);
    }
}
