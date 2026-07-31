<?php

namespace App\Domain\Hr\Services;

use App\Models\Employee;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Project;
use App\Models\PayrollRunItem;
use App\Models\User;
use App\Services\Hr\EmployeeProfileService;
use App\Services\Security\CompanyScopeService;
use App\Support\PaginationPolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class EmployeeRegister
{
    public function __construct(
        private readonly EmployeeProfileService $profiles,
        private readonly EmployeeFieldVisibility $visibility,
        private readonly ActiveInternalUserEligibility $internalUsers,
        private readonly CompanyScopeService $scope,
        private readonly PaginationPolicy $pagination,
    ) {}

    public function query(User $user, array $filters, bool $includeDirectoryCompensation = false): Builder
    {
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();
        $canViewCompensation = $this->visibility->canViewCompensation($user);
        $columns = [
            'employees.id',
            'employees.company_id',
            'employees.branch_id',
            'employees.project_id',
            'employees.user_id',
            'employees.manager_employee_id',
            'employees.employee_code',
            'employees.name',
            'employees.designation',
            'employees.department',
            'employees.grade',
            'employees.employment_type',
            'employees.status',
            'employees.joined_on',
            'employees.statutory_state',
            'employees.lock_version',
            'employees.created_at',
            'employees.updated_at',
        ];

        if ($canViewCompensation) {
            $columns[] = 'employees.monthly_ctc';
        }

        $q = Employee::query()
            ->select($columns)
            ->with($this->profiles->relations())
            ->withCount([
                'directReports',
                'managedDocuments',
                'attendanceRecords as attendance_days_count' => fn (Builder $query) => $query->whereBetween('work_date', [$monthStart, $monthEnd]),
            ]);

        if ($includeDirectoryCompensation && $canViewCompensation) {
            $q->addSelect([
                'latest_approved_net_salary' => PayrollRunItem::query()
                    ->select('payroll_run_items.net_payable')
                    ->join('payroll_runs', 'payroll_runs.id', '=', 'payroll_run_items.payroll_run_id')
                    ->whereColumn('payroll_run_items.employee_id', 'employees.id')
                    ->where('payroll_runs.status', 'approved')
                    ->whereNull('payroll_runs.deleted_at')
                    ->orderByDesc('payroll_runs.period_year')
                    ->orderByDesc('payroll_runs.period_month')
                    ->limit(1),
            ]);
        }

        $q
            ->when($filters['company_id'] ?? null, fn ($q, $v) => $q->where('company_id', $v))
            ->when($filters['branch_id'] ?? null, fn ($q, $v) => $q->where('branch_id', $v))
            ->when($filters['project_id'] ?? null, fn ($q, $v) => $q->where('project_id', $v))
            ->when($filters['department'] ?? null, fn ($q, $v) => $q->where('department', $v))
            ->when($filters['designation'] ?? null, fn ($q, $v) => $q->where('designation', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['search'] ?? null, fn ($q, $v) => $q->where(fn ($n) => $n
                ->where('employee_code', 'like', "%{$v}%")
                ->orWhere('name', 'like', "%{$v}%")
                ->orWhere('department', 'like', "%{$v}%")
                ->orWhere('designation', 'like', "%{$v}%")
                ->orWhereHas('user', fn (Builder $userQuery) => $userQuery->where('email', 'like', "%{$v}%"))))
            ->orderBy('name');
        $this->scope->apply($q, $user);

        return $q;
    }

    public function paginate(User $user, array $filters, bool $includeDirectoryCompensation = false): LengthAwarePaginator
    {
        return $this->query($user, $filters, $includeDirectoryCompensation)->paginate($this->pagination->defaultPerPage($filters['per_page'] ?? null));
    }

    public function self(User $user): ?Employee
    {
        return Employee::query()->with($this->profiles->relations())->withCount(['directReports', 'managedDocuments'])->where('user_id', $user->id)->first();
    }

    public function detail(Employee $employee): Employee
    {
        return $employee->load($this->profiles->relations())->loadCount($this->detailCounts());
    }

    public function detailCounts(): array
    {
        return ['directReports', 'managedDocuments', 'assets', 'leaveRequests', 'attendanceRecords', 'payrollRunItems', 'taxDocuments', 'confirmationCases', 'separationSettlements', 'expenseClaims', 'loans', 'performanceReviews'];
    }

    public function companies(User $actor): Collection
    {
        return $this->scope->apply(Company::query()->where('status', 'active'), $actor, 'id')->orderBy('name')->get(['id', 'code', 'name']);
    }

    public function branches(User $actor): Collection
    {
        return $this->scope->apply(Branch::query()->where('status', 'active'), $actor)->orderBy('name')->get(['id', 'code', 'name', 'company_id']);
    }

    public function projects(User $actor): Collection
    {
        return $this->scope->apply(Project::query(), $actor)->orderBy('name')->get(['id', 'code', 'name', 'company_id']);
    }

    public function availableUsers(User $actor, ?int $employeeId = null): Collection
    {
        $candidates = $this->internalUsers->forActor($actor);
        $linkedEmployeeIds = Employee::query()
            ->whereNotNull('user_id')
            ->whereIn('user_id', $candidates->modelKeys())
            ->when($employeeId, fn (Builder $query) => $query->whereKeyNot($employeeId))
            ->pluck('user_id')
            ->map(fn ($id): int => (int) $id);

        return $candidates
            ->reject(fn (User $candidate): bool => $linkedEmployeeIds->contains((int) $candidate->id))
            ->values();
    }

    public function managers(User $actor, ?int $excludedEmployeeId = null): Collection
    {
        return $this->scope->apply(Employee::query()->where('status', 'active'), $actor)
            ->when($excludedEmployeeId, fn (Builder $query) => $query->whereKeyNot($excludedEmployeeId))
            ->orderBy('name')
            ->get(['id', 'employee_code', 'name', 'designation', 'department', 'company_id']);
    }

    public function departments(User $actor): Collection
    {
        return $this->scope->apply(Employee::query(), $actor)->whereNotNull('department')->distinct()->orderBy('department')->pluck('department');
    }

    public function designations(User $actor): Collection
    {
        return $this->scope->apply(Employee::query(), $actor)->whereNotNull('designation')->distinct()->orderBy('designation')->pluck('designation');
    }
}
