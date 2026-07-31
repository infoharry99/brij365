<section class="people-ops-grid is-wide-left" aria-label="Employee asset controls">
    <article class="people-ops-panel" id="asset-form">
        <header class="people-ops-panel-head">
            <div><h2>Register employee asset</h2><p>Add an asset to the governed company inventory.</p></div>
        </header>
        <div class="people-ops-panel-body">
            @if ($abilities['canCreateAsset'])
                <form method="POST" action="{{ route('hr.assets.store') }}" class="people-form-grid" x-data="serverFormState" x-on:submit="beginSubmit" x-bind:aria-busy="busyAria" data-idle-label="Register asset" data-busy-label="Registering…">
                    @csrf
                    @if ($companies->count() > 1)
                        <label class="people-field"><span>Company</span><select class="people-control" name="company_id" required><option value="">Select company</option>@foreach($companies as $company)<option value="{{ $company->id }}" @selected((string) old('company_id') === (string) $company->id)>{{ $company->code }} - {{ $company->name }}</option>@endforeach</select>@error('company_id')<small class="people-field-error">{{ $message }}</small>@enderror</label>
                    @elseif ($companies->first())
                        <input type="hidden" name="company_id" value="{{ $companies->first()->id }}">
                    @endif
                    <label class="people-field"><span>Asset code</span><input class="people-control" name="asset_code" value="{{ old('asset_code') }}" maxlength="40" pattern="[A-Z0-9-]+" required placeholder="AST-1001">@error('asset_code')<small class="people-field-error">{{ $message }}</small>@enderror</label>
                    <label class="people-field"><span>Category</span><select class="people-control" name="category" required><option value="">Select category</option>@foreach($assetCategories as $category)<option value="{{ $category }}" @selected(old('category') === $category)>{{ $category }}</option>@endforeach</select>@error('category')<small class="people-field-error">{{ $message }}</small>@enderror</label>
                    <label class="people-field"><span>Asset name</span><input class="people-control" name="name" value="{{ old('name') }}" maxlength="160" required placeholder="Device or equipment name">@error('name')<small class="people-field-error">{{ $message }}</small>@enderror</label>
                    <label class="people-field"><span>Serial number</span><input class="people-control" name="serial_number" value="{{ old('serial_number') }}" maxlength="120" placeholder="Optional manufacturer serial">@error('serial_number')<small class="people-field-error">{{ $message }}</small>@enderror</label>
                    <label class="people-field"><span>Condition</span><select class="people-control" name="condition">@foreach($assetConditions as $condition)<option value="{{ $condition }}" @selected(old('condition', 'good') === $condition)>{{ ucfirst($condition) }}</option>@endforeach</select>@error('condition')<small class="people-field-error">{{ $message }}</small>@enderror</label>
                    <label class="people-field"><span>Estimated value (INR)</span><input class="people-control" type="number" name="estimated_value" value="{{ old('estimated_value', 0) }}" min="0" step="0.01">@error('estimated_value')<small class="people-field-error">{{ $message }}</small>@enderror</label>
                    <div class="people-modal-actions is-wide"><button type="submit" class="people-button is-primary" x-bind:disabled="busy"><span x-text="submitLabel">Register asset</span></button></div>
                </form>
            @else
                <div class="people-ops-empty"><i class="fa-solid fa-lock" aria-hidden="true"></i><strong>Asset registration unavailable</strong><span>Your role can review authorized assets but cannot add inventory.</span></div>
            @endif
        </div>
    </article>

    <article class="people-ops-panel">
        <header class="people-ops-panel-head"><div><h2>Asset filters</h2><p>Filter the authorized inventory without changing company scope.</p></div></header>
        <div class="people-ops-panel-body">
            <form method="GET" action="{{ route('hr.assets.index') }}" class="people-form-grid">
                <label class="people-field is-wide"><span>Search</span><input class="people-control" name="search" value="{{ request('search') }}" maxlength="120" placeholder="Asset code, name, or serial number"></label>
                <label class="people-field"><span>Employee</span><select class="people-control" name="employee_id"><option value="">All employees</option>@foreach($employees as $employee)<option value="{{ $employee->id }}" @selected((string) request('employee_id') === (string) $employee->id)>{{ $employee->employee_code }} - {{ $employee->name }}</option>@endforeach</select></label>
                <label class="people-field"><span>Category</span><select class="people-control" name="category"><option value="">All categories</option>@foreach($assetCategories as $category)<option value="{{ $category }}" @selected(request('category') === $category)>{{ $category }}</option>@endforeach</select></label>
                <label class="people-field"><span>Status</span><select class="people-control" name="status"><option value="">All statuses</option>@foreach($assetStatuses as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></label>
                <div class="people-modal-actions is-wide"><button class="people-button" type="submit">Apply filters</button><a class="people-button" href="{{ route('hr.assets.index') }}">Clear</a></div>
            </form>
        </div>
    </article>
</section>

<section class="people-ops-panel has-mobile-cards" aria-labelledby="employee-assets-title">
    <header class="people-ops-panel-head"><div><h2 id="employee-assets-title">Employee assets</h2><p>{{ $assets->total() }} asset{{ $assets->total() === 1 ? '' : 's' }} match the selected filters.</p></div></header>
    <div class="people-ops-table-wrap">
        <table class="people-ops-table">
            <caption>Employee asset register</caption>
            <thead><tr><th scope="col">Asset</th><th scope="col">Category / serial</th><th scope="col">Custodian</th><th scope="col">Condition</th><th scope="col">Status / dates</th><th scope="col">Value / history</th><th scope="col" class="is-actions">Action</th></tr></thead>
            <tbody>
                @forelse ($assets as $asset)
                    <tr>
                        <td><strong>{{ $asset->assetCode }}</strong><small>{{ $asset->name }}</small></td>
                        <td>{{ $asset->category }}<small>{{ $asset->serialNumber }}</small></td>
                        <td><div class="people-ops-identity"><span class="people-avatar">{{ $asset->employeeInitial }}</span><div><strong>{{ $asset->employeeName }}</strong><small>{{ $asset->employeeCode }} / {{ $asset->employeeContext }}</small></div></div></td>
                        <td><span class="people-status is-{{ $asset->conditionTone }}">{{ $asset->conditionLabel }}</span></td>
                        <td><span class="people-status is-{{ $asset->statusTone }}">{{ $asset->statusLabel }}</span><small>Assigned: {{ $asset->assignedOn }}</small><small>Recovered: {{ $asset->recoveredOn }}</small></td>
                        <td>{{ $asset->estimatedValue }}<small>{{ $asset->workflowNote }}</small><small>{{ $asset->workflowActor }} / {{ $asset->workflowAt }}</small></td>
                        <td class="is-actions">
                            @include('hr.operations.partials.asset-actions', ['asset' => $asset])
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7"><div class="people-ops-empty"><i class="fa-solid fa-laptop" aria-hidden="true"></i><strong>No employee assets found</strong><span>Clear the filters or register a new asset when permitted.</span></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="people-ops-mobile-list">
        @forelse ($assets as $asset)
            <article class="people-ops-mobile-card">
                <div class="people-ops-mobile-card-head"><strong>{{ $asset->assetCode }} / {{ $asset->name }}</strong><span class="people-status is-{{ $asset->statusTone }}">{{ $asset->statusLabel }}</span></div>
                <dl class="people-ops-mobile-facts"><div><dt>Category / serial</dt><dd>{{ $asset->category }} / {{ $asset->serialNumber }}</dd></div><div><dt>Custodian</dt><dd>{{ $asset->employeeName }} / {{ $asset->employeeCode }}</dd></div><div><dt>Condition</dt><dd>{{ $asset->conditionLabel }}</dd></div><div><dt>Estimated value</dt><dd>{{ $asset->estimatedValue }}</dd></div><div><dt>Assigned</dt><dd>{{ $asset->assignedOn }}</dd></div><div><dt>Recovered</dt><dd>{{ $asset->recoveredOn }}</dd></div></dl>
                <p>{{ $asset->workflowNote }} / {{ $asset->workflowActor }} / {{ $asset->workflowAt }}</p>
                <div class="people-ops-mobile-actions">
                    @include('hr.operations.partials.asset-actions', ['asset' => $asset])
                </div>
            </article>
        @empty
            <div class="people-ops-empty"><strong>No employee assets found</strong><span>Clear the filters or register a new asset when permitted.</span></div>
        @endforelse
    </div>
    <div class="people-pagination">{{ $assets->withQueryString()->links() }}</div>
</section>
