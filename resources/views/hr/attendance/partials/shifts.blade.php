@if ($abilities['canCreateShift'])
    <details class="people-ops-panel" id="shift-form" @if($errors->any()) open @endif>
        <summary class="people-ops-panel-head"><div><h2>Create shift definition</h2><p>Configure stored timing thresholds used by attendance processing.</p></div><span class="people-button is-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i> New shift</span></summary>
        <form method="POST" action="{{ route('hr.attendance-shifts.store') }}" class="people-ops-panel-body">
            @csrf
            <x-forms.company-context :companies="$companies" placeholder="Use my company" />
            <div class="people-ops-controls-grid">
                <label class="people-field">Code<input class="people-control" name="code" value="{{ old('code') }}" maxlength="32" required placeholder="DAY_GENERAL">@error('code')<span class="people-field-error">{{ $message }}</span>@enderror</label>
                <label class="people-field">Name<input class="people-control" name="name" value="{{ old('name') }}" maxlength="255" required placeholder="General day shift">@error('name')<span class="people-field-error">{{ $message }}</span>@enderror</label>
                <label class="people-field">Starts at<input class="people-control" type="time" name="starts_at" value="{{ old('starts_at') }}" required>@error('starts_at')<span class="people-field-error">{{ $message }}</span>@enderror</label>
                <label class="people-field">Ends at<input class="people-control" type="time" name="ends_at" value="{{ old('ends_at') }}" required>@error('ends_at')<span class="people-field-error">{{ $message }}</span>@enderror</label>
                <label class="people-field">Late grace minutes<input class="people-control" type="number" name="late_grace_minutes" min="0" max="240" value="{{ old('late_grace_minutes', 0) }}" required></label>
                <label class="people-field">Early-leave grace minutes<input class="people-control" type="number" name="early_leave_grace_minutes" min="0" max="240" value="{{ old('early_leave_grace_minutes', 0) }}" required></label>
                <label class="people-field">Half-day threshold minutes<input class="people-control" type="number" name="half_day_threshold_minutes" min="1" max="1440" value="{{ old('half_day_threshold_minutes', 240) }}" required></label>
                <label class="people-field">Full-day threshold minutes<input class="people-control" type="number" name="full_day_threshold_minutes" min="1" max="1440" value="{{ old('full_day_threshold_minutes', 480) }}" required></label>
                <label class="people-field">Shift type<select class="people-control" name="rules[shift_type]"><option value="">Not specified</option>@foreach($shiftTypes as $type)<option value="{{ $type['value'] }}" @selected(old('rules.shift_type') === $type['value'])>{{ $type['label'] }}</option>@endforeach</select></label>
                <label class="people-field">Weekly-off policy<input class="people-control" name="rules[weekly_off_policy]" maxlength="120" value="{{ old('rules.weekly_off_policy') }}" placeholder="Configured policy name"></label>
            </div>
            <input type="hidden" name="is_overnight" value="0">
            <label class="people-field"><span><input type="checkbox" name="is_overnight" value="1" @checked(old('is_overnight'))> Overnight shift</span></label>
            <label class="people-field"><span><input type="checkbox" name="rules[overtime_enabled]" value="1" @checked(old('rules.overtime_enabled'))> Overtime rule enabled</span></label>
            <label class="people-field"><span><input type="checkbox" name="rules[geofence_required]" value="1" @checked(old('rules.geofence_required'))> Geofence required</span></label>
            <details class="people-ops-panel" @if($errors->has('segments') || $errors->has('segments.*')) open @endif>
                <summary class="people-ops-panel-head"><div><h3>Split-shift working segments</h3><p>Required only when Shift type is Split. Blank rows are ignored.</p></div></summary>
                <div class="people-ops-panel-body people-ops-controls-grid">
                    @for ($segmentIndex = 0; $segmentIndex < 4; $segmentIndex++)
                        <label class="people-field">Segment {{ $segmentIndex + 1 }} label
                            <input class="people-control" name="segments[{{ $segmentIndex }}][label]" maxlength="80" value="{{ old("segments.$segmentIndex.label") }}" placeholder="{{ $segmentIndex === 0 ? 'Morning' : ($segmentIndex === 1 ? 'Afternoon' : 'Optional') }}">
                            @error("segments.$segmentIndex.label")<span class="people-field-error">{{ $message }}</span>@enderror
                        </label>
                        <label class="people-field">Segment {{ $segmentIndex + 1 }} starts
                            <input class="people-control" type="time" name="segments[{{ $segmentIndex }}][starts_at]" value="{{ old("segments.$segmentIndex.starts_at") }}">
                            @error("segments.$segmentIndex.starts_at")<span class="people-field-error">{{ $message }}</span>@enderror
                        </label>
                        <label class="people-field">Segment {{ $segmentIndex + 1 }} ends
                            <input class="people-control" type="time" name="segments[{{ $segmentIndex }}][ends_at]" value="{{ old("segments.$segmentIndex.ends_at") }}">
                            @error("segments.$segmentIndex.ends_at")<span class="people-field-error">{{ $message }}</span>@enderror
                        </label>
                    @endfor
                    @error('segments')<span class="people-field-error">{{ $message }}</span>@enderror
                </div>
            </details>
            <button class="people-button is-primary" type="submit"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Save shift</button>
        </form>
    </details>
@endif

<section class="people-ops-panel">
    <header class="people-ops-panel-head"><div><h2>Shift definitions</h2><p>Active timing and attendance threshold rules in your authorized company scope.</p></div><span class="people-count">{{ $shifts->total() }} shifts</span></header>

    <div class="people-shift-grid">
        @forelse ($shifts as $shift)
            <article class="people-shift-card">
                <header class="people-shift-card-head"><span class="people-shift-card-icon"><i class="fa-regular fa-clock" aria-hidden="true"></i></span><span @class(['people-status', 'is-purple' => $shift->overnight, 'is-success' => ! $shift->overnight])>{{ $shift->overnight ? 'Overnight' : 'Same day' }}</span></header>
                <h2>{{ $shift->name }}</h2>
                <strong class="people-shift-time">{{ $shift->timing }}</strong>
                <p>{{ $shift->code }} / Late grace {{ $shift->lateGraceMinutes }} min / Early grace {{ $shift->earlyLeaveGraceMinutes }} min</p>
                <p>Half day {{ $shift->halfDayThresholdMinutes }} min / Full day {{ $shift->fullDayThresholdMinutes }} min</p>
                @if ($shift->segments)
                    <ol aria-label="Split-shift working segments">
                        @foreach ($shift->segments as $segment)
                            <li>{{ $segment['label'] ?: 'Segment '.$segment['sequence'] }}: {{ $segment['timing'] }}</li>
                        @endforeach
                    </ol>
                @endif
                <div class="people-shift-meta"><span>{{ $shift->activeAssignments }} active assignments</span><span>{{ $shift->rules ? count($shift->rules).' optional rules' : 'No optional rules' }}</span></div>
            </article>
        @empty
            <div class="people-ops-empty"><i class="fa-regular fa-clock" aria-hidden="true"></i><strong>No active shift definitions</strong><span>Create a shift only when its approved timing and attendance thresholds are known.</span></div>
        @endforelse
    </div>

    <div class="people-pagination"><span>Showing {{ $shifts->firstItem() ?? 0 }} to {{ $shifts->lastItem() ?? 0 }} of {{ $shifts->total() }}</span>{{ $shifts->withQueryString()->links() }}</div>
</section>
