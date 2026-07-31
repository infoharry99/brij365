<section class="people-ops-grid is-wide-left" aria-label="Payroll run controls">
    <article class="people-ops-panel" id="generate-payroll-run">
        <header class="people-ops-panel-head">
            <div><h2>Generate payroll run</h2><p>Create one controlled payroll run for a company period.</p></div>
        </header>
        <div class="people-ops-panel-body">
            @if ($abilities['canGenerateRun'])
                <form method="POST" action="{{ route('payroll.runs.generate') }}" class="people-form-grid">
                    @csrf
                    <label class="people-field">
                        <span>Period year</span>
                        <input class="people-control" type="number" name="period_year" min="2000" max="2100" value="{{ old('period_year', now()->addMonthNoOverflow()->year) }}" required aria-invalid="{{ $errors->has('period_year') ? 'true' : 'false' }}" @error('period_year') aria-describedby="payroll-period-year-error" @enderror>
                        @error('period_year') <small class="people-field-error" id="payroll-period-year-error">{{ $message }}</small> @enderror
                    </label>
                    <label class="people-field">
                        <span>Period month</span>
                        <input class="people-control" type="number" name="period_month" min="1" max="12" value="{{ old('period_month', now()->addMonthNoOverflow()->month) }}" required aria-invalid="{{ $errors->has('period_month') ? 'true' : 'false' }}" @error('period_month') aria-describedby="payroll-period-month-error" @enderror>
                        @error('period_month') <small class="people-field-error" id="payroll-period-month-error">{{ $message }}</small> @enderror
                    </label>
                    <label class="people-field">
                        <span>Working days</span>
                        <input class="people-control" type="number" name="working_days" min="1" max="31" value="{{ old('working_days', 26) }}" required aria-invalid="{{ $errors->has('working_days') ? 'true' : 'false' }}" @error('working_days') aria-describedby="payroll-working-days-error" @enderror>
                        @error('working_days') <small class="people-field-error" id="payroll-working-days-error">{{ $message }}</small> @enderror
                    </label>
                    <div class="people-modal-actions"><button type="submit" class="people-button is-primary"><i class="fa-solid fa-play" aria-hidden="true"></i>Generate run</button></div>
                </form>
            @else
                <div class="people-ops-empty"><i class="fa-solid fa-lock" aria-hidden="true"></i><strong>Generation unavailable</strong><span>Your role can review payroll runs but cannot generate one.</span></div>
            @endif
        </div>
    </article>

    <article class="people-ops-panel">
        <header class="people-ops-panel-head"><div><h2>Run filters</h2><p>Filter the authorized run register by supported lifecycle state.</p></div></header>
        <div class="people-ops-panel-body">
            <form method="GET" action="{{ route('payroll.runs.index') }}" class="people-form-grid">
                <label class="people-field"><span>Year</span><input class="people-control" type="number" name="period_year" min="2000" max="2100" value="{{ request('period_year') }}"></label>
                <label class="people-field"><span>Month</span><input class="people-control" type="number" name="period_month" min="1" max="12" value="{{ request('period_month') }}"></label>
                <label class="people-field is-wide"><span>Status</span><select class="people-control" name="status"><option value="">All supported statuses</option>@foreach($runStatuses as $status)<option value="{{ $status['value'] }}" @selected(request('status') === $status['value'])>{{ $status['label'] }}</option>@endforeach</select></label>
                <div class="people-modal-actions is-wide"><button class="people-button" type="submit">Apply filters</button><a class="people-button" href="{{ route('payroll.runs.index') }}">Clear</a></div>
            </form>
        </div>
    </article>
</section>

