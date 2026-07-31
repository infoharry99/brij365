<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\SeparationWorkspaceData;
use App\Domain\Hr\Services\EmployeeFieldVisibility;
use App\Domain\Hr\Services\SeparationSettlementRegister;
use App\Models\EmployeeSeparationSettlement;
use App\Models\User;

final class ListSeparationWorkspace
{
    public function __construct(
        private readonly SeparationSettlementRegister $register,
        private readonly EmployeeFieldVisibility $fieldVisibility,
    ) {}

    public function execute(User $actor, array $filters): SeparationWorkspaceData
    {
        $settlements = $this->register->all($actor, $filters);

        return new SeparationWorkspaceData(
            settlements: $settlements,
            employees: $this->register->employees($actor),
            abilities: ['canCreate' => $actor->can('create', EmployeeSeparationSettlement::class)],
            settlementActions: $settlements->getCollection()->mapWithKeys(fn (EmployeeSeparationSettlement $settlement): array => [$settlement->id => ['canHrApprove' => $actor->can('hrApprove', $settlement), 'canFinanceApprove' => $actor->can('financeApprove', $settlement), 'canComplete' => $actor->can('complete', $settlement)]])->all(),
            settlementCompensationVisibility: $settlements->getCollection()->mapWithKeys(fn (EmployeeSeparationSettlement $settlement): array => [
                $settlement->id => $settlement->employee !== null
                    && $this->fieldVisibility->canViewCompensation($actor, $settlement->employee),
            ])->all(),
        );
    }
}
