<?php $__env->startSection('title', 'Calendar Management | Builder360'); ?>

<?php
    $view = $filters['view'] ?? 'month';
    $scope = $filters['scope'] ?? 'all';
    $calendarQuery = fn(array $changes=[]) => array_filter(array_merge($filters,$changes), static fn($value) => $value !== null && $value !== '');
    $step = in_array($view,['week','employee','team'],true) ? 'week' : ($view==='day' ? 'day' : 'month');
    $previousDate = $step==='day' ? $focusDate->subDay() : ($step==='week' ? $focusDate->subWeek() : $focusDate->subMonthNoOverflow());
    $nextDate = $step==='day' ? $focusDate->addDay() : ($step==='week' ? $focusDate->addWeek() : $focusDate->addMonthNoOverflow());
    $eventColors=['meeting'=>'#F6740C','call'=>'#0ea5a4','follow_up'=>'#e08600','demo'=>'#7c3aed','appointment'=>'#2570eb','task_deadline'=>'#e22d3d','internal'=>'#64748b','client_event'=>'#12a85b','reminder'=>'#db2777','site_visit'=>'#2570eb','interview'=>'#F6740C','payment_follow_up'=>'#e08600','inspection'=>'#2570eb','training'=>'#64748b'];
    $eventTypeMap=['site_visit'=>'appointment','inspection'=>'appointment','interview'=>'meeting','payment_follow_up'=>'follow_up','training'=>'internal'];
    $eventTypeOf=fn($event)=>$eventTypeMap[$event->event_type] ?? $event->event_type;
    $activeFilters=collect(['status'=>'Status','priority'=>'Priority','participant_user_id'=>'Employee','project_id'=>'Project','event_type'=>'Type','invitation_response'=>'Response'])->filter(fn($label,$key)=>!empty($filters[$key]));
?>

<?php $__env->startSection('content'); ?>
<section
  class="b360-calendar-workspace"
  x-data="calendarWorkspace"
  x-bind:class="workspaceClass()"
  x-on:keydown.escape.window="escapeWorkspace"
  data-open-create="<?php echo e($errors->any() ? '1':'0'); ?>"
  data-selected-event="<?php echo e($selectedEvent?->id); ?>"
  data-company-id="<?php echo e(auth()->user()->company_id); ?>"
  data-user-id="<?php echo e(auth()->id()); ?>"
  data-view="<?php echo e($view); ?>"
  data-scroll-key="<?php echo e($view); ?>:<?php echo e($scope); ?>:<?php echo e($focusDate->toDateString()); ?>"
  data-realtime-enabled="<?php echo e(config('broadcasting.default')==='reverb'?'1':'0'); ?>"
  data-reverb-key="<?php echo e(config('broadcasting.connections.reverb.key')); ?>"
  data-reverb-host="<?php echo e(config('broadcasting.connections.reverb.options.host')); ?>"
  data-reverb-port="<?php echo e(config('broadcasting.connections.reverb.options.port')); ?>"
  data-reverb-scheme="<?php echo e(config('broadcasting.connections.reverb.options.scheme')); ?>"
  aria-label="Calendar Management"
