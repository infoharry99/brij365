<div class="cal-body" x-ref="calendarBody" x-on:scroll.passive="rememberScroll">
@if($view==='month')
    <section class="cal-month"><div class="cal-month-grid">
        @foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $day)<div class="cal-dow">{{ $day }}</div>@endforeach
        @foreach($calendarDays as $day)<div class="cal-cell {{ !$day['in_month']?'dim':'' }} {{ $day['date']->isToday()?'today':'' }}"><span class="cal-cell-date">{{ $day['date']->day }}</span>
            @foreach($day['events']->take(4) as $event)@include('collaboration.calendar-events.partials.event-pill',['event'=>$event])@endforeach
            @if($day['events']->count()>4)<a class="cal-ev-more" href="{{ route('collaboration.calendar-events.index',$calendarQuery(['view'=>'list','date_from'=>$day['date']->toDateString(),'date_to'=>$day['date']->toDateString(),'page'=>null])) }}">+{{ $day['events']->count()-4 }} more</a>@endif
        </div>@endforeach
    </div></section>
@elseif(in_array($view,['week','day'],true))
    @php $hourHeight=52; $gridHeight=count($calendarHours)*$hourHeight; $nowLocal=now($calendarTimezone); @endphp
    <section class="cal-timegrid {{ $view==='day'?'is-day':'' }}">
        <div class="cal-tg-header" style="--cal-columns:{{ $timedDays->count() }}"><div class="cal-tg-corner"></div>@foreach($timedDays as $day)<div class="cal-tg-daycol {{ $day['date']->isToday()?'today':'' }}"><span class="dow">{{ $day['date']->format('D') }}</span><span class="dnum">{{ $day['date']->format('d') }}</span></div>@endforeach</div>
        <div class="cal-tg-stage" style="--cal-columns:{{ $timedDays->count() }};--cal-grid-height:{{ $gridHeight }}px">
            <div class="cal-tg-times">@foreach($calendarHours as $hour)<span style="height:{{ $hourHeight }}px">{{ \Carbon\CarbonImmutable::createFromTime($hour)->format('g A') }}</span>@endforeach</div>
            @foreach($timedDays as $day)<div class="cal-tg-daybody {{ $day['date']->isToday()?'today':'' }}" style="height:{{ $gridHeight }}px">
                @foreach($calendarHours as $hour)<i class="cal-tg-hour" style="top:{{ ($loop->index)*$hourHeight }}px"></i>@endforeach
                @foreach($day['blocks'] as $block)@php $event=$block['event']; $width=100/max(1,$day['columns']); @endphp
                    <a x-show="!categoryHidden('{{ $eventTypeOf($event) }}')" class="cal-tg-event" href="{{ route('collaboration.calendar-events.index',$calendarQuery(['event_id'=>$event->id])) }}" style="top:{{ $block['top'] }}px;height:{{ $block['height'] }}px;left:calc({{ $block['column']*$width }}% + 3px);width:calc({{ $width }}% - 6px);background:{{ $block['color'] }}" title="{{ $event->title }}"><time>{{ $block['local_start']->format('g:i A') }}</time><b>{{ $event->title }}</b></a>
                @endforeach
                @if($day['date']->isToday() && $nowLocal->hour >= min($calendarHours) && $nowLocal->hour <= max($calendarHours))<span class="cal-now-line" style="top:{{ (($nowLocal->hour-min($calendarHours))*60+$nowLocal->minute)/60*$hourHeight }}px"></span>@endif
            </div>@endforeach
        </div>
    </section>
@elseif($view==='list')
    <section class="cal-list">@forelse($periodEvents->groupBy(fn($event)=>$event->starts_at?->setTimezone($calendarTimezone)->toDateString()) as $date=>$dayEvents)
        <h2 class="cal-list-day">{{ \Illuminate\Support\Carbon::parse($date,$calendarTimezone)->format('D, d M Y') }}</h2>
        @foreach($dayEvents as $event)@php $localStart=$event->starts_at?->setTimezone($calendarTimezone); $localEnd=$event->ends_at?->setTimezone($calendarTimezone); $type=$eventTypeOf($event); @endphp
            <a x-show="!categoryHidden('{{ $type }}')" class="cal-list-row" href="{{ route('collaboration.calendar-events.index',$calendarQuery(['event_id'=>$event->id])) }}"><span class="cal-list-time"><b class="t1">{{ $localStart?->format('g:i A') }}</b><small class="t2">{{ max(1,$localStart?->diffInMinutes($localEnd)??0) }} min</small></span><i class="cal-list-bar" style="background:{{ $eventColors[$type]??'#64748b' }}"></i><span class="cal-list-main"><b class="cal-list-title">{{ $event->title }}</b><span class="cal-list-meta"><span class="m" style="color:{{ $eventColors[$type]??'#64748b' }}"><i class="fa-regular fa-calendar"></i>{{ $eventTypes[$type]??str($type)->headline() }}</span><span class="m"><i class="fa-solid fa-user"></i>{{ $event->organizer?->name }}</span>@if($event->project)<span class="m"><i class="fa-solid fa-building"></i>{{ $event->project->code }}</span>@endif @if($event->location)<span class="m"><i class="fa-solid fa-location-dot"></i>{{ $event->location }}</span>@endif</span></span><span class="blade-status-pill">{{ $statuses[$event->status]??$event->status }}</span></a>
        @endforeach
    @empty<div class="cal-empty-state"><i class="fa-regular fa-calendar"></i><h2>No events found</h2><p>Create an event or adjust the selected filters.</p></div>@endforelse</section>
@else
    @php $lanes=$view==='employee'?$employeeLanes:$teamLanes; @endphp
    <section class="cal-lanes"><div class="cal-lanes-dow"><div class="cal-lane-corner"></div>@for($offset=0;$offset<7;$offset++)<div class="{{ $periodStart->addDays($offset)->isToday()?'today':'' }}">{{ $periodStart->addDays($offset)->format('D d') }}</div>@endfor</div>
        @foreach($lanes as $lane)<div class="cal-lane"><header class="cal-lane-head"><span class="tm-card-owner">{{ strtoupper(substr($lane['label'],0,1)) }}</span><span><b class="cal-lane-name">{{ $lane['label'] }}</b><small class="cal-lane-sub">{{ $lane['sub'] }}</small></span></header><div class="cal-lane-track">@for($offset=0;$offset<7;$offset++)@php $laneDay=$periodStart->addDays($offset); @endphp<div class="cal-lane-cell {{ $laneDay->isToday()?'today':'' }}">@foreach($lane['events']->filter(fn($event)=>$event->starts_at?->setTimezone($calendarTimezone)->isSameDay($laneDay)) as $event)@php $type=$eventTypeOf($event); @endphp<a x-show="!categoryHidden('{{ $type }}')" class="cal-lane-pill" style="background:{{ $eventColors[$type]??'#64748b' }}" href="{{ route('collaboration.calendar-events.index',$calendarQuery(['event_id'=>$event->id])) }}">{{ $event->starts_at?->setTimezone($calendarTimezone)->format('g:i A') }} {{ $event->title }}</a>@endforeach</div>@endfor</div></div>@endforeach
    </section>
@endif
</div>
