<div class="tm-body">
@if ($view === 'board')
    @php
        $boardColumns = [
            'backlog' => ['Backlog', '#64748b', 'draft'],
            'todo' => ['To Do', '#2570eb', 'assigned'],
            'in_progress' => ['In Progress', '#F6740C', 'in_progress'],
            'review' => ['Review', '#7c3aed', 'under_review'],
            'approval' => ['Approval', '#e08600', 'waiting_approval'],
            'blocked' => ['Blocked', '#dc2f3a', 'blocked'],
            'completed' => ['Completed', '#15a657', 'completed'],
            'cancelled' => ['Cancelled', '#94a3b8', 'cancelled'],
        ];
    @endphp
    <section class="tm-board-shell" x-data="taskBoard" data-scroll-key="task-board:{{ $scope }}" aria-label="Task board">
        <nav class="tm-board-nav" aria-label="Navigate task columns">
            <button type="button" class="tm-iconbtn" x-on:click="scrollColumns(-1)" aria-label="Previous task columns"><i class="fa-solid fa-chevron-left"></i></button>
            <span>Scroll to view every workflow status</span>
            <button type="button" class="tm-iconbtn" x-on:click="scrollColumns(1)" aria-label="Next task columns"><i class="fa-solid fa-chevron-right"></i></button>
        </nav>
        <div class="tm-kanban-viewport" x-ref="viewport" x-on:scroll.passive="rememberScroll" x-on:keydown="navigateBoard" tabindex="0">
        <div class="tm-kanban-track">
        @foreach ($boardColumns as $column => [$label, $color, $targetStatus])

            <section class="tm-col" data-column="{{ $column }}" data-target-status="{{ $targetStatus }}" x-on:dragover="dragOver" x-on:dragleave="dragLeave" x-on:drop="dropTask">
                <header class="tm-col-head">
                    <span class="tm-col-dot" style="background:{{ $color }}"></span>
                    <h2 class="tm-col-title">{{ $label }}</h2>
                    <span class="tm-col-count">{{ $taskBoard[$column]->count() }}</span>
                </header>
                <div class="tm-col-body">
                    @forelse($taskBoard[$column] as $task)
                        @include('collaboration.tasks.partials.task-card', ['task' => $task, 'allowedTargets' => $taskTransitionTargets[$task->id] ?? []])
                    @empty
                        <p class="tm-empty-copy" style="text-align:center;padding:24px 12px;">No tasks</p>
                    @endforelse
                </div>
            </section>

        @endforeach
        </div>
        </div>
    </section>

@elseif ($view === 'calendar')
    @php
        $month       = \Illuminate\Support\Carbon::parse($filters['focus_date'] ?? now())->startOfMonth();
        $gridStart   = $month->copy()->startOfWeek(\Carbon\Carbon::SUNDAY);
        $gridEnd     = $month->copy()->endOfMonth()->endOfWeek(\Carbon\Carbon::SATURDAY);
        $calendarTasks = collect($taskBoard)->flatten(1)->unique('id');
    @endphp
    <section class="tm-cal">
        <header class="tm-calendar-title">
            <div class="tm-calendar-nav">
                <a class="tm-iconbtn"
                   href="{{ route('collaboration.tasks.index', $taskQuery(['focus_date' => $month->copy()->subMonth()->toDateString()])) }}"
                   aria-label="Previous month">
                    <i class="fa-solid fa-chevron-left"></i>
                </a>
                <a class="tm-tbtn"
                   href="{{ route('collaboration.tasks.index', $taskQuery(['focus_date' => now()->toDateString()])) }}">
                    Today
                </a>
                <a class="tm-iconbtn"
                   href="{{ route('collaboration.tasks.index', $taskQuery(['focus_date' => $month->copy()->addMonth()->toDateString()])) }}"
                   aria-label="Next month">
                    <i class="fa-solid fa-chevron-right"></i>
                </a>
            </div>
            <div>
                <h2>{{ $month->format('F Y') }}</h2>
                <p>Tasks grouped by due date</p>
            </div>
        </header>
        <div class="tm-cal-grid">
            @foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $day)
                <div class="tm-cal-dow">{{ $day }}</div>
            @endforeach
            @for($date = $gridStart->copy(); $date->lte($gridEnd); $date->addDay())
                <div class="tm-cal-cell {{ $date->month !== $month->month ? 'dim' : '' }} {{ $date->isToday() ? 'today' : '' }}">
                    <div class="tm-cal-date">
                        <span>{{ $date->day }}</span>
                    </div>
                    @foreach($calendarTasks->filter(fn($task) => $task->due_at?->isSameDay($date))->take(4) as $task)
                        <a class="tm-cal-task"
                           href="{{ route('collaboration.tasks.index', $taskQuery(['task_id' => $task->id])) }}">
                            {{ $task->title }}
                        </a>
                    @endforeach
                </div>
            @endfor
        </div>
    </section>

