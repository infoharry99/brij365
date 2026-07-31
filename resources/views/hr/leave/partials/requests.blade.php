<section class="people-ops-grid is-wide-left" aria-label="Leave requests">
    <article class="people-ops-panel" id="leave-request-form">
        <header class="people-ops-panel-head">
            <div><h2>Submit leave request</h2><p>Request time away under an active company leave policy.</p></div>
        </header>
        <div class="people-ops-panel-body">
            @if ($abilities['canCreateLeaveRequest'])
                <form method="POST" action="{{ route('hr.leave-requests.store') }}" class="people-form-grid">
                    @csrf
                    <label class="people-field">
                        <span>Employee</span>
                        <select class="people-control" name="employee_id" required aria-invalid="{{ $errors->has('employee_id') ? 'true' : 'false' }}" @error('employee_id') aria-describedby="leave-employee-error" @enderror>
                            <option value="">Select employee</option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}" @selected((string) old('employee_id') === (string) $employee->id)>{{ $employee->employee_code }} - {{ $employee->name }}{{ $employee->department ? ' / '.$employee->department : '' }}</option>
                            @endforeach
                        </select>
                        @error('employee_id') <small class="people-field-error" id="leave-employee-error">{{ $message }}</small> @enderror
                    </label>
                    <label class="people-field">
                        <span>Leave type</span>
                        <select class="people-control" name="leave_type_id" required aria-invalid="{{ $errors->has('leave_type_id') ? 'true' : 'false' }}" @error('leave_type_id') aria-describedby="leave-type-error" @enderror>
                            <option value="">Select leave type</option>
                            @foreach ($leaveTypeOptions as $leaveType)
                                <option value="{{ $leaveType->id }}" @selected((string) old('leave_type_id') === (string) $leaveType->id)>{{ $leaveType->code }} - {{ $leaveType->name }}</option>
                            @endforeach
                        </select>
                        @error('leave_type_id') <small class="people-field-error" id="leave-type-error">{{ $message }}</small> @enderror
                    </label>
                    <label class="people-field">
                        <span>Starts on</span>
                        <input class="people-control" type="date" name="starts_on" value="{{ old('starts_on', now()->addDays(7)->toDateString()) }}" min="{{ now()->toDateString() }}" required>
                        @error('starts_on') <small class="people-field-error">{{ $message }}</small> @enderror
                    </label>
                    <label class="people-field">
                        <span>Ends on</span>
                        <input class="people-control" type="date" name="ends_on" value="{{ old('ends_on', now()->addDays(7)->toDateString()) }}" min="{{ now()->toDateString() }}" required>
                        @error('ends_on') <small class="people-field-error">{{ $message }}</small> @enderror
                    </label>
                    <label class="people-field">
                        <span>Duration</span>
                        <select class="people-control" name="duration_unit" required>
                            <option value="full_day" @selected(old('duration_unit', 'full_day') === 'full_day')>Full day</option>
                            <option value="half_day" @selected(old('duration_unit') === 'half_day')>Half day</option>
                        </select>
                        @error('duration_unit') <small class="people-field-error">{{ $message }}</small> @enderror
                    </label>
                    <label class="people-field is-wide">
                        <span>Reason</span>
                        <textarea class="people-control" name="reason" rows="3" maxlength="2000" placeholder="Reason for leave request">{{ old('reason') }}</textarea>
                        @error('reason') <small class="people-field-error">{{ $message }}</small> @enderror
                    </label>
                    <div class="people-modal-actions is-wide"><button type="submit" class="people-button is-primary">Submit leave request</button></div>
                </form>
            @else
                <div class="people-ops-empty"><i class="fa-solid fa-lock" aria-hidden="true"></i><strong>Request creation unavailable</strong><span>Your role can view leave data but cannot submit requests.</span></div>
            @endif
        </div>
    </article>

    <article class="people-ops-panel">
        <header class="people-ops-panel-head"><div><h2>Request filters</h2><p>Filter the authorized request register.</p></div></header>
        <div class="people-ops-panel-body">
            <form method="GET" action="{{ route('hr.leave-requests.index') }}" class="people-form-grid">
                <label class="people-field"><span>Employee</span><select class="people-control" name="employee_id"><option value="">All employees</option>@foreach($employees as $employee)<option value="{{ $employee->id }}" @selected((string) request('employee_id') === (string) $employee->id)>{{ $employee->employee_code }} - {{ $employee->name }}</option>@endforeach</select></label>
                <label class="people-field"><span>Status</span><select class="people-control" name="status"><option value="">All statuses</option>@foreach($requestStatuses as $status)<option value="{{ $status['value'] }}" @selected(request('status') === $status['value'])>{{ $status['label'] }}</option>@endforeach</select></label>
                <div class="people-modal-actions is-wide"><button class="people-button" type="submit">Apply filters</button><a class="people-button" href="{{ route('hr.leave-requests.index') }}">Clear</a></div>
            </form>
        </div>
    </article>
