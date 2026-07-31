<?php

namespace App\Domain\Collaboration\Services;

use App\Domain\Collaboration\TaskLifecycle;
use App\Models\Employee;
use App\Models\ProjectTeamAssignment;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\WorkTask;
use App\Services\Collaboration\CollaborationService;
use App\Support\PaginationPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class TaskWorkspaceRegister
{
    public function __construct(
        private readonly CollaborationService $collaboration,
        private readonly PaginationPolicy $pagination,
        private readonly TaskTransitionService $transitions,
    ) {}

    /**
     * @param array<string, mixed> $filters
     * @return array{
     *   tasks: LengthAwarePaginator<int, WorkTask>,
     *   summary: array<string, int|float>,
     *   scope_counts: array<string, int>,
     *   board: array<string, Collection<int, WorkTask>>,
     *   activity: Collection<int, array<string, mixed>>,
     *   workload: Collection<int, array<string, mixed>>,
     *   completion_trend: array<int, array{label:string,count:int}>,
     *   status_distribution: array<string,int>,
     *   approval_queue: Collection<int, WorkTask>,
     *   permission_matrix: array<int, array<string,mixed>>,
     *   selected_task: ?WorkTask,
     *   task_setting: ?SystemSetting,
     *   templates: array<int, array<string, mixed>>
     * }
     */
    public function workspace(User $user, array $filters): array
    {
        $scope = (string) ($filters['scope'] ?? 'dashboard');
        $baseFilters = collect($filters)
            ->except(['scope', 'view', 'task_id', 'sort', 'direction', 'page'])
            ->all();

        $unscoped = $this->collaboration->taskIndexQuery($user, $baseFilters);
        $scopeCounts = $this->scopeCounts($user, $unscoped);
        $summary = $this->summary($unscoped);

        $visible = clone $unscoped;
        $this->applyScope($visible, $user, $scope);
        $this->applySorting($visible, $filters);

        $boardTasks = (clone $visible)->limit(500)->get();

        $tasks = $visible
            ->paginate($this->pagination->workspacePerPage())
            ->withQueryString();

        $taskSetting = SystemSetting::query()
            ->where('setting_key', 'collaboration.task_settings')
            ->where('status', 'active')
            ->where(function (Builder $query) use ($user): void {
                $query->where('company_id', $user->company_id)->orWhereNull('company_id');
            })
            ->orderByRaw('case when company_id is null then 1 else 0 end')
            ->orderByDesc('version')
            ->first();

        $selectedTask = $this->selectedTask($user, $filters);
        $transitionTasks = $boardTasks
            ->concat($tasks->getCollection())
            ->when($selectedTask, fn (Collection $items) => $items->push($selectedTask))
            ->unique('id');

        return [
            'tasks' => $tasks,
            'summary' => $summary,
            'scope_counts' => $scopeCounts,
            'board' => $this->board($boardTasks),
            'activity' => $this->activity(clone $unscoped, (string) ($filters['activity_filter'] ?? 'all')),
            'workload' => $this->workload(clone $unscoped),
            'completion_trend' => $this->completionTrend(clone $unscoped),
            'status_distribution' => $this->statusDistribution(clone $unscoped),
            'approval_queue' => $this->approvalQueue(clone $unscoped),
            'permission_matrix' => $this->permissionMatrix(),
            'selected_task' => $selectedTask,
            'task_setting' => $taskSetting,
            'templates' => array_values((array) data_get($taskSetting?->value, 'templates', [])),
            'transition_targets' => $transitionTasks->mapWithKeys(function (WorkTask $task) use ($user): array {
                return [$task->id => $user->can('updateStatus', $task)
                    ? $this->transitions->allowedTargets($task, $user)
                    : []];
            })->all(),
        ];
    }

    /** @return array<string, int|float> */
    private function summary(Builder $query): array
    {
        $now = CarbonImmutable::now();
        $today = $now->toDateString();
        $weekEnd = $now->endOfWeek()->toDateString();

        $total = (clone $query)->count();
        $completed = (clone $query)->where('status', 'completed')->count();
        $inProgress = (clone $query)->where('status', 'in_progress')->count();
        $overdue = (clone $query)
            ->whereNotIn('status', TaskLifecycle::terminalStatuses())
            ->whereNotNull('due_at')
            ->where('due_at', '<', $now)
            ->count();

        return [
            'total' => $total,
            'open' => (clone $query)->where('status', 'open')->count(),
            'in_progress' => $inProgress,
            'completed' => $completed,
            'blocked' => (clone $query)->whereIn('status', ['on_hold', 'waiting_info', 'waiting_dependency', 'blocked', 'rejected'])->count(),
            'cancelled' => (clone $query)->whereIn('status', ['rejected', 'cancelled'])->count(),
            'critical' => (clone $query)->where('priority', 'critical')->count(),
            'overdue' => $overdue,
            'due_today' => (clone $query)->whereDate('due_at', $today)->whereNotIn('status', TaskLifecycle::terminalStatuses())->count(),
            'due_week' => (clone $query)->whereBetween('due_at', [$now->startOfDay(), $weekEnd.' 23:59:59'])->whereNotIn('status', TaskLifecycle::terminalStatuses())->count(),
            'awaiting_approval' => (clone $query)->where(function (Builder $q): void { $q->where('status', 'waiting_approval')->orWhereHas('transferRequests', fn (Builder $approval) => $approval->where('status', 'pending')); })->count(),
            'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
        ];
    }

    /** @return array<string, int> */
    private function scopeCounts(User $user, Builder $query): array
    {
        $scopes = [
            'mine', 'assigned-to-me', 'assigned-by-me', 'team', 'department', 'all',
            'due-today', 'due-week', 'overdue', 'pending', 'completed', 'archived',
        ];

        $counts = [];
        foreach ($scopes as $scope) {
            $scoped = clone $query;
            $this->applyScope($scoped, $user, $scope);
            $counts[$scope] = $scoped->count();
        }

        return $counts;
    }

    private function applyScope(Builder $query, User $user, string $scope): void
    {
        $now = CarbonImmutable::now();

        match ($scope) {
            'mine' => $query->where(fn (Builder $q) => $q->where('created_by_user_id', $user->id)->orWhere('assigned_to_user_id', $user->id)->orWhereHas('assignees', fn ($aq) => $aq->where('users.id', $user->id))),
            'assigned-to-me' => $query->where(fn (Builder $q) => $q->where('assigned_to_user_id', $user->id)->orWhereHas('assignees', fn ($aq) => $aq->where('users.id', $user->id))),
            'assigned-by-me' => $query->where('created_by_user_id', $user->id),
            'team' => $this->applyTeamScope($query, $user),
            'department' => $this->applyDepartmentScope($query, $user),
            'due-today' => $query->whereDate('due_at', $now->toDateString())->whereNotIn('status', TaskLifecycle::terminalStatuses()),
            'due-week' => $query->whereBetween('due_at', [$now->startOfDay(), $now->endOfWeek()])->whereNotIn('status', TaskLifecycle::terminalStatuses()),
            'overdue' => $query->whereNotNull('due_at')->where('due_at', '<', $now)->whereNotIn('status', TaskLifecycle::terminalStatuses()),
            'pending' => $query->whereNotIn('status', TaskLifecycle::terminalStatuses()),
            'completed' => $query->where('status', 'completed'),
            'archived' => $query->onlyTrashed(),
            default => null,
        };
    }

    private function applyTeamScope(Builder $query, User $user): void
    {
        $projectIds = ProjectTeamAssignment::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->pluck('project_id');

        if ($projectIds->isEmpty()) {
            $query->where(fn (Builder $q) => $q->where('created_by_user_id', $user->id)->orWhere('assigned_to_user_id', $user->id)->orWhereHas('assignees', fn ($aq) => $aq->where('users.id', $user->id)));
            return;
        }

        $query->whereIn('project_id', $projectIds);
    }

    private function applyDepartmentScope(Builder $query, User $user): void
    {
        $department = Employee::query()->where('user_id', $user->id)->value('department');
        if (! $department) {
            $query->where(fn (Builder $q) => $q->where('created_by_user_id', $user->id)->orWhere('assigned_to_user_id', $user->id)->orWhereHas('assignees', fn ($aq) => $aq->where('users.id', $user->id)));
            return;
        }

        $userIds = Employee::query()
            ->where('company_id', $user->company_id)
            ->where('department', $department)
            ->whereNotNull('user_id')
            ->pluck('user_id');

        $query->where(fn (Builder $q) => $q->whereIn('assigned_to_user_id', $userIds)->orWhereHas('assignees', fn ($aq) => $aq->whereIn('users.id', $userIds)));
    }

    /** @param array<string, mixed> $filters */
    private function applySorting(Builder $query, array $filters): void
    {
        $sort = (string) ($filters['sort'] ?? 'due_at');
        $direction = (string) ($filters['direction'] ?? 'asc');
        $columns = ['task_number', 'title', 'priority', 'status', 'due_at', 'created_at'];
        if (! in_array($sort, $columns, true)) {
            return;
        }

        $query->reorder()->orderBy($sort, $direction)->orderBy('id');
    }

    /** @param Collection<int, WorkTask> $tasks @return array<string, Collection<int, WorkTask>> */
    private function board(Collection $tasks): array
    {
        $columns = collect([
            'backlog' => collect(), 'todo' => collect(), 'in_progress' => collect(),
            'review' => collect(), 'approval' => collect(), 'blocked' => collect(),
            'completed' => collect(), 'cancelled' => collect(),
        ]);

        foreach ($tasks as $task) {
            $column = TaskLifecycle::boardColumn(
                $task->status,
                (bool) $task->assigned_to_user_id,
                $task->transferRequests->contains('status', 'pending'),
            );
            $columns[$column]->push($task);
        }

        return $columns->all();
    }

    /** @return Collection<int, array<string, mixed>> */
    private function activity(Builder $query, string $filter): Collection
    {
        $activity = $query->limit(40)->get()->flatMap(function (WorkTask $task): Collection {
            $history = collect($task->workflow_history ?? [])->map(fn (array $row): array => [
                'task_id' => $task->id,
                'task_number' => $task->task_number,
                'task_title' => $task->title,
                'type' => (string) ($row['status'] ?? 'updated'),
                'label' => (string) ($row['note'] ?? 'Task updated'),
                'at' => $row['at'] ?? $task->updated_at,
                'actor' => $row['actor_name'] ?? null,
            ]);
            $comments = $task->comments->map(fn ($comment): array => [
                'task_id' => $task->id,
                'task_number' => $task->task_number,
                'task_title' => $task->title,
                'type' => 'comment',
                'label' => $comment->body,
                'at' => $comment->created_at,
                'actor' => $comment->author?->name,
            ]);

            return $history->concat($comments);
        })->map(function (array $item): array {
            $type = strtolower((string) $item['type']);
            $category = match (true) {
                $type === 'comment' => 'comments',
                str_contains($type, 'transfer') || str_contains($type, 'assign') => 'transfers',
                str_contains($type, 'approv') || $type === 'waiting_approval' => 'approvals',
                str_contains($type, 'attachment') || str_contains($type, 'file') => 'attachments',
                str_contains($type, 'time') || str_contains($type, 'logged') => 'time',
                default => 'status',
            };
            $at = CarbonImmutable::parse($item['at'] ?? now())->timezone(config('app.timezone', 'Asia/Kolkata'));

            return [...$item,
                'category' => $category,
                'absolute_time' => $at->format('d M Y, h:i A'),
                'relative_time' => $at->diffForHumans(),
                'icon' => match ($category) {
                    'comments' => 'fa-comment',
                    'transfers' => 'fa-right-left',
                    'approvals' => 'fa-flag',
                    'attachments' => 'fa-paperclip',
                    'time' => 'fa-stopwatch',
                    default => 'fa-clock-rotate-left',
                },
                'tone' => $category,
            ];
        })->sortByDesc('at');

        return $activity
            ->when($filter !== 'all', fn (Collection $items) => $items->where('category', $filter))
            ->take(40)
            ->values();
    }

    /** @return Collection<int, array<string, mixed>> */
    private function workload(Builder $query): Collection
    {
        return $query->whereNotNull('assigned_to_user_id')
            ->selectRaw('assigned_to_user_id, count(*) as total')
            ->selectRaw("sum(case when status = 'completed' then 1 else 0 end) as completed")
            ->selectRaw("sum(case when status = 'in_progress' then 1 else 0 end) as in_progress")
            ->groupBy('assigned_to_user_id')
            ->with('assignedTo:id,name,email,profile_photo_path')
            ->limit(10)
            ->get()
            ->map(fn (WorkTask $row): array => [
                'user' => $row->assignedTo,
                'total' => (int) $row->getAttribute('total'),
                'completed' => (int) $row->getAttribute('completed'),
                'in_progress' => (int) $row->getAttribute('in_progress'),
            ]);
    }

    /** @return array<int, array{label:string,count:int}> */
    private function completionTrend(Builder $query): array
    {
        $start = CarbonImmutable::now()->startOfWeek()->subWeeks(6);
        $completed = $query->whereNotNull('completed_at')->where('completed_at', '>=', $start)->get(['id', 'completed_at']);

        return collect(range(0, 6))->map(function (int $offset) use ($start, $completed): array {
            $weekStart = $start->addWeeks($offset);
            $weekEnd = $weekStart->endOfWeek();

            return [
                'label' => $weekStart->format('d M'),
                'count' => $completed->filter(fn (WorkTask $task): bool => $task->completed_at?->betweenIncluded($weekStart, $weekEnd) ?? false)->count(),
            ];
        })->all();
    }

    /** @return array<string, int> */
    private function statusDistribution(Builder $query): array
    {
        return $query->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status')->map(fn ($value): int => (int) $value)->all();
    }

    /** @return Collection<int, WorkTask> */
    private function approvalQueue(Builder $query): Collection
    {
        return $query->where(function (Builder $q): void {
            $q->where('status', 'waiting_approval')->orWhereHas('transferRequests', fn (Builder $approval) => $approval->where('status', 'pending'));
        })->orderByDesc('priority')->orderBy('due_at')->limit(6)->get();
    }

    /** @return array<int, array<string, mixed>> */
    private function permissionMatrix(): array
    {
        return Role::query()->where('is_active', true)->orderBy('name')->get()->map(function (Role $role): array {
            $permissions = collect($role->permissions ?? []);
            $manage = $permissions->contains('collaboration.manage');
            $view = $manage || $permissions->contains('collaboration.view');
            $self = $permissions->contains('employee.self_service');

            return [
                'role' => $role->name,
                'view' => $view ? 'all' : ($self ? 'own' : 'none'),
                'create' => $manage || $self ? 'all' : 'none',
                'edit' => $manage ? 'all' : ($self ? 'own' : 'none'),
                'assign' => $manage ? 'all' : 'none',
                'transfer' => $manage ? 'all' : ($self ? 'own' : 'none'),
                'approve' => $manage ? 'all' : 'none',
                'archive' => $manage ? 'all' : ($self ? 'own' : 'none'),
                'export' => $view ? 'all' : 'none',
                'comment' => $view || $self ? 'all' : 'none',
            ];
        })->all();
    }

    /** @param array<string, mixed> $filters */
    private function selectedTask(User $user, array $filters): ?WorkTask
    {
        $taskId = (int) ($filters['task_id'] ?? 0);
        if ($taskId < 1) {
            return null;
        }

        $task = WorkTask::withTrashed()->with($this->collaboration->taskRelations())->findOrFail($taskId);
        abort_unless($user->can('view', $task), 403);

        return $task;
    }
}
