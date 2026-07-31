<section class="people-ops-kpis is-four" aria-label="Commission rule summary">
    <article class="people-ops-kpi is-info"><span class="people-ops-kpi-icon"><i class="fa-solid fa-percent" aria-hidden="true"></i></span><span>Active commission rules</span><strong>{{ number_format($summary->activeCommissionRules) }}</strong><small>Rules eligible for controlled commission runs.</small></article>
    <article class="people-ops-kpi is-warning"><span class="people-ops-kpi-icon"><i class="fa-solid fa-hourglass-half" aria-hidden="true"></i></span><span>Runs awaiting decision</span><strong>{{ number_format($summary->generatedCommissionRuns) }}</strong><small>Generated runs require a separate approver.</small></article>
    <article class="people-ops-kpi is-success"><span class="people-ops-kpi-icon"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></span><span>Approved commission runs</span><strong>{{ number_format($summary->approvedCommissionRuns) }}</strong><small>Approved total: {{ $summary->approvedCommissionTotal }}</small></article>
</section>

<section class="people-ops-grid is-wide-left" aria-label="Commission rule controls">
    <article class="people-ops-panel" id="create-commission-rule">
        <header class="people-ops-panel-head"><div><h2>Create commission rule</h2><p>Define a company-scoped commission basis without changing payroll calculations in the browser.</p></div></header>
        <div class="people-ops-panel-body">
            @if($abilities['canCreateCommissionRule'])
                <form method="POST" action="{{ route('payroll.commission-rules.store') }}" class="people-form-grid" x-data="commissionRuleForm" data-initial-rule-type="{{ old('rule_type', 'percentage') }}">
                    @csrf
                    <label class="people-field"><span>Rule code</span><input class="people-control" name="rule_code" value="{{ old('rule_code') }}" maxlength="40" pattern="[A-Z0-9-]+" required aria-invalid="{{ $errors->has('rule_code') ? 'true' : 'false' }}">@error('rule_code')<small class="people-field-error">{{ $message }}</small>@enderror</label>
                    <label class="people-field"><span>Name</span><input class="people-control" name="name" value="{{ old('name') }}" maxlength="160" required aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}">@error('name')<small class="people-field-error">{{ $message }}</small>@enderror</label>
                    <label class="people-field"><span>Rule type</span><select class="people-control" name="rule_type" x-on:change="selectRuleType" required>@foreach($commissionRuleTypes as $type)<option value="{{ $type['value'] }}" @selected(old('rule_type', 'percentage') === $type['value'])>{{ $type['label'] }}</option>@endforeach</select></label>
                    <label class="people-field"><span>Basis</span><select class="people-control" name="basis" required>@foreach($commissionBases as $basis)<option value="{{ $basis['value'] }}" @selected(old('basis') === $basis['value'])>{{ $basis['label'] }}</option>@endforeach</select></label>
                    <label class="people-field"><span>Project</span><select class="people-control" name="project_id"><option value="">All projects</option>@foreach($projectOptions as $project)<option value="{{ $project['id'] }}" @selected((string)old('project_id') === (string)$project['id'])>{{ $project['label'] }}</option>@endforeach</select></label>
                    <label class="people-field"><span>Status</span><select class="people-control" name="status">@foreach($commissionRuleStatuses as $status)<option value="{{ $status['value'] }}" @selected(old('status', 'active') === $status['value'])>{{ $status['label'] }}</option>@endforeach</select></label>
                    <label class="people-field" x-show="isFixed"><span>Fixed amount</span><input class="people-control" type="number" min="0.01" step="0.01" name="fixed_amount" value="{{ old('fixed_amount') }}" x-bind:disabled="!isFixed" x-bind:required="isFixed">@error('fixed_amount')<small class="people-field-error">{{ $message }}</small>@enderror</label>
                    <label class="people-field" x-show="isPercentage"><span>Rate percent</span><input class="people-control" type="number" min="0.0001" max="100" step="0.0001" name="rate_percent" value="{{ old('rate_percent') }}" x-bind:disabled="!isPercentage" x-bind:required="isPercentage">@error('rate_percent')<small class="people-field-error">{{ $message }}</small>@enderror</label>
                    <label class="people-field" x-show="isTarget"><span>Target amount</span><input class="people-control" type="number" min="0.01" step="0.01" name="target_amount" value="{{ old('target_amount') }}" x-bind:disabled="!isTarget" x-bind:required="isTarget">@error('target_amount')<small class="people-field-error">{{ $message }}</small>@enderror</label>
                    <label class="people-field" x-show="isTarget"><span>Rate after target</span><input class="people-control" type="number" min="0.0001" max="100" step="0.0001" name="rate_percent" value="{{ old('rate_percent') }}" x-bind:disabled="!isTarget" x-bind:required="isTarget">@error('rate_percent')<small class="people-field-error">{{ $message }}</small>@enderror</label>
                    <div class="people-form-grid is-wide" x-show="isSlab">
                        <label class="people-field"><span>Slab from</span><input class="people-control" type="number" min="0" step="0.01" name="slab_rules[0][from]" value="{{ old('slab_rules.0.from', 0) }}" x-bind:disabled="!isSlab" x-bind:required="isSlab"></label>
                        <label class="people-field"><span>Slab to (optional)</span><input class="people-control" type="number" min="0" step="0.01" name="slab_rules[0][to]" value="{{ old('slab_rules.0.to') }}" x-bind:disabled="!isSlab"></label>
                        <label class="people-field"><span>Slab rate percent</span><input class="people-control" type="number" min="0.0001" max="100" step="0.0001" name="slab_rules[0][rate_percent]" value="{{ old('slab_rules.0.rate_percent') }}" x-bind:disabled="!isSlab" x-bind:required="isSlab"></label>
                        @error('slab_rules')<small class="people-field-error is-wide">{{ $message }}</small>@enderror
                    </div>
                    <label class="people-field"><span>Effective from</span><input class="people-control" type="date" name="effective_from" value="{{ old('effective_from', now()->toDateString()) }}" required>@error('effective_from')<small class="people-field-error">{{ $message }}</small>@enderror</label>
                    <label class="people-field"><span>Effective to</span><input class="people-control" type="date" name="effective_to" value="{{ old('effective_to') }}">@error('effective_to')<small class="people-field-error">{{ $message }}</small>@enderror</label>
                    <div class="people-modal-actions is-wide"><button class="people-button is-primary" type="submit"><i class="fa-solid fa-plus" aria-hidden="true"></i>Create rule</button></div>
                </form>
            @else
                <div class="people-ops-empty"><i class="fa-solid fa-lock" aria-hidden="true"></i><strong>Rule creation unavailable</strong><span>Your role can review commission rules but cannot create one.</span></div>
            @endif
        </div>
    </article>
    <article class="people-ops-panel">
        <header class="people-ops-panel-head"><div><h2>Rule filters</h2><p>Limit the register by supported rule attributes.</p></div></header>
        <div class="people-ops-panel-body">
            <form method="GET" action="{{ route('payroll.commission-rules.index') }}" class="people-form-grid">
                <label class="people-field"><span>Search</span><input class="people-control" name="search" maxlength="120" value="{{ request('search') }}"></label>
                <label class="people-field"><span>Status</span><select class="people-control" name="status"><option value="">All statuses</option>@foreach($commissionRuleStatuses as $status)<option value="{{ $status['value'] }}" @selected(request('status') === $status['value'])>{{ $status['label'] }}</option>@endforeach</select></label>
                <label class="people-field"><span>Rule type</span><select class="people-control" name="rule_type"><option value="">All rule types</option>@foreach($commissionRuleTypes as $type)<option value="{{ $type['value'] }}" @selected(request('rule_type') === $type['value'])>{{ $type['label'] }}</option>@endforeach</select></label>
                <label class="people-field"><span>Basis</span><select class="people-control" name="basis"><option value="">All bases</option>@foreach($commissionBases as $basis)<option value="{{ $basis['value'] }}" @selected(request('basis') === $basis['value'])>{{ $basis['label'] }}</option>@endforeach</select></label>
                <label class="people-field is-wide"><span>Project</span><select class="people-control" name="project_id"><option value="">All projects</option>@foreach($projectOptions as $project)<option value="{{ $project['id'] }}" @selected((string)request('project_id') === (string)$project['id'])>{{ $project['label'] }}</option>@endforeach</select></label>
                <div class="people-modal-actions is-wide"><button class="people-button" type="submit">Apply filters</button><a class="people-button" href="{{ route('payroll.commission-rules.index') }}">Clear</a></div>
            </form>
        </div>
    </article>