<section class="people-ops-panel has-mobile-cards" aria-labelledby="payroll-runs-title">
    <header class="people-ops-panel-head"><div><h2 id="payroll-runs-title">Payroll runs</h2><p>{{ $runs->total() }} run{{ $runs->total() === 1 ? '' : 's' }} in this authorized register.</p></div></header>
    <div class="people-ops-table-wrap">
        <table class="people-ops-table">
            <caption>Payroll run register</caption>
            <thead><tr><th scope="col">Run</th><th scope="col">Period</th><th scope="col">Status</th><th scope="col" class="is-number">Employees</th><th scope="col" class="is-number">Gross</th><th scope="col" class="is-number">Deductions</th><th scope="col" class="is-number">Net payable</th><th scope="col">Control</th><th scope="col" class="is-actions">Action</th></tr></thead>
            <tbody>
                @forelse($runs as $run)
                    <tr>
                        <td><strong>{{ $run->runNumber }}</strong>@unless($run->canViewCompensation)<small>Employee trace restricted</small>@endunless</td>
                        <td>{{ $run->period }}<small>{{ $run->dateRange }}</small></td>
                        <td><span class="people-status is-{{ $run->status === 'approved' ? 'success' : 'warning' }}">{{ $run->statusLabel }}</span></td>
                        <td class="is-number">{{ number_format($run->employeeCount) }}</td>
                        <td class="is-number">{{ $run->grossEarnings }}</td>
                        <td class="is-number">{{ $run->deductions }}</td>
                        <td class="is-number"><strong>{{ $run->netPayable }}</strong></td>
                        <td>{{ $run->generatedBy }}@if($run->approvedBy)<small>Approved by {{ $run->approvedBy }}</small>@endif</td>
                        <td class="is-actions">
                            @if($run->canApprove)
                                <form method="POST" action="{{ route('payroll.runs.approve', $run->id) }}">@csrf @method('PATCH')<label class="people-field"><span class="sr-only">Approval note for {{ $run->runNumber }}</span><input class="people-control" name="note" maxlength="1000" placeholder="Approval note"></label><button type="submit" class="people-ops-action-link">Approve run</button></form>
                            @endif
                            @if($run->canPrepareBatch)
                                <details class="people-edit-details" id="payroll-run-{{ $run->id }}-bank-batch">
                                    <summary>Prepare bank batch</summary>
                                    <form method="POST" action="{{ route('payroll.runs.bank-transfer-batches.store', $run->id) }}" class="people-form-grid people-edit-form">
                                        @csrf
                                        <label class="people-field"><span>Bank name</span><input class="people-control" name="bank_name" maxlength="120" required></label>
                                        <label class="people-field"><span>Payment date</span><input class="people-control" type="date" name="payment_date" min="{{ now()->toDateString() }}" value="{{ now()->addDay()->toDateString() }}" required></label>
                                        <label class="people-field"><span>Debit account number</span><input class="people-control" name="debit_account_number" inputmode="numeric" minlength="6" maxlength="32" pattern="[0-9]+" required></label>
                                        <label class="people-field"><span>Narration</span><input class="people-control" name="narration" maxlength="160"></label>
                                        <button type="submit" class="people-button is-primary is-wide">Prepare batch</button>
                                    </form>
                                </details>
                            @endif
                            @if(! $run->canApprove && ! $run->canPrepareBatch)<span class="people-subtext">No permitted action</span>@endif
                        </td>
                    </tr>
                    @if($run->canViewCompensation)
                        <tr class="people-payroll-trace-row">
                            <td colspan="9">
                                <details class="people-payroll-trace">
                                    <summary>
                                        <span><i class="fa-solid fa-users-rectangle" aria-hidden="true"></i>Employee line trace</span>
                                        <span>{{ count($run->items) }} persisted line{{ count($run->items) === 1 ? '' : 's' }}</span>
                                    </summary>
                                    @if($run->items === [])
                                        <div class="people-payroll-trace-empty">No persisted employee lines are available for this run.</div>
                                    @else
                                        <div class="people-payroll-trace-scroll" tabindex="0" aria-label="Employee payroll lines for {{ $run->runNumber }}">
                                            <table class="people-payroll-trace-table">
                                                <caption>Persisted employee payroll lines for {{ $run->runNumber }}</caption>
                                                <thead><tr><th scope="col">Employee</th><th scope="col">Department</th><th scope="col" class="is-number">Payable days</th><th scope="col" class="is-number">Gross</th><th scope="col" class="is-number">Deductions</th><th scope="col" class="is-number">Net payable</th><th scope="col">Line status</th></tr></thead>
                                                <tbody>
                                                    @foreach($run->items as $item)
                                                        <tr>
                                                            <td><strong>{{ $item->employeeName }}</strong><small>{{ $item->employeeCode }} / {{ $item->designation }}</small></td>
                                                            <td>{{ $item->department }}</td>
                                                            <td class="is-number">{{ number_format($item->payableDays) }}</td>
                                                            <td class="is-number">{{ $item->grossEarnings }}</td>
                                                            <td class="is-number">{{ $item->deductions }}</td>
                                                            <td class="is-number"><strong>{{ $item->netPayable }}</strong></td>
                                                            <td><span class="people-status">{{ $item->statusLabel }}</span></td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @endif
                                </details>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="9"><div class="people-ops-empty"><i class="fa-solid fa-file-circle-xmark" aria-hidden="true"></i><strong>No payroll runs found</strong><span>Clear filters or generate the first authorized payroll run.</span></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="people-ops-mobile-list">
        @forelse($runs as $run)
            <article class="people-ops-mobile-card">
                <header class="people-ops-mobile-card-head"><div><strong>{{ $run->runNumber }}</strong><small class="people-subtext">{{ $run->period }} / {{ $run->dateRange }}</small></div><span class="people-status is-{{ $run->status === 'approved' ? 'success' : 'warning' }}">{{ $run->statusLabel }}</span></header>
                <dl class="people-ops-mobile-facts"><div><dt>Employees</dt><dd>{{ $run->employeeCount }}</dd></div><div><dt>Net payable</dt><dd>{{ $run->netPayable }}</dd></div><div><dt>Generated by</dt><dd>{{ $run->generatedBy }}</dd></div><div><dt>Approved by</dt><dd>{{ $run->approvedBy ?? 'Not approved' }}</dd></div></dl>
                @if($run->canViewCompensation)
                    <details class="people-payroll-trace is-mobile">
                        <summary><span><i class="fa-solid fa-users-rectangle" aria-hidden="true"></i>Employee line trace</span><span>{{ count($run->items) }}</span></summary>
                        @forelse($run->items as $item)
                            <article class="people-payroll-trace-card">
                                <header><div><strong>{{ $item->employeeName }}</strong><small>{{ $item->employeeCode }} / {{ $item->designation }}</small></div><span class="people-status">{{ $item->statusLabel }}</span></header>
                                <dl>
                                    <div><dt>Department</dt><dd>{{ $item->department }}</dd></div>
                                    <div><dt>Payable days</dt><dd>{{ $item->payableDays }}</dd></div>
                                    <div><dt>Gross</dt><dd>{{ $item->grossEarnings }}</dd></div>
                                    <div><dt>Deductions</dt><dd>{{ $item->deductions }}</dd></div>
                                    <div><dt>Net payable</dt><dd><strong>{{ $item->netPayable }}</strong></dd></div>
                                </dl>
                            </article>
                        @empty
                            <div class="people-payroll-trace-empty">No persisted employee lines are available for this run.</div>
                        @endforelse
                    </details>
                @else
                    <p class="people-subtext"><i class="fa-solid fa-lock" aria-hidden="true"></i> Employee trace is restricted for your role.</p>
                @endif
                <div class="people-ops-mobile-actions">
                    @if($run->canApprove)<form method="POST" action="{{ route('payroll.runs.approve', $run->id) }}">@csrf @method('PATCH')<button class="people-button is-primary" type="submit">Approve</button></form>@endif
                    @if($run->canPrepareBatch)
                        <details class="people-edit-details">
                            <summary>Prepare bank batch</summary>
                            <form method="POST" action="{{ route('payroll.runs.bank-transfer-batches.store', $run->id) }}" class="people-form-grid people-edit-form">
                                @csrf
                                <label class="people-field"><span>Bank name</span><input class="people-control" name="bank_name" maxlength="120" required></label>
                                <label class="people-field"><span>Payment date</span><input class="people-control" type="date" name="payment_date" min="{{ now()->toDateString() }}" value="{{ now()->addDay()->toDateString() }}" required></label>
                                <label class="people-field"><span>Debit account number</span><input class="people-control" name="debit_account_number" inputmode="numeric" minlength="6" maxlength="32" pattern="[0-9]+" required></label>
                                <label class="people-field"><span>Narration</span><input class="people-control" name="narration" maxlength="160"></label>
                                <button type="submit" class="people-button is-primary is-wide">Prepare batch</button>
                            </form>
                        </details>
                    @endif
                    @if(! $run->canApprove && ! $run->canPrepareBatch)<span class="people-subtext">No permitted action</span>@endif
                </div>
            </article>
        @empty
            <div class="people-ops-empty"><strong>No payroll runs found</strong><span>Clear filters or generate the first authorized payroll run.</span></div>
        @endforelse
    </div>
    <div class="people-pagination">{{ $runs->withQueryString()->links() }}</div>
</section>
