@extends('layouts.builder360-classic')

@section('title', 'Unit Pricing - Builder360 ERP-CRM')

@section('content')
@php
        $money = fn ($amount) => 'Rs. '.number_format((float) ($amount ?? 0), 2);
    @endphp

    <div class="blade-workspace" aria-labelledby="unit-pricing-title">
        <header class="blade-workspace-header">
            <div>
                <p class="blade-dashboard-eyebrow">Projects and Inventory</p>
                <h1 id="unit-pricing-title">Unit Pricing</h1>
                <p>
                    Workspace for effective-dated unit price versions,
                    charge break-up, tax calculation, workflow audit and approval segregation.
                </p>
            </div>
            <nav class="blade-workspace-actions" aria-label="Workspace actions">
                <a href="{{ route('builder360.dashboard') }}">Dashboard</a>
                <a href="{{ route('inventory.units.index') }}">Unit Inventory</a>
                <a href="{{ route('sales.bookings.index') }}">Sales Booking</a>
                <a href="{{ route('inventory.unit-price-versions.index') }}">Reset filters</a>
            </nav>
        </header>

        @if (session('status'))
            <section class="blade-alert blade-alert-success" role="status">
                {{ session('status') }}
            </section>
        @endif

        @if ($errors->any())
            <section class="blade-alert blade-alert-error" role="alert">
                <strong>Unit price action was not saved.</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="blade-workspace-grid">
            <article class="blade-dashboard-card">
                <div class="blade-dashboard-section-title">
                    <div>
                        <span class="blade-dashboard-label">Create</span>
                        <h2>Draft price version</h2>
                    </div>
                    <small>{{ $canCreateVersion ? 'Authorized' : 'Read only' }}</small>
                </div>

                @if ($canCreateVersion)
                    <form method="POST" action="{{ route('inventory.unit-price-versions.store') }}" class="blade-form-grid">
                        @csrf

                        <label class="blade-form-wide">
                            Unit
                            <select name="project_unit_id" required>
                                <option value="">Select unit</option>
                                @foreach ($units as $unit)
                                    <option value="{{ $unit->id }}" @selected((string) old('project_unit_id') === (string) $unit->id)>
                                        {{ $unit->unit_code }} - {{ $unit->project?->code ?? 'No project' }} - {{ $unit->unit_type }} - {{ number_format((float) $unit->saleable_area_sqft, 2) }} sq.ft.
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label>
                            Effective from
                            <input type="date" name="effective_from" value="{{ old('effective_from', now()->toDateString()) }}" required>
                        </label>

                        <label>
                            Effective to
                            <input type="date" name="effective_to" value="{{ old('effective_to') }}">
                        </label>

                        <label>
                            Base rate
                            <input type="number" name="base_rate" value="{{ old('base_rate') }}" min="0.01" step="0.01" required>
                        </label>

                        <label>
                            Floor premium
                            <input type="number" name="floor_premium" value="{{ old('floor_premium', 0) }}" min="0" step="0.01">
                        </label>

                        <label>
                            Location premium
                            <input type="number" name="location_premium" value="{{ old('location_premium', 0) }}" min="0" step="0.01">
                        </label>

                        <label>
                            Parking charges
                            <input type="number" name="parking_charges" value="{{ old('parking_charges', 0) }}" min="0" step="0.01">
                        </label>

                        <label>
                            Other charges
                            <input type="number" name="other_charges" value="{{ old('other_charges', 0) }}" min="0" step="0.01">
                        </label>

                        <label>
                            Tax rate %
                            <input type="number" name="tax_rate_percent" value="{{ old('tax_rate_percent', 5) }}" min="0" max="100" step="0.0001">
                        </label>

                        <fieldset class="blade-form-wide blade-fieldset">
                            <legend>Charge break-up</legend>
                            <div class="blade-form-grid">
                                <label>
                                    Clubhouse / amenity charge
                                    <input type="number" name="charge_breakup[clubhouse]" value="{{ old('charge_breakup.clubhouse', 0) }}" min="0" step="0.01">
                                </label>

                                <label>
                                    Legal / documentation charge
                                    <input type="number" name="charge_breakup[legal]" value="{{ old('charge_breakup.legal', 0) }}" min="0" step="0.01">
                                </label>
                            </div>
                        </fieldset>

                        <button type="submit" class="blade-primary-action">Draft price version</button>
                    </form>
                @else
                    <p class="blade-workspace-note">Your role can view unit pricing but cannot draft new price versions.</p>
                @endif
            </article>

            <article class="blade-dashboard-card">
                <div class="blade-dashboard-section-title">
                    <div>
                        <span class="blade-dashboard-label">Controls</span>
                        <h2>Price version filters</h2>
                    </div>
                    <small>{{ $versions->total() }} record(s)</small>
                </div>

                <form method="GET" action="{{ route('inventory.unit-price-versions.index') }}" class="blade-filter-grid blade-filter-grid-compact">
                    <label>
                        Project
                        <select name="project_id">
                            <option value="">All projects</option>
                            @foreach ($projects as $project)
                                <option value="{{ $project->id }}" @selected((string) ($filters['project_id'] ?? '') === (string) $project->id)>
                                    {{ $project->code }} - {{ $project->name }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label>
                        Unit
                        <select name="project_unit_id">
                            <option value="">All units</option>
                            @foreach ($units as $unit)
                                <option value="{{ $unit->id }}" @selected((string) ($filters['project_unit_id'] ?? '') === (string) $unit->id)>
                                    {{ $unit->unit_code }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label>
                        Status
                        <select name="status">
                            <option value="">All statuses</option>
                            @foreach ($statuses as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label>
                        Effective on
                        <input type="date" name="effective_on" value="{{ $filters['effective_on'] ?? '' }}">
                    </label>

                    <button type="submit" class="blade-secondary-action">Apply filters</button>
                </form>

                <p class="blade-workspace-note">
                    Pricing calculations are performed by the configured pricing engine.
                    Approval retires overlapping active versions without changing historical bookings.
                </p>
            </article>
        </section>

        <section class="blade-dashboard-card">
            <div class="blade-dashboard-section-title">
                <div>
                    <span class="blade-dashboard-label">Register</span>
                    <h2>Unit price version register</h2>
                </div>
                <small>{{ $versions->firstItem() ?? 0 }}-{{ $versions->lastItem() ?? 0 }} of {{ $versions->total() }}</small>
            </div>

            <div class="blade-dashboard-table-wrap">
                <table class="blade-dashboard-table">
                    <thead>
                        <tr>
                            <th scope="col">Price version</th>
                            <th scope="col">Project / unit</th>
                            <th scope="col">Effective period</th>
                            <th scope="col">Commercials</th>
                            <th scope="col">Workflow</th>
                            <th scope="col">Status</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($versions as $version)
                            <tr>
                                <td>
                                    <strong>{{ $version->price_code }}</strong>
                                    <span>Version {{ $version->version_number }}</span>
                                </td>
                                <td>
                                    <strong>{{ $version->project?->code ?? 'No project' }}</strong>
                                    <span>{{ $version->unit?->unit_code ?? 'No unit' }} / {{ $version->unit?->unit_type ?? 'No type' }}</span>
                                </td>
                                <td>
                                    <strong>{{ $version->effective_from?->format('d M Y') ?? 'Start pending' }}</strong>
                                    <span>{{ $version->effective_to?->format('d M Y') ?? 'Open ended' }}</span>
                                </td>
                                <td>
                                    <strong>{{ $money($version->total_price) }}</strong>
                                    <span>Base: {{ $money($version->base_price) }}</span>
                                    <span>Gross: {{ $money($version->gross_price_before_tax) }}</span>
                                    <span>Tax: {{ $money($version->tax_amount) }} @ {{ rtrim(rtrim(number_format((float) $version->tax_rate_percent, 4), '0'), '.') }}%</span>
                                </td>
                                <td>
                                    <strong>Created by {{ $version->createdBy?->name ?? 'User missing' }}</strong>
                                    <span>Approved by {{ $version->approvedBy?->name ?? 'Pending' }}</span>
                                    <span>{{ $version->approved_at?->format('d M Y H:i') ?? 'Approval pending' }}</span>
                                </td>
                                <td>{{ $statuses[$version->status] ?? str($version->status)->headline() }}</td>
                                <td>
                                    @can('approve', $version)
                                        @if ($version->status === 'draft')
                                            <details class="blade-row-actions">
                                                <summary>Approve</summary>
                                                <form method="POST" action="{{ route('inventory.unit-price-versions.approve', $version) }}" class="blade-inline-form">
                                                    @csrf
                                                    @method('PATCH')
                                                    <textarea name="note" maxlength="500" rows="2" placeholder="Approval note"></textarea>
                                                    <button type="submit" class="blade-primary-action">Approve version</button>
                                                </form>
                                            </details>
                                        @else
                                            <span>No action</span>
                                        @endif
                                    @else
                                        <span>No action</span>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">No price versions match the selected filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="blade-pagination">
                {{ $versions->links() }}
            </div>
        </section>
    </div>
@endsection
