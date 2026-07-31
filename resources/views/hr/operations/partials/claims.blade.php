<section class="people-ops-grid is-wide-left" aria-label="Expense claim controls">
    <article class="people-ops-panel" id="claim-form">
        <header class="people-ops-panel-head"><div><h2>Submit expense claim</h2><p>Record an employee reimbursement request for governed review.</p></div></header>
        <div class="people-ops-panel-body">
            @if ($abilities['canCreateClaim'])
                <form method="POST" action="{{ route('hr.expense-claims.store') }}" class="people-form-grid" x-data="serverFormState" x-on:submit="beginSubmit" x-bind:aria-busy="busyAria" data-idle-label="Submit expense claim" data-busy-label="Submitting…">
                    @csrf
                    <label class="people-field"><span>Employee</span><select class="people-control" name="employee_id" required><option value="">Select employee</option>@foreach($employees as $employee)<option value="{{ $employee->id }}" @selected((string) old('employee_id') === (string) $employee->id)>{{ $employee->employee_code }} - {{ $employee->name }}{{ $employee->department ? ' / '.$employee->department : '' }}</option>@endforeach</select>@error('employee_id')<small class="people-field-error">{{ $message }}</small>@enderror</label>
                    <label class="people-field"><span>Claim type</span><select class="people-control" name="claim_type" required><option value="">Select type</option>@foreach($claimTypes as $type)<option value="{{ $type['value'] }}" @selected(old('claim_type') === $type['value'])>{{ $type['label'] }}</option>@endforeach</select>@error('claim_type')<small class="people-field-error">{{ $message }}</small>@enderror</label>
                    <label class="people-field"><span>Claim date</span><input class="people-control" type="date" name="claim_date" value="{{ old('claim_date', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required>@error('claim_date')<small class="people-field-error">{{ $message }}</small>@enderror</label>
                    <label class="people-field"><span>Amount (INR)</span><input class="people-control" type="number" name="amount" value="{{ old('amount') }}" min="1" step="0.01" required>@error('amount')<small class="people-field-error">{{ $message }}</small>@enderror</label>
                    <input type="hidden" name="currency" value="INR">
                    <label class="people-field is-wide"><span>Description</span><textarea class="people-control" name="description" rows="3" minlength="10" maxlength="255" required placeholder="Business purpose and expense details">{{ old('description') }}</textarea>@error('description')<small class="people-field-error">{{ $message }}</small>@enderror</label>
                    <div class="people-modal-actions is-wide"><button type="submit" class="people-button is-primary" x-bind:disabled="busy"><span x-text="submitLabel">Submit expense claim</span></button></div>
                </form>
            @else
                <div class="people-ops-empty"><i class="fa-solid fa-lock" aria-hidden="true"></i><strong>Claim submission unavailable</strong><span>Your role can view claims but cannot submit a reimbursement request.</span></div>
            @endif
        </div>
    </article>

    <article class="people-ops-panel">
        <header class="people-ops-panel-head"><div><h2>Claim filters</h2><p>Filter without changing the authorized company scope.</p></div></header>
        <div class="people-ops-panel-body">
            <form method="GET" action="{{ route('hr.expense-claims.index') }}" class="people-form-grid">
                <label class="people-field"><span>Employee</span><select class="people-control" name="employee_id"><option value="">All employees</option>@foreach($employees as $employee)<option value="{{ $employee->id }}" @selected((string) request('employee_id') === (string) $employee->id)>{{ $employee->employee_code }} - {{ $employee->name }}</option>@endforeach</select></label>
                <label class="people-field"><span>Status</span><select class="people-control" name="status"><option value="">All statuses</option>@foreach($claimStatuses as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></label>
                <label class="people-field"><span>Claim type</span><select class="people-control" name="claim_type"><option value="">All types</option>@foreach($claimTypes as $type)<option value="{{ $type['value'] }}" @selected(request('claim_type') === $type['value'])>{{ $type['label'] }}</option>@endforeach</select></label>
                <label class="people-field"><span>From</span><input class="people-control" type="date" name="date_from" value="{{ request('date_from') }}"></label>
                <label class="people-field"><span>To</span><input class="people-control" type="date" name="date_to" value="{{ request('date_to') }}"></label>
                <div class="people-modal-actions is-wide"><button class="people-button" type="submit">Apply filters</button><a class="people-button" href="{{ route('hr.expense-claims.index') }}">Clear</a></div>
            </form>
        </div>
    </article>
</section>

<section class="people-ops-panel has-mobile-cards" aria-labelledby="expense-claims-title">
    <header class="people-ops-panel-head"><div><h2 id="expense-claims-title">Expense claims</h2><p>{{ $claims->total() }} claim{{ $claims->total() === 1 ? '' : 's' }} in this authorized register. Claimed {{ $claimSummary->claimedAmount }}; approved {{ $claimSummary->approvedAmount }}.</p></div></header>
    <div class="people-ops-table-wrap">
        <table class="people-ops-table">
            <caption>Expense claim register</caption>
            <thead><tr><th scope="col">Claim</th><th scope="col">Employee</th><th scope="col">Type / date</th><th scope="col" class="is-number">Amounts</th><th scope="col">Status</th><th scope="col">Evidence / history</th><th scope="col" class="is-actions">Action</th></tr></thead>
            <tbody>
                @forelse ($claims as $claim)
                    <tr>
                        <td><strong>{{ $claim->claimNumber }}</strong><small>{{ $claim->description }}</small></td>
                        <td><div class="people-ops-identity"><span class="people-avatar">{{ $claim->employeeInitial }}</span><div><strong>{{ $claim->employeeName }}</strong><small>{{ $claim->employeeCode }} / {{ $claim->employeeContext }}</small></div></div></td>
                        <td>{{ $claim->claimTypeLabel }}<small>{{ $claim->claimDate }}</small></td>
                        <td class="is-number">{{ $claim->claimedAmount }}<small>Approved: {{ $claim->approvedAmount }}</small></td>
                        <td><span class="people-status is-{{ $claim->statusTone }}">{{ $claim->statusLabel }}</span></td>
                        <td>
                            @if ($claim->attachmentCount)<strong>{{ $claim->attachmentCount }} attachment{{ $claim->attachmentCount === 1 ? '' : 's' }} recorded</strong><small>{{ implode(', ', $claim->attachmentNames) }}</small>@else<span>No attachments recorded</span>@endif
                            <small>{{ $claim->workflowNote }} / {{ $claim->workflowActor }} / {{ $claim->workflowAt }}</small>
                        </td>
                        <td class="is-actions">@include('hr.operations.partials.claim-actions', ['claim' => $claim, 'actionContext' => 'desktop'])</td>
                    </tr>
                @empty
                    <tr><td colspan="7"><div class="people-ops-empty"><i class="fa-solid fa-receipt" aria-hidden="true"></i><strong>No expense claims found</strong><span>Clear the filters or submit a new expense claim.</span></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="people-ops-mobile-list">
        @foreach ($claims as $claim)
            <article class="people-ops-mobile-card"><div class="people-ops-mobile-card-head"><strong>{{ $claim->claimNumber }} / {{ $claim->employeeName }}</strong><span class="people-status is-{{ $claim->statusTone }}">{{ $claim->statusLabel }}</span></div><dl class="people-ops-mobile-facts"><div><dt>Type / date</dt><dd>{{ $claim->claimTypeLabel }} / {{ $claim->claimDate }}</dd></div><div><dt>Claimed</dt><dd>{{ $claim->claimedAmount }}</dd></div><div><dt>Approved</dt><dd>{{ $claim->approvedAmount }}</dd></div><div><dt>Evidence</dt><dd>{{ $claim->attachmentCount ? $claim->attachmentCount.' recorded' : 'Unavailable' }}</dd></div></dl><p>{{ $claim->description }}</p><div class="people-ops-mobile-actions">@include('hr.operations.partials.claim-actions', ['claim' => $claim, 'actionContext' => 'mobile'])</div></article>
        @endforeach
    </div>
    <div class="people-pagination">{{ $claims->withQueryString()->links() }}</div>
</section>
