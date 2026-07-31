@extends('layouts.builder360-classic')

@section('title', 'Employee Tax Input Review - Builder360 ERP-CRM')

@section('content')
@php
    $money = static fn (int $minor): string => sprintf('%d.%02d', intdiv($minor, 100), $minor % 100);
    $tone = static fn (string $status): string => match ($status) {
        'locked' => 'success', 'verified' => 'info', 'submitted' => 'warning', default => 'muted',
    };
@endphp

<x-hr.people-workspace
    title="Employee tax input review"
    description="Independent Payroll and Compliance review of employee declarations. Formula variables and statutory packs remain in Scoring Logic."
    eyebrow="Payroll governance"
    active="payroll"
>
    <x-slot:actions>
        <a class="people-button" href="{{ route('payroll.runs.index') }}"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Payroll workspace</a>
    </x-slot:actions>

    @if (session('status'))<section class="people-alert is-success" role="status">{{ session('status') }}</section>@endif
    @if ($errors->any())
        <section class="people-alert is-danger" role="alert" tabindex="-1"><strong>The tax-input workflow was not updated.</strong><ul>@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></section>
    @endif

    <section class="people-ops-panel">
        <form method="GET" action="{{ route('payroll.employee-tax-profiles.index') }}" class="people-ops-filterbar">
            <label class="people-field"><span>Status</span><select class="people-control" name="status"><option value="">All statuses</option>@foreach(['draft','submitted','verified','locked'] as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></label>
            <label class="people-field"><span>Financial year</span><input class="people-control" name="financial_year" value="{{ $filters['financial_year'] ?? '' }}" placeholder="YYYY-YY" inputmode="numeric"></label>
            <button class="people-button" type="submit"><i class="fa-solid fa-filter" aria-hidden="true"></i> Apply</button>
            @if(array_filter($filters))<a class="people-button" href="{{ route('payroll.employee-tax-profiles.index') }}">Clear</a>@endif
        </form>
        <div class="people-ops-table-wrap">
            <table class="people-ops-table">
                <caption>Employee tax input versions available to the current reviewer</caption>
                <thead><tr><th scope="col">Employee</th><th scope="col">Financial year</th><th scope="col">Regime code</th><th scope="col">Version</th><th scope="col">Declarations</th><th scope="col">Status</th><th scope="col">Updated</th><th scope="col" class="is-actions">Action</th></tr></thead>
                <tbody>
                    @forelse ($taxProfiles as $profile)
                        <tr>
                            <td><strong>{{ $profile->employee?->name ?? 'Unavailable employee' }}</strong><small>{{ $profile->employee?->employee_code ?? '-' }}</small></td>
                            <td>{{ $profile->financial_year }}</td><td>{{ $profile->regime_code }}</td><td>v{{ $profile->version }}</td><td>{{ $profile->declarations_count }}</td>
                            <td><span class="people-status is-{{ $tone($profile->status) }}">{{ ucfirst($profile->status) }}</span></td>
                            <td>{{ $profile->updated_at?->timezone(config('app.timezone'))->format('d M Y, h:i A') }}</td>
                            <td class="is-actions"><a class="people-button" href="{{ route('payroll.employee-tax-profiles.show', $profile) }}" aria-label="Review tax inputs for {{ $profile->employee?->name ?? 'employee' }}">Review</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="8">No employee tax profiles match the current filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="people-ops-panel-body">{{ $taxProfiles->links() }}</div>
    </section>

    @if ($selectedTaxProfile)
        @php $inputPayload = $selectedTaxProfile->input_payload ?? []; @endphp
        <section class="people-ops-panel" aria-labelledby="selected-tax-profile-title">
            <header class="people-ops-panel-head">
                <div><h2 id="selected-tax-profile-title">{{ $selectedTaxProfile->employee?->name }} &middot; {{ $selectedTaxProfile->financial_year }}</h2><p>Version {{ $selectedTaxProfile->version }} &middot; lock {{ $selectedTaxProfile->lock_version }} &middot; checksum {{ substr($selectedTaxProfile->input_checksum, 0, 16) }}&hellip;</p></div>
                <span class="people-status is-{{ $tone($selectedTaxProfile->status) }}">{{ ucfirst($selectedTaxProfile->status) }}</span>
            </header>
            <div class="people-ops-panel-body people-form-grid">
                <div class="people-field"><span>Regime code</span><strong>{{ $selectedTaxProfile->regime_code }}</strong></div>
                <div class="people-field"><span>Previous employer income</span><strong>INR {{ $money((int) ($inputPayload['previous_employer_income_minor'] ?? 0)) }}</strong></div>
                <div class="people-field"><span>Previous employer TDS</span><strong>INR {{ $money((int) ($inputPayload['previous_employer_tds_minor'] ?? 0)) }}</strong></div>
                <div class="people-field"><span>Projected other income</span><strong>INR {{ $money((int) ($inputPayload['projected_other_income_minor'] ?? 0)) }}</strong></div>
                @if ($selectedTaxProfile->supersedes)<div class="people-field is-wide"><span>Amendment history</span><strong>Supersedes locked version {{ $selectedTaxProfile->supersedes->version }}</strong></div>@endif
            </div>

            <div class="people-ops-table-wrap">
                <table class="people-ops-table"><caption>Employee tax declaration decisions</caption><thead><tr><th scope="col">Category</th><th scope="col">Type</th><th scope="col" class="is-number">Declared</th><th scope="col" class="is-number">Verified</th><th scope="col">Proof</th><th scope="col">Decision</th></tr></thead><tbody>
                    @forelse ($selectedTaxProfile->declarations as $declaration)
                        <tr>
                            <td><strong>{{ $declaration->category_code }}</strong></td><td>{{ str_replace('_', ' ', ucfirst($declaration->declaration_type)) }}</td>
                            <td class="is-number">INR {{ $money((int) data_get($declaration->amount_payload, 'declared_minor', 0)) }}</td>
                            <td class="is-number">INR {{ $money((int) data_get($declaration->amount_payload, 'verified_minor', 0)) }}</td>
                            <td>@if($declaration->proofDocument) @can('view', $declaration->proofDocument)<a href="{{ route('documents.download', $declaration->proofDocument) }}">{{ $declaration->proofDocument->title }}</a><small>Pinned v{{ data_get($declaration->metadata, 'proof_snapshot.version', $declaration->proofDocument->version) }} &middot; {{ substr((string) data_get($declaration->metadata, 'proof_snapshot.checksum_sha256', $declaration->proofDocument->checksum_sha256), 0, 12) }}&hellip;</small>@else Restricted @endcan @elseif(data_get($declaration->metadata, 'proof_snapshot')) Pinned proof {{ data_get($declaration->metadata, 'proof_snapshot.document_number', '#'.data_get($declaration->metadata, 'proof_snapshot.managed_document_id')) }}<small>Original private document is no longer current; the locked checksum trace remains preserved.</small>@else No proof @endif</td>
                            <td><span class="people-status is-{{ $declaration->status === 'verified' ? 'success' : ($declaration->status === 'rejected' ? 'danger' : 'warning') }}">{{ ucfirst($declaration->status) }}</span>@if($declaration->decision_note)<small>{{ $declaration->decision_note }}</small>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="6">This employee submitted no declaration rows. Income inputs still require independent review.</td></tr>
                    @endforelse
                </tbody></table>
            </div>

            @can('verify', $selectedTaxProfile)
                <form method="POST" action="{{ route('payroll.employee-tax-profiles.verify', $selectedTaxProfile) }}" class="people-ops-panel-body">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="lock_version" value="{{ $selectedTaxProfile->lock_version }}">
                    <h3>Independent declaration decisions</h3>
                    <p class="people-muted">Every declaration must be verified or rejected. Rejections require a reason.</p>
                    <div class="people-form-grid">
                        @foreach ($selectedTaxProfile->declarations as $index => $declaration)
                            <fieldset class="people-ops-panel is-wide">
                                <input type="hidden" name="decisions[{{ $index }}][category_code]" value="{{ $declaration->category_code }}">
                                <header class="people-ops-panel-head"><div><h2>{{ $declaration->category_code }}</h2><p>Declared INR {{ $money((int) data_get($declaration->amount_payload, 'declared_minor', 0)) }}</p></div></header>
                                <div class="people-ops-panel-body people-form-grid">
                                    <label class="people-field"><span>Decision</span><select class="people-control" name="decisions[{{ $index }}][status]"><option value="verified" @selected(old("decisions.$index.status", 'verified') === 'verified')>Verify</option><option value="rejected" @selected(old("decisions.$index.status") === 'rejected')>Reject</option></select></label>
                                    <label class="people-field"><span>Verified amount (INR)</span><input class="people-control" name="decisions[{{ $index }}][verified_amount]" inputmode="decimal" value="{{ old("decisions.$index.verified_amount", $money((int) data_get($declaration->amount_payload, 'declared_minor', 0))) }}"></label>
                                    <label class="people-field is-wide"><span>Decision note</span><textarea class="people-control people-textarea" name="decisions[{{ $index }}][decision_note]" maxlength="1000">{{ old("decisions.$index.decision_note") }}</textarea>@error("decisions.$index.decision_note")<span class="people-field-error">{{ $message }}</span>@enderror</label>
                                </div>
                            </fieldset>
                        @endforeach
                    </div>
                    <div class="people-form-actions"><button class="people-button is-primary" type="submit"><i class="fa-solid fa-user-check" aria-hidden="true"></i> Verify tax inputs</button></div>
                </form>
            @endcan

            @can('lock', $selectedTaxProfile)
                <form method="POST" action="{{ route('payroll.employee-tax-profiles.lock', $selectedTaxProfile) }}" class="people-ops-panel-body people-form-actions">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="lock_version" value="{{ $selectedTaxProfile->lock_version }}">
                    <button class="people-button is-primary" type="submit"><i class="fa-solid fa-lock" aria-hidden="true"></i> Lock verified version</button>
                </form>
            @endcan

            <div class="people-ops-table-wrap">
                <table class="people-ops-table"><caption>Employee tax input workflow history</caption><thead><tr><th scope="col">Event</th><th scope="col">When</th><th scope="col">Note</th></tr></thead><tbody>
                    @forelse ($selectedTaxProfile->workflow_history ?? [] as $entry)
                        <tr><td>{{ str_replace('_', ' ', ucfirst($entry['event'] ?? 'updated')) }}</td><td>@if(filled($entry['at'] ?? null))<time datetime="{{ $entry['at'] }}">{{ $entry['at'] }}</time>@else - @endif</td><td>{{ $entry['note'] ?? '-' }}</td></tr>
                    @empty<tr><td colspan="3">No workflow entries are available.</td></tr>@endforelse
                </tbody></table>
            </div>
        </section>
    @else
        <x-hr.people-state title="Select a tax profile" message="Choose Review to inspect encrypted employee inputs and perform an authorized independent decision." icon="fa-file-shield" />
    @endif
</x-hr.people-workspace>
@endsection