</section>

<section class="people-ops-panel" aria-labelledby="commission-rules-title">
    <header class="people-ops-panel-head"><div><h2 id="commission-rules-title">Commission rules</h2><p>{{ $commissionRules->total() }} rule{{ $commissionRules->total() === 1 ? '' : 's' }} in this authorized register.</p></div></header>
    <div class="people-ops-table-wrap"><table class="people-ops-table"><caption>Commission rule register</caption><thead><tr><th scope="col">Rule</th><th scope="col">Type / basis</th><th scope="col">Value</th><th scope="col">Project</th><th scope="col">Effective</th><th scope="col">Status</th><th scope="col">Created by</th></tr></thead><tbody>
        @forelse($commissionRules as $rule)
            <tr><td><strong>{{ $rule->code }}</strong><small>{{ $rule->name }}</small></td><td>{{ $rule->typeLabel }}<small>{{ $rule->basisLabel }}</small></td><td>{{ $rule->valueLabel }}</td><td>{{ $rule->projectLabel }}</td><td>{{ $rule->effectiveRange }}</td><td><span class="people-status is-{{ $rule->status === 'active' ? 'success' : ($rule->status === 'draft' ? 'warning' : 'neutral') }}">{{ $rule->statusLabel }}</span></td><td>{{ $rule->createdBy }}</td></tr>
        @empty
            <tr><td colspan="7"><div class="people-ops-empty"><i class="fa-solid fa-percent" aria-hidden="true"></i><strong>No commission rules found</strong><span>Clear filters or create the first permitted commission rule.</span></div></td></tr>
        @endforelse
    </tbody></table></div>
    <div class="people-pagination">{{ $commissionRules->withQueryString()->links() }}</div>
</section>