@else
    {{-- List / table view --}}
    <div class="tm-grid-wrap">
        <table class="tm-table">
            <thead>
                <tr>
                    <th>Task</th>
                    <th>Assignee</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Due</th>
                    <th>Progress</th>
                </tr>
            </thead>
            <tbody>
                @forelse($tasks as $task)
                    <tr>
                        <td>
                            <a class="tm-td-title"
                               href="{{ route('collaboration.tasks.index', $taskQuery(['task_id' => $task->id])) }}">
                                <b>{{ $task->task_number }}</b>
                                <span class="sub">{{ $task->title }}</span>
                            </a>
                        </td>
                        <td>{{ $task->assignedTo?->name ?? 'Unassigned' }}</td>
                        <td>
                            <span class="tm-pri tm-pri-{{ $task->priority }}">
                                <span class="pdot"></span>{{ $priorities[$task->priority] ?? $task->priority }}
                            </span>
                        </td>
                        <td>
                            @php($allowedTargets = $taskTransitionTargets[$task->id] ?? [])
                            @if($allowedTargets !== [])
                                <details class="tm-list-status-menu">
                                    <summary class="blade-status-pill" aria-label="Change status for {{ $task->task_number }}">
                                        {{ $statuses[$task->status] ?? $task->status }}
                                        <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                                    </summary>
                                    <div class="tm-list-status-options" role="menu">
                                        @foreach($allowedTargets as $targetStatus)
                                            <form method="POST" action="{{ route('collaboration.tasks.status.update', $task) }}" x-data="taskStatusForm" data-target-status="{{ $targetStatus }}" x-on:submit="confirmTransition">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="status" value="{{ $targetStatus }}">
                                                <input type="hidden" name="lock_version" value="{{ $task->lock_version }}">
                                                <input type="hidden" name="note" value="Status changed from List view.">
                                                <button type="submit" role="menuitem">
                                                    {{ $statuses[$targetStatus] ?? str($targetStatus)->replace('_', ' ')->title() }}
                                                </button>
                                            </form>
                                        @endforeach
                                    </div>
                                </details>
                            @else
                                <span class="blade-status-pill">{{ $statuses[$task->status] ?? $task->status }}</span>
                            @endif
                        </td>
                        <td>{{ $task->due_at?->format('d M Y') ?? 'No due date' }}</td>
                        <td>
                            {{ $task->subtasks->count()
                                ? round($task->subtasks->where('status','completed')->count() / $task->subtasks->count() * 100)
                                : 0 }}%
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">
                            <p class="tm-empty-copy">No tasks match the selected filters.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endif

<div class="tm-pagination">{{ $tasks->links() }}</div>
</div>

{{-- ── DRAG AND DROP ──────────────────────────────────────────────────
     The Vite-managed Task workspace controller handles drag behaviour. The CSRF token meta tag
     must be present in your layout's <head>:
       <meta name="csrf-token" content="{{ csrf_token() }}">
──────────────────────────────────────────────────────────────────── --}}
