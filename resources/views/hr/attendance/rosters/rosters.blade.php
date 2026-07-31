@if ($abilities['canManage'])
    <section class="people-ops-grid is-wide-left">
        <article class="people-ops-panel" id="roster-create">
            <header class="people-ops-panel-head"><div><h2>Create dated roster</h2><p>Draft entries do not affect attendance until publication.</p></div><span class="people-status is-muted">Draft first</span></header>
            <form class="people-ops-panel-body people-form-grid" method="POST" action="{{ route('hr.attendance-rosters.store') }}" data-disable-on-submit>
                @csrf
                <label class="people-field is-wide">Roster name<input class="people-control" name="name" value="{{ old('name') }}" required maxlength="160"></label>
                <label class="people-field">Period start<input class="people-control" type="date" name="period_start" value="{{ old('period_start', now()->startOfWeek()->toDateString()) }}" required></label>
                <label class="people-field">Period end<input class="people-control" type="date" name="period_end" value="{{ old('period_end', now()->endOfWeek()->toDateString()) }}" required></label>
                <label class="people-field is-wide">Governed timezone <small>Resolved again from the active rule pack for the selected period start.</small><input class="people-control" value="{{ $governedTimezone }}" readonly aria-readonly="true"></label>
                <div class="people-form-actions is-wide"><button class="people-button is-primary" type="submit"><i class="fa-solid fa-plus" aria-hidden="true"></i> Create draft</button></div>
            </form>
        </article>

        <article class="people-ops-panel">
            <header class="people-ops-panel-head"><div><h2>Effective shift assignment</h2><p>Overlapping active assignments are rejected.</p></div><span class="people-status is-info">Effective dated</span></header>
            <form class="people-ops-panel-body people-form-grid" method="POST" action="{{ route('hr.attendance-shift-assignments.store') }}" data-disable-on-submit>
                @csrf
                <label class="people-field is-wide">Employee<select class="people-control" name="employee_id" required><option value="">Select employee</option>@foreach($employees as $employee)<option value="{{ $employee->id }}" @selected(old('employee_id') == $employee->id)>{{ $employee->name }} · {{ $employee->department ?: 'No department' }}</option>@endforeach</select></label>
                <label class="people-field is-wide">Shift<select class="people-control" name="attendance_shift_id" required><option value="">Select shift</option>@foreach($shifts as $shift)<option value="{{ $shift->id }}" @selected(old('attendance_shift_id') == $shift->id)>{{ $shift->code }} · {{ $shift->name }}</option>@endforeach</select></label>
                <label class="people-field">Effective from<input class="people-control" type="date" name="effective_from" value="{{ old('effective_from', now()->toDateString()) }}" required></label>
                <label class="people-field">Effective to<input class="people-control" type="date" name="effective_to" value="{{ old('effective_to') }}"></label>
                <div class="people-form-actions is-wide"><button class="people-button is-primary" type="submit"><i class="fa-solid fa-user-clock" aria-hidden="true"></i> Assign shift</button></div>
            </form>
        </article>
    </section>
@endif

