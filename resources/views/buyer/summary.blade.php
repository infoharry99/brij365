@extends('layouts.builder360-classic')

@section('title', 'Buyer Portal - Builder360 ERP-CRM')

@section('content')
@php
        $customer = $summary['customer'] ?? null;
        $bookings = collect($summary['recent_bookings'] ?? []);
        $paymentSchedule = collect($summary['payment_schedule'] ?? []);
        $receipts = collect($summary['recent_receipts'] ?? []);
        $documents = collect($summary['documents'] ?? []);
        $tickets = collect($summary['service_tickets'] ?? []);
        $nextDue = $summary['next_due'] ?? null;
    @endphp

    <div class="blade-workspace" aria-labelledby="buyer-portal-title">
        <header class="blade-workspace-header">
            <div>
                <p class="blade-dashboard-eyebrow">Customer Channel</p>
                <h1 id="buyer-portal-title">Buyer Portal</h1>
                <p>
                    Secure buyer workspace for booking visibility, payment schedule,
                    approved receipts, approved documents, service tickets and customer self-service actions.
                </p>
            </div>
            @include('buyer.partials.navigation')
        </header>

        @if (session('status'))
            <div class="blade-alert blade-alert-success">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="blade-alert blade-alert-danger">
                <strong>Check the highlighted inputs.</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @unless ($customer)
            <section class="blade-dashboard-card">
                <h2>No linked customer profile</h2>
                <p class="blade-muted">This buyer login does not have a customer record linked to it.</p>
            </section>
        @else
            <section class="blade-dashboard-kpis" aria-label="Buyer portal KPIs">
                <article class="blade-dashboard-kpi">
                    <span>Bookings</span>
                    <strong>{{ number_format((int) ($summary['bookings_count'] ?? 0)) }}</strong>
                    <small>{{ $customer['code'] ?? 'Customer' }}</small>
                </article>
                <article class="blade-dashboard-kpi">
                    <span>Outstanding</span>
                    <strong>₹{{ number_format((float) ($summary['outstanding_amount'] ?? 0), 2) }}</strong>
                    <small>Scheduled less approved receipts</small>
                </article>
                <article class="blade-dashboard-kpi">
                    <span>Paid Receipts</span>
                    <strong>₹{{ number_format((float) ($summary['approved_receipts_total'] ?? 0), 2) }}</strong>
                    <small>Approved collections</small>
                </article>
                <article class="blade-dashboard-kpi">
                    <span>Open Tickets</span>
                    <strong>{{ number_format((int) ($summary['open_tickets_count'] ?? 0)) }}</strong>
                    <small>Active complaints</small>
                </article>
            </section>

            <section class="blade-workspace-grid">
                <article class="blade-dashboard-card">
                    <div class="blade-dashboard-section-title">
                        <div>
                            <span class="blade-dashboard-label">Profile</span>
                            <h2>{{ $customer['name'] }}</h2>
                        </div>
                        <small>{{ $customer['status'] }}</small>
                    </div>
                    <dl class="blade-definition-list">
                        <div>
                            <dt>Customer Code</dt>
                            <dd>{{ $customer['code'] }}</dd>
                        </div>
                        <div>
                            <dt>Email</dt>
                            <dd>{{ $customer['email'] }}</dd>
                        </div>
                        <div>
                            <dt>Phone</dt>
                            <dd>{{ $customer['phone'] }}</dd>
                        </div>
                        <div>
                            <dt>Next Due</dt>
                            <dd>
                                @if ($nextDue)
                                    {{ $nextDue['booking_code'] }} · {{ $nextDue['milestone'] }} ·
                                    ₹{{ number_format((float) $nextDue['amount'], 2) }} due {{ $nextDue['due_on'] }}
                                @else
                                    No pending milestone due found.
                                @endif
                            </dd>
                        </div>
                    </dl>
                </article>

                <article class="blade-dashboard-card">
                    <div class="blade-dashboard-section-title">
                        <div>
                            <span class="blade-dashboard-label">Self Service</span>
                            <h2>Raise service ticket</h2>
                        </div>
                        <small>Buyer access</small>
                    </div>
                    <form method="POST" action="{{ route('buyer.service-tickets.store') }}" class="blade-form-grid">
                        @csrf
                        <label class="blade-form-wide">
                            Booking / unit
                            <select name="booking_id" required>
                                <option value="">Select your booking</option>
                                @foreach ($bookings as $booking)
                                    <option value="{{ $booking['id'] }}" @selected((int) old('booking_id') === (int) $booking['id'])>
                                        {{ $booking['booking_code'] }}
                                        @if ($booking['unit'] ?? null)
                                            · {{ $booking['unit']['unit_code'] ?? 'Unit' }}
                                        @endif
                                        @if ($booking['project'] ?? null)
                                            · {{ $booking['project']['name'] ?? 'Project' }}
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label>
                            Category
                            <select name="category" required>
                                @foreach ($categories as $value => $label)
                                    <option value="{{ $value }}" @selected(old('category', 'maintenance') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label>
                            Priority
                            <select name="priority" required>
                                @foreach ($priorities as $value => $label)
                                    <option value="{{ $value }}" @selected(old('priority', 'medium') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="blade-form-wide">
                            Subject
                            <input type="text" name="subject" value="{{ old('subject') }}" maxlength="255" required>
                        </label>

                        <label class="blade-form-wide">
                            Description
                            <textarea name="description" rows="4" required>{{ old('description') }}</textarea>
                        </label>

                        <button type="submit" class="blade-primary-action">Submit service ticket</button>
                    </form>
                </article>
            </section>

            <section class="blade-dashboard-card">
                <div class="blade-dashboard-section-title">
                    <div>
                        <span class="blade-dashboard-label">Bookings</span>
                        <h2>Recent booking and unit details</h2>
                    </div>
                    <small>{{ $bookings->count() }} shown</small>
                </div>
                <div class="blade-dashboard-table-wrap">
                    <table class="blade-dashboard-table">
                        <thead>
                            <tr>
                                <th scope="col">Booking</th>
                                <th scope="col">Project</th>
                                <th scope="col">Unit</th>
                                <th scope="col">Value</th>
                                <th scope="col">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($bookings as $booking)
                                <tr>
                                    <td>
                                        <strong>{{ $booking['booking_code'] }}</strong>
                                        <span>{{ $booking['booked_on'] ?? '—' }}</span>
                                    </td>
                                    <td>
                                        <strong>{{ $booking['project']['name'] ?? '—' }}</strong>
                                        <span>{{ $booking['project']['city'] ?? '—' }}</span>
                                    </td>
                                    <td>
                                        <strong>{{ $booking['unit']['unit_code'] ?? '—' }}</strong>
                                        <span>{{ $booking['unit']['unit_type'] ?? '—' }}</span>
                                    </td>
                                    <td>₹{{ number_format((float) ($booking['net_receivable'] ?? 0), 2) }}</td>
                                    <td><span class="blade-status-pill">{{ $booking['status'] }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="5">No bookings found for this buyer.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="blade-workspace-grid">
                <article class="blade-dashboard-card">
                    <div class="blade-dashboard-section-title">
                        <div>
                            <span class="blade-dashboard-label">Payments</span>
                            <h2>Payment schedule</h2>
                        </div>
                        <small>{{ $paymentSchedule->count() }} milestone(s)</small>
                    </div>
                    <div class="blade-dashboard-table-wrap">
                        <table class="blade-dashboard-table">
                            <thead>
                                <tr>
                                    <th scope="col">Milestone</th>
                                    <th scope="col">Due</th>
                                    <th scope="col">Amount</th>
                                    <th scope="col">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($paymentSchedule as $schedule)
                                    <tr>
                                        <td>
                                            <strong>{{ $schedule['booking_code'] }}</strong>
                                            <span>{{ $schedule['milestone'] }}</span>
                                        </td>
                                        <td>{{ $schedule['due_on'] ?? '—' }}</td>
                                        <td>₹{{ number_format((float) $schedule['amount'], 2) }}</td>
                                        <td><span class="blade-status-pill">{{ $schedule['status'] }}</span></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4">No payment milestones found.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </article>

                <article class="blade-dashboard-card">
                    <div class="blade-dashboard-section-title">
                        <div>
                            <span class="blade-dashboard-label">Receipts</span>
                            <h2>Approved receipts</h2>
                        </div>
                        <small>{{ $receipts->count() }} shown</small>
                    </div>
                    <div class="blade-dashboard-table-wrap">
                        <table class="blade-dashboard-table">
                            <thead>
                                <tr>
                                    <th scope="col">Receipt</th>
                                    <th scope="col">Booking</th>
                                    <th scope="col">Date</th>
                                    <th scope="col">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($receipts as $receipt)
                                    <tr>
                                        <td>
                                            <strong>{{ $receipt['receipt_number'] }}</strong>
                                            <span>{{ $receipt['payment_mode'] }}</span>
                                        </td>
                                        <td>{{ $receipt['booking_code'] ?? '—' }}</td>
                                        <td>{{ $receipt['receipt_date'] ?? '—' }}</td>
                                        <td>₹{{ number_format((float) $receipt['amount'], 2) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4">No approved receipts found.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </article>
            </section>

            <section class="blade-workspace-grid">
                <article class="blade-dashboard-card">
                    <div class="blade-dashboard-section-title">
                        <div>
                            <span class="blade-dashboard-label">Documents</span>
                            <h2>Approved documents</h2>
                        </div>
                        <small>{{ number_format((int) ($summary['documents_count'] ?? 0)) }} available</small>
                    </div>
                    <div class="blade-list">
                        @forelse ($documents as $document)
                            <div class="blade-list-row">
                                <div>
                                    <strong>{{ $document['document_number'] }} · {{ $document['title'] }}</strong>
                                    <span>{{ $document['category'] ?? 'Document' }} · v{{ $document['version'] }}</span>
                                </div>
                                <a href="{{ $document['download_url'] }}">Download</a>
                            </div>
                        @empty
                            <p class="blade-muted">No approved customer or booking documents found.</p>
                        @endforelse
                    </div>
                </article>
            </section>

            <section class="blade-dashboard-card">
                <div class="blade-dashboard-section-title">
                    <div>
                        <span class="blade-dashboard-label">Support</span>
                        <h2>Service ticket status</h2>
                    </div>
                    <small>{{ $tickets->count() }} shown</small>
                </div>
                <div class="blade-dashboard-table-wrap">
                    <table class="blade-dashboard-table">
                        <thead>
                            <tr>
                                <th scope="col">Ticket</th>
                                <th scope="col">Category</th>
                                <th scope="col">Priority</th>
                                <th scope="col">SLA</th>
                                <th scope="col">Status</th>
                                <th scope="col">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($tickets as $ticket)
                                <tr>
                                    <td>
                                        <strong>{{ $ticket['ticket_number'] }}</strong>
                                        <span>{{ $ticket['subject'] }}</span>
                                    </td>
                                    <td>{{ $categories[$ticket['category']] ?? $ticket['category'] }}</td>
                                    <td>{{ $priorities[$ticket['priority']] ?? $ticket['priority'] }}</td>
                                    <td>{{ $ticket['sla_due_at'] ?? '—' }}</td>
                                    <td><span class="blade-status-pill">{{ $ticketStatuses[$ticket['status']] ?? $ticket['status'] }}</span></td>
                                    <td>
                                        @if ($ticket['status'] === 'resolved')
                                            <form method="POST" action="{{ route('buyer.service-tickets.close', $ticket['id']) }}" class="blade-inline-form">
                                                @csrf
                                                @method('PATCH')
                                                <input type="number" name="customer_rating" min="1" max="5" placeholder="Rating" required>
                                                <input type="text" name="note" placeholder="Closure note">
                                                <button type="submit">Close</button>
                                            </form>
                                        @else
                                            <span class="blade-muted">No action</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6">No service tickets found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endunless
    </div>
@endsection
