<section class="people-ops-panel">
    <header class="people-ops-panel-head">
        <div>
            <h2>Attendance calculation trace</h2>
            <p>Recorded inputs, the current linked shift definition, and the persisted attendance result.</p>
        </div>
        <span class="people-count">{{ $calculationTraces->total() }} records</span>
    </header>

    <form method="GET" action="{{ route('hr.attendance-records.index') }}" class="people-ops-filterbar">
        <input type="hidden" name="view" value="trace">
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
            Stored result
            <select class="people-control" name="status">
                <option value="">All results</option>
                @foreach ($statusFilters as $status)
                    <option value="{{ $status['value'] }}" @selected(request('status') === $status['value'])>{{ $status['label'] }}</option>
                @endforeach
            </select>
        </label>
        <label class="people-field">Date from<input class="people-control" type="date" name="date_from" value="{{ request('date_from') }}"></label>
        <label class="people-field">Date to<input class="people-control" type="date" name="date_to" value="{{ request('date_to') }}"></label>
        <button class="people-button" type="submit"><i class="fa-solid fa-filter" aria-hidden="true"></i> Apply</button>
        @if (request()->hasAny(['employee_id', 'status', 'date_from', 'date_to']))
            <a class="people-button" href="{{ route('hr.attendance-records.index', ['view' => 'trace']) }}">Clear</a>
        @endif
    </form>

    <div class="people-alert" role="note">
        <strong>Evidence boundary.</strong> The trace never recalculates attendance. Shift values are the current linked definition because a calculation-time rule snapshot and provider punch stream are not stored on attendance records.
    </div>

    <div class="people-ops-panel-body">
        <div class="people-ops-stack">
            @forelse ($calculationTraces as $trace)
                <details class="people-processing-details">
                    <summary>
                        {{ $trace->employeeName }} / {{ $trace->workDate }} / {{ $trace->statusLabel }}
                    </summary>
                    <div class="people-processing-details-body">
                        <section aria-labelledby="attendance-inputs-{{ $trace->recordId }}">
                            <h3 id="attendance-inputs-{{ $trace->recordId }}">1. Recorded inputs</h3>
                            <dl class="people-processing-rules">
                                <div><dt>Employee</dt><dd>{{ $trace->employeeCode }} / {{ $trace->employeeName }}</dd></div>
                                <div><dt>Branch</dt><dd>{{ $trace->branch }}</dd></div>
                                <div><dt>Work date</dt><dd>{{ $trace->workDate }}</dd></div>
                                <div><dt>Source</dt><dd>{{ $trace->sourceLabel }}</dd></div>
                                <div><dt>Check-in</dt><dd>{{ $trace->checkIn }}</dd></div>
                                <div><dt>Check-out</dt><dd>{{ $trace->checkOut }}</dd></div>
                                @if ($trace->regularizationRequestNumber)
                                    <div class="is-wide"><dt>Regularization request</dt><dd>{{ $trace->regularizationRequestNumber }}</dd></div>
                                @endif
                            </dl>
                        </section>

                        <section aria-labelledby="attendance-shift-{{ $trace->recordId }}">
                            <h3 id="attendance-shift-{{ $trace->recordId }}">2. Current linked shift</h3>
                            @if ($trace->hasLinkedShift)
                                <dl class="people-processing-rules">
                                    <div><dt>Shift</dt><dd>{{ $trace->shiftCode }} / {{ $trace->shiftName }}</dd></div>
                                    <div><dt>Timing</dt><dd>{{ $trace->shiftTiming }}</dd></div>
                                    <div><dt>Overnight</dt><dd>{{ $trace->overnight ? 'Yes' : 'No' }}</dd></div>
                                    <div><dt>Late grace</dt><dd>{{ $trace->lateGraceMinutes }} min</dd></div>
                                    <div><dt>Early-leave grace</dt><dd>{{ $trace->earlyLeaveGraceMinutes }} min</dd></div>
                                    <div><dt>Half-day threshold</dt><dd>{{ $trace->halfDayThresholdMinutes }} min</dd></div>
                                    <div><dt>Full-day threshold</dt><dd>{{ $trace->fullDayThresholdMinutes }} min</dd></div>
                                </dl>
                            @else
                                <div class="people-ops-empty">
                                    <i class="fa-solid fa-link-slash" aria-hidden="true"></i>
                                    <strong>No linked shift</strong>
                                    <span>No persisted shift definition is available for this record.</span>
                                </div>
                            @endif
                        </section>

                        <section aria-labelledby="attendance-result-{{ $trace->recordId }}">
                            <h3 id="attendance-result-{{ $trace->recordId }}">3. Persisted result</h3>
                            <dl class="people-processing-rules">
                                <div><dt>Status</dt><dd>{{ $trace->statusLabel }}</dd></div>
                                <div><dt>Worked</dt><dd>{{ $trace->workedMinutes }} min</dd></div>
                                <div><dt>Late</dt><dd>{{ $trace->lateMinutes }} min</dd></div>
                                <div><dt>Early leave</dt><dd>{{ $trace->earlyLeaveMinutes }} min</dd></div>
                            </dl>
                        </section>

                        <section aria-labelledby="attendance-provenance-{{ $trace->recordId }}">
                            <h3 id="attendance-provenance-{{ $trace->recordId }}">Provenance</h3>
                            <p>{{ $trace->provenanceNote }}</p>
                        </section>
                    </div>
                </details>
            @empty
                <div class="people-ops-empty">
                    <i class="fa-solid fa-diagram-project" aria-hidden="true"></i>
                    <strong>No calculation traces</strong>
                    <span>No persisted attendance records match the selected employee, result, and date filters.</span>
                </div>
            @endforelse
        </div>
    </div>

    <div class="people-pagination">
        <span>Showing {{ $calculationTraces->firstItem() ?? 0 }} to {{ $calculationTraces->lastItem() ?? 0 }} of {{ $calculationTraces->total() }}</span>
        {{ $calculationTraces->withQueryString()->links() }}
    </div>
</section>
