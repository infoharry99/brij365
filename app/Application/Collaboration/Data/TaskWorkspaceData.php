<?php

namespace App\Application\Collaboration\Data;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use App\Models\SystemSetting;
use App\Models\WorkTask;

final readonly class TaskWorkspaceData
{
    /** @param LengthAwarePaginator<int, \App\Models\WorkTask> $tasks @param array<string,mixed> $filters */
    public function __construct(
        public LengthAwarePaginator $tasks,
        public array $filters,
        public Collection $companies,
        public Collection $projects,
        public Collection $users,
        public array $statuses,
        public array $priorities,
        public array $moduleContexts,
        public bool $canCreate,
        public bool $canManage,
        public array $summary,
        public array $scopeCounts,
        public array $board,
        public Collection $activity,
        public Collection $workload,
        public array $completionTrend,
        public array $statusDistribution,
        public Collection $approvalQueue,
        public array $permissionMatrix,
        public ?WorkTask $selectedTask,
        public ?SystemSetting $taskSetting,
        public array $templates,
        public array $transitionTargets,
    ) {}
}
