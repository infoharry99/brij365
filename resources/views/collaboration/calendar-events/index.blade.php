@extends('layouts.builder360-classic')

@section('title', 'Calendar Management | Builder360')

@php
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
@endphp

@section('content')
<section
  class="b360-calendar-workspace"
  x-data="calendarWorkspace"
  x-bind:class="workspaceClass()"
  x-on:keydown.escape.window="escapeWorkspace"
  data-open-create="{{ $errors->any() ? '1':'0' }}"
  data-selected-event="{{ $selectedEvent?->id }}"
  data-company-id="{{ auth()->user()->company_id }}"
  data-user-id="{{ auth()->id() }}"
  data-view="{{ $view }}"
  data-scroll-key="{{ $view }}:{{ $scope }}:{{ $focusDate->toDateString() }}"
  data-realtime-enabled="{{ config('broadcasting.default')==='reverb'?'1':'0' }}"
  data-reverb-key="{{ config('broadcasting.connections.reverb.key') }}"
  data-reverb-host="{{ config('broadcasting.connections.reverb.options.host') }}"
  data-reverb-port="{{ config('broadcasting.connections.reverb.options.port') }}"
  data-reverb-scheme="{{ config('broadcasting.connections.reverb.options.scheme') }}"
  aria-label="Calendar Management"
