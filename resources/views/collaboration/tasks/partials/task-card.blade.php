@php
    $isOverdue    = $task->due_at && $task->due_at->isPast() && ! in_array($task->status, ['completed', 'cancelled'], true);
    $checklist    = collect($task->checklist ?? []);
    $doneChecklist = $checklist->filter(fn ($row) => (bool) ($row['done'] ?? false))->count();
@endphp

{{--
  data-task-id      → identifies the task for the Vite-managed Board controller
  data-status       → current status (updated in-place after a successful drag)
  data-lock-version → optimistic locking (sent with PATCH request)
--}}
<article
    class="tm-card"
    data-task-id="{{ $task->id }}"
    data-status="{{ $task->status }}"
    data-lock-version="{{ $task->lock_version ?? 0 }}"
    data-allowed-targets='@json(array_values($allowedTargets ?? []))'
>
    @if(! empty($allowedTargets))
        <button
            type="button"
            class="tm-card-drag-handle"
            draggable="true"
            x-on:dragstart="beginDrag"
            x-on:dragend="endDrag"
            aria-label="Move {{ $task->task_number }} to another permitted status"
            title="Drag to move task">
            <i class="fa-solid fa-grip-vertical" aria-hidden="true"></i>
        </button>
    @endif
    <a class="tm-card-link"
       href="{{ route('collaboration.tasks.index', $taskQuery(['task_id' => $task->id])) }}"
       aria-label="Open {{ $task->task_number }} {{ $task->title }}"
       draggable="false">{{-- prevent link from interfering with card drag --}}

        <div class="tm-card-top">
            <span class="tm-card-id">{{ $task->task_number }}</span>
            <span class="tm-pri tm-pri-{{ $task->priority }}">
                <span class="pdot"></span>{{ $priorities[$task->priority] ?? $task->priority }}
            </span>
        </div>

        <h3 class="tm-card-title">{{ $task->title }}</h3>

        <div class="tm-card-tags">
            @if($task->module_context)
                <span class="tm-tag">{{ str_replace('_', ' ', $task->module_context) }}</span>
            @endif
            @if($task->project)
                <span class="tm-tag">{{ $task->project->code }}</span>
            @endif
        </div>

        <footer class="tm-card-foot">
            <span class="tm-card-meta {{ $isOverdue ? 'due-over' : '' }}">
                <i class="fa-regular fa-calendar"></i>
                {{ $task->due_at?->format('d M') ?? 'No due date' }}
            </span>
            @if($checklist->isNotEmpty())
                <span class="tm-subprog">
                    <span class="tm-miniring" style="--p:{{ round($doneChecklist / max(1, $checklist->count()) * 100) }}"></span>
                    {{ $doneChecklist }}/{{ $checklist->count() }}
                </span>
            @endif
            @php
                $cardAssignees = $task->assignees->isNotEmpty() ? $task->assignees : collect(array_filter([$task->assignedTo]));
            @endphp
            <div style="display:flex; align-items:center; gap:2px;">
                @forelse($cardAssignees->take(3) as $cardAssignee)
                    <span class="tm-card-owner" title="{{ $cardAssignee->name }}">
                        {{ strtoupper(substr($cardAssignee->name, 0, 1)) }}
                    </span>
                @empty
                    <span class="tm-card-owner" title="Unassigned">U</span>
                @endforelse
                @if($cardAssignees->count() > 3)
                    <small style="font-size:10px; font-weight:700; color:var(--tm-text-muted);">+{{ $cardAssignees->count() - 3 }}</small>
                @endif
            </div>
        </footer>
    </a>

    @if(! empty($allowedTargets))
        <form id="task-board-status-{{ $task->id }}" class="tm-board-status-form" method="POST" action="{{ route('collaboration.tasks.status.update', $task) }}">
            @csrf
            @method('PATCH')
            <input type="hidden" name="status" value="">
            <input type="hidden" name="lock_version" value="{{ $task->lock_version }}">
            <input type="hidden" name="note" value="Status changed from Board view.">
        </form>
    @endif
</article>
