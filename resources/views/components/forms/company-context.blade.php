@props([
    'companies',
    'selected' => null,
    'required' => false,
    'label' => 'Company',
    'placeholder' => 'Select company',
])

@php
    $singleCompany = (bool) config('builder360.single_company.enabled', true);
    $configuredCode = (string) config('builder360.single_company.code', 'B360D');
    $availableCompanies = collect($companies);
    $activeCompany = $availableCompanies->firstWhere('code', $configuredCode) ?? $availableCompanies->first();
    $selectedCompanyId = old('company_id', $selected ?? $activeCompany?->id);
@endphp

@if ($singleCompany && $activeCompany)
    <input type="hidden" name="company_id" value="{{ $activeCompany->id }}">
@else
    <label>
        {{ $label }}
        <select name="company_id" @required($required)>
            <option value="">{{ $placeholder }}</option>
            @foreach ($availableCompanies as $company)
                <option value="{{ $company->id }}" @selected((string) $selectedCompanyId === (string) $company->id)>
                    {{ $company->code }} &middot; {{ $company->name }}
                </option>
            @endforeach
        </select>
        @error('company_id') <span>{{ $message }}</span> @enderror
    </label>
@endif