>

  {{-- Live-state stale banner --}}
  <!-- <div class="cal-live-state" x-show="stale" x-cloak>
     Calendar changed in another session.
     <button type="button" x-on:click="window.location.reload()">Refresh now</button>
  </div> -->

  @if(session('status'))
    <div class="blade-alert blade-alert-success tm-workspace-alert">{{ session('status') }}</div>
  @endif
  @if($errors->any())
    <div class="blade-alert blade-alert-danger tm-workspace-alert">
      <strong>Check the highlighted inputs.</strong>
      <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
  @endif

  {{-- ── SUMMARY BAR ── --}}
  <section
    id="calendar-summary-options"
    class="cal-summary"
    aria-label="Calendar summary"
    x-show="optionsOpen"
    x-cloak
  >
    @foreach([
      ['today',    "Today’s events",    'fa-calendar-day',          'accent'],
      ['upcoming', 'Upcoming',           'fa-arrow-trend-up',        'blue'],
      ['pending',  'Pending follow-ups', 'fa-reply',                 'violet'],
      ['completed','Completed',          'fa-check',                 'green'],
      ['missed',   'Missed',             'fa-xmark',                 'red'],
      ['overdue',  'Overdue',            'fa-triangle-exclamation',  'orange'],
    ] as [$key, $label, $icon, $tone])
      <a
        class="cal-sum {{ ($filters['summary'] ?? null) === $key ? 'on' : '' }}"
        href="{{ route('collaboration.calendar-events.index', $calendarQuery(['summary' => $key, 'page' => null])) }}"
      >
        <span class="cal-sum-ic is-{{ $tone }}">
          <i class="fa-solid {{ $icon }}"></i>
        </span>
        <span>
          <b class="cal-sum-n">{{ $calendarSummary[$key] ?? 0 }}</b>
          <small class="cal-sum-l">{{ $label }}</small>
        </span>
      </a>
    @endforeach
  </section>

  {{-- ── PRIMARY TOOLBAR ── --}}
  <header class="cal-primarybar">

    {{-- Prev / Next --}}
    <nav class="cal-navbtns" aria-label="Calendar period">
      <a
        href="{{ route('collaboration.calendar-events.index', $calendarQuery(['focus_date' => $previousDate->toDateString(), 'page' => null])) }}"
        aria-label="Previous {{ $step }}"
      ><i class="fa-solid fa-chevron-left"></i></a>
      <a
        href="{{ route('collaboration.calendar-events.index', $calendarQuery(['focus_date' => $nextDate->toDateString(), 'page' => null])) }}"
        aria-label="Next {{ $step }}"
      ><i class="fa-solid fa-chevron-right"></i></a>
    </nav>

    {{-- Today --}}
    <a
      class="cal-today-btn"
      href="{{ route('collaboration.calendar-events.index', $calendarQuery(['focus_date' => now($calendarTimezone)->toDateString(), 'page' => null])) }}"
    >Today</a>

    {{-- Period heading --}}
    <h1 class="cal-period">{{ $periodLabel }}</h1>

    {{-- Scope (All / Team / Mine) --}}
    <div
      id="calendar-scope-options"
      class="cal-scope-seg"
      role="tablist"
      aria-label="Calendar scope"
      x-show="optionsOpen"
      x-cloak
    >
      @foreach(['all' => 'All', 'team' => 'Team', 'mine' => 'Mine'] as $key => $label)
        <a
          role="tab"
          aria-selected="{{ $scope === $key ? 'true' : 'false' }}"
          class="{{ $scope === $key ? 'on' : '' }}"
          href="{{ route('collaboration.calendar-events.index', $calendarQuery(['scope' => $key, 'participant_user_id' => null, 'page' => null])) }}"
        >{{ $label }}</a>
      @endforeach
    </div>

    {{-- Search + Filters --}}
    <form
      id="calendar-filter-options"
      method="GET"
      action="{{ route('collaboration.calendar-events.index') }}"
      class="cal-filter-form"
      x-ref="calendarFilters"
      x-show="optionsOpen"
      x-cloak
    >
      <input type="hidden" name="view"       value="{{ $view }}">
      <input type="hidden" name="focus_date" value="{{ $focusDate->toDateString() }}">
      <input type="hidden" name="scope"      value="{{ $scope }}">

      <label class="cal-search">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input
          name="q"
          type="search"
          value="{{ $filters['q'] ?? '' }}"
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
        @if($activeFilters->isNotEmpty())
          <span class="cal-filter-count">{{ $activeFilters->count() }}</span>
        @endif
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
            @foreach($statuses as $value => $label)
              <option value="{{ $value }}" @selected(($filters['status'] ?? null) === $value)>{{ $label }}</option>
            @endforeach
          </select>
        </label>
        <label>
          Priority
          <select class="tm-select" name="priority" x-on:change="$refs.calendarFilters.submit()">
            <option value="">Any priority</option>
            @foreach(['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'urgent' => 'Urgent'] as $value => $label)
              <option value="{{ $value }}" @selected(($filters['priority'] ?? null) === $value)>{{ $label }}</option>
            @endforeach
          </select>
        </label>
        <label>
          Employee
          <select class="tm-select" name="participant_user_id" x-on:change="$refs.calendarFilters.submit()">
            <option value="">Anyone</option>
            @foreach($calendarUsers as $userOption)
              <option value="{{ $userOption->id }}" @selected(($filters['participant_user_id'] ?? null) == $userOption->id)>{{ $userOption->name }}</option>
            @endforeach
          </select>
        </label>
        <label>
          Project
          <select class="tm-select" name="project_id" x-on:change="$refs.calendarFilters.submit()">
            <option value="">All projects</option>
            @foreach($projects as $project)
              <option value="{{ $project->id }}" @selected(($filters['project_id'] ?? null) == $project->id)>{{ $project->code }} · {{ $project->name }}</option>
            @endforeach
          </select>
        </label>
        <label>
          Event type
          <select class="tm-select" name="event_type" x-on:change="$refs.calendarFilters.submit()">
            <option value="">Any type</option>
            @foreach($eventTypes as $value => $label)
              <option value="{{ $value }}" @selected(($filters['event_type'] ?? null) === $value)>{{ $label }}</option>
            @endforeach
          </select>
        </label>
        <label>
          Invitation response
          <select class="tm-select" name="invitation_response" x-on:change="$refs.calendarFilters.submit()">
            <option value="">Any response</option>
            @foreach(['pending' => 'Pending', 'accepted' => 'Accepted', 'tentative' => 'Tentative', 'declined' => 'Declined'] as $value => $label)
              <option value="{{ $value }}" @selected(($filters['invitation_response'] ?? null) === $value)>{{ $label }}</option>
            @endforeach
          </select>
        </label>
        <a
          class="tm-tbtn"
          href="{{ route('collaboration.calendar-events.index', ['view' => $view, 'focus_date' => $focusDate->toDateString(), 'scope' => $scope]) }}"
        >Clear filters</a>
      </div>
    </form>

    {{-- View switcher --}}
    <div class="cal-viewseg" role="tablist" aria-label="Calendar view">
      @foreach([
        'month'    => ['fa-table-cells',   'Month'],
        'week'     => ['fa-table-columns', 'Week'],
        'day'      => ['fa-calendar-day',  'Day'],
        'list'     => ['fa-list',          'List'],
        'employee' => ['fa-user-group',    'Employee'],
        'team'     => ['fa-building',      'Team'],
      ] as $key => [$icon, $label])
        <a
          role="tab"
          aria-selected="{{ $view === $key ? 'true' : 'false' }}"
          class="{{ $view === $key ? 'on' : '' }}"
          href="{{ route('collaboration.calendar-events.index', $calendarQuery(['view' => $key, 'page' => null])) }}"
        ><i class="fa-solid {{ $icon }}"></i>{{ $label }}</a>
      @endforeach
    </div>

    {{-- Options / fullscreen --}}
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

    {{-- New event --}}
    @if($canCreateEvent)
      <button type="button" class="cal-new" x-on:click="openCreate">
        <i class="fa-solid fa-plus"></i> New event
      </button>
    @endif

  </header>

  {{-- ── LEGEND ── --}}
  <div
    id="calendar-legend-options"
    class="cal-legend"
    x-show="optionsOpen"
    x-cloak
  >
    @foreach($eventTypes as $value => $label)
      <button
        type="button"
        class="cal-legend-item"
        x-on:click="toggleCategory('{{ $value }}')"
        x-bind:class="categoryHidden('{{ $value }}') ? 'off' : ''"
        x-bind:aria-pressed="(!categoryHidden('{{ $value }}')).toString()"
      >
        <i class="cal-legend-dot" style="background:{{ $eventColors[$value] }}"></i>
        {{ $label }}
      </button>
    @endforeach
  </div>

  {{-- Active filter tags --}}
  @if(!empty($filters['summary']) || $activeFilters->isNotEmpty())
    <div
      id="calendar-active-filter-options"
      class="cal-active-filters"
      x-show="optionsOpen"
      x-cloak
    >
      @if(!empty($filters['summary']))
        <a href="{{ route('collaboration.calendar-events.index', $calendarQuery(['summary' => null])) }}">
          {{ str($filters['summary'])->headline() }}
          <i class="fa-solid fa-xmark"></i>
        </a>
      @endif
      @foreach($activeFilters as $key => $label)
        <a href="{{ route('collaboration.calendar-events.index', $calendarQuery([$key => null, 'page' => null])) }}">
          {{ $label }} <i class="fa-solid fa-xmark"></i>
        </a>
      @endforeach
      <a
        class="clear"
        href="{{ route('collaboration.calendar-events.index', ['view' => $view, 'focus_date' => $focusDate->toDateString(), 'scope' => $scope]) }}"
      >Clear all</a>
    </div>
  @endif

  {{-- Calendar body + partials (unchanged) --}}
  @include('collaboration.calendar-events.partials.views')
  @include('collaboration.calendar-events.partials.create-modal')
  @if($selectedEvent)
    @include('collaboration.calendar-events.partials.drawer')
  @endif

</section>
@endsection
