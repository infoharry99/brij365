<?php

namespace App\Domain\Payroll\Services;

use App\Application\Payroll\Data\EmployeeTaxProfilePageData;
use App\Application\Payroll\Data\EmployeeTaxProfileReviewWorkspaceData;
use App\Models\Employee;
use App\Models\EmployeeTaxProfile;
use App\Models\ManagedDocument;
use App\Models\User;
use App\Services\Security\CompanyScopeService;
use App\Support\PaginationPolicy;
use Illuminate\Auth\Access\AuthorizationException;

final class EmployeeTaxInputRegister
{
    public function __construct(
        private readonly CompanyScopeService $companyScope,
        private readonly PaginationPolicy $pagination,
    ) {}

    public function own(User $user, string $financialYear): EmployeeTaxProfilePageData
    {
        $employee = Employee::query()
            ->with('user')
            ->where('user_id', $user->id)
            ->first();
        if ($employee === null || ! $user->can('create', [EmployeeTaxProfile::class, $employee])) {
            throw new AuthorizationException('Employee self-service tax inputs are not available.');
        }

        $profile = EmployeeTaxProfile::query()
            ->with(['employee.user', 'declarations.proofDocument', 'createdBy', 'submittedBy', 'verifiedBy', 'lockedBy', 'supersedes'])
            ->where('company_id', $employee->company_id)
            ->where('employee_id', $employee->id)
            ->where('financial_year', $financialYear)
            ->orderByDesc('version')
            ->orderByDesc('id')
            ->first();

        $documents = ManagedDocument::query()
            ->where('company_id', $employee->company_id)
            ->where('owner_type', 'employee')
            ->where('owner_id', $employee->id)
            ->whereIn('status', ['submitted', 'approved'])
            ->where('is_current', true)
            ->orderBy('title')
            ->get()
            ->filter(fn (ManagedDocument $document): bool => $user->can('view', $document))
            ->values();

        return EmployeeTaxProfilePageData::from($employee, $profile, $documents, $financialYear);
    }

    /** @param array<string, mixed> $filters */
    public function review(User $user, array $filters, ?EmployeeTaxProfile $selected = null): EmployeeTaxProfileReviewWorkspaceData
    {
        if (! $user->can('viewAny', EmployeeTaxProfile::class)) {
            throw new AuthorizationException('Employee tax-profile review is not available.');
        }

        $query = EmployeeTaxProfile::query()
            ->with(['employee.user', 'createdBy', 'submittedBy', 'verifiedBy', 'lockedBy'])
            ->withCount('declarations');
        $this->companyScope->apply($query, $user);
        $query
            ->when($filters['status'] ?? null, fn ($builder, $status) => $builder->where('status', $status))
            ->when($filters['financial_year'] ?? null, fn ($builder, $financialYear) => $builder->where('financial_year', $financialYear))
            ->when($filters['employee_id'] ?? null, fn ($builder, $employeeId) => $builder->where('employee_id', $employeeId))
            ->orderByDesc('updated_at')
            ->orderByDesc('id');

        $profiles = $query->paginate(
            $this->pagination->workspacePerPage(isset($filters['per_page']) ? (int) $filters['per_page'] : null),
        )->withQueryString();
        if ($selected !== null) {
            abort_unless($user->can('view', $selected), 403);
            $selected->load(['employee.user', 'declarations.proofDocument', 'createdBy', 'submittedBy', 'verifiedBy', 'lockedBy', 'supersedes']);
        }

        return new EmployeeTaxProfileReviewWorkspaceData($profiles, $filters, $selected);
    }
}
