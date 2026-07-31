@extends('layouts.builder360-classic')

@section('title', 'Sales Booking - Builder360 ERP-CRM')

@section('content')
@php
        $quote = session('quote');
        $money = fn ($amount) => 'Rs. '.number_format((float) ($amount ?? 0), 2);
    @endphp

    <div class="blade-workspace" aria-labelledby="sales-booking-title">
        <header class="blade-workspace-header">
            <div>
                <p class="blade-dashboard-eyebrow">Sales and CRM</p>
                <h1 id="sales-booking-title">Sales Booking</h1>
                <p>
                    Workspace for booking quote preview, customer and unit selection,
                    payment schedule capture, booking confirmation, audit trail generation and unit status update.
                </p>
            </div>
            <nav class="blade-workspace-actions" aria-label="Workspace actions">
                <a href="{{ route('builder360.dashboard') }}">Dashboard</a>
                <a href="{{ route('crm.leads.index') }}">Lead Management</a>
                <a href="{{ route('crm.lead-qualifications.index') }}">Lead Qualification</a>
                <a href="{{ route('crm.site-visits.index') }}">Site Visits</a>
                <a href="{{ route('sales.bookings.index') }}">Reset filters</a>
            </nav>
        </header>

        @if (session('status'))
            <section class="blade-alert blade-alert-success" role="status">
                {{ session('status') }}
            </section>
        @endif

        @if ($errors->any())
            <section class="blade-alert blade-alert-error" role="alert">
                <strong>Booking action was not saved.</strong>
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
                        <span class="blade-dashboard-label">Quote</span>
                        <h2>Booking quote preview</h2>
                    </div>
                    <small>{{ $bookableUnits->count() }} bookable unit(s)</small>
                </div>

                <form method="POST" action="{{ route('sales.booking-quotes.store') }}" class="blade-form-grid">
                    @csrf

                    <label class="blade-form-wide">
                        Unit
                        <select name="project_unit_id" required>
                            <option value="">Select available unit</option>
                            @foreach ($bookableUnits as $unit)
                                <option value="{{ $unit->id }}" @selected((string) old('project_unit_id') === (string) $unit->id)>
                                    {{ $unit->unit_code }} - {{ $unit->project?->code ?? 'No project' }} - {{ $unit->unit_type }} - {{ $money($unit->total_price) }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label>
                        Quoted on
                        <input type="date" name="quoted_on" value="{{ old('quoted_on', now()->toDateString()) }}">
                    </label>

                    <label>
                        Discount amount
                        <input type="number" name="discount_amount" value="{{ old('discount_amount', 0) }}" min="0" step="0.01">
                    </label>

                    <button type="submit" class="blade-secondary-action">Preview quote</button>
                </form>

                @if (is_array($quote))
                    <div class="blade-workspace-note">
                        Quote source: {{ str($quote['source'] ?? 'not_available')->headline() }}
                    </div>

                    <div class="blade-dashboard-table-wrap">
                        <table class="blade-dashboard-table">
                            <tbody>
                                <tr>
                                    <th scope="row">Unit</th>
                                    <td>{{ $quote['unit']['unit_code'] ?? 'Not available' }}</td>
                                </tr>
                                <tr>
                                    <th scope="row">Price code</th>
                                    <td>{{ $quote['price_code'] ?? 'Snapshot pricing' }}</td>
                                </tr>
                                <tr>
                                    <th scope="row">Gross before tax</th>
                                    <td>{{ $money($quote['gross_price_before_tax'] ?? 0) }}</td>
                                </tr>
                                <tr>
                                    <th scope="row">Discount</th>
                                    <td>{{ $money($quote['discount_amount'] ?? 0) }}</td>
                                </tr>
                                <tr>
                                    <th scope="row">Tax amount</th>
                                    <td>{{ $money($quote['tax_amount'] ?? 0) }}</td>
                                </tr>
                                <tr>
                                    <th scope="row">Net payable</th>
                                    <td><strong>{{ $money($quote['total_payable'] ?? 0) }}</strong></td>
                                </tr>
                                <tr>
                                    <th scope="row">Discount approval</th>
                                    <td>{{ ($quote['requires_discount_approval'] ?? false) ? 'Required' : 'Not required' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="blade-workspace-note">
                        Use Preview quote before booking to verify effective price, discount, tax and net receivable.
                    </p>
                @endif
            </article>

            <article class="blade-dashboard-card">
                <div class="blade-dashboard-section-title">
                    <div>
                        <span class="blade-dashboard-label">Create</span>
                        <h2>Create booking</h2>
                    </div>
                    <small>{{ $canCreateBooking ? 'Authorized' : 'Read only' }}</small>
                </div>

                @if ($canCreateBooking)
                    <form method="POST" action="{{ route('sales.bookings.store') }}" class="blade-form-grid">
                        @csrf

                        <label class="blade-form-wide">
                            Unit
                            <select name="project_unit_id" required>
                                <option value="">Select available unit</option>
                                @foreach ($bookableUnits as $unit)
                                    <option value="{{ $unit->id }}" @selected((string) old('project_unit_id') === (string) $unit->id)>
                                        {{ $unit->unit_code }} - {{ $unit->project?->code ?? 'No project' }} - {{ $unit->unit_type }} - {{ $money($unit->total_price) }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label class="blade-form-wide">
                            Lead
                            <select name="lead_id">
                                <option value="">Direct booking / no lead</option>
                                @foreach ($leads as $lead)
                                    <option value="{{ $lead->id }}" @selected((string) old('lead_id') === (string) $lead->id)>
                                        {{ $lead->lead_code }} - {{ $lead->customer?->name ?? 'Customer pending' }} - {{ $lead->project?->code ?? 'No project' }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label class="blade-form-wide">
                            Customer
                            <select name="customer_id" required>
                                <option value="">Select customer</option>
                                @foreach ($customers as $customer)
                                    <option value="{{ $customer->id }}" @selected((string) old('customer_id') === (string) $customer->id)>
                                        {{ $customer->code }} - {{ $customer->name }} - {{ $customer->phone ?? 'No phone' }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label>
                            Channel partner / broker
                            <select name="partner_id">
                                <option value="">No partner</option>
                                @foreach ($partners as $partner)
                                    <option value="{{ $partner->id }}" @selected((string) old('partner_id') === (string) $partner->id)>
                                        {{ $partner->code }} - {{ $partner->name }} - {{ str($partner->partner_type)->headline() }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label>
                            Booking date
                            <input type="date" name="booked_on" value="{{ old('booked_on', now()->toDateString()) }}">
                        </label>

                        <label>
                            Booking amount
                            <input type="number" name="booking_amount" value="{{ old('booking_amount') }}" min="0" step="0.01" required>
                        </label>

                        <label>
                            Discount amount
                            <input type="number" name="discount_amount" value="{{ old('discount_amount', 0) }}" min="0" step="0.01">
                        </label>

                        <fieldset class="blade-form-wide blade-fieldset">
                            <legend>Payment schedule</legend>
                            <div class="blade-form-grid">
                                @foreach ([
                                    ['sequence' => 1, 'milestone' => 'Booking Amount', 'percentage' => 10],
                                    ['sequence' => 2, 'milestone' => 'Agreement', 'percentage' => 20],
                                    ['sequence' => 3, 'milestone' => 'Possession', 'percentage' => 70],
                                ] as $index => $schedule)
                                    <input type="hidden" name="payment_schedule[{{ $index }}][sequence]" value="{{ old("payment_schedule.$index.sequence", $schedule['sequence']) }}">

                                    <label>
                                        Milestone {{ $schedule['sequence'] }}
                                        <input type="text" name="payment_schedule[{{ $index }}][milestone]" value="{{ old("payment_schedule.$index.milestone", $schedule['milestone']) }}" maxlength="120">
                                    </label>

                                    <label>
                                        Percentage
                                        <input type="number" name="payment_schedule[{{ $index }}][percentage]" value="{{ old("payment_schedule.$index.percentage", $schedule['percentage']) }}" min="0" max="100" step="0.01">
                                    </label>

                                    <label>
                                        Due date
                                        <input type="date" name="payment_schedule[{{ $index }}][due_on]" value="{{ old("payment_schedule.$index.due_on") }}">
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>

                        <button type="submit" class="blade-primary-action">Confirm booking</button>
                    </form>
                @else
                    <p class="blade-workspace-note">Your role can view bookings but cannot create new bookings.</p>
                @endif
            </article>
        </section>

        <section class="blade-dashboard-card">
            <div class="blade-dashboard-section-title">
                <div>
                    <span class="blade-dashboard-label">Controls</span>
                    <h2>Booking filters</h2>
                </div>
                <small>{{ $bookings->total() }} record(s)</small>
            </div>

            <form method="GET" action="{{ route('sales.bookings.index') }}" class="blade-filter-grid blade-filter-grid-compact">
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
                    Customer
                    <select name="customer_id">
                        <option value="">All customers</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" @selected((string) ($filters['customer_id'] ?? '') === (string) $customer->id)>
                                {{ $customer->code }} - {{ $customer->name }}
                            </option>
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
                    <h2>Booking register</h2>
                </div>
                <small>{{ $bookings->firstItem() ?? 0 }}-{{ $bookings->lastItem() ?? 0 }} of {{ $bookings->total() }}</small>
            </div>

            <div class="blade-dashboard-table-wrap">
                <table class="blade-dashboard-table">
                    <thead>
                        <tr>
                            <th scope="col">Booking</th>
                            <th scope="col">Customer</th>
                            <th scope="col">Project / unit</th>
                            <th scope="col">Booked by</th>
                            <th scope="col">Commercials</th>
                            <th scope="col">Payment schedule</th>
                            <th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($bookings as $booking)
                            <tr>
                                <td>
                                    <strong>{{ $booking->booking_code }}</strong>
                                    <span>{{ $booking->booked_on?->format('d M Y') ?? 'Date pending' }}</span>
                                </td>
                                <td>
                                    <strong>{{ $booking->customer?->name ?? 'Customer missing' }}</strong>
                                    <span>{{ $booking->lead?->lead_code ?? 'No linked lead' }}</span>
                                </td>
                                <td>
                                    <strong>{{ $booking->project?->code ?? 'No project' }}</strong>
                                    <span>{{ $booking->unit?->unit_code ?? 'No unit' }}</span>
                                </td>
                                <td>
                                    <strong>{{ $booking->bookedBy?->name ?? 'User missing' }}</strong>
                                    <span>{{ $booking->partner?->name ?? 'No partner' }}</span>
                                </td>
                                <td>
                                    <strong>{{ $money($booking->net_receivable) }}</strong>
                                    <span>Booking: {{ $money($booking->booking_amount) }}</span>
                                    <span>Discount: {{ $money($booking->discount_amount) }}</span>
                                </td>
                                <td>
                                    @forelse ($booking->paymentSchedules as $schedule)
                                        <span>
                                            {{ $schedule->sequence }}. {{ $schedule->milestone }} -
                                            {{ $schedule->percentage ? rtrim(rtrim(number_format((float) $schedule->percentage, 2), '0'), '.') . '%' : $money($schedule->amount) }}
                                        </span>
                                    @empty
                                        <span>No schedule</span>
                                    @endforelse
                                </td>
                                <td>{{ $statuses[$booking->status] ?? str($booking->status)->headline() }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">No bookings match the selected filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="blade-pagination">
                {{ $bookings->links() }}
            </div>
        </section>
    </div>
@endsection
