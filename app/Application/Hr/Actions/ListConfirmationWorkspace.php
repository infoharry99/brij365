<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\ConfirmationWorkspaceData;
use App\Domain\Hr\Services\ConfirmationCaseRegister;
use App\Models\EmployeeConfirmationCase;
use App\Models\User;

final class ListConfirmationWorkspace
{
    public function __construct(private readonly ConfirmationCaseRegister $register) {}

    public function execute(User $actor, array $filters): ConfirmationWorkspaceData
    {
        $cases = $this->register->cases($actor, $filters);

        return new ConfirmationWorkspaceData(
            cases: $cases,
            employees: $this->register->employees($actor),
            departments: $this->register->departments($actor),
            abilities: ['canCreate' => $actor->can('create', EmployeeConfirmationCase::class)],
            caseActions: $cases->getCollection()->mapWithKeys(fn (EmployeeConfirmationCase $case): array => [$case->id => ['canRecommend' => $actor->can('recommend', $case), 'canDecide' => $actor->can('decide', $case)]])->all(),
        );
    }
}