</section>

<section class="people-ops-panel" aria-labelledby="leave-requests-title">
    <header class="people-ops-panel-head"><div><h2 id="leave-requests-title">Leave requests</h2><p>{{ $leaveRequests->total() }} request{{ $leaveRequests->total() === 1 ? '' : 's' }} in this authorized register.</p></div></header>
    <div class="people-ops-table-wrap">
        <table class="people-ops-table">
            <caption>Leave request register</caption>
            <thead><tr><th scope="col">Request</th><th scope="col">Employee</th><th scope="col">Leave type</th><th scope="col">Dates</th><th scope="col">Days</th><th scope="col">Status</th><th scope="col">Reason / decision</th><th scope="col" class="is-actions">Action</th></tr></thead>
            <tbody>
                @forelse ($leaveRequests as $leaveRequest)
                    <tr>
                        <td><strong>{{ $leaveRequest->requestNumber }}</strong></td>
                        <td><div class="people-ops-identity"><div><strong>{{ $leaveRequest->employeeName }}</strong><small>{{ $leaveRequest->employeeCode }}</small></div></div></td>
                        <td>{{ $leaveRequest->leaveTypeName }}<small>{{ $leaveRequest->leaveTypeCode }}</small></td>
                        <td>{{ $leaveRequest->dateRange }}</td>
                        <td>{{ $leaveRequest->requestedDays }}<small>{{ $leaveRequest->duration }}</small></td>
                        <td><span class="people-status is-{{ $leaveRequest->status }}">{{ $leaveRequest->statusLabel }}</span></td>
                        <td>{{ $leaveRequest->reason }}@if($leaveRequest->decisionNote)<small>Decision: {{ $leaveRequest->decisionNote }}</small>@endif</td>
                        <td class="is-actions">
                            @if ($leaveRequest->canApprove)
                                <form method="POST" action="{{ route('hr.leave-requests.approve', $leaveRequest->id) }}">@csrf @method('PATCH')<input class="people-control" name="decision_note" maxlength="2000" placeholder="Approval note"><button class="people-ops-action-link" type="submit">Approve</button></form>
                            @endif
                            @if ($leaveRequest->canReject)
                                <form method="POST" action="{{ route('hr.leave-requests.reject', $leaveRequest->id) }}">@csrf @method('PATCH')<input class="people-control" name="decision_note" maxlength="2000" placeholder="Rejection reason" required><button class="people-ops-action-link is-danger" type="submit">Reject</button></form>
                            @endif
                            @if (! $leaveRequest->canApprove && ! $leaveRequest->canReject)
                                <span class="people-subtext">No action</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8"><div class="people-ops-empty"><strong>No leave requests found</strong><span>Try clearing the filters or submit a new request.</span></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="people-pagination">{{ $leaveRequests->withQueryString()->links() }}</div>
</section>
