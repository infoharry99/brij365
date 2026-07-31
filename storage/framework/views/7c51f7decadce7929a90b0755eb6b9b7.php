<div class="tm-body">
<?php if($view === 'board'): ?>
    <?php
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
    ?>
    <section class="tm-board-shell" x-data="taskBoard" data-scroll-key="task-board:<?php echo e($scope); ?>" aria-label="Task board">
        <nav class="tm-board-nav" aria-label="Navigate task columns">
            <button type="button" class="tm-iconbtn" x-on:click="scrollColumns(-1)" aria-label="Previous task columns"><i class="fa-solid fa-chevron-left"></i></button>
            <span>Scroll to view every workflow status</span>
            <button type="button" class="tm-iconbtn" x-on:click="scrollColumns(1)" aria-label="Next task columns"><i class="fa-solid fa-chevron-right"></i></button>
        </nav>
        <div class="tm-kanban-viewport" x-ref="viewport" x-on:scroll.passive="rememberScroll" x-on:keydown="navigateBoard" tabindex="0">
        <div class="tm-kanban-track">
        <?php $__currentLoopData = $boardColumns; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $column => [$label, $color, $targetStatus]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>

            <section class="tm-col" data-column="<?php echo e($column); ?>" data-target-status="<?php echo e($targetStatus); ?>" x-on:dragover="dragOver" x-on:dragleave="dragLeave" x-on:drop="dropTask">
                <header class="tm-col-head">
                    <span class="tm-col-dot" style="background:<?php echo e($color); ?>"></span>
                    <h2 class="tm-col-title"><?php echo e($label); ?></h2>
                    <span class="tm-col-count"><?php echo e($taskBoard[$column]->count()); ?></span>
                </header>
                <div class="tm-col-body">
                    <?php $__empty_1 = true; $__currentLoopData = $taskBoard[$column]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $task): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <?php echo $__env->make('collaboration.tasks.partials.task-card', ['task' => $task, 'allowedTargets' => $taskTransitionTargets[$task->id] ?? []], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <p class="tm-empty-copy" style="text-align:center;padding:24px 12px;">No tasks</p>
                    <?php endif; ?>
                </div>
            </section>

        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
        </div>
    </section>

<?php elseif($view === 'calendar'): ?>
    <?php
        $month       = \Illuminate\Support\Carbon::parse($filters['focus_date'] ?? now())->startOfMonth();
        $gridStart   = $month->copy()->startOfWeek(\Carbon\Carbon::SUNDAY);
        $gridEnd     = $month->copy()->endOfMonth()->endOfWeek(\Carbon\Carbon::SATURDAY);
        $calendarTasks = collect($taskBoard)->flatten(1)->unique('id');
    ?>
    <section class="tm-cal">
        <header class="tm-calendar-title">
            <div class="tm-calendar-nav">
                <a class="tm-iconbtn"
                   href="<?php echo e(route('collaboration.tasks.index', $taskQuery(['focus_date' => $month->copy()->subMonth()->toDateString()]))); ?>"
                   aria-label="Previous month">
                    <i class="fa-solid fa-chevron-left"></i>
                </a>
                <a class="tm-tbtn"
                   href="<?php echo e(route('collaboration.tasks.index', $taskQuery(['focus_date' => now()->toDateString()]))); ?>">
                    Today
                </a>
                <a class="tm-iconbtn"
                   href="<?php echo e(route('collaboration.tasks.index', $taskQuery(['focus_date' => $month->copy()->addMonth()->toDateString()]))); ?>"
                   aria-label="Next month">
                    <i class="fa-solid fa-chevron-right"></i>
                </a>
            </div>
            <div>
                <h2><?php echo e($month->format('F Y')); ?></h2>
                <p>Tasks grouped by due date</p>
            </div>
        </header>
        <div class="tm-cal-grid">
            <?php $__currentLoopData = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $day): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="tm-cal-dow"><?php echo e($day); ?></div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            <?php for($date = $gridStart->copy(); $date->lte($gridEnd); $date->addDay()): ?>
                <div class="tm-cal-cell <?php echo e($date->month !== $month->month ? 'dim' : ''); ?> <?php echo e($date->isToday() ? 'today' : ''); ?>">
                    <div class="tm-cal-date">
                        <span><?php echo e($date->day); ?></span>
                    </div>
                    <?php $__currentLoopData = $calendarTasks->filter(fn($task) => $task->due_at?->isSameDay($date))->take(4); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $task): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <a class="tm-cal-task"
                           href="<?php echo e(route('collaboration.tasks.index', $taskQuery(['task_id' => $task->id]))); ?>">
                            <?php echo e($task->title); ?>

                        </a>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            <?php endfor; ?>
        </div>
    </section>

<?php else: ?>
    
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
                <?php $__empty_1 = true; $__currentLoopData = $tasks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $task): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <tr>
                        <td>
                            <a class="tm-td-title"
                               href="<?php echo e(route('collaboration.tasks.index', $taskQuery(['task_id' => $task->id]))); ?>">
                                <b><?php echo e($task->task_number); ?></b>
                                <span class="sub"><?php echo e($task->title); ?></span>
                            </a>
                        </td>
                        <td><?php echo e($task->assignedTo?->name ?? 'Unassigned'); ?></td>
                        <td>
                            <span class="tm-pri tm-pri-<?php echo e($task->priority); ?>">
                                <span class="pdot"></span><?php echo e($priorities[$task->priority] ?? $task->priority); ?>

                            </span>
                        </td>
                        <td>
                            <?php ($allowedTargets = $taskTransitionTargets[$task->id] ?? []); ?>
                            <?php if($allowedTargets !== []): ?>
                                <details class="tm-list-status-menu">
                                    <summary class="blade-status-pill" aria-label="Change status for <?php echo e($task->task_number); ?>">
                                        <?php echo e($statuses[$task->status] ?? $task->status); ?>

                                        <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                                    </summary>
                                    <div class="tm-list-status-options" role="menu">
                                        <?php $__currentLoopData = $allowedTargets; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $targetStatus): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <form method="POST" action="<?php echo e(route('collaboration.tasks.status.update', $task)); ?>" x-data="taskStatusForm" data-target-status="<?php echo e($targetStatus); ?>" x-on:submit="confirmTransition">
                                                <?php echo csrf_field(); ?>
                                                <?php echo method_field('PATCH'); ?>
                                                <input type="hidden" name="status" value="<?php echo e($targetStatus); ?>">
                                                <input type="hidden" name="lock_version" value="<?php echo e($task->lock_version); ?>">
                                                <input type="hidden" name="note" value="Status changed from List view.">
                                                <button type="submit" role="menuitem">
                                                    <?php echo e($statuses[$targetStatus] ?? str($targetStatus)->replace('_', ' ')->title()); ?>

                                                </button>
                                            </form>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    </div>
                                </details>
                            <?php else: ?>
                                <span class="blade-status-pill"><?php echo e($statuses[$task->status] ?? $task->status); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo e($task->due_at?->format('d M Y') ?? 'No due date'); ?></td>
                        <td>
                            <?php echo e($task->subtasks->count()
                                ? round($task->subtasks->where('status','completed')->count() / $task->subtasks->count() * 100)
                                : 0); ?>%
                        </td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr>
                        <td colspan="6">
                            <p class="tm-empty-copy">No tasks match the selected filters.</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<div class="tm-pagination"><?php echo e($tasks->links()); ?></div>
</div>


<?php /**PATH /home/developer/public_html/build365/resources/views/collaboration/tasks/partials/views.blade.php ENDPATH**/ ?>