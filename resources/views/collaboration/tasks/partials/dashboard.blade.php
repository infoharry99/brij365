<style>
    /* 1. Workspace root — must be a flex row with locked height */
.b360-task-workspace {
  display: flex;
  height: 100vh;
  overflow: hidden;   /* keeps the rail from overflowing */
}
 
/* 2. Rail — full height, internal scroll only in nav */
.tm-rail {
  height: 100vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;   /* no outer scroll; .tm-nav scrolls internally */
}
.tm-nav {
  flex: 1;
  min-height: 0;      /* ← critical: lets flex child shrink below content size */
  overflow-y: auto;
}
 
/* 3. Main column — flex column, must not overflow itself */
.tm-main {
  flex: 1;
  min-width: 0;
  min-height: 0;      /* ← critical */
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
 
/* 4. tm-body is the direct scrolling container for dashboard/views */
.tm-body {
  flex: 1;
  min-height: 0;      /* ← critical: without this, flex child doesn't shrink */
  overflow: hidden;
  display: flex;
  flex-direction: column;
}
 
/* 5. .pad variant = the dashboard and insights pages that need to scroll */
.tm-body.pad {
  overflow-y: auto !important;  /* override the hidden from .tm-body */
  padding: 20px;
}
.tm-body.pad::-webkit-scrollbar        { width: 5px; }
.tm-body.pad::-webkit-scrollbar-track  { background: transparent; }
.tm-body.pad::-webkit-scrollbar-thumb  { background: #C7D5EA; border-radius: 4px; }
.tm-body.pad::-webkit-scrollbar-thumb:hover { background: #94A3B8; }
 
/* 6. Kanban board — horizontal scroll, already correct but ensure height */
.tm-kanban {
  flex: 1;
  min-height: 0;
  overflow-x: auto;
  overflow-y: hidden;
}
 
/* 7. List/grid wrap — vertical scroll */
.tm-grid-wrap {
  flex: 1;
  min-height: 0;
  overflow: auto;
}
 
/* 8. Calendar — same pattern */
.tm-cal {
  flex: 1;
  min-height: 0;
  overflow: auto;
  display: flex;
  flex-direction: column;
}
</style>

<div class="tm-body pad">
    <div class="tm-dashboard-heading"><div><h1>Task Dashboard</h1><p>Operational execution across teams and projects.</p></div>@if($canCreateTask)<button type="button" class="tm-new compact" x-on:click="openCreate"><i class="fa-solid fa-plus"></i> New task</button>@endif</div>
    <section class="tm-dash-grid" aria-label="Task summary">
        @foreach ([['total','Total Tasks','fa-list-check','accent'],['in_progress','In Progress','fa-play','violet'],['completed','Completed','fa-check','green'],['overdue','Overdue','fa-triangle-exclamation','red'],['due_today','Due Today','fa-calendar-day','orange'],['awaiting_approval','Awaiting Approval','fa-shield-halved','blue']] as [$key,$label,$icon,$tone])
            <a class="blade-dashboard-kpi tm-dashboard-stat" href="{{ route('collaboration.tasks.index', $taskQuery(['scope' => $key === 'due_today' ? 'due-today' : ($key === 'overdue' ? 'overdue' : ($key === 'completed' ? 'completed' : 'all')), 'page' => null])) }}"><span class="tm-stat-icon is-{{ $tone }}"><i class="fa-solid {{ $icon }}"></i></span><small>{{ $label }}</small><strong>{{ number_format($taskSummary[$key] ?? 0) }}</strong></a>
        @endforeach
    </section>
    @php
        $trendMax = max(1, collect($taskCompletionTrend)->max('count'));
        $trendPoints = collect($taskCompletionTrend)->values()->map(fn($row,$index) => ($index * 100).','.round(92 - (($row['count'] / $trendMax) * 72), 1))->implode(' ');
        $distributionTotal = max(1, array_sum($taskStatusDistribution));
        $statusColors = ['completed'=>'#15a657','in_progress'=>'#F6740C','open'=>'#2570eb','assigned'=>'#2570eb','accepted'=>'#7c3aed','blocked'=>'#dc2f3a','on_hold'=>'#64748b','waiting_info'=>'#e08600','waiting_dependency'=>'#e08600','under_review'=>'#7c3aed','waiting_approval'=>'#e08600','rejected'=>'#dc2f3a','cancelled'=>'#8d93a4','draft'=>'#64748b'];
        $statusCursor = 0;
        $statusGradient = collect($taskStatusDistribution)->map(function ($count, $status) use (&$statusCursor, $distributionTotal, $statusColors) {
            $start = $statusCursor;
            $statusCursor += ($count / $distributionTotal) * 100;
            return ($statusColors[$status] ?? '#F6740C').' '.round($start, 2).'% '.round($statusCursor, 2).'%';
        })->implode(', ');
    @endphp
    <div class="tm-dashboard-panels tm-dashboard-top-panels">
        <article class="blade-dashboard-card tm-trend-card"><div class="blade-dashboard-section-title"><div><span class="blade-dashboard-label">Completion trend</span><h2>Tasks completed per week</h2></div><small>Last seven weeks</small></div><div class="tm-trend-chart" role="img" aria-label="Weekly task completion trend"><svg viewBox="0 0 600 110" preserveAspectRatio="none"><polyline class="tm-trend-area" points="0,100 {{ $trendPoints }} 600,100"></polyline><polyline class="tm-trend-line" points="{{ $trendPoints }}"></polyline></svg><div class="tm-trend-labels">@foreach($taskCompletionTrend as $row)<span><b>{{ $row['count'] }}</b><small>{{ $row['label'] }}</small></span>@endforeach</div></div></article>
        <article class="blade-dashboard-card tm-status-card"><div class="blade-dashboard-section-title"><div><span class="blade-dashboard-label">By status</span><h2>Status distribution</h2></div></div>@if(array_sum($taskStatusDistribution))<div class="tm-status-donut" style="--status-gradient:{{ $statusGradient }}" role="img" aria-label="Task distribution by status"><span><b>{{ number_format(array_sum($taskStatusDistribution)) }}</b><small>tasks</small></span></div>@endif<div class="tm-status-breakdown">@forelse($taskStatusDistribution as $status=>$count)<a href="{{ route('collaboration.tasks.index',$taskQuery(['scope'=>'all','status'=>$status])) }}"><span class="tm-status-dot" style="background:{{ $statusColors[$status] ?? '#F6740C' }}"></span><span>{{ $statuses[$status] ?? str_replace('_',' ',$status) }}</span><b>{{ $count }}</b><small>{{ round($count/$distributionTotal*100) }}%</small></a>@empty<p class="tm-empty-copy">No status data is available.</p>@endforelse</div></article>
    </div>
    <div class="tm-dashboard-panels">
        <article class="blade-dashboard-card"><div class="blade-dashboard-section-title"><div><span class="blade-dashboard-label">Workload</span><h2>Team workload</h2></div><small>Active tasks by member</small></div>
            @forelse($taskWorkload as $row)<div class="tm-workload-row"><span class="tm-card-owner">{{ strtoupper(substr($row['user']?->name ?? 'U',0,1)) }}</span><span class="wl-name">{{ $row['user']?->name ?? 'Unassigned' }}</span><div class="tm-wl-bar"><i class="tm-wl-seg is-complete" style="width:{{ $row['total'] ? ($row['completed']/$row['total']*100) : 0 }}%"></i><i class="tm-wl-seg is-progress" style="width:{{ $row['total'] ? ($row['in_progress']/$row['total']*100) : 0 }}%"></i></div><b>{{ $row['total'] }}</b></div>@empty<p class="tm-empty-copy">No assigned task workload is available.</p>@endforelse
        </article>
        <article class="blade-dashboard-card"><div class="blade-dashboard-section-title"><div><span class="blade-dashboard-label">Approvals</span><h2>Awaiting your approval</h2></div><a href="{{ route('collaboration.tasks.index', $taskQuery(['scope'=>'pending'])) }}">View all</a></div>
            @forelse($taskApprovalQueue as $approvalTask)<a class="tm-approval-row" href="{{ route('collaboration.tasks.index',$taskQuery(['task_id'=>$approvalTask->id])) }}"><span><b>{{ $approvalTask->title }}</b><small>{{ $approvalTask->task_number }} · {{ $approvalTask->assignedTo?->name ?? 'Unassigned' }}</small></span><span class="tm-pri tm-pri-{{ $approvalTask->priority }}"><span class="pdot"></span>{{ $priorities[$approvalTask->priority] ?? $approvalTask->priority }}</span></a>@empty<p class="tm-empty-copy">No task approvals are waiting.</p>@endforelse
        </article>
    </div>
    <article class="blade-dashboard-card tm-recent-task-card">
        <div class="blade-dashboard-section-title">
            <div><span class="blade-dashboard-label">Current work</span><h2>Recent tasks</h2></div>
            <a href="{{ route('collaboration.tasks.index', $taskQuery(['scope' => 'all', 'view' => 'list'])) }}">View all</a>
        </div>
        <div class="tm-recent-task-list">
            @forelse($tasks->take(8) as $recentTask)
                <a href="{{ route('collaboration.tasks.index', $taskQuery(['task_id' => $recentTask->id])) }}">
                    <span><b>{{ $recentTask->title }}</b><small>{{ $recentTask->task_number }} · {{ $recentTask->assignedTo?->name ?? 'Unassigned' }}</small></span>
                    <span class="tm-pri tm-pri-{{ $recentTask->priority }}"><span class="pdot"></span>{{ $priorities[$recentTask->priority] ?? $recentTask->priority }}</span>
                </a>
            @empty
                <p class="tm-empty-copy">No tasks are available in your current view.</p>
            @endforelse
        </div>
    </article>
</div>


