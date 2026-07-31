<section class="people-ops-kpis is-four" aria-label="Commission run summary">
    <article class="people-ops-kpi is-info"><span class="people-ops-kpi-icon"><i class="fa-solid fa-percent" aria-hidden="true"></i></span><span>Active commission rules</span><strong>{{ number_format($summary->activeCommissionRules) }}</strong><small>Available for controlled generation.</small></article>
    <article class="people-ops-kpi is-warning"><span class="people-ops-kpi-icon"><i class="fa-solid fa-hourglass-half" aria-hidden="true"></i></span><span>Awaiting decision</span><strong>{{ number_format($summary->generatedCommissionRuns) }}</strong><small>Segregation of duties applies.</small></article>
    <article class="people-ops-kpi is-success"><span class="people-ops-kpi-icon"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></span><span>Approved runs</span><strong>{{ number_format($summary->approvedCommissionRuns) }}</strong><small>Approved total: {{ $summary->approvedCommissionTotal }}</small></article>
</section>

<section class="people-ops-grid is-wide-left" aria-label="Commission run controls">
    <article class="people-ops-panel" id="generate-commission-run">
        <header class="people-ops-panel-head"><div><h2>Generate commission run</h2><p>Calculate one persisted run from an active rule and accounting period.</p></div></header>
        <div class="people-ops-panel-body">
            @if($abilities['canGenerateCommissionRun'])
                <form method="POST" action="{{ route('payroll.commission-runs.store') }}" class="people-form-grid">
                    @csrf
                    <label class="people-field is-wide"><span>Commission rule</span><select class="people-control" name="commission_rule_id" required><option value="">Select active rule</option>@foreach($commissionRuleOptions as $rule)<option value="{{ $rule['id'] }}" @selected((string)old('commission_rule_id') === (string)$rule['id'])>{{ $rule['label'] }}</option>@endforeach</select>@error('commission_rule_id')<small class="people-field-error">{{ $message }}</small>@enderror</label>
                    <label class="people-field"><span>Period year</span><input class="people-control" type="number" name="period_year" min="2020" max="2100" value="{{ old('period_year', now()->year) }}" required>@error('period_year')<small class="people-field-error">{{ $message }}</small>@enderror</label>
                    <label class="people-field"><span>Period month</span><input class="people-control" type="number" name="period_month" min="1" max="12" value="{{ old('period_month', now()->month) }}" required>@error('period_month')<small class="people-field-error">{{ $message }}</small>@enderror</label>
                    <label class="people-field is-wide"><span>Generation note</span><textarea class="people-control" name="note" maxlength="500" rows="3">{{ old('note') }}</textarea></label>
                    <div class="people-modal-actions is-wide"><button class="people-button is-primary" type="submit"><i class="fa-solid fa-play" aria-hidden="true"></i>Generate commission run</button></div>
                </form>
            @else
                <div class="people-ops-empty"><i class="fa-solid fa-lock" aria-hidden="true"></i><strong>Generation unavailable</strong><span>Your role can review commission runs but cannot generate one.</span></div>
            @endif
        </div>
    </article>
    <article class="people-ops-panel">
        <header class="people-ops-panel-head"><div><h2>Run filters</h2><p>Filter by lifecycle, rule, or accounting period.</p></div></header>
        <div class="people-ops-panel-body"><form method="GET" action="{{ route('payroll.commission-runs.index') }}" class="people-form-grid">
            <label class="people-field"><span>Status</span><select class="people-control" name="status"><option value="">All statuses</option>@foreach($commissionRunStatuses as $status)<option value="{{ $status['value'] }}" @selected(request('status') === $status['value'])>{{ $status['label'] }}</option>@endforeach</select></label>
            <label class="people-field"><span>Rule</span><select class="people-control" name="commission_rule_id"><option value="">All active rules</option>@foreach($commissionRuleOptions as $rule)<option value="{{ $rule['id'] }}" @selected((string)request('commission_rule_id') === (string)$rule['id'])>{{ $rule['label'] }}</option>@endforeach</select></label>
            <label class="people-field"><span>Year</span><input class="people-control" type="number" name="period_year" min="2020" max="2100" value="{{ request('period_year') }}"></label>
            <label class="people-field"><span>Month</span><input class="people-control" type="number" name="period_month" min="1" max="12" value="{{ request('period_month') }}"></label>
            <div class="people-modal-actions is-wide"><button class="people-button" type="submit">Apply filters</button><a class="people-button" href="{{ route('payroll.commission-runs.index') }}">Clear</a></div>
        </form></div>
    </article>