>

  
  <!-- <div class="cal-live-state" x-show="stale" x-cloak>
     Calendar changed in another session.
     <button type="button" x-on:click="window.location.reload()">Refresh now</button>
  </div> -->

  <?php if(session('status')): ?>
    <div class="blade-alert blade-alert-success tm-workspace-alert"><?php echo e(session('status')); ?></div>
  <?php endif; ?>
  <?php if($errors->any()): ?>
    <div class="blade-alert blade-alert-danger tm-workspace-alert">
      <strong>Check the highlighted inputs.</strong>
      <ul><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($error); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></ul>
    </div>
  <?php endif; ?>

  
  <section
    id="calendar-summary-options"
    class="cal-summary"
    aria-label="Calendar summary"
    x-show="optionsOpen"
    x-cloak
  >
    <?php $__currentLoopData = [
      ['today',    "Today’s events",    'fa-calendar-day',          'accent'],
      ['upcoming', 'Upcoming',           'fa-arrow-trend-up',        'blue'],
      ['pending',  'Pending follow-ups', 'fa-reply',                 'violet'],
      ['completed','Completed',          'fa-check',                 'green'],
      ['missed',   'Missed',             'fa-xmark',                 'red'],
      ['overdue',  'Overdue',            'fa-triangle-exclamation',  'orange'],
    ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$key, $label, $icon, $tone]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <a
        class="cal-sum <?php echo e(($filters['summary'] ?? null) === $key ? 'on' : ''); ?>"
        href="<?php echo e(route('collaboration.calendar-events.index', $calendarQuery(['summary' => $key, 'page' => null]))); ?>"
      >
        <span class="cal-sum-ic is-<?php echo e($tone); ?>">
          <i class="fa-solid <?php echo e($icon); ?>"></i>
        </span>
        <span>
          <b class="cal-sum-n"><?php echo e($calendarSummary[$key] ?? 0); ?></b>
          <small class="cal-sum-l"><?php echo e($label); ?></small>
        </span>
      </a>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
  </section>

  
  <header class="cal-primarybar">

    
    <nav class="cal-navbtns" aria-label="Calendar period">
      <a
        href="<?php echo e(route('collaboration.calendar-events.index', $calendarQuery(['focus_date' => $previousDate->toDateString(), 'page' => null]))); ?>"
        aria-label="Previous <?php echo e($step); ?>"
      ><i class="fa-solid fa-chevron-left"></i></a>
      <a
        href="<?php echo e(route('collaboration.calendar-events.index', $calendarQuery(['focus_date' => $nextDate->toDateString(), 'page' => null]))); ?>"
        aria-label="Next <?php echo e($step); ?>"
      ><i class="fa-solid fa-chevron-right"></i></a>
    </nav>

    
    <a
      class="cal-today-btn"
      href="<?php echo e(route('collaboration.calendar-events.index', $calendarQuery(['focus_date' => now($calendarTimezone)->toDateString(), 'page' => null]))); ?>"
    >Today</a>

    
    <h1 class="cal-period"><?php echo e($periodLabel); ?></h1>

    
    <div
      id="calendar-scope-options"
      class="cal-scope-seg"
      role="tablist"
      aria-label="Calendar scope"
      x-show="optionsOpen"
      x-cloak
    >
      <?php $__currentLoopData = ['all' => 'All', 'team' => 'Team', 'mine' => 'Mine']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <a
          role="tab"
          aria-selected="<?php echo e($scope === $key ? 'true' : 'false'); ?>"
          class="<?php echo e($scope === $key ? 'on' : ''); ?>"
          href="<?php echo e(route('collaboration.calendar-events.index', $calendarQuery(['scope' => $key, 'participant_user_id' => null, 'page' => null]))); ?>"
        ><?php echo e($label); ?></a>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>

    
    <form
      id="calendar-filter-options"
      method="GET"
      action="<?php echo e(route('collaboration.calendar-events.index')); ?>"
      class="cal-filter-form"
      x-ref="calendarFilters"
      x-show="optionsOpen"
      x-cloak
    >
      <input type="hidden" name="view"       value="<?php echo e($view); ?>">
      <input type="hidden" name="focus_date" value="<?php echo e($focusDate->toDateString()); ?>">
      <input type="hidden" name="scope"      value="<?php echo e($scope); ?>">

      <label class="cal-search">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input
          name="q"
          type="search"
          value="<?php echo e($filters['q'] ?? ''); ?>"
          placeholder="Search events..."
          x-on:search="$refs.calendarFilters.submit()"
        >
      </label>

      <button
        type="button"
        class="cal-control-btn"
        x-on:click="toggleFilters"
        x-bind:aria-expanded="filterOpen.toString()"
      >
        <i class="fa-solid fa-filter"></i>
        Filters
        <?php if($activeFilters->isNotEmpty()): ?>
          <span class="cal-filter-count"><?php echo e($activeFilters->count()); ?></span>
        <?php endif; ?>
      </button>

      <div
        class="cal-filter-pop"
        x-show="filterOpen"
        x-on:click.outside="filterOpen = false"
        x-cloak
      >
        <label>
          Status
          <select class="tm-select" name="status" x-on:change="$refs.calendarFilters.submit()">
            <option value="">Any status</option>
            <?php $__currentLoopData = $statuses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <option value="<?php echo e($value); ?>" <?php if(($filters['status'] ?? null) === $value): echo 'selected'; endif; ?>><?php echo e($label); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </select>
        </label>
        <label>
          Priority
          <select class="tm-select" name="priority" x-on:change="$refs.calendarFilters.submit()">
            <option value="">Any priority</option>
            <?php $__currentLoopData = ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'urgent' => 'Urgent']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <option value="<?php echo e($value); ?>" <?php if(($filters['priority'] ?? null) === $value): echo 'selected'; endif; ?>><?php echo e($label); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </select>
        </label>
        <label>
          Employee
          <select class="tm-select" name="participant_user_id" x-on:change="$refs.calendarFilters.submit()">
            <option value="">Anyone</option>
            <?php $__currentLoopData = $calendarUsers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $userOption): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <option value="<?php echo e($userOption->id); ?>" <?php if(($filters['participant_user_id'] ?? null) == $userOption->id): echo 'selected'; endif; ?>><?php echo e($userOption->name); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </select>
        </label>
        <label>
          Project
          <select class="tm-select" name="project_id" x-on:change="$refs.calendarFilters.submit()">
            <option value="">All projects</option>
            <?php $__currentLoopData = $projects; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $project): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <option value="<?php echo e($project->id); ?>" <?php if(($filters['project_id'] ?? null) == $project->id): echo 'selected'; endif; ?>><?php echo e($project->code); ?> · <?php echo e($project->name); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </select>
        </label>
        <label>
          Event type
          <select class="tm-select" name="event_type" x-on:change="$refs.calendarFilters.submit()">
            <option value="">Any type</option>
            <?php $__currentLoopData = $eventTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <option value="<?php echo e($value); ?>" <?php if(($filters['event_type'] ?? null) === $value): echo 'selected'; endif; ?>><?php echo e($label); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </select>
        </label>
        <label>
          Invitation response
          <select class="tm-select" name="invitation_response" x-on:change="$refs.calendarFilters.submit()">
            <option value="">Any response</option>
            <?php $__currentLoopData = ['pending' => 'Pending', 'accepted' => 'Accepted', 'tentative' => 'Tentative', 'declined' => 'Declined']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <option value="<?php echo e($value); ?>" <?php if(($filters['invitation_response'] ?? null) === $value): echo 'selected'; endif; ?>><?php echo e($label); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </select>
        </label>
        <a
          class="tm-tbtn"
          href="<?php echo e(route('collaboration.calendar-events.index', ['view' => $view, 'focus_date' => $focusDate->toDateString(), 'scope' => $scope])); ?>"
        >Clear filters</a>
      </div>
    </form>

    
    <div class="cal-viewseg" role="tablist" aria-label="Calendar view">
      <?php $__currentLoopData = [
        'month'    => ['fa-table-cells',   'Month'],
        'week'     => ['fa-table-columns', 'Week'],
        'day'      => ['fa-calendar-day',  'Day'],
        'list'     => ['fa-list',          'List'],
        'employee' => ['fa-user-group',    'Employee'],
        'team'     => ['fa-building',      'Team'],
      ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => [$icon, $label]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <a
          role="tab"
          aria-selected="<?php echo e($view === $key ? 'true' : 'false'); ?>"
          class="<?php echo e($view === $key ? 'on' : ''); ?>"
          href="<?php echo e(route('collaboration.calendar-events.index', $calendarQuery(['view' => $key, 'page' => null]))); ?>"
        ><i class="fa-solid <?php echo e($icon); ?>"></i><?php echo e($label); ?></a>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>

    
    <button
      type="button"
      class="cal-control-btn cal-options-toggle"
      x-ref="optionsToggle"
      x-on:click="toggleOptions"
      x-bind:aria-expanded="optionsOpen.toString()"
      aria-controls="calendar-summary-options calendar-scope-options calendar-filter-options calendar-legend-options calendar-active-filter-options"
    >
      <i class="fa-solid fa-sliders"></i>
      <span x-show="optionsOpen">Hide options</span>
      <span x-show="!optionsOpen" x-cloak>Show options</span>
    </button>

    <button
      type="button"
      class="cal-control-btn"
      x-on:click="toggleFullScreen"
      aria-label="Full Screen"
    ><i class="fa-solid fa-expand"></i><span class="sr-only">Full Screen</span></button>

    
    <?php if($canCreateEvent): ?>
      <button type="button" class="cal-new" x-on:click="openCreate">
        <i class="fa-solid fa-plus"></i> New event
      </button>
    <?php endif; ?>

  </header>

  
  <div
    id="calendar-legend-options"
    class="cal-legend"
    x-show="optionsOpen"
    x-cloak
  >
    <?php $__currentLoopData = $eventTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <button
        type="button"
        class="cal-legend-item"
        x-on:click="toggleCategory('<?php echo e($value); ?>')"
        x-bind:class="categoryHidden('<?php echo e($value); ?>') ? 'off' : ''"
        x-bind:aria-pressed="(!categoryHidden('<?php echo e($value); ?>')).toString()"
      >
        <i class="cal-legend-dot" style="background:<?php echo e($eventColors[$value]); ?>"></i>
        <?php echo e($label); ?>

      </button>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
  </div>

  
  <?php if(!empty($filters['summary']) || $activeFilters->isNotEmpty()): ?>
    <div
      id="calendar-active-filter-options"
      class="cal-active-filters"
      x-show="optionsOpen"
      x-cloak
    >
      <?php if(!empty($filters['summary'])): ?>
        <a href="<?php echo e(route('collaboration.calendar-events.index', $calendarQuery(['summary' => null]))); ?>">
          <?php echo e(str($filters['summary'])->headline()); ?>

          <i class="fa-solid fa-xmark"></i>
        </a>
      <?php endif; ?>
      <?php $__currentLoopData = $activeFilters; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <a href="<?php echo e(route('collaboration.calendar-events.index', $calendarQuery([$key => null, 'page' => null]))); ?>">
          <?php echo e($label); ?> <i class="fa-solid fa-xmark"></i>
        </a>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      <a
        class="clear"
        href="<?php echo e(route('collaboration.calendar-events.index', ['view' => $view, 'focus_date' => $focusDate->toDateString(), 'scope' => $scope])); ?>"
      >Clear all</a>
    </div>
  <?php endif; ?>

  
  <?php echo $__env->make('collaboration.calendar-events.partials.views', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <?php echo $__env->make('collaboration.calendar-events.partials.create-modal', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <?php if($selectedEvent): ?>
    <?php echo $__env->make('collaboration.calendar-events.partials.drawer', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <?php endif; ?>

</section>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.builder360-classic', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/developer/public_html/build365/resources/views/collaboration/calendar-events/index.blade.php ENDPATH**/ ?>