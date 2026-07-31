<section class="people-ops-panel has-mobile-cards" aria-labelledby="department-performance-title">
    <header class="people-ops-panel-head">
        <div><h2 id="department-performance-title">Department performance dashboard</h2><p>Factual review completion and final scores from the complete authorized query.</p></div>
    </header>
    <div class="people-ops-panel-body">
        <form method="GET" action="{{ route('hr.performance-dashboard.index') }}" class="people-ops-filterbar">
            <label class="people-field"><span>Cycle</span><select class="people-control" name="cycle_id"><option value="">All visible cycles</option>@foreach($activeCycles as $cycle)<option value="{{ $cycle->id }}" @selected((string)request('cycle_id') === (string)$cycle->id)>{{ $cycle->cycle_code }} - {{ $cycle->name }}</option>@endforeach</select></label>
            <label class="people-field"><span>Department</span><select class="people-control" name="department"><option value="">All departments</option>@foreach($departments as $department)<option value="{{ $department }}" @selected(request('department') === $department)>{{ $department }}</option>@endforeach</select></label>
            <button class="people-button is-primary" type="submit">Apply filters</button>
            <a class="people-button" href="{{ route('hr.performance-dashboard.index') }}">Clear</a>
        </form>
    </div>
    <div class="people-ops-table-wrap">
        <table class="people-ops-table"><caption>Department performance outcomes</caption>
            <thead><tr><th scope="col">Department</th><th scope="col" class="is-number">Employees</th><th scope="col" class="is-number">Reviews</th><th scope="col">Completion</th><th scope="col" class="is-number">Open</th><th scope="col" class="is-number">Average score</th><th scope="col" class="is-number">PIP</th></tr></thead>
            <tbody>@forelse($departmentRows as $row)<tr><td><strong>{{ $row->department }}</strong></td><td class="is-number">{{ $row->employees }}</td><td class="is-number">{{ $row->reviews }}</td><td><div class="people-ops-progress"><progress value="{{ $row->completionRate }}" max="100">{{ $row->completionRate }}%</progress><span>{{ $row->completionRate }}%</span></div><small>{{ $row->closedReviews }} closed</small></td><td class="is-number">{{ $row->openReviews }}</td><td class="is-number">{{ $row->averageFinalScore ?? '—' }}</td><td class="is-number">{{ $row->pipRequired }}</td></tr>@empty<tr><td colspan="7"><div class="people-ops-empty"><i class="fa-solid fa-chart-column" aria-hidden="true"></i><strong>No department review outcomes</strong><span>No persisted performance reviews match the selected authorized scope.</span></div></td></tr>@endforelse</tbody>
        </table>
    </div>
    <div class="people-ops-mobile-list">
        @forelse($departmentRows as $row)<article class="people-ops-mobile-card"><header class="people-ops-mobile-card-head"><strong>{{ $row->department }}</strong><span class="people-status is-info">{{ $row->completionRate }}% closed</span></header><dl class="people-ops-mobile-facts"><div><dt>Employees</dt><dd>{{ $row->employees }}</dd></div><div><dt>Reviews</dt><dd>{{ $row->reviews }}</dd></div><div><dt>Average final score</dt><dd>{{ $row->averageFinalScore ?? '—' }}</dd></div><div><dt>PIP required</dt><dd>{{ $row->pipRequired }}</dd></div></dl></article>@empty<div class="people-ops-empty"><strong>No department review outcomes</strong><span>No persisted performance reviews match the selected scope.</span></div>@endforelse
    </div>
</section>
