<section class="people-ops-kpis is-four" aria-label="Attendance exception summary">
    <article class="people-ops-kpi is-warning"><span class="people-ops-kpi-icon"><i class="fa-solid fa-clock" aria-hidden="true"></i></span><span>Late</span><strong>{{ $summary->late }}</strong><small>Stored results beyond the linked shift grace</small></article>
    <article class="people-ops-kpi is-warning"><span class="people-ops-kpi-icon"><i class="fa-solid fa-person-walking-arrow-right" aria-hidden="true"></i></span><span>Early leaving</span><strong>{{ $summary->earlyLeave }}</strong><small>Stored early-leave classifications</small></article>
    <article class="people-ops-kpi is-purple"><span class="people-ops-kpi-icon"><i class="fa-solid fa-circle-half-stroke" aria-hidden="true"></i></span><span>Half-day</span><strong>{{ $summary->halfDay }}</strong><small>Below the configured full-day threshold</small></article>
    <article class="people-ops-kpi is-danger"><span class="people-ops-kpi-icon"><i class="fa-solid fa-user-xmark" aria-hidden="true"></i></span><span>Absent</span><strong>{{ $summary->absent }}</strong><small>Persisted absent records in scope</small></article>
</section>

<section class="people-ops-panel">
    <header class="people-ops-panel-head">
        <div><h2>Attendance exceptions</h2><p>Review stored exceptions and their linked shift calculation basis.</p></div>
        <span class="people-count">{{ $records->total() }} exceptions</span>
    </header>

    <form method="GET" action="{{ route('hr.attendance-records.index') }}" class="people-ops-filterbar">
        <input type="hidden" name="view" value="exceptions">
        <label class="people-field">Employee<select class="people-control" name="employee_id"><option value="">All visible employees</option>@foreach ($employees as $employee)<option value="{{ $employee->id }}" @selected((string) request('employee_id') === (string) $employee->id)>{{ $employee->employee_code }} - {{ $employee->name }}</option>@endforeach</select></label>
        <label class="people-field">Exception type<select class="people-control" name="status"><option value="">All exception types</option>@foreach ($statusFilters as $status)@if(in_array($status['value'], ['late', 'early_leave', 'half_day', 'absent'], true))<option value="{{ $status['value'] }}" @selected(request('status') === $status['value'])>{{ $status['label'] }}</option>@endif @endforeach</select></label>
        <label class="people-field">Date from<input class="people-control" type="date" name="date_from" value="{{ request('date_from') }}"></label>
        <label class="people-field">Date to<input class="people-control" type="date" name="date_to" value="{{ request('date_to') }}"></label>
        <button class="people-button" type="submit"><i class="fa-solid fa-filter" aria-hidden="true"></i> Apply</button>
        @if (request()->hasAny(['employee_id', 'status', 'date_from', 'date_to']))<a class="people-button" href="{{ route('hr.attendance-records.index', ['view' => 'exceptions']) }}">Clear</a>@endif
    </form>

    <div class="people-alert" role="note">
        <strong>Explainable stored result.</strong> This screen displays the persisted attendance outcome and linked shift thresholds; it does not recalculate or override attendance policy.
    </div>

    @forelse ($records as $record)
        <article class="people-ops-list-row">
            <span class="people-avatar">{{ $record->employeeInitial }}</span>
            <div class="people-ops-list-copy">
                <strong>{{ $record->employeeName }} / {{ $record->statusLabel }}</strong>
                <span>{{ $record->workDate }} / {{ $record->shiftName }} / {{ $record->branch }}</span>
                <small>{{ $record->calculationBasis }}</small>
            </div>
            <div class="people-ops-list-actions">
                @if ($record->lateMinutes > 0)<span class="people-status is-warning">Late {{ $record->lateMinutes }} min</span>@endif
                @if ($record->earlyLeaveMinutes > 0)<span class="people-status is-warning">Early {{ $record->earlyLeaveMinutes }} min</span>@endif
                @if ($record->status === 'absent')<span class="people-status is-danger">Absent</span>@endif
                <span class="people-status is-muted">{{ $record->workedMinutes }} min worked</span>
            </div>
        </article>
    @empty
        <div class="people-ops-empty"><i class="fa-solid fa-circle-check" aria-hidden="true"></i><strong>No attendance exceptions</strong><span>No stored exceptions match the selected employee and date filters.</span></div>
    @endforelse

    <div class="people-pagination"><span>Showing {{ $records->firstItem() ?? 0 }} to {{ $records->lastItem() ?? 0 }} of {{ $records->total() }}</span>{{ $records->withQueryString()->links() }}</div>
</section>
