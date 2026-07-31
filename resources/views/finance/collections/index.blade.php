@extends('layouts.builder360-classic')

@section('title', 'Customer Collections - Builder360 ERP-CRM')

@section('content')
@php
        $money = fn ($amount) => 'Rs. '.number_format((float) ($amount ?? 0), 2);
    @endphp

    <div class="blade-workspace" aria-labelledby="collections-title">
        <header class="blade-workspace-header">
            <div>
                <p class="blade-dashboard-eyebrow">Finance and Operations</p>
                <h1 id="collections-title">Customer Collections</h1>
                <p>
                    Workspace for receipt capture, booking milestone linkage,
                    submitted-to-approved workflow, payment schedule update, filters and CSV export.
                </p>
            </div>
            <nav class="blade-workspace-actions" aria-label="Workspace actions">
                <a href="{{ route('builder360.dashboard') }}">Dashboard</a>
                <a href="{{ route('sales.bookings.index') }}">Sales Booking</a>
                <a href="{{ route('finance.dashboard') }}">Finance Dashboard</a>
                <a href="{{ route('finance.collections.export', request()->query()) }}">Export CSV</a>
                <a href="{{ route('finance.collections.index') }}">Reset filters</a>
            </nav>
        </header>

        @if (session('status'))
            <section class="blade-alert blade-alert-success" role="status">
                {{ session('status') }}
            </section>
        @endif

        @if ($errors->any())
            <section class="blade-alert blade-alert-error" role="alert">
                <strong>Collection action was not saved.</strong>
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
                        <h2>Capture receipt</h2>
                    </div>
                    <small>{{ $canCreateReceipt ? 'Authorized' : 'Read only' }}</small>
                </div>

                @if ($canCreateReceipt)
                    <form method="POST" action="{{ route('finance.collections.store') }}" class="blade-form-grid">
                        @csrf

                        <label class="blade-form-wide">
                            Booking
                            <select name="booking_id" required>
                                <option value="">Select booking</option>
                                @foreach ($bookings as $booking)
                                    <option value="{{ $booking->id }}" @selected((string) old('booking_id') === (string) $booking->id)>
                                        {{ $booking->booking_code }} - {{ $booking->customer?->name ?? 'Customer missing' }} - {{ $booking->project?->code ?? 'No project' }} - {{ $booking->unit?->unit_code ?? 'No unit' }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label class="blade-form-wide">
                            Payment schedule
                            <select name="booking_payment_schedule_id">
                                <option value="">Unallocated receipt</option>
                                @foreach ($bookings as $booking)
                                    @foreach ($booking->paymentSchedules as $schedule)
                                        <option value="{{ $schedule->id }}" @selected((string) old('booking_payment_schedule_id') === (string) $schedule->id)>
                                            {{ $booking->booking_code }} - {{ $schedule->sequence }}. {{ $schedule->milestone }} - {{ $money($schedule->amount) }} - {{ str($schedule->status)->headline() }}
                                        </option>
                                    @endforeach
                                @endforeach
                            </select>
                        </label>

                        <label>
                            Receipt date
                            <input type="date" name="receipt_date" value="{{ old('receipt_date', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required>
                        </label>

                        <label>
                            Payment mode
                            <select name="payment_mode" required>
                                @foreach ($paymentModes as $value => $label)
                                    <option value="{{ $value }}" @selected(old('payment_mode', 'neft') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label>
                            Instrument / reference no.
                            <input type="text" name="instrument_number" value="{{ old('instrument_number') }}" maxlength="120" placeholder="Required except cash">
                        </label>

                        <label>
                            Bank name
                            <input type="text" name="bank_name" value="{{ old('bank_name') }}" maxlength="255">
                        </label>

                        <label>
                            Amount
                            <input type="number" name="amount" value="{{ old('amount') }}" min="1" step="0.01" required>
                        </label>

                        <label>
                            TDS / tax deducted
                            <input type="number" name="tax_deducted_amount" value="{{ old('tax_deducted_amount', 0) }}" min="0" step="0.01">
                        </label>

                        <label class="blade-form-wide">
                            Notes
                            <textarea name="notes" maxlength="2000" rows="3" placeholder="Bank statement reference, collection remarks or approval context.">{{ old('notes') }}</textarea>
                        </label>

                        <button type="submit" class="blade-primary-action">Submit receipt</button>
                    </form>
                @else
                    <p class="blade-workspace-note">Your role can view collections but cannot capture receipts.</p>
                @endif
            </article>

            <article class="blade-dashboard-card">
                <div class="blade-dashboard-section-title">
                    <div>
                        <span class="blade-dashboard-label">Milestones</span>
                        <h2>Open booking schedules</h2>
                    </div>
                    <small>{{ $bookings->count() }} active booking(s)</small>
                </div>

                <div class="blade-dashboard-table-wrap">
                    <table class="blade-dashboard-table">
                        <thead>
                            <tr>
                                <th scope="col">Booking</th>
                                <th scope="col">Customer</th>
                                <th scope="col">Net receivable</th>
                                <th scope="col">Schedule</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($bookings as $booking)
                                <tr>
                                    <td>
                                        <strong>{{ $booking->booking_code }}</strong>
                                        <span>{{ $booking->project?->code ?? 'No project' }} / {{ $booking->unit?->unit_code ?? 'No unit' }}</span>
                                    </td>
                                    <td>{{ $booking->customer?->name ?? 'Customer missing' }}</td>
                                    <td>{{ $money($booking->net_receivable) }}</td>
                                    <td>
                                        @forelse ($booking->paymentSchedules as $schedule)
                                            <span>{{ $schedule->sequence }}. {{ $schedule->milestone }} - {{ $money($schedule->amount) }} - {{ str($schedule->status)->headline() }}</span>
                                        @empty
                                            <span>No payment schedule</span>
                                        @endforelse
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4">No active confirmed bookings are available for collection capture.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </article>
        </section>

        <section class="blade-dashboard-card">
            <div class="blade-dashboard-section-title">
                <div>
                    <span class="blade-dashboard-label">Controls</span>
                    <h2>Collection filters</h2>
                </div>
                <small>{{ $receipts->total() }} record(s)</small>
            </div>

            <form method="GET" action="{{ route('finance.collections.index') }}" class="blade-filter-grid blade-filter-grid-compact">
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

                <label>
                    Booking
                    <select name="booking_id">
                        <option value="">All bookings</option>
                        @foreach ($bookings as $booking)
                            <option value="{{ $booking->id }}" @selected((string) ($filters['booking_id'] ?? '') === (string) $booking->id)>
                                {{ $booking->booking_code }}
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
                    Payment mode
                    <select name="payment_mode">
                        <option value="">All modes</option>
                        @foreach ($paymentModes as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['payment_mode'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label>
                    From
                    <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
                </label>

                <label>
                    To
                    <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
                </label>

                <button type="submit" class="blade-secondary-action">Apply filters</button>
            </form>
        </section>

        <section class="blade-dashboard-card">
            <div class="blade-dashboard-section-title">
                <div>
                    <span class="blade-dashboard-label">Register</span>
                    <h2>Collection receipt register</h2>
                </div>
                <small>{{ $receipts->firstItem() ?? 0 }}-{{ $receipts->lastItem() ?? 0 }} of {{ $receipts->total() }}</small>
            </div>

            <div class="blade-dashboard-table-wrap">
                <table class="blade-dashboard-table">
                    <thead>
                        <tr>
                            <th scope="col">Receipt</th>
                            <th scope="col">Booking / customer</th>
                            <th scope="col">Mode</th>
                            <th scope="col">Amount</th>
                            <th scope="col">Collected / approved</th>
                            <th scope="col">Status</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($receipts as $receipt)
                            <tr>
                                <td>
                                    <strong>{{ $receipt->receipt_number }}</strong>
                                    <span>{{ $receipt->receipt_date?->format('d M Y') ?? 'Date pending' }}</span>
                                </td>
                                <td>
                                    <strong>{{ $receipt->booking?->booking_code ?? 'Booking missing' }}</strong>
                                    <span>{{ $receipt->customer?->name ?? 'Customer missing' }}</span>
                                    <span>{{ $receipt->paymentSchedule?->milestone ?? 'Unallocated' }}</span>
                                </td>
                                <td>
                                    <strong>{{ $paymentModes[$receipt->payment_mode] ?? str($receipt->payment_mode)->headline() }}</strong>
                                    <span>{{ $receipt->instrument_number ?? 'No instrument' }}</span>
                                    <span>{{ $receipt->bank_name ?? 'No bank' }}</span>
                                </td>
                                <td>
                                    <strong>{{ $money($receipt->amount) }}</strong>
                                    <span>TDS: {{ $money($receipt->tax_deducted_amount) }}</span>
                                </td>
                                <td>
                                    <strong>{{ $receipt->collectedBy?->name ?? 'Collector missing' }}</strong>
                                    <span>{{ $receipt->approvedBy?->name ?? 'Approval pending' }}</span>
                                </td>
                                <td>{{ $statuses[$receipt->status] ?? str($receipt->status)->headline() }}</td>
                                <td>
                                    @can('approve', $receipt)
                                        <details class="blade-row-actions">
                                            <summary>Approve</summary>
                                            <form method="POST" action="{{ route('finance.collections.approve', $receipt) }}" class="blade-inline-form">
                                                @csrf
                                                @method('PATCH')
                                                <textarea name="note" maxlength="1000" rows="2" placeholder="Approval note"></textarea>
                                                <button type="submit" class="blade-primary-action">Approve receipt</button>
                                            </form>
                                        </details>
                                    @else
                                        <span>No action</span>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">No collection receipts match the selected filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="blade-pagination">
                {{ $receipts->links() }}
            </div>
        </section>
    </div>
@endsection
