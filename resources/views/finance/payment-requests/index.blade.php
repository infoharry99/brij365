@extends('layouts.builder360-classic')

@section('title', 'Payment Requests - Builder360 ERP-CRM')

@section('content')
@php
        $money = fn ($amount) => 'Rs. '.number_format((float) ($amount ?? 0), 2);
    @endphp

    <div class="blade-workspace" aria-labelledby="payment-requests-title">
        <header class="blade-workspace-header">
            <div>
                <p class="blade-dashboard-eyebrow">Finance and Customer Collections</p>
                <h1 id="payment-requests-title">Buyer Payment Requests</h1>
                <p>
                    Workspace for creating buyer payment links from active bookings,
                    tracking simulated/configured gateway status, cancelling requested links and reconciling paid receipts.
                </p>
            </div>
            <nav class="blade-workspace-actions" aria-label="Workspace actions">
                <a href="{{ route('builder360.dashboard') }}">Dashboard</a>
                <a href="{{ route('finance.dashboard') }}">Finance Dashboard</a>
                <a href="{{ route('finance.collections.index') }}">Collections</a>
                <a href="{{ route('sales.bookings.index') }}">Bookings</a>
                <a href="{{ route('finance.payment-requests.index') }}">Reset filters</a>
            </nav>
        </header>

        @if (session('status'))
            <section class="blade-alert blade-alert-success" role="status">{{ session('status') }}</section>
        @endif

        @if ($errors->any())
            <section class="blade-alert blade-alert-error" role="alert">
                <strong>Payment request action was not saved.</strong>
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
                        <h2>Create buyer payment link</h2>
                    </div>
                    <small>{{ $canCreatePaymentRequest ? 'Authorized' : 'Read only' }}</small>
                </div>

                @if ($canCreatePaymentRequest)
                    <form method="POST" action="{{ route('finance.payment-requests.store') }}" class="blade-form-grid">
                        @csrf

                        <label class="blade-form-wide">
                            Booking
                            <select name="booking_id" required>
                                <option value="">Select active booking</option>
                                @foreach ($bookings as $booking)
                                    <option value="{{ $booking->id }}" @selected((string) old('booking_id') === (string) $booking->id)>
                                        {{ $booking->booking_code }} - {{ $booking->customer?->name ?? 'Customer missing' }} - {{ $booking->project?->code ?? 'No project' }} - {{ $money($booking->net_receivable) }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label class="blade-form-wide">
                            Payment schedule
                            <select name="booking_payment_schedule_id">
                                <option value="">Booking-level request</option>
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
                            Amount
                            <input type="number" name="amount" value="{{ old('amount') }}" min="1" step="0.01" required>
                        </label>

                        <label>
                            Expiry date/time
                            <input type="datetime-local" name="expires_at" value="{{ old('expires_at') }}">
                        </label>

                        <label class="blade-form-wide">
                            Purpose
                            <input type="text" name="purpose" value="{{ old('purpose') }}" maxlength="160" required placeholder="Slab completion milestone payment link">
                        </label>

                        <button type="submit" class="blade-primary-action">Create payment request</button>
                    </form>
                @else
                    <p class="blade-workspace-note">Your role can view payment requests but cannot create buyer payment links.</p>
                @endif
            </article>

            <article class="blade-dashboard-card">
                <div class="blade-dashboard-section-title">
                    <div>
                        <span class="blade-dashboard-label">Controls</span>
                        <h2>Payment request filters</h2>
                    </div>
                    <small>{{ $paymentRequests->total() }} record(s)</small>
                </div>

                <form method="GET" action="{{ route('finance.payment-requests.index') }}" class="blade-filter-grid blade-filter-grid-compact">
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
                        Project
                        <select name="project_id">
                            <option value="">All projects</option>
                            @foreach ($projects as $project)
                                <option value="{{ $project->id }}" @selected((string) ($filters['project_id'] ?? '') === (string) $project->id)>{{ $project->code }}</option>
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
                                <option value="{{ $booking->id }}" @selected((string) ($filters['booking_id'] ?? '') === (string) $booking->id)>{{ $booking->booking_code }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label>
                        Search
                        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="120" placeholder="Request, gateway ref, purpose">
                    </label>

                    <button type="submit" class="blade-secondary-action">Apply filters</button>
                </form>

                <p class="blade-workspace-note">
                    Gateway provider is read from system configuration. Browser users cannot override it from the form.
                </p>
            </article>
        </section>

        <section class="blade-dashboard-card">
            <div class="blade-dashboard-section-title">
                <div>
                    <span class="blade-dashboard-label">Register</span>
                    <h2>Payment request register</h2>
                </div>
                <small>{{ $paymentRequests->firstItem() ?? 0 }}-{{ $paymentRequests->lastItem() ?? 0 }} of {{ $paymentRequests->total() }}</small>
            </div>

            <div class="blade-dashboard-table-wrap">
                <table class="blade-dashboard-table">
                    <thead>
                        <tr>
                            <th scope="col">Request</th>
                            <th scope="col">Booking / customer</th>
                            <th scope="col">Gateway</th>
                            <th scope="col">Amount</th>
                            <th scope="col">Timeline</th>
                            <th scope="col">Status</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($paymentRequests as $paymentRequest)
                            <tr>
                                <td>
                                    <strong>{{ $paymentRequest->request_number }}</strong>
                                    <span>{{ $paymentRequest->purpose }}</span>
                                    <span>{{ $paymentRequest->paymentSchedule?->milestone ?? 'Booking-level request' }}</span>
                                </td>
                                <td>
                                    <strong>{{ $paymentRequest->booking?->booking_code ?? 'Booking missing' }}</strong>
                                    <span>{{ $paymentRequest->customer?->name ?? 'Customer missing' }}</span>
                                    <span>{{ $paymentRequest->project?->code ?? 'No project' }}</span>
                                </td>
                                <td>
                                    <strong>{{ str($paymentRequest->gateway_provider)->headline() }}</strong>
                                    <span>{{ $paymentRequest->gateway_reference }}</span>
                                    <span>{{ $paymentRequest->gateway_payload['simulation_notice'] ?? 'Configured gateway mode' }}</span>
                                </td>
                                <td>
                                    <strong>{{ $money($paymentRequest->amount) }}</strong>
                                    <span>{{ $paymentRequest->currency }}</span>
                                    <span>Receipt {{ $paymentRequest->collectionReceipt?->receipt_number ?? 'Pending' }}</span>
                                </td>
                                <td>
                                    <strong>Created by {{ $paymentRequest->createdBy?->name ?? 'User missing' }}</strong>
                                    <span>Expires {{ $paymentRequest->expires_at?->format('d M Y H:i') ?? 'Default expiry' }}</span>
                                    <span>Paid {{ $paymentRequest->paid_at?->format('d M Y H:i') ?? 'Pending' }}</span>
                                </td>
                                <td>{{ $statuses[$paymentRequest->status] ?? str($paymentRequest->status)->headline() }}</td>
                                <td>
                                    @can('cancel', $paymentRequest)
                                        <details class="blade-row-actions">
                                            <summary>Cancel</summary>
                                            <form method="POST" action="{{ route('finance.payment-requests.cancel', $paymentRequest) }}" class="blade-inline-form">
                                                @csrf
                                                @method('PATCH')
                                                <textarea name="reason" required maxlength="500" rows="2" placeholder="Cancellation reason"></textarea>
                                                <button type="submit" class="blade-secondary-action">Cancel request</button>
                                            </form>
                                        </details>
                                    @else
                                        <span>No action</span>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">No payment requests match the selected filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="blade-pagination">{{ $paymentRequests->links() }}</div>
        </section>
    </div>
@endsection
