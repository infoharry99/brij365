<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\EmployeeMovementWorkspaceData;
use App\Domain\Hr\Services\EmployeeMovementPresenter;
use App\Domain\Hr\Services\EmployeeMovementRegister;
use App\Domain\Hr\Services\EmployeeProfileNavigation;
use App\Domain\Hr\Services\EmployeeRegister;
use App\Models\Employee;
use App\Models\EmployeeMovement;
use App\Models\User;

final class ListEmployeeMovementWorkspace
{
    public function __construct(
        private readonly EmployeeMovementRegister $movements,
        private readonly EmployeeMovementPresenter $presenter,
        private readonly EmployeeRegister $employees,
        private readonly EmployeeProfileNavigation $navigation,
    ) {}

    public function execute(Employee $employee, User $actor, array $filters): EmployeeMovementWorkspaceData
    {
        $rows = $this->movements->all($employee, $filters);
        $canUpdate = $actor->can('update', $employee);
        $models = $rows->getCollection();
        $movementActions = $models->mapWithKeys(fn (EmployeeMovement $movement): array => [
            $movement->id => [
                'canApprove' => $canUpdate
                    && $movement->status === 'pending'
                    && ! $movement->effective_on?->isFuture(),
            ],
        ])->all();

        $rows->setCollection($models->map(
            fn (EmployeeMovement $movement) => $this->presenter->row($movement, $employee, $actor),
        ));

        return new EmployeeMovementWorkspaceData(
            employee: $employee->loadMissing(['branch', 'project', 'manager']),
            movements: $rows,
            branches: $this->employees->branches($actor),
            projects: $this->employees->projects($actor),
            managers: $this->employees->managers($actor, $employee->id),
            abilities: ['canCreate' => $canUpdate],
            movementActions: $movementActions,
            profileNavigation: $this->navigation->links($employee, $actor, $this->navigation->isSelfServiceOnly($employee, $actor)),
        );
    }
}
