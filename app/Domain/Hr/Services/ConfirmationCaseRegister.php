<?php

namespace App\Domain\Hr\Services;

use App\Models\Employee;
use App\Models\EmployeeConfirmationCase;
use App\Models\User;
use App\Services\Hr\EmployeeConfirmationService;
use App\Services\Security\CompanyScopeService;
use App\Support\PaginationPolicy;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class ConfirmationCaseRegister
{
    public function __construct(
        private readonly CompanyScopeService $scope,
        private readonly PaginationPolicy $pagination,
        private readonly EmployeeConfirmationService $confirmation,
    ) {}

    public function cases(User $actor, array $filters): LengthAwarePaginator
    {
        $employee = $actor->employee;
        $query = EmployeeConfirmationCase::query()
            ->with($this->confirmation->relations())
            ->when($actor->hasPermission('employee.self_service') && ! $actor->hasPermission('hr.view') && ! $actor->hasPermission('hr.manage') && ! $actor->hasPermission('performance.manage'), fn ($query) => $query->where('employee_id', $employee?->id ?? 0))
            ->when($actor->hasPermission('performance.manage') && ! $actor->hasPermission('hr.view') && ! $actor->hasPermission('hr.manage'), function ($query) use ($employee): void {
                $query->where(fn ($nested) => $nested->where('employee_id', $employee?->id ?? 0)->orWhere('manager_employee_id', $employee?->id ?? 0));
            })
            ->when(isset($filters['employee_id']), fn ($query) => $query->where('employee_id', $filters['employee_id']))
            ->when(isset($filters['manager_employee_id']), fn ($query) => $query->where('manager_employee_id', $filters['manager_employee_id']))
            ->when(isset($filters['department']), fn ($query) => $query->whereHas('employee', fn ($employeeQuery) => $employeeQuery->where('department', $filters['department'])))
            ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->when(isset($filters['due_from']), fn ($query) => $query->whereDate('review_due_on', '>=', $filters['due_from']))
            ->when(isset($filters['due_to']), fn ($query) => $query->whereDate('review_due_on', '<=', $filters['due_to']))
            ->orderBy('review_due_on')
            ->latest();

        $this->scope->apply($query, $actor);

        return $query->paginate($this->pagination->defaultPerPage($filters['per_page'] ?? null));
    }

    public function employees(User $actor): Collection
    {
        $query = Employee::query()->where('status', 'active')->orderBy('name');
        $this->scope->apply($query, $actor);

        return $query->get(['id', 'employee_code', 'name', 'designation', 'department', 'manager_employee_id', 'company_id']);
    }

    public function departments(User $actor): Collection
    {
        $query = Employee::query()->whereNotNull('department')->distinct()->orderBy('department');
        $this->scope->apply($query, $actor);

        return $query->pluck('department');
    }
}
