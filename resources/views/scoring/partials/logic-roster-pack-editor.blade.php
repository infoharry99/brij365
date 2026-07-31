@if ($page->capabilities['manageRosters'])
    @php
        $attendanceKey = \App\Domain\Hr\Services\AttendanceRosterRulePackValidator::ATTENDANCE_KEY;
        $rosterKey = \App\Domain\Hr\Services\AttendanceRosterRulePackValidator::ROSTER_KEY;
        $failedPack = old('setting_key');
        $today = now()->toDateString();
    @endphp

    <section class="logic-editor-stack" aria-labelledby="logic-roster-editor-title">
        <div class="logic-editor-heading">
            <div>
                <span class="b360-eyebrow">Governed variables</span>
                <h2 id="logic-roster-editor-title">Attendance and roster rule drafts</h2>
                <p>Only an approved active version changes attendance or roster decisions. Draft creators cannot approve their own version.</p>
            </div>
        </div>

        <details class="logic-pack-editor" @if($errors->any() && $failedPack === $attendanceKey) open @endif>
            <summary>
                <span><i class="fa-solid fa-clock" aria-hidden="true"></i><strong>Create attendance calculation draft</strong></span>
                <small>Timezone, grace, day thresholds and deterministic minute rounding.</small>
            </summary>

            <form method="POST" action="{{ route('scoring.attendance-roster-rule-packs.store') }}" class="logic-pack-form" x-data="serverFormState" x-on:submit="beginSubmit" x-bind:aria-busy="busyAria" data-idle-label="Create attendance draft" data-busy-label="Creating attendance draft...">
                @csrf
                <input type="hidden" name="setting_key" value="{{ $attendanceKey }}">
                <div class="blade-form-grid">
                    <x-forms.field name="label" for="attendance-rule-label" label="Version label" required>
                        <x-forms.input id="attendance-rule-label" name="label" :value="$failedPack === $attendanceKey ? old('label') : ''" maxlength="255" required />
                    </x-forms.field>
                    <x-forms.field name="effective_from" for="attendance-rule-effective" label="Effective from" required>
                        <x-forms.input id="attendance-rule-effective" name="effective_from" type="date" :value="$failedPack === $attendanceKey ? old('effective_from', $today) : $today" required />
                    </x-forms.field>
                    <x-forms.field name="value[company_timezone]" for="attendance-rule-timezone" label="Company timezone" required>
                        <x-forms.input id="attendance-rule-timezone" name="value[company_timezone]" :value="$failedPack === $attendanceKey ? old('value.company_timezone', 'Asia/Kolkata') : 'Asia/Kolkata'" required />
                    </x-forms.field>
                    <x-forms.field name="value[late_grace_minutes]" for="attendance-late-grace" label="Late grace (minutes)">
                        <x-forms.input id="attendance-late-grace" name="value[late_grace_minutes]" type="number" min="0" max="1440" step="1" :value="$failedPack === $attendanceKey ? old('value.late_grace_minutes') : ''" />
                    </x-forms.field>
                    <x-forms.field name="value[early_leave_grace_minutes]" for="attendance-early-grace" label="Early-leave grace (minutes)">
                        <x-forms.input id="attendance-early-grace" name="value[early_leave_grace_minutes]" type="number" min="0" max="1440" step="1" :value="$failedPack === $attendanceKey ? old('value.early_leave_grace_minutes') : ''" />
                    </x-forms.field>
                    <x-forms.field name="value[half_day_threshold_minutes]" for="attendance-half-day" label="Half-day threshold (minutes)">
                        <x-forms.input id="attendance-half-day" name="value[half_day_threshold_minutes]" type="number" min="0" max="2880" step="1" :value="$failedPack === $attendanceKey ? old('value.half_day_threshold_minutes') : ''" />
                    </x-forms.field>
                    <x-forms.field name="value[full_day_threshold_minutes]" for="attendance-full-day" label="Full-day threshold (minutes)">
                        <x-forms.input id="attendance-full-day" name="value[full_day_threshold_minutes]" type="number" min="0" max="2880" step="1" :value="$failedPack === $attendanceKey ? old('value.full_day_threshold_minutes') : ''" />
                    </x-forms.field>
                    <x-forms.field name="value[rounding]" for="attendance-rounding" label="Minute rounding" required>
                        <x-forms.select id="attendance-rounding" name="value[rounding]" required>
                            @foreach(['nearest_minute' => 'Nearest minute', 'floor_minute' => 'Floor to minute', 'ceil_minute' => 'Ceil to minute'] as $value => $label)
                                <option value="{{ $value }}" @selected(($failedPack === $attendanceKey ? old('value.rounding', 'nearest_minute') : 'nearest_minute') === $value)>{{ $label }}</option>
                            @endforeach
                        </x-forms.select>
                    </x-forms.field>
                    <x-forms.field name="description" for="attendance-rule-description" label="Change reason and scope">
                        <x-forms.textarea id="attendance-rule-description" name="description" rows="3" maxlength="5000">{{ $failedPack === $attendanceKey ? old('description') : '' }}</x-forms.textarea>
                    </x-forms.field>
                </div>
                <div class="logic-guard-notice" role="note">
                    <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                    <span><strong>Draft only.</strong> A different authorized user must review and approve this version before it becomes effective.</span>
                </div>
                <div class="blade-form-actions">
                    <x-ui.action type="submit" variant="primary" x-bind:disabled="busy"><span x-text="submitLabel">Create attendance draft</span></x-ui.action>
                </div>
            </form>
        </details>

        <details class="logic-pack-editor" @if($errors->any() && $failedPack === $rosterKey) open @endif>
            <summary>
                <span><i class="fa-solid fa-calendar-days" aria-hidden="true"></i><strong>Create roster governance draft</strong></span>
                <small>Publication, overlap, rest, coverage, swap cutoff and reopen controls.</small>
            </summary>

            <form method="POST" action="{{ route('scoring.attendance-roster-rule-packs.store') }}" class="logic-pack-form" x-data="serverFormState" x-on:submit="beginSubmit" x-bind:aria-busy="busyAria" data-idle-label="Create roster draft" data-busy-label="Creating roster draft...">
                @csrf
                <input type="hidden" name="setting_key" value="{{ $rosterKey }}">
                <div class="blade-form-grid">
                    <x-forms.field name="label" for="roster-rule-label" label="Version label" required>
                        <x-forms.input id="roster-rule-label" name="label" :value="$failedPack === $rosterKey ? old('label') : ''" maxlength="255" required />
                    </x-forms.field>
                    <x-forms.field name="effective_from" for="roster-rule-effective" label="Effective from" required>
                        <x-forms.input id="roster-rule-effective" name="effective_from" type="date" :value="$failedPack === $rosterKey ? old('effective_from', $today) : $today" required />
                    </x-forms.field>
                    <x-forms.field name="value[company_timezone]" for="roster-rule-timezone" label="Company timezone" required>
                        <x-forms.input id="roster-rule-timezone" name="value[company_timezone]" :value="$failedPack === $rosterKey ? old('value.company_timezone', 'Asia/Kolkata') : 'Asia/Kolkata'" required />
                    </x-forms.field>
                    <x-forms.field name="value[block_shift_overlaps]" for="roster-overlap" label="Block shift overlaps" required>
                        <x-forms.select id="roster-overlap" name="value[block_shift_overlaps]" required>
                            <option value="1" @selected(($failedPack === $rosterKey ? old('value.block_shift_overlaps', '1') : '1') === '1')>Yes</option>
                            <option value="0" @selected($failedPack === $rosterKey && old('value.block_shift_overlaps') === '0')>No</option>
                        </x-forms.select>
                    </x-forms.field>
                    <x-forms.field name="value[minimum_rest_minutes]" for="roster-min-rest" label="Minimum rest (minutes)" required>
                        <x-forms.input id="roster-min-rest" name="value[minimum_rest_minutes]" type="number" min="0" max="2880" step="1" :value="$failedPack === $rosterKey ? old('value.minimum_rest_minutes', 0) : 0" required />
                    </x-forms.field>
                    <x-forms.field name="value[maximum_consecutive_workdays]" for="roster-max-days" label="Maximum consecutive workdays" required>
                        <x-forms.input id="roster-max-days" name="value[maximum_consecutive_workdays]" type="number" min="0" max="31" step="1" :value="$failedPack === $rosterKey ? old('value.maximum_consecutive_workdays', 0) : 0" required />
                    </x-forms.field>
                    <x-forms.field name="value[require_complete_period_assignment]" for="roster-complete-assignment" label="Require complete period assignment" required>
                        <x-forms.select id="roster-complete-assignment" name="value[require_complete_period_assignment]" required>
                            <option value="0" @selected(($failedPack === $rosterKey ? old('value.require_complete_period_assignment', '0') : '0') === '0')>No</option>
                            <option value="1" @selected($failedPack === $rosterKey && old('value.require_complete_period_assignment') === '1')>Yes</option>
                        </x-forms.select>
                    </x-forms.field>
                    <x-forms.field name="value[coverage_scope]" for="roster-coverage" label="Coverage scope" required>
                        <x-forms.select id="roster-coverage" name="value[coverage_scope]" required>
                            <option value="roster_employees" @selected(($failedPack === $rosterKey ? old('value.coverage_scope', 'roster_employees') : 'roster_employees') === 'roster_employees')>Employees present in roster</option>
                            <option value="all_active_employees" @selected($failedPack === $rosterKey && old('value.coverage_scope') === 'all_active_employees')>All active employees</option>
                        </x-forms.select>
                    </x-forms.field>
                    @foreach([
                        'publication_lead_days' => ['Publication lead (days)', 0, 90, 0],
                        'swap_request_cutoff_hours' => ['Swap request cutoff (hours)', 0, 720, 0],
                        'maximum_rotation_generation_horizon_days' => ['Rotation generation horizon (days)', 1, 366, 366],
                        'roster_reopen_limit_days' => ['Roster reopen limit (days)', 0, 3650, 0],
                        'attendance_reopen_limit_days' => ['Attendance reopen limit (days)', 0, 3650, 0],
                    ] as $key => [$label, $min, $max, $default])
                        <x-forms.field name="value[{{ $key }}]" for="roster-{{ str($key)->replace('_', '-') }}" :label="$label" required>
                            <x-forms.input id="roster-{{ str($key)->replace('_', '-') }}" name="value[{{ $key }}]" type="number" :min="$min" :max="$max" step="1" :value="$failedPack === $rosterKey ? old('value.'.$key, $default) : $default" required />
                        </x-forms.field>
                    @endforeach
                    <x-forms.field name="description" for="roster-rule-description" label="Change reason and scope">
                        <x-forms.textarea id="roster-rule-description" name="description" rows="3" maxlength="5000">{{ $failedPack === $rosterKey ? old('description') : '' }}</x-forms.textarea>
                    </x-forms.field>
                </div>
                <div class="logic-guard-notice" role="note">
                    <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                    <span><strong>Draft only.</strong> Publishing, swaps and attendance locks continue using the current approved version until a different authorized user approves this one.</span>
                </div>
                <div class="blade-form-actions">
                    <x-ui.action type="submit" variant="primary" x-bind:disabled="busy"><span x-text="submitLabel">Create roster draft</span></x-ui.action>
                </div>
            </form>
        </details>
    </section>
@endif
