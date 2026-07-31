<?php

namespace App\Domain\Hr\Services;

use App\Application\Hr\Data\PolicyAcknowledgementRegisterData;
use App\Models\Employee;
use App\Models\EmployeePolicyAcknowledgement;
use App\Models\User;
use App\Services\Hr\EmployeePolicyAcknowledgementService;
use App\Services\Security\CompanyScopeService;
use App\Support\PaginationPolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class PolicyAcknowledgementRegister
{
    public function __construct(private readonly CompanyScopeService $scope, private readonly PaginationPolicy $pagination, private readonly EmployeePolicyAcknowledgementService $policies) {}

    public function all(User $actor, array $filters): PolicyAcknowledgementRegisterData
    {
        $actorEmployee = $actor->employee;
        $hasInternalAccess = $actor->hasPermission('hr.manage') || $actor->hasPermission('audit.view') || $actor->hasPermission('*');
        $query = EmployeePolicyAcknowledgement::query()->with(['employee', 'acknowledgedBy'])
            ->when($filters['employee_id'] ?? null, fn (Builder $query, int $employeeId) => $query->where('employee_id', $employeeId))
            ->when($filters['policy_key'] ?? null, fn (Builder $query, string $policyKey) => $query->where('policy_key', $policyKey))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->latest('acknowledged_at');

        if ($hasInternalAccess) {
            $this->scope->apply($query, $actor);
        } else {
            $query->where('employee_id', $actorEmployee?->id ?? 0);
        }

        $acknowledgements = $query->paginate($this->pagination->workspacePerPage($filters['per_page'] ?? null));
        $employee = $hasInternalAccess && ($filters['employee_id'] ?? null) ? Employee::query()->find($filters['employee_id']) : $actorEmployee;
        $catalogue = $employee ? collect($this->policies->policyCatalogue($employee))->map(function (array $policy) use ($acknowledgements): array {
            $row = $acknowledgements->getCollection()->first(fn (EmployeePolicyAcknowledgement $acknowledgement): bool => $acknowledgement->policy_key === $policy['policy_key'] && (int) $acknowledgement->policy_version === (int) $policy['policy_version']);

            return array_merge($policy, ['status' => $row?->status ?? 'pending', 'acknowledged_at' => $row?->acknowledged_at?->toISOString(), 'acknowledgement_id' => $row?->id]);
        })->values()->all() : [];

        return new PolicyAcknowledgementRegisterData($acknowledgements, $catalogue);
    }

    public function employees(User $actor): Collection
    {
        $query = Employee::query()->orderBy('name');
        $this->scope->apply($query, $actor);

        return $query->get(['id', 'employee_code', 'name', 'department', 'company_id']);
    }
}
