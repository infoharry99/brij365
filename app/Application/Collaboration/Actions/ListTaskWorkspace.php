<?php

namespace App\Application\Collaboration\Actions;

use App\Application\Collaboration\Data\TaskWorkspaceData;
use App\Domain\Collaboration\Services\CollaborationWorkspaceOptions;
use App\Models\User;
use App\Models\WorkTask;
use App\Services\Collaboration\CollaborationService;
use App\Support\PaginationPolicy;
use App\Domain\Collaboration\Services\TaskWorkspaceRegister;
use App\Domain\Collaboration\Services\TaskPeopleCandidates;

final class ListTaskWorkspace
{
    public function __construct(
        private readonly CollaborationService $collaboration,
        private readonly CollaborationWorkspaceOptions $options,
        private readonly PaginationPolicy $pagination,
        private readonly TaskWorkspaceRegister $workspace,
        private readonly TaskPeopleCandidates $taskPeople,
    ) {}

    /** @param array<string,mixed> $filters */
    public function execute(User $user, array $filters): TaskWorkspaceData
    {
        $workspace = $this->workspace->workspace($user, $filters);

        return new TaskWorkspaceData(
            tasks: $workspace['tasks'],
            filters: $filters,
            companies: $this->options->companies($user),
            projects: $this->options->projects($user),
            users: $this->taskPeople->forActor($user, $workspace['selected_task']),
            statuses: $this->options->taskStatuses(),
            priorities: $this->options->taskPriorities(),
            moduleContexts: $this->options->taskModuleContexts(),
            canCreate: $user->can('create', WorkTask::class),
            canManage: $user->hasPermission('collaboration.manage'),
            summary: $workspace['summary'],
            scopeCounts: $workspace['scope_counts'],
            board: $workspace['board'],
            activity: $workspace['activity'],
            workload: $workspace['workload'],
            completionTrend: $workspace['completion_trend'],
            statusDistribution: $workspace['status_distribution'],
            approvalQueue: $workspace['approval_queue'],
            permissionMatrix: $workspace['permission_matrix'],
            selectedTask: $workspace['selected_task'],
            taskSetting: $workspace['task_setting'],
            templates: $workspace['templates'],
            transitionTargets: $workspace['transition_targets'],
        );
    }
}
