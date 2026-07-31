<section class="people-ops-kpis is-four" aria-label="Attendance regularization summary">
    <article class="people-ops-kpi is-warning">
        <span class="people-ops-kpi-icon"><i class="fa-solid fa-hourglass-half" aria-hidden="true"></i></span>
        <span>Pending review</span>
        <strong>{{ $summary->pendingRegularizations }}</strong>
        <small>Submitted requests awaiting an authorized decision</small>
    </article>
    <article class="people-ops-kpi is-purple">
        <span class="people-ops-kpi-icon"><i class="fa-solid fa-list-check" aria-hidden="true"></i></span>
        <span>Visible requests</span>
        <strong>{{ $regularizations->total() }}</strong>
        <small>Requests in the current employee and status filter</small>
    </article>
</section>

<section class="people-ops-grid is-wide-left">
    <article class="people-ops-panel has-mobile-cards">
        <header class="people-ops-panel-head">
            <div><h2>Regularization queue</h2><p>Review requested attendance corrections without changing the original request history.</p></div>
            <span class="people-count">{{ $regularizations->total() }} requests</span>
        </header>

        <form method="GET" action="{{ route('hr.attendance-regularizations.index') }}" class="people-ops-filterbar">
            <label class="people-field">Employee
                <select class="people-control" name="employee_id">
                    <option value="">All visible employees</option>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->id }}" @selected((string) request('employee_id') === (string) $employee->id)>{{ $employee->employee_code }} - {{ $employee->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="people-field">Status
                <select class="people-control" name="status">
                    <option value="">All statuses</option>
                    @foreach ($regularizationStatuses as $status)
                        <option value="{{ $status['value'] }}" @selected(request('status') === $status['value'])>{{ $status['label'] }}</option>
                    @endforeach
                </select>
            </label>
            <button class="people-button" type="submit"><i class="fa-solid fa-filter" aria-hidden="true"></i> Apply</button>
            @if (request()->hasAny(['employee_id', 'status']))
                <a class="people-button" href="{{ route('hr.attendance-regularizations.index') }}">Clear</a>
            @endif
        </form>

        <div class="people-ops-table-wrap">
            <table class="people-ops-table">
                <caption>Attendance regularization requests in the selected filters</caption>
                <thead><tr><th scope="col">Request</th><th scope="col">Employee</th><th scope="col">Work date</th><th scope="col">Requested attendance</th><th scope="col">Reason</th><th scope="col">Status</th><th scope="col" class="is-actions">Decision</th></tr></thead>
                <tbody>
                    @forelse ($regularizations as $item)
                        <tr>
                            <td><strong>{{ $item->requestNumber }}</strong></td>
                            <td><strong>{{ $item->employeeName }}</strong><small>{{ $item->employeeCode }}</small></td>
                            <td>{{ $item->workDate }}</td>
                            <td><strong>{{ $item->requestedCheckIn }}</strong><small>to {{ $item->requestedCheckOut }}</small></td>
                            <td>{{ $item->reason }}</td>
                            <td><span @class(['people-status', 'is-warning' => $item->status === 'submitted', 'is-success' => $item->status === 'approved', 'is-danger' => $item->status === 'rejected'])>{{ $item->statusLabel }}</span>@if($item->decisionNote)<small>{{ $item->decisionNote }}</small>@endif</td>
                            <td class="is-actions">
                                @if ($item->canApprove || $item->canReject)
                                    @if ($item->canApprove)
                                    <form method="POST" action="{{ route('hr.attendance-regularizations.approve', $item->id) }}">
                                        @csrf @method('PATCH')
                                        <input class="people-control" name="decision_note" maxlength="2000" placeholder="Approval note">
                                        <button class="people-ops-action-link" type="submit">Approve</button>
                                    </form>
                                    @endif
                                    @if ($item->canReject)
                                    <form method="POST" action="{{ route('hr.attendance-regularizations.reject', $item->id) }}">
                                        @csrf @method('PATCH')
                                        <input class="people-control" name="decision_note" maxlength="2000" required placeholder="Rejection reason">
                                        <button class="people-ops-action-link is-danger" type="submit">Reject</button>
                                    </form>
                                    @endif
                                @else
                                    <span class="people-status is-muted">No action available</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="people-ops-empty"><i class="fa-solid fa-clipboard-check" aria-hidden="true"></i><strong>No regularization requests</strong><span>No requests match the selected employee and status filters.</span></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="people-ops-mobile-list">
            @foreach ($regularizations as $item)
                <article class="people-ops-mobile-card">
                    <header class="people-ops-mobile-card-head"><div><strong>{{ $item->employeeName }}</strong><small>{{ $item->requestNumber }} / {{ $item->employeeCode }}</small></div><span class="people-status is-muted">{{ $item->statusLabel }}</span></header>
                    <dl class="people-ops-mobile-facts"><div><dt>Work date</dt><dd>{{ $item->workDate }}</dd></div><div><dt>Requested</dt><dd>{{ $item->requestedCheckIn }} to {{ $item->requestedCheckOut }}</dd></div><div><dt>Reason</dt><dd>{{ $item->reason }}</dd></div>@if($item->decisionNote)<div><dt>Decision note</dt><dd>{{ $item->decisionNote }}</dd></div>@endif</dl>
                    @if ($item->canApprove || $item->canReject)
                        <div class="people-ops-list-actions">
                            @if ($item->canApprove)
                                <form method="POST" action="{{ route('hr.attendance-regularizations.approve', $item->id) }}">
                                    @csrf @method('PATCH')
                                    <input class="people-control" name="decision_note" maxlength="2000" placeholder="Approval note">
                                    <button class="people-ops-action-link" type="submit">Approve</button>
                                </form>
                            @endif
                            @if ($item->canReject)
                                <form method="POST" action="{{ route('hr.attendance-regularizations.reject', $item->id) }}">
                                    @csrf @method('PATCH')
                                    <input class="people-control" name="decision_note" maxlength="2000" required placeholder="Rejection reason">
                                    <button class="people-ops-action-link is-danger" type="submit">Reject</button>
                                </form>
                            @endif
                        </div>
                    @endif
                </article>
            @endforeach
        </div>

        <div class="people-pagination"><span>Showing {{ $regularizations->firstItem() ?? 0 }} to {{ $regularizations->lastItem() ?? 0 }} of {{ $regularizations->total() }}</span>{{ $regularizations->withQueryString()->links() }}</div>
    </article>

    <aside class="people-ops-panel" id="regularization-form">
        <header class="people-ops-panel-head"><div><h2>Submit correction request</h2><p>Provide the requested attendance interval and a clear business reason.</p></div></header>
        @if ($abilities['canCreateRegularization'])
            <form method="POST" action="{{ route('hr.attendance-regularizations.store') }}" class="people-ops-panel-body">
                @csrf
                <label class="people-field">Employee
                    <select class="people-control" name="employee_id" required>
                        <option value="">Select employee</option>
                        @foreach ($employees as $employee)
                            <option value="{{ $employee->id }}" @selected((string) old('employee_id') === (string) $employee->id)>{{ $employee->employee_code }} - {{ $employee->name }}</option>
                        @endforeach
                    </select>
                    @error('employee_id')<span class="people-field-error">{{ $message }}</span>@enderror
                </label>
                <label class="people-field">Work date<input class="people-control" type="date" name="work_date" value="{{ old('work_date') }}" required>@error('work_date')<span class="people-field-error">{{ $message }}</span>@enderror</label>
                <label class="people-field">Requested check-in<input class="people-control" type="datetime-local" name="requested_check_in_at" value="{{ old('requested_check_in_at') }}" required>@error('requested_check_in_at')<span class="people-field-error">{{ $message }}</span>@enderror</label>
                <label class="people-field">Requested check-out<input class="people-control" type="datetime-local" name="requested_check_out_at" value="{{ old('requested_check_out_at') }}" required>@error('requested_check_out_at')<span class="people-field-error">{{ $message }}</span>@enderror</label>
                <label class="people-field">Reason<textarea class="people-control" name="reason" rows="4" maxlength="2000" required>{{ old('reason') }}</textarea>@error('reason')<span class="people-field-error">{{ $message }}</span>@enderror</label>
                <button class="people-button is-primary" type="submit"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Submit request</button>
            </form>
        @else
            <div class="people-ops-empty"><i class="fa-solid fa-lock" aria-hidden="true"></i><strong>Submission unavailable</strong><span>Your current role can review the queue but cannot submit a regularization request.</span></div>
        @endif
    </aside>
</section>
