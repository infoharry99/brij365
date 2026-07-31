<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\ExitInterviewWorkspaceData;
use App\Domain\Hr\Services\ExitInterviewRegister;
use App\Models\EmployeeExitInterview;
use App\Models\User;

final class ListExitInterviewWorkspace
{
    public function __construct(private readonly ExitInterviewRegister $register) {}

    public function execute(User $actor, array $filters): ExitInterviewWorkspaceData
    {
        $interviews = $this->register->all($actor, $filters);

        return new ExitInterviewWorkspaceData(
            interviews: $interviews,
            summary: $this->register->summary($actor, $filters),
            employees: $this->register->employees($actor),
            settlements: $this->register->settlements($actor),
            abilities: ['canCreate' => $actor->can('create', EmployeeExitInterview::class)],
            interviewActions: $interviews->getCollection()->mapWithKeys(fn (EmployeeExitInterview $interview): array => [
                $interview->id => [
                    'canSubmit' => $actor->can('submit', $interview),
                    'canReview' => $actor->can('review', $interview),
                    'canViewConfidential' => $actor->can('viewConfidential', $interview),
                ],
            ])->all(),
        );
    }
}
