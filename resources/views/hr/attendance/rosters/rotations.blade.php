@if ($abilities['canManage'])
    @php($initialCycleDays = max(1, min(31, (int) old('cycle_days', 7))))
    <section class="people-ops-panel people-roster-create">
        <header class="people-ops-panel-head">
            <div>
                <h2>Create reusable rotation</h2>
                <p>Define one deterministic cycle item per day. Generation never duplicates an existing occurrence.</p>
            </div>
            <span class="people-status is-info">Versioned</span>
        </header>
        <form
            class="people-ops-panel-body people-form-grid"
            method="POST"
            action="{{ route('hr.attendance-rotation-rules.store') }}"
            data-disable-on-submit
            x-data="rotationPatternEditor"
            data-initial-cycle-days="{{ $initialCycleDays }}"
        >
            @csrf
            <label class="people-field">
                Rule name
                <input class="people-control" name="name" value="{{ old('name') }}" required maxlength="160">
            </label>
            <label class="people-field">
                Employee
                <select class="people-control" name="employee_id" required>
                    <option value="">Select employee</option>
                    @foreach($employees as $employee)
                        <option value="{{ $employee->id }}" @selected(old('employee_id') == $employee->id)>{{ $employee->name }} &middot; {{ $employee->department ?: 'No department' }}</option>
                    @endforeach
                </select>
            </label>
            <label class="people-field">
                Anchor date
                <input class="people-control" type="date" name="anchor_date" value="{{ old('anchor_date', now()->startOfWeek()->toDateString()) }}" required>
            </label>
            <label class="people-field">
                Generation horizon
                <input class="people-control" type="number" name="generation_horizon_days" value="{{ old('generation_horizon_days', 90) }}" min="1" max="366" required>
            </label>
            <label class="people-field">
                Cycle length
                <input class="people-control" type="number" name="cycle_days" value="{{ $initialCycleDays }}" min="1" max="31" required x-on:input="updateCycleDays">
                <small>Choose between 1 and 31 days.</small>
            </label>

            <fieldset class="people-rotation-pattern is-wide">
                <legend x-ref="cycleLabel">{{ $initialCycleDays }}-day rotation pattern</legend>
                <p>Choose a working shift, weekly off, or holiday for each cycle day.</p>
                <div class="people-rotation-days" x-ref="days" x-on:change="normalizeDay">
                    @for($day = 0; $day < $initialCycleDays; $day++)
                        <div class="people-rotation-day" data-rotation-day>
                            <strong data-day-label>Day {{ $day + 1 }}</strong>
                            <label class="people-field">
                                <span class="sr-only">Day {{ $day + 1 }} type</span>
                                <select class="people-control" name="pattern[{{ $day }}][type]" required data-day-type>
                                    <option value="shift" @selected(old("pattern.$day.type", 'shift') === 'shift')>Working shift</option>
                                    <option value="off" @selected(old("pattern.$day.type") === 'off')>Weekly off</option>
                                    <option value="holiday" @selected(old("pattern.$day.type") === 'holiday')>Holiday</option>
                                </select>
                            </label>
                            <label class="people-field">
                                <span class="sr-only">Day {{ $day + 1 }} shift</span>
                                <select class="people-control" name="pattern[{{ $day }}][attendance_shift_id]" data-day-shift>
                                    <option value="">No shift</option>
                                    @foreach($shifts as $shift)
                                        <option value="{{ $shift->id }}" @selected(old("pattern.$day.attendance_shift_id") == $shift->id)>{{ $shift->code }} &middot; {{ $shift->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>
                    @endfor
                </div>
                <template x-ref="dayTemplate">
                    <div class="people-rotation-day" data-rotation-day>
                        <strong data-day-label>Day</strong>
                        <label class="people-field">
                            <span class="sr-only">Rotation day type</span>
                            <select class="people-control" required data-day-type>
                                <option value="shift">Working shift</option>
                                <option value="off">Weekly off</option>
                                <option value="holiday">Holiday</option>
                            </select>
                        </label>
                        <label class="people-field">
                            <span class="sr-only">Rotation day shift</span>
                            <select class="people-control" data-day-shift>
                                <option value="">No shift</option>
                                @foreach($shifts as $shift)
                                    <option value="{{ $shift->id }}">{{ $shift->code }} &middot; {{ $shift->name }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                </template>
            </fieldset>
            <div class="people-form-actions is-wide">
                <button class="people-button is-primary" type="submit"><i class="fa-solid fa-rotate" aria-hidden="true"></i> Create rotation</button>
            </div>
        </form>
    </section>
@endif

<section class="people-ops-panel has-mobile-cards">
    <header class="people-ops-panel-head">
        <div><h2>Rotation rules</h2><p>Rules generate dated entries only into draft rosters.</p></div>
        <span class="people-count">{{ $rotations->total() }} rules</span>
    </header>
    <div class="people-ops-table-wrap">
        <table class="people-ops-table">
            <caption>Attendance rotation rules</caption>
            <thead><tr><th scope="col">Employee / rule</th><th scope="col">Anchor</th><th scope="col">Cycle</th><th scope="col">Horizon</th><th scope="col">Status</th><th scope="col">Generate into draft</th></tr></thead>
            <tbody>
                @forelse($rotations as $rotation)
                    <tr>
                        <td><strong>{{ $rotation->employee->name }}</strong><small>{{ $rotation->name }}</small></td>
                        <td>{{ $rotation->anchor_date->format('d M Y') }}</td>
                        <td>{{ $rotation->cycle_days }} days</td>
                        <td>{{ $rotation->generation_horizon_days }} days</td>
                        <td><span @class(['people-status', 'is-success' => $rotation->status === 'active', 'is-warning' => $rotation->status === 'paused'])>{{ str($rotation->status)->headline() }}</span></td>
                        <td>
                            @if($abilities['canManage'] && $rotation->status === 'active' && $draftRosters->isNotEmpty())
                                <details class="people-compact-menu">
                                    <summary class="people-button">Generate</summary>
                                    <div>
                                        @foreach($draftRosters as $draft)
                                            <form method="POST" action="{{ route('hr.attendance-rotation-rules.generate', [$rotation, $draft]) }}">
                                                @csrf
                                                <input type="hidden" name="lock_version" value="{{ $draft->lock_version }}">
                                                <button type="submit">{{ $draft->name }}<small>{{ $draft->period_start->format('d M') }} &ndash; {{ $draft->period_end->format('d M Y') }}</small></button>
                                            </form>
                                        @endforeach
                                    </div>
                                </details>
                            @else
                                <span class="people-muted">Unavailable</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6"><div class="people-ops-empty"><i class="fa-solid fa-rotate" aria-hidden="true"></i><strong>No rotation rules</strong><span>Create an employee rotation to generate dated roster occurrences.</span></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="people-ops-mobile-list">
        @foreach($rotations as $rotation)
            <article class="people-ops-mobile-card">
                <header class="people-ops-mobile-card-head"><div><strong>{{ $rotation->employee->name }}</strong><small>{{ $rotation->name }}</small></div><span class="people-status is-success">{{ str($rotation->status)->headline() }}</span></header>
                <dl class="people-ops-mobile-facts"><div><dt>Anchor</dt><dd>{{ $rotation->anchor_date->format('d M Y') }}</dd></div><div><dt>Cycle</dt><dd>{{ $rotation->cycle_days }} days</dd></div><div><dt>Horizon</dt><dd>{{ $rotation->generation_horizon_days }} days</dd></div></dl>
            </article>
        @endforeach
    </div>
    <div class="people-pagination"><span>Showing {{ $rotations->firstItem() ?? 0 }} to {{ $rotations->lastItem() ?? 0 }} of {{ $rotations->total() }}</span>{{ $rotations->links() }}</div>
</section>
