@extends('layouts.builder360-classic')

@section('title', 'Unit Inventory - Builder360 ERP-CRM')

@section('content')
@php
        $money = fn ($amount) => 'Rs. '.number_format((float) ($amount ?? 0), 2);
    @endphp

    <div class="blade-workspace" aria-labelledby="unit-inventory-title">
        <header class="blade-workspace-header">
            <div>
                <p class="blade-dashboard-eyebrow">Projects and Inventory</p>
                <h1 id="unit-inventory-title">Unit Inventory</h1>
                <p>
                    Workspace for project-wise unit availability, pricing snapshot,
                    booking reference, filters and CSV availability export.
                </p>
            </div>
            <nav class="blade-workspace-actions" aria-label="Workspace actions">
                <a href="{{ route('builder360.dashboard') }}">Dashboard</a>
                <a href="{{ route('inventory.unit-price-versions.index') }}">Unit Pricing</a>
                <a href="{{ route('sales.bookings.index') }}">Sales Booking</a>
                <a href="{{ route('inventory.units.export', array_merge(request()->query(), ['format' => 'csv'])) }}">Export CSV</a>
                <a href="{{ route('inventory.units.index') }}">Reset filters</a>
            </nav>
        </header>

        @if ($errors->any())
            <section class="blade-alert blade-alert-error" role="alert">
                <strong>Unit inventory filters were not applied.</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="blade-dashboard-grid">
            @foreach ($statuses as $value => $label)
                <article class="blade-dashboard-card">
                    <span class="blade-dashboard-label">{{ $label }}</span>
                    <strong>{{ (int) ($summary[$value] ?? 0) }}</strong>
                    <small>Available units</small>
                </article>
            @endforeach
        </section>

        <section class="blade-dashboard-card">
            <div class="blade-dashboard-section-title">
                <div>
                    <span class="blade-dashboard-label">Controls</span>
                    <h2>Availability filters</h2>
                </div>
                <small>{{ $units->total() }} record(s)</small>
            </div>

            <form method="GET" action="{{ route('inventory.units.index') }}" class="blade-filter-grid blade-filter-grid-compact">
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
                    Status
                    <select name="status">
                        <option value="">All statuses</option>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label>
                    Unit type
                    <select name="unit_type">
                        <option value="">All types</option>
                        @foreach ($unitTypes as $unitType)
                            <option value="{{ $unitType }}" @selected(($filters['unit_type'] ?? '') === $unitType)>{{ $unitType }}</option>
                        @endforeach
                    </select>
                </label>

                <button type="submit" class="blade-secondary-action">Apply filters</button>
            </form>
        </section>

        <section class="blade-dashboard-card">
            <div class="blade-dashboard-section-title">
                <div>
                    <span class="blade-dashboard-label">Register</span>
                    <h2>Unit availability register</h2>
                </div>
                <small>{{ $units->firstItem() ?? 0 }}-{{ $units->lastItem() ?? 0 }} of {{ $units->total() }}</small>
            </div>

            <div class="blade-dashboard-table-wrap">
                <table class="blade-dashboard-table">
                    <thead>
                        <tr>
                            <th scope="col">Unit</th>
                            <th scope="col">Project</th>
                            <th scope="col">Structure</th>
                            <th scope="col">Area</th>
                            <th scope="col">Price snapshot</th>
                            <th scope="col">Booking reference</th>
                            <th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($units as $unit)
                            <tr>
                                <td>
                                    <strong>{{ $unit->unit_code }}</strong>
                                    <span>{{ $unit->unit_type }}</span>
                                </td>
                                <td>
                                    <strong>{{ $unit->project?->code ?? 'No project' }}</strong>
                                    <span>{{ $unit->project?->name ?? 'Project missing' }}</span>
                                    <span>{{ $unit->project?->city ?? 'City pending' }}</span>
                                </td>
                                <td>
                                    <strong>Tower {{ $unit->tower }}</strong>
                                    <span>Floor {{ $unit->floor }} / Unit {{ $unit->unit_number }}</span>
                                </td>
                                <td>
                                    <strong>{{ number_format((float) $unit->saleable_area_sqft, 2) }} saleable sq.ft.</strong>
                                    <span>{{ number_format((float) $unit->carpet_area_sqft, 2) }} carpet sq.ft.</span>
                                </td>
                                <td>
                                    <strong>{{ $money($unit->total_price) }}</strong>
                                    <span>Base rate: {{ $money($unit->base_rate) }}</span>
                                    <span>Tax: {{ $money($unit->tax_amount) }}</span>
                                </td>
                                <td>
                                    @if ($unit->activeBooking)
                                        <strong>{{ $unit->activeBooking->booking_code }}</strong>
                                        <span>{{ str($unit->activeBooking->status)->headline() }}</span>
                                    @else
                                        <span>No active booking</span>
                                    @endif
                                </td>
                                <td>
                                    <strong>{{ $statuses[$unit->status] ?? str($unit->status)->headline() }}</strong>
                                    <span>{{ $unit->isBookable() ? 'Bookable' : 'Not bookable' }}</span>
                                    @if ($unit->reserved_until)
                                        <span>Reserved until {{ $unit->reserved_until->format('d M Y H:i') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">No units match the selected filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="blade-pagination">
                {{ $units->links() }}
            </div>
        </section>
    </div>
@endsection
