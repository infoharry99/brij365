<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\LifecycleWorkspaceData;
use App\Domain\Hr\Services\EmployeeLifecycleRegister;
use App\Models\User;

final class ListLifecycleWorkspace
{
    public function __construct(private readonly EmployeeLifecycleRegister $register) {}

    public function execute(User $actor, array $filters): LifecycleWorkspaceData
    {
        return new LifecycleWorkspaceData(
            summary: $this->register->summary($actor, $filters),
            events: $this->register->events($actor, $filters),
            employees: $this->register->employees($actor),
            departments: $this->register->departments($actor),
            filters: $filters,
        );
    }
}
