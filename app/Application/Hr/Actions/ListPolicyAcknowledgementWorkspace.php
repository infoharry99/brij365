<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\PolicyAcknowledgementWorkspaceData;
use App\Domain\Hr\Services\PolicyAcknowledgementRegister;
use App\Models\EmployeePolicyAcknowledgement;
use App\Models\Employee;
use App\Models\User;

final class ListPolicyAcknowledgementWorkspace
{
    public function __construct(private readonly PolicyAcknowledgementRegister $register) {}

    public function execute(User $actor, array $filters): PolicyAcknowledgementWorkspaceData
    {
        $data = $this->register->all($actor, $filters);

        return new PolicyAcknowledgementWorkspaceData(
            acknowledgements: $data->acknowledgements,
            policies: $data->policies,
            employees: $this->register->employees($actor),
            currentEmployee: $actor->employee,
            abilities: ['canAcknowledge' => $actor->can('create', EmployeePolicyAcknowledgement::class) && $actor->employee !== null],
            selfService: ! $actor->can('viewAny', Employee::class),
        );
    }
}