</section>

<section class="people-ops-panel has-mobile-cards" aria-labelledby="commission-runs-title">
    <header class="people-ops-panel-head"><div><h2 id="commission-runs-title">Commission runs</h2><p>{{ $commissionRuns->total() }} run{{ $commissionRuns->total() === 1 ? '' : 's' }} in this authorized register.</p></div></header>
    <div class="people-ops-table-wrap"><table class="people-ops-table"><caption>Commission run register</caption><thead><tr><th scope="col">Run</th><th scope="col">Rule / period</th><th scope="col">Status</th><th scope="col" class="is-number">Items</th><th scope="col" class="is-number">Source</th><th scope="col" class="is-number">Eligible</th><th scope="col" class="is-number">Commission</th><th scope="col">Control</th><th scope="col" class="is-actions">Action</th></tr></thead><tbody>
        @forelse($commissionRuns as $run)
            <tr><td><strong>{{ $run->runNumber }}</strong></td><td>{{ $run->ruleLabel }}<small>{{ $run->period }} / {{ $run->dateRange }}</small></td><td><span class="people-status is-{{ $run->status === 'approved' ? 'success' : ($run->status === 'rejected' ? 'danger' : 'warning') }}">{{ $run->statusLabel }}</span></td><td class="is-number">{{ number_format($run->itemCount) }}</td><td class="is-number">{{ $run->sourceTotal }}</td><td class="is-number">{{ $run->eligibleTotal }}</td><td class="is-number"><strong>{{ $run->commissionTotal }}</strong></td><td>{{ $run->generatedBy }}@if($run->approvedBy)<small>Approved by {{ $run->approvedBy }}</small>@endif</td><td class="is-actions">
                @if($run->canApprove)<form method="POST" action="{{ route('payroll.commission-runs.approve', $run->id) }}">@csrf @method('PATCH')<label class="people-field"><span class="sr-only">Approval note for {{ $run->runNumber }}</span><input class="people-control" name="decision_note" maxlength="500" placeholder="Approval note"></label><button class="people-ops-action-link" type="submit">Approve</button></form>@endif
                @if($run->canReject)<form method="POST" action="{{ route('payroll.commission-runs.reject', $run->id) }}">@csrf @method('PATCH')<label class="people-field"><span class="sr-only">Rejection reason for {{ $run->runNumber }}</span><input class="people-control" name="decision_note" maxlength="500" placeholder="Required rejection reason" required></label><button class="people-ops-action-link is-danger" type="submit">Reject</button></form>@endif
                @if(! $run->canApprove && ! $run->canReject)<span class="people-subtext">No permitted action</span>@endif
            </td></tr>
        @empty
            <tr><td colspan="9"><div class="people-ops-empty"><i class="fa-solid fa-coins" aria-hidden="true"></i><strong>No commission runs found</strong><span>Clear filters or generate the first permitted commission run.</span></div></td></tr>
        @endforelse
    </tbody></table></div>
    <div class="people-ops-mobile-list">
        @forelse($commissionRuns as $run)
            <article class="people-ops-mobile-card"><header class="people-ops-mobile-card-head"><div><strong>{{ $run->runNumber }}</strong><small class="people-subtext">{{ $run->ruleLabel }}</small></div><span class="people-status is-{{ $run->status === 'approved' ? 'success' : ($run->status === 'rejected' ? 'danger' : 'warning') }}">{{ $run->statusLabel }}</span></header><dl class="people-ops-mobile-facts"><div><dt>Period</dt><dd>{{ $run->period }}</dd></div><div><dt>Items</dt><dd>{{ $run->itemCount }}</dd></div><div><dt>Commission</dt><dd>{{ $run->commissionTotal }}</dd></div><div><dt>Generated by</dt><dd>{{ $run->generatedBy }}</dd></div></dl></article>
        @empty<div class="people-ops-empty"><strong>No commission runs found</strong><span>Clear filters or generate the first permitted commission run.</span></div>@endforelse
    </div>
    <div class="people-pagination">{{ $commissionRuns->withQueryString()->links() }}</div>
</section>
