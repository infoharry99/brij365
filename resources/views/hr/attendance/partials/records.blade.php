<section class="people-ops-kpis" aria-label="Attendance summary" data-summary-total="{{ $summary->total }}">
    <article class="people-ops-kpi is-success">
        <span class="people-ops-kpi-icon"><i class="fa-solid fa-user-check" aria-hidden="true"></i></span>
        <span>Present</span>
        <strong>{{ $summary->present }}</strong>
        <small>Stored present results in the selected date and employee scope</small>
    </article>
    <article class="people-ops-kpi is-warning">
        <span class="people-ops-kpi-icon"><i class="fa-solid fa-clock" aria-hidden="true"></i></span>
        <span>Late</span>
        <strong>{{ $summary->late }}</strong>
        <small>Records beyond the linked shift grace threshold</small>
    </article>
    <article class="people-ops-kpi is-warning">
        <span class="people-ops-kpi-icon"><i class="fa-solid fa-person-walking-arrow-right" aria-hidden="true"></i></span>
        <span>Early leaving</span>
        <strong>{{ $summary->earlyLeave }}</strong>
        <small>Stored early-leave results in your authorized scope</small>
    </article>
    <article class="people-ops-kpi is-purple">
        <span class="people-ops-kpi-icon"><i class="fa-solid fa-circle-half-stroke" aria-hidden="true"></i></span>
        <span>Half-day</span>
        <strong>{{ $summary->halfDay }}</strong>
        <small>Records classified below the configured full-day threshold</small>
    </article>
    <article class="people-ops-kpi is-danger">
        <span class="people-ops-kpi-icon"><i class="fa-solid fa-user-xmark" aria-hidden="true"></i></span>
        <span>Absent</span>
        <strong>{{ $summary->absent }}</strong>
        <small>{{ $summary->attendanceRate }}% attendance coverage across {{ $summary->total }} records</small>
    </article>
</section>

