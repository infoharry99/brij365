<?php

namespace App\Domain\Hr\Services;

use App\Models\Employee;
use App\Models\EmployeeExitInterview;
use App\Models\EmployeeSeparationSettlement;
use App\Models\User;
use App\Services\Hr\EmployeeExitInterviewService;
use App\Services\Security\CompanyScopeService;
use App\Support\PaginationPolicy;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class ExitInterviewRegister
{
    public function __construct(private readonly CompanyScopeService $scope, private readonly PaginationPolicy $pagination, private readonly EmployeeExitInterviewService $interviews) {}

    public function all(User $actor, array $filters): LengthAwarePaginator
    {
        $query = EmployeeExitInterview::query()
            ->with($this->interviews->relations())
            ->when($actor->hasPermission('employee.self_service') && ! $actor->hasPermission('hr.view') && ! $actor->hasPermission('hr.manage'), fn ($query) => $query->whereHas('employee', fn ($employeeQuery) => $employeeQuery->where('user_id', $actor->id)))
            ->when(isset($filters['employee_id']), fn ($query) => $query->where('employee_id', $filters['employee_id']))
            ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->when(isset($filters['separation_reason']), fn ($query) => $query->where('separation_reason', $filters['separation_reason']))
            ->when(isset($filters['rehire_recommendation']), fn ($query) => $query->where('rehire_recommendation', $filters['rehire_recommendation']))
            ->when(isset($filters['from']), fn ($query) => $query->whereDate('interview_due_on', '>=', $filters['from']))
            ->when(isset($filters['to']), fn ($query) => $query->whereDate('interview_due_on', '<=', $filters['to']))
            ->latest();

        $this->scope->apply($query, $actor);

        return $query->paginate($this->pagination->defaultPerPage($filters['per_page'] ?? null));
    }

    public function summary(User $actor, array $filters): array
    {
        return $this->interviews->summary($filters, $actor);
    }

    public function employees(User $actor): Collection
    {
        $query = Employee::query()->whereIn('status', ['active', 'on_notice', 'separated'])->orderBy('name');
        $this->scope->apply($query, $actor);

        return $query->get(['id', 'employee_code', 'name', 'designation', 'department', 'company_id']);
    }

    public function settlements(User $actor): Collection
    {
        $query = EmployeeSeparationSettlement::query()->with('employee:id,employee_code,name')->latest('last_working_date');
        $this->scope->apply($query, $actor);

        return $query->limit(100)->get(['id', 'employee_id', 'settlement_number', 'status', 'last_working_date', 'company_id']);
    }
}
