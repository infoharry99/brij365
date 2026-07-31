@extends('layouts.builder360-classic')

@section('title', 'Compliance Center - Builder360 ERP-CRM')

@section('content')
<x-hr.people-workspace title="Compliance Center" description="Compliance Rules are governed through maker-checker approval and effective-dated versions using verified statutory values only." active="compliance">
    <x-slot:actions><a class="people-button" href="{{ route('hr.policy-acknowledgements.index') }}"><i class="fa-solid fa-file-signature" aria-hidden="true"></i> Policy acknowledgements</a></x-slot:actions>

    @if(session('status'))<section class="people-alert is-success" role="status">{{ session('status') }}</section>@endif
    @if($errors->any())<section class="people-alert is-danger" role="alert" aria-labelledby="compliance-errors-title" tabindex="-1"><strong id="compliance-errors-title">Please correct the highlighted compliance fields.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></section>@endif

    <section class="people-ops-kpis" aria-label="Compliance rule summary">
        @foreach ([
            ['Rule versions', $summary->total, 'fa-shield-halved', ''],
            ['Drafts awaiting review', $summary->draft, 'fa-hourglass-half', 'is-warning'],
            ['Active versions', $summary->active, 'fa-circle-check', 'is-success'],
            ['Archived versions', $summary->archived, 'fa-box-archive', ''],
            ['Validation required', $summary->verificationRequired, 'fa-magnifying-glass-chart', 'is-warning'],
        ] as [$label, $value, $icon, $tone])
            <article class="people-ops-kpi {{ $tone }}"><span class="people-ops-kpi-icon"><i class="fa-solid {{ $icon }}" aria-hidden="true"></i></span><span>{{ $label }}</span><strong>{{ number_format($value) }}</strong><small>Authorized compliance scope</small></article>
        @endforeach
    </section>

    @if($abilities['canCreate'])
        <details class="people-edit-details" @if($errors->any()) open @endif>
            <summary>Create governed compliance rule draft</summary>
            <form method="POST" action="{{ route('hr.compliance-rule-settings.store') }}" class="people-form-grid people-edit-form" x-data="serverFormState" x-on:submit="beginSubmit" x-bind:aria-busy="busyAria" data-idle-label="Create draft for approval" data-busy-label="Creating draft…">@csrf
                <x-forms.company-context :companies="$companies" />
                <label class="people-field"><span>Rule type</span><select class="people-control" name="setting_key" required><option value="">Select rule type</option>@foreach($settingKeys as $value=>$label)<option value="{{ $value }}" @selected(old('setting_key')===$value)>{{ $label }}</option>@endforeach</select>@error('setting_key')<small class="people-field-error">{{ $message }}</small>@enderror</label>
                <label class="people-field"><span>Rule label</span><input class="people-control" name="label" value="{{ old('label') }}" maxlength="255" required>@error('label')<small class="people-field-error">{{ $message }}</small>@enderror</label>
                <label class="people-field"><span>Effective from</span><input class="people-control" type="date" name="effective_from" value="{{ old('effective_from') }}" required>@error('effective_from')<small class="people-field-error">{{ $message }}</small>@enderror</label>
                <label class="people-field"><span>Approval step</span><input class="people-control" name="value[approval_chain][0]" value="{{ old('value.approval_chain.0') }}" placeholder="Authorized approval role" required>@error('value.approval_chain')<small class="people-field-error">{{ $message }}</small>@enderror</label>
                <label class="people-field"><span>Statutory validation</span><select class="people-control" name="value[statutory_validation_required]" required><option value="">Select requirement</option><option value="1" @selected(old('value.statutory_validation_required')==='1')>Required</option><option value="0" @selected(old('value.statutory_validation_required')==='0')>Not required</option></select></label>
                <label class="people-field"><span>Applicability</span><input class="people-control" name="value[applicability]" value="{{ old('value.applicability') }}" placeholder="Enter approved applicability"></label>
                <label class="people-field"><span>Wage basis</span><input class="people-control" name="value[wage_basis]" value="{{ old('value.wage_basis') }}" placeholder="Enter verified wage basis"></label>
                <label class="people-field"><span>Calculation method</span><input class="people-control" name="value[calculation_method]" value="{{ old('value.calculation_method') }}" placeholder="Enter approved calculation method"></label>
                <label class="people-field"><span>Default rate</span><input class="people-control" type="number" min="0" step="0.0001" name="value[rates][default]" value="{{ old('value.rates.default') }}" placeholder="No prototype default"></label>
                <label class="people-field"><span>Financial year</span><input class="people-control" name="value[financial_year]" value="{{ old('value.financial_year') }}" placeholder="e.g. 2026-2027"></label>
                <label class="people-field"><span>Form 16 template version</span><input class="people-control" name="value[form16_template_version]" value="{{ old('value.form16_template_version') }}"></label>
                <label class="people-field"><span>Payroll year locked</span><select class="people-control" name="value[payroll_year_locked]"><option value="">Not specified</option><option value="0" @selected(old('value.payroll_year_locked')==='0')>No</option><option value="1" @selected(old('value.payroll_year_locked')==='1')>Yes</option></select></label>
                <label class="people-field"><span>GST transaction type</span><input class="people-control" name="value[supported_transaction_types][0]" value="{{ old('value.supported_transaction_types.0') }}"></label>
                <label class="people-field"><span>GST default tax rate</span><input class="people-control" type="number" min="0" step="0.01" name="value[default_tax_rates][standard]" value="{{ old('value.default_tax_rates.standard') }}"></label>
                <label class="people-field"><span>Leave encashment method</span><select class="people-control" name="value[encashment_formula]"><option value="">Select approved method</option><option value="daily_basic_rate" @selected(old('value.encashment_formula')==='daily_basic_rate')>Daily basic rate</option><option value="daily_gross_rate" @selected(old('value.encashment_formula')==='daily_gross_rate')>Daily gross rate</option><option value="fixed_policy_rate" @selected(old('value.encashment_formula')==='fixed_policy_rate')>Fixed policy rate</option></select></label>
                <label class="people-field is-wide"><span>Description</span><textarea class="people-control" name="description" maxlength="5000" rows="3">{{ old('description') }}</textarea>@error('description')<small class="people-field-error">{{ $message }}</small>@enderror</label>
                <div class="people-alert is-danger is-wide" role="note"><strong>Verified values only.</strong> Builder360 does not infer statutory rates, applicability, formulas, or legal effective dates from the prototype.</div>
                <div class="people-modal-actions is-wide"><button class="people-button is-primary" type="submit" x-bind:disabled="busy"><span x-text="submitLabel">Create draft for approval</span></button></div>
            </form>
        </details>
    @endif

    <section class="people-ops-panel has-mobile-cards" aria-labelledby="compliance-register-title">
        <header class="people-ops-panel-head"><div><h2 id="compliance-register-title">Versioned rule register</h2><p>{{ number_format($settings->total()) }} rule version{{ $settings->total() === 1 ? '' : 's' }} match the selected filters.</p></div></header>
        <form method="GET" action="{{ route('hr.compliance-rule-settings.index') }}" class="people-ops-filterbar" aria-label="Filter compliance rules">
            <label class="people-field"><span>Rule type</span><select class="people-control" name="setting_key"><option value="">All rule types</option>@foreach($settingKeys as $value=>$label)<option value="{{ $value }}" @selected(request('setting_key')===$value)>{{ $label }}</option>@endforeach</select></label>
            <label class="people-field"><span>Status</span><select class="people-control" name="status"><option value="">All statuses</option>@foreach(['draft'=>'Draft','active'=>'Active','archived'=>'Archived'] as $value=>$label)<option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>@endforeach</select></label>
            <div class="people-modal-actions"><button class="people-button" type="submit">Apply filters</button><a class="people-button" href="{{ route('hr.compliance-rule-settings.index') }}">Clear</a></div>
        </form>
        <div class="people-ops-table-wrap"><table class="people-ops-table"><caption>Compliance rule versions</caption><thead><tr><th scope="col">Rule</th><th scope="col">Version / scope</th><th scope="col">Effective date</th><th scope="col">Maker / checker</th><th scope="col">Validation</th><th scope="col">Status</th><th scope="col" class="is-actions">Action</th></tr></thead><tbody>
            @forelse($settings as $setting)
                <tr><td><strong>{{ $setting->label }}</strong><small>{{ $setting->settingType }}</small></td><td>v{{ $setting->version }}<small>{{ $setting->scope }}</small></td><td>{{ $setting->effectiveFrom }}</td><td>{{ $setting->createdBy }}<small>{{ $setting->approvalState }}</small></td><td>{{ $setting->verificationLabel }}<small>{{ $setting->sourceAuthority }} / {{ $setting->sourceReference }}@if($setting->verifiedBy) / Verified by {{ $setting->verifiedBy }}@endif</small></td><td><span class="people-status {{ $setting->statusTone }}">{{ $setting->statusLabel }}</span></td><td class="is-actions">@include('hr.compliance.partials.rule-actions', ['setting' => $setting, 'actionContext' => 'desktop'])</td></tr>
            @empty<tr><td colspan="7"><div class="people-ops-empty"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i><strong>No compliance rule versions found</strong><span>Clear the filters or create a governed draft if your role permits it.</span></div></td></tr>@endforelse
        </tbody></table></div>
        <div class="people-ops-mobile-list">@foreach($settings as $setting)<article class="people-ops-mobile-card"><div class="people-ops-mobile-card-head"><strong>{{ $setting->label }} / v{{ $setting->version }}</strong><span class="people-status {{ $setting->statusTone }}">{{ $setting->statusLabel }}</span></div><dl class="people-ops-mobile-facts"><div><dt>Rule type</dt><dd>{{ $setting->settingType }}</dd></div><div><dt>Scope</dt><dd>{{ $setting->scope }}</dd></div><div><dt>Effective</dt><dd>{{ $setting->effectiveFrom }}</dd></div><div><dt>Verification</dt><dd>{{ $setting->verificationLabel }}</dd></div><div><dt>Official source</dt><dd>{{ $setting->sourceAuthority }} / {{ $setting->sourceReference }}</dd></div><div><dt>Approval</dt><dd>{{ $setting->approvalState }}</dd></div></dl><div class="people-ops-mobile-actions">@include('hr.compliance.partials.rule-actions', ['setting' => $setting, 'actionContext' => 'mobile'])</div></article>@endforeach</div>
        <div class="people-pagination">{{ $settings->withQueryString()->links() }}</div>
    </section>
</x-hr.people-workspace>
@endsection
