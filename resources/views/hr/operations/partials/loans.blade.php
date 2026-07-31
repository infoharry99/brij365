<section class="people-ops-grid is-wide-left" aria-label="Employee loan controls">
    <article class="people-ops-panel" id="loan-form">
        <header class="people-ops-panel-head"><div><h2>Request employee loan</h2><p>Submit a governed advance or welfare loan request.</p></div></header>
        <div class="people-ops-panel-body">
            @if ($abilities['canCreateLoan'])
                <form method="POST" action="{{ route('hr.loans.store') }}" class="people-form-grid" x-data="serverFormState" x-on:submit="beginSubmit" x-bind:aria-busy="busyAria" data-idle-label="Request employee loan" data-busy-label="Submitting…">
                    @csrf
                    <label class="people-field"><span>Employee</span><select class="people-control" name="employee_id" required><option value="">Select employee</option>@foreach($employees as $employee)<option value="{{ $employee->id }}" @selected((string) old('employee_id') === (string) $employee->id)>{{ $employee->employee_code }} - {{ $employee->name }}{{ $employee->department ? ' / '.$employee->department : '' }}</option>@endforeach</select>@error('employee_id')<small class="people-field-error">{{ $message }}</small>@enderror</label>
                    <label class="people-field"><span>Loan type</span><select class="people-control" name="loan_type" required><option value="">Select type</option>@foreach($loanTypes as $type)<option value="{{ $type['value'] }}" @selected(old('loan_type') === $type['value'])>{{ $type['label'] }}</option>@endforeach</select>@error('loan_type')<small class="people-field-error">{{ $message }}</small>@enderror</label>
                    <label class="people-field"><span>Principal amount (INR)</span><input class="people-control" type="number" name="principal_amount" value="{{ old('principal_amount') }}" min="1000" step="0.01" required>@error('principal_amount')<small class="people-field-error">{{ $message }}</small>@enderror</label>
                    <label class="people-field"><span>Installments</span><input class="people-control" type="number" name="installment_months" value="{{ old('installment_months', 6) }}" min="1" max="60" required>@error('installment_months')<small class="people-field-error">{{ $message }}</small>@enderror</label>
                    <label class="people-field"><span>Requested on</span><input class="people-control" type="date" name="requested_on" value="{{ old('requested_on', now()->toDateString()) }}" max="{{ now()->toDateString() }}">@error('requested_on')<small class="people-field-error">{{ $message }}</small>@enderror</label>
                    <label class="people-field is-wide"><span>Purpose</span><textarea class="people-control" name="purpose" rows="3" minlength="10" maxlength="255" required placeholder="Reason and intended use">{{ old('purpose') }}</textarea>@error('purpose')<small class="people-field-error">{{ $message }}</small>@enderror</label>
                    <div class="people-modal-actions is-wide"><button type="submit" class="people-button is-primary" x-bind:disabled="busy"><span x-text="submitLabel">Request employee loan</span></button></div>
                </form>
            @else
                <div class="people-ops-empty"><i class="fa-solid fa-lock" aria-hidden="true"></i><strong>Loan requests unavailable</strong><span>Your role can view loans but cannot submit a request.</span></div>
            @endif
        </div>
    </article>

    <article class="people-ops-panel">
        <header class="people-ops-panel-head"><div><h2>Loan filters</h2><p>Filter recorded loan terms without changing scope.</p></div></header>
        <div class="people-ops-panel-body">
            <form method="GET" action="{{ route('hr.loans.index') }}" class="people-form-grid">
                <label class="people-field"><span>Employee</span><select class="people-control" name="employee_id"><option value="">All employees</option>@foreach($employees as $employee)<option value="{{ $employee->id }}" @selected((string) request('employee_id') === (string) $employee->id)>{{ $employee->employee_code }} - {{ $employee->name }}</option>@endforeach</select></label>
                <label class="people-field"><span>Status</span><select class="people-control" name="status"><option value="">All statuses</option>@foreach($loanStatuses as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></label>
                <label class="people-field"><span>Loan type</span><select class="people-control" name="loan_type"><option value="">All types</option>@foreach($loanTypes as $type)<option value="{{ $type['value'] }}" @selected(request('loan_type') === $type['value'])>{{ $type['label'] }}</option>@endforeach</select></label>
                <div class="people-modal-actions is-wide"><button class="people-button" type="submit">Apply filters</button><a class="people-button" href="{{ route('hr.loans.index') }}">Clear</a></div>
            </form>
        </div>
    </article>
</section>

<section class="people-ops-panel has-mobile-cards" aria-labelledby="employee-loans-title">
    <header class="people-ops-panel-head"><div><h2 id="employee-loans-title">Employee loans</h2><p>{{ $loans->total() }} loan{{ $loans->total() === 1 ? '' : 's' }} in this authorized register. Requested {{ $loanSummary->requestedAmount }}; approved {{ $loanSummary->approvedAmount }}.</p></div></header>
    <div class="people-ops-table-wrap">
        <table class="people-ops-table">
            <caption>Employee loan register</caption>
            <thead><tr><th scope="col">Loan</th><th scope="col">Employee</th><th scope="col">Type / requested</th><th scope="col" class="is-number">Recorded terms</th><th scope="col">Status</th><th scope="col">Workflow history</th><th scope="col" class="is-actions">Action</th></tr></thead>
            <tbody>
                @forelse ($loans as $loan)
                    <tr>
                        <td><strong>{{ $loan->loanNumber }}</strong><small>{{ $loan->purpose }}</small></td>
                        <td><div class="people-ops-identity"><span class="people-avatar">{{ $loan->employeeInitial }}</span><div><strong>{{ $loan->employeeName }}</strong><small>{{ $loan->employeeCode }} / {{ $loan->employeeContext }}</small></div></div></td>
                        <td>{{ $loan->loanTypeLabel }}<small>{{ $loan->requestedOn }}</small></td>
                        <td class="is-number">{{ $loan->principalAmount }}<small>{{ $loan->installmentMonths }} months / {{ $loan->monthlyInstallment }} recorded monthly installment</small><small>Repayment starts: {{ $loan->repaymentStartsOn }}</small></td>
                        <td><span class="people-status is-{{ $loan->statusTone }}">{{ $loan->statusLabel }}</span></td>
                        <td>{{ $loan->workflowNote }}<small>{{ $loan->workflowActor }} / {{ $loan->workflowAt }}</small></td>
                        <td class="is-actions">@include('hr.operations.partials.loan-actions', ['loan' => $loan, 'actionContext' => 'desktop'])</td>
                    </tr>
                @empty
                    <tr><td colspan="7"><div class="people-ops-empty"><i class="fa-solid fa-hand-holding-dollar" aria-hidden="true"></i><strong>No employee loans found</strong><span>Clear the filters or submit a new loan request.</span></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="people-ops-mobile-list">
        @foreach ($loans as $loan)
            <article class="people-ops-mobile-card"><div class="people-ops-mobile-card-head"><strong>{{ $loan->loanNumber }} / {{ $loan->employeeName }}</strong><span class="people-status is-{{ $loan->statusTone }}">{{ $loan->statusLabel }}</span></div><dl class="people-ops-mobile-facts"><div><dt>Type</dt><dd>{{ $loan->loanTypeLabel }}</dd></div><div><dt>Requested</dt><dd>{{ $loan->principalAmount }}</dd></div><div><dt>Approved</dt><dd>{{ $loan->approvedAmount }}</dd></div><div><dt>Recorded term</dt><dd>{{ $loan->installmentMonths }} months</dd></div></dl><p>{{ $loan->purpose }}</p><div class="people-ops-mobile-actions">@include('hr.operations.partials.loan-actions', ['loan' => $loan, 'actionContext' => 'mobile'])</div></article>
        @endforeach
    </div>
    <div class="people-pagination">{{ $loans->withQueryString()->links() }}</div>
</section>
