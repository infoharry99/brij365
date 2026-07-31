<?php

namespace App\Domain\Hr\Services;

use App\Models\Employee;
use App\Models\EmployeeSeparationSettlement;
use App\Models\User;
use App\Services\Hr\EmployeeSeparationSettlementService;
use App\Services\Security\CompanyScopeService;
use App\Support\PaginationPolicy;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class SeparationSettlementRegister
{
    public function __construct(private readonly CompanyScopeService $scope, private readonly PaginationPolicy $pagination, private readonly EmployeeSeparationSettlementService $settlements) {}

    public function all(User $actor, array $filters): LengthAwarePaginator
    {
        $query = EmployeeSeparationSettlement::query()
            ->with($this->settlements->relations())
            ->when($actor->hasPermission('employee.self_service') && ! $actor->hasPermission('hr.view') && ! $actor->hasPermission('hr.manage') && ! $actor->hasPermission('finance.view') && ! $actor->hasPermission('finance.approve'), fn ($query) => $query->whereHas('employee', fn ($employeeQuery) => $employeeQuery->where('user_id', $actor->id)))
            ->when(isset($filters['employee_id']), fn ($query) => $query->where('employee_id', $filters['employee_id']))
            ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->when(isset($filters['separation_type']), fn ($query) => $query->where('separation_type', $filters['separation_type']))
            ->when(isset($filters['from']), fn ($query) => $query->whereDate('last_working_date', '>=', $filters['from']))
            ->when(isset($filters['to']), fn ($query) => $query->whereDate('last_working_date', '<=', $filters['to']))
            ->latest();

        $this->scope->apply($query, $actor);

        return $query->paginate($this->pagination->defaultPerPage($filters['per_page'] ?? null));
    }

    public function employees(User $actor): Collection
    {
        $query = Employee::query()->whereIn('status', ['active', 'on_notice'])->orderBy('name');
        $this->scope->apply($query, $actor);

        return $query->get(['id', 'employee_code', 'name', 'designation', 'department', 'company_id']);
    }
}