<section class="people-ops-grid is-wide-left">
    <article class="people-ops-panel has-mobile-cards">
        <header class="people-ops-panel-head">
            <div>
                <h2>Attendance register</h2>
                <p>Persisted results from the complete authorized attendance query.</p>
            </div>
            <span class="people-count">{{ $records->total() }} records</span>
        </header>

        <form method="GET" action="{{ route('hr.attendance-records.index') }}" class="people-ops-filterbar">
            <label class="people-field">
                Employee
                <select class="people-control" name="employee_id">
                    <option value="">All visible employees</option>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->id }}" @selected((string) request('employee_id') === (string) $employee->id)>{{ $employee->employee_code }} - {{ $employee->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="people-field">
                Status
                <select class="people-control" name="status">
                    <option value="">All statuses</option>
                    @foreach ($statusFilters as $status)
                        <option value="{{ $status['value'] }}" @selected(request('status') === $status['value'])>{{ $status['label'] }}</option>
                    @endforeach
                </select>
            </label>
            <label class="people-field">
                Date from
                <input class="people-control" type="date" name="date_from" value="{{ request('date_from') }}">
            </label>
            <label class="people-field">
                Date to
                <input class="people-control" type="date" name="date_to" value="{{ request('date_to') }}">
            </label>
            <button type="submit" class="people-button"><i class="fa-solid fa-filter" aria-hidden="true"></i> Apply</button>
            @if (request()->hasAny(['employee_id', 'status', 'date_from', 'date_to']))
                <a class="people-button" href="{{ route('hr.attendance-records.index') }}">Clear</a>
            @endif
        </form>

        <div class="people-ops-table-wrap">
            <table class="people-ops-table">
                <caption>Attendance records for the selected filters</caption>
                <thead><tr><th scope="col">Date</th><th scope="col">Employee</th><th scope="col">Shift</th><th scope="col">Check-in</th><th scope="col">Check-out</th><th scope="col">Status</th><th scope="col" class="is-number">Late</th><th scope="col" class="is-number">Early</th><th scope="col" class="is-number">Worked</th><th scope="col">Source</th></tr></thead>
                <tbody>
                    @forelse ($records as $record)
                        <tr>
                            <td>{{ $record->workDate }}</td>
                            <td><div class="people-ops-identity"><span class="people-avatar">{{ $record->employeeInitial }}</span><div><strong>{{ $record->employeeName }}</strong><small>{{ $record->employeeCode }} / {{ $record->branch }}</small></div></div></td>
                            <td><strong>{{ $record->shiftCode }}</strong><small>{{ $record->shiftName }} / {{ $record->shiftTiming }}</small></td>
                            <td>{{ $record->checkIn }}</td>
                            <td>{{ $record->checkOut }}</td>
                            <td><span @class(['people-status', 'is-success' => $record->status === 'present', 'is-warning' => in_array($record->status, ['late', 'early_leave', 'half_day'], true), 'is-danger' => $record->status === 'absent', 'is-muted' => ! in_array($record->status, ['present', 'late', 'early_leave', 'half_day', 'absent'], true)])>{{ $record->statusLabel }}</span></td>
                            <td class="is-number">{{ $record->lateMinutes }} min</td>
                            <td class="is-number">{{ $record->earlyLeaveMinutes }} min</td>
                            <td class="is-number">{{ $record->workedMinutes }} min</td>
                            <td>{{ $record->sourceLabel }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="10"><div class="people-ops-empty"><i class="fa-solid fa-calendar-xmark" aria-hidden="true"></i><strong>No attendance records</strong><span>No records match the selected employee, status, and date filters.</span></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="people-ops-mobile-list">
            @foreach ($records as $record)
                <article class="people-ops-mobile-card">
                    <header class="people-ops-mobile-card-head"><div class="people-ops-identity"><span class="people-avatar">{{ $record->employeeInitial }}</span><div><strong>{{ $record->employeeName }}</strong><small>{{ $record->employeeCode }}</small></div></div><span class="people-status is-muted">{{ $record->statusLabel }}</span></header>
                    <dl class="people-ops-mobile-facts"><div><dt>Date</dt><dd>{{ $record->workDate }}</dd></div><div><dt>Shift</dt><dd>{{ $record->shiftCode }} / {{ $record->shiftTiming }}</dd></div><div><dt>Check-in</dt><dd>{{ $record->checkIn }}</dd></div><div><dt>Check-out</dt><dd>{{ $record->checkOut }}</dd></div><div><dt>Exceptions</dt><dd>Late {{ $record->lateMinutes }} min / Early {{ $record->earlyLeaveMinutes }} min</dd></div><div><dt>Worked</dt><dd>{{ $record->workedMinutes }} min</dd></div></dl>
                </article>
            @endforeach
        </div>

        <div class="people-pagination"><span>Showing {{ $records->firstItem() ?? 0 }} to {{ $records->lastItem() ?? 0 }} of {{ $records->total() }}</span>{{ $records->withQueryString()->links() }}</div>
    </article>

    <aside class="people-ops-panel">
        <header class="people-ops-panel-head"><div><h2>Site attendance coverage</h2><p>Distinct employees marked present-like within the selected period.</p></div></header>
        @forelse ($siteAttendance as $site)
            <div class="people-ops-panel-body">
                <strong>{{ $site['location'] }}</strong>
                <div class="people-ops-progress"><progress max="100" value="{{ $site['coverage'] }}">{{ $site['coverage'] }}%</progress><span>{{ $site['coverage'] }}%</span></div>
                <small>{{ $site['marked'] }} marked of {{ $site['strength'] }} active employees</small>
            </div>
        @empty
            <div class="people-ops-empty"><i class="fa-solid fa-location-dot" aria-hidden="true"></i><strong>No site coverage available</strong><span>No active employee branch data is available in your authorized scope.</span></div>
        @endforelse
    </aside>
</section>
