@extends('layouts.builder360-classic')

@section('title', 'My Tax Inputs - Builder360 ERP-CRM')

@section('content')
<x-hr.people-workspace
    title="My tax declarations"
    description="Declare employee tax inputs and attach only your existing private proof documents. Statutory formulas remain governed separately."
    eyebrow="Employee Self Service"
    active="employees"
    :self-service="true"
>
    <x-slot:actions>
        <a class="people-button" href="{{ route('hr.employees.me') }}"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Self service</a>
    </x-slot:actions>

    @if (session('status'))
        <section class="people-alert is-success" role="status">{{ session('status') }}</section>
    @endif

    @if ($errors->any())
        <section class="people-alert is-danger" role="alert" tabindex="-1">
            <strong>Tax inputs were not saved.</strong>
            <ul>@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
        </section>
    @endif

    <section class="people-ops-panel">
        <header class="people-ops-panel-head">
            <div>
                <h2>{{ $employee->name }} &middot; {{ $employee->employee_code }}</h2>
                <p>Financial year {{ $financialYear }}. Amounts are stored as encrypted integer minor units.</p>
            </div>
            @if ($taxProfile)
                <span class="people-status is-{{ $statusTone }}">{{ $statusLabel }} &middot; v{{ $taxProfile->version }}</span>
            @endif
        </header>
        <div class="people-ops-panel-body">
            <form method="GET" action="{{ route('hr.employees.me.tax-inputs.edit') }}" class="people-inline-form" aria-label="Select financial year">
                <label class="people-field">
                    <span>Financial year</span>
                    <input class="people-control" name="financial_year" value="{{ $financialYear }}" inputmode="numeric" pattern="\d{4}-\d{2}" aria-describedby="financial-year-help">
                    <small id="financial-year-help">Use YYYY-YY, for example 2026-27.</small>
                </label>
                <button class="people-button" type="submit">Open year</button>
            </form>
        </div>
    </section>

    @if ($isLocked)
        <section class="people-alert" role="note">
            Locked version {{ $taxProfile->version }} is immutable. Saving creates version {{ $amendmentVersion }} as a governed amendment and preserves this locked record and checksum.
        </section>
    @elseif ($isReadOnly)
        <section class="people-alert" role="note">This version is {{ strtolower($statusLabel) }} and is read-only while Payroll or Compliance completes the independent review.</section>
    @endif

    <form method="POST" action="{{ route('hr.employees.me.tax-inputs.update') }}" class="people-ops-panel" novalidate>
        @csrf
        @method('PUT')
        <input type="hidden" name="financial_year" value="{{ $financialYear }}">
        @if ($taxProfile)<input type="hidden" name="lock_version" value="{{ $taxProfile->lock_version }}">@endif

        <header class="people-ops-panel-head">
            <div><h2>Income and regime inputs</h2><p>These are personal payroll inputs, not statutory rate or slab settings.</p></div>
        </header>
        <div class="people-ops-panel-body people-form-grid">
            <label class="people-field">
                <span>Tax regime code</span>
                <input class="people-control" name="regime_code" value="{{ old('regime_code', $regimeCodeInput) }}" maxlength="64" pattern="[A-Za-z0-9_-]{2,64}" @disabled(! $editable) aria-invalid="{{ $errors->has('regime_code') ? 'true' : 'false' }}">
                <small>Stable code from the governed payroll configuration; no rate is edited here.</small>
                @error('regime_code')<span class="people-field-error">{{ $message }}</span>@enderror
            </label>
            <label class="people-field">
                <span>Previous employer income (INR)</span>
                <input class="people-control" type="text" inputmode="decimal" name="previous_employer_income" value="{{ old('previous_employer_income', $previousEmployerIncomeInput) }}" @disabled(! $editable) aria-invalid="{{ $errors->has('previous_employer_income') ? 'true' : 'false' }}">
                @error('previous_employer_income')<span class="people-field-error">{{ $message }}</span>@enderror
            </label>
            <label class="people-field">
                <span>Previous employer TDS (INR)</span>
                <input class="people-control" type="text" inputmode="decimal" name="previous_employer_tds" value="{{ old('previous_employer_tds', $previousEmployerTdsInput) }}" @disabled(! $editable) aria-invalid="{{ $errors->has('previous_employer_tds') ? 'true' : 'false' }}">
                @error('previous_employer_tds')<span class="people-field-error">{{ $message }}</span>@enderror
            </label>
            <label class="people-field">
                <span>Projected other income (INR)</span>
                <input class="people-control" type="text" inputmode="decimal" name="projected_other_income" value="{{ old('projected_other_income', $projectedOtherIncomeInput) }}" @disabled(! $editable) aria-invalid="{{ $errors->has('projected_other_income') ? 'true' : 'false' }}">
                @error('projected_other_income')<span class="people-field-error">{{ $message }}</span>@enderror
            </label>
        </div>

        <header class="people-ops-panel-head">
            <div><h2>Declarations and proofs</h2><p>Blank rows are ignored. Category codes must be stable and unique for this version.</p></div>
        </header>
        <div class="people-ops-table-wrap">
            <table class="people-ops-table">
                <caption>Employee tax declarations for {{ $financialYear }}</caption>
                <thead><tr><th scope="col">Category code</th><th scope="col">Type</th><th scope="col" class="is-number">Declared amount (INR)</th><th scope="col">Private proof</th></tr></thead>
                <tbody>
                    @foreach (old('declarations', $declarationRows) as $index => $row)
                        <tr>
                            <td>
                                <input class="people-control" name="declarations[{{ $index }}][category_code]" value="{{ $row['category_code'] ?? '' }}" maxlength="64" placeholder="e.g. DECLARATION_CODE" @disabled(! $editable) aria-label="Declaration {{ $index + 1 }} category code" aria-invalid="{{ $errors->has("declarations.$index.category_code") ? 'true' : 'false' }}">
                                @error("declarations.$index.category_code")<span class="people-field-error">{{ $message }}</span>@enderror
                            </td>
                            <td>
                                <select class="people-control" name="declarations[{{ $index }}][declaration_type]" @disabled(! $editable) aria-label="Declaration {{ $index + 1 }} type">
                                    <option value="">Select type</option>
                                    @foreach (['deduction' => 'Deduction', 'exemption' => 'Exemption', 'other_income' => 'Other income'] as $value => $label)
                                        <option value="{{ $value }}" @selected(($row['declaration_type'] ?? '') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error("declarations.$index.declaration_type")<span class="people-field-error">{{ $message }}</span>@enderror
                            </td>
                            <td class="is-number">
                                <input class="people-control" type="text" inputmode="decimal" name="declarations[{{ $index }}][declared_amount]" value="{{ $row['declared_amount'] ?? '' }}" placeholder="0.00" @disabled(! $editable) aria-label="Declaration {{ $index + 1 }} amount">
                                @error("declarations.$index.declared_amount")<span class="people-field-error">{{ $message }}</span>@enderror
                            </td>
                            <td>
                                <select class="people-control" name="declarations[{{ $index }}][managed_document_id]" @disabled(! $editable) aria-label="Declaration {{ $index + 1 }} proof document">
                                    <option value="">No proof selected</option>
                                    @foreach ($proofOptions as $document)
                                        <option value="{{ $document['id'] }}" @selected((string) ($row['managed_document_id'] ?? '') === (string) $document['id'])>{{ $document['label'] }}</option>
                                    @endforeach
                                </select>
                                @error("declarations.$index.managed_document_id")<span class="people-field-error">{{ $message }}</span>@enderror
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($editable)
            <div class="people-ops-panel-body people-form-actions">
                <button class="people-button is-primary" type="submit"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> {{ $saveButtonLabel }}</button>
            </div>
        @endif
    </form>

    @if ($canSubmit)
        <section class="people-ops-panel">
            <header class="people-ops-panel-head"><div><h2>Submit for verification</h2><p>Submission freezes editing until an independent Payroll or Compliance reviewer completes the decision.</p></div></header>
            <form method="POST" action="{{ route('hr.employees.me.tax-inputs.submit', $taxProfile) }}" class="people-ops-panel-body people-form-actions">
                @csrf
                @method('PATCH')
                <input type="hidden" name="lock_version" value="{{ $taxProfile->lock_version }}">
                <button class="people-button is-primary" type="submit"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Submit tax inputs</button>
            </form>
        </section>
    @endif

    @if ($taxProfile)
        <section class="people-ops-panel">
            <header class="people-ops-panel-head"><div><h2>Governance trace</h2><p>Version {{ $taxProfile->version }} &middot; checksum {{ $checksumPrefix }}&hellip;</p></div></header>
            <div class="people-ops-table-wrap">
                <table class="people-ops-table"><caption>Tax input workflow history</caption><thead><tr><th scope="col">Event</th><th scope="col">When</th><th scope="col">Note</th></tr></thead><tbody>
                    @forelse ($workflowRows as $entry)
                        <tr><td><strong>{{ $entry['event_label'] }}</strong></td><td>@if($entry['at'])<time datetime="{{ $entry['at'] }}">{{ $entry['at'] }}</time>@else - @endif</td><td>{{ $entry['note'] }}</td></tr>
                    @empty
                        <tr><td colspan="3">No workflow entries are available.</td></tr>
                    @endforelse
                </tbody></table>
            </div>
        </section>
    @endif
</x-hr.people-workspace>
@endsection