<section class="people-roster-list" aria-label="Dated attendance rosters">
    @forelse ($rosters as $roster)
        <article class="people-ops-panel people-roster-card">
            <header class="people-ops-panel-head">
                <div><h2>{{ $roster->name }}</h2><p>{{ $roster->period_start->format('d M Y') }} – {{ $roster->period_end->format('d M Y') }} · {{ $roster->timezone }}</p></div>
                <div class="people-roster-badges"><span @class(['people-status', 'is-muted' => $roster->status === 'draft', 'is-info' => $roster->status === 'published', 'is-success' => $roster->status === 'locked', 'is-danger' => $roster->status === 'cancelled'])>{{ str($roster->status)->headline() }}</span><span class="people-count">{{ $roster->entries_count }} entries</span></div>
            </header>

            @if ($roster->relationLoaded('entries') && $roster->entries->isNotEmpty())
                <div class="people-ops-table-wrap"><table class="people-ops-table people-roster-entry-table"><caption>Entries in {{ $roster->name }}</caption><thead><tr><th scope="col">Employee</th><th scope="col">Work date</th><th scope="col">Assignment</th><th scope="col">Source</th></tr></thead><tbody>
                @foreach($roster->entries->sortBy(fn($entry) => $entry->work_date->format('Y-m-d').'-'.$entry->employee->name) as $entry)
                    <tr><td><strong>{{ $entry->employee->name }}</strong><small>{{ $entry->employee->department ?: 'No department' }}</small></td><td>{{ $entry->work_date->format('d M Y') }}</td><td>{{ $entry->entry_type === 'shift' ? ($entry->shift?->code.' · '.$entry->shift?->name) : str($entry->entry_type)->headline() }}</td><td><span class="people-status is-muted">{{ str($entry->source)->headline() }}</span></td></tr>
                @endforeach
                </tbody></table></div>
            @else
                <div class="people-ops-empty"><i class="fa-solid fa-calendar-plus" aria-hidden="true"></i><strong>No roster entries</strong><span>Add manual entries or generate them from an active rotation.</span></div>
            @endif

            @can('manage', $roster)
                <details class="people-roster-details"><summary>Add roster entry</summary><form class="people-form-grid" method="POST" action="{{ route('hr.attendance-rosters.entries.store', $roster) }}" data-disable-on-submit>@csrf<input type="hidden" name="lock_version" value="{{ $roster->lock_version }}"><label class="people-field is-wide">Employee<select class="people-control" name="employee_id" required><option value="">Select employee</option>@foreach($employees as $employee)<option value="{{ $employee->id }}">{{ $employee->name }} · {{ $employee->department ?: 'No department' }}</option>@endforeach</select></label><label class="people-field">Work date<input class="people-control" type="date" name="work_date" min="{{ $roster->period_start->toDateString() }}" max="{{ $roster->period_end->toDateString() }}" required></label><label class="people-field">Entry type<select class="people-control" name="entry_type" required><option value="shift">Working shift</option><option value="off">Weekly off</option><option value="holiday">Holiday</option></select></label><label class="people-field is-wide">Shift <small>Leave blank for an off day or holiday.</small><select class="people-control" name="attendance_shift_id"><option value="">No working shift</option>@foreach($shifts as $shift)<option value="{{ $shift->id }}">{{ $shift->code }} · {{ $shift->name }}</option>@endforeach</select></label><div class="people-form-actions is-wide"><button class="people-button is-primary" type="submit">Add entry</button></div></form></details>
            @endcan

            <footer class="people-roster-actions">
                @can('publish', $roster)<form method="POST" action="{{ route('hr.attendance-rosters.publish', $roster) }}">@csrf @method('PATCH')<input type="hidden" name="lock_version" value="{{ $roster->lock_version }}"><button class="people-button is-primary" type="submit"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Publish</button></form>@endcan
                @can('lock', $roster)<form method="POST" action="{{ route('hr.attendance-rosters.lock', $roster) }}">@csrf @method('PATCH')<input type="hidden" name="lock_version" value="{{ $roster->lock_version }}"><button class="people-button is-primary" type="submit"><i class="fa-solid fa-lock" aria-hidden="true"></i> Lock</button></form>@endcan
                @can('reopen', $roster)<form class="people-roster-cancel" method="POST" action="{{ route('hr.attendance-rosters.reopen', $roster) }}">@csrf @method('PATCH')<input type="hidden" name="lock_version" value="{{ $roster->lock_version }}"><label class="people-field"><span class="sr-only">Roster reopen reason</span><input class="people-control" name="status_note" placeholder="Required reopen reason" required maxlength="2000"></label><button class="people-button is-secondary" type="submit"><i class="fa-solid fa-lock-open" aria-hidden="true"></i> Reopen roster</button></form>@endcan
                @can('cancel', $roster)<form class="people-roster-cancel" method="POST" action="{{ route('hr.attendance-rosters.cancel', $roster) }}">@csrf @method('PATCH')<input type="hidden" name="lock_version" value="{{ $roster->lock_version }}"><label class="people-field"><span class="sr-only">Cancellation reason</span><input class="people-control" name="status_note" placeholder="Required cancellation reason" required maxlength="2000"></label><button class="people-button is-danger" type="submit">Cancel roster</button></form>@endcan
            </footer>
        </article>
    @empty
        <section class="people-ops-panel"><div class="people-ops-empty"><i class="fa-solid fa-calendar-days" aria-hidden="true"></i><strong>No rosters yet</strong><span>Create a dated roster to begin governed scheduling.</span></div></section>
    @endforelse
</section>

<div class="people-pagination"><span>Showing {{ $rosters->firstItem() ?? 0 }} to {{ $rosters->lastItem() ?? 0 }} of {{ $rosters->total() }}</span>{{ $rosters->links() }}</div>
