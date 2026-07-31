@extends('layouts.builder360-classic')

@section('title', 'Maintenance Dues - Builder360 ERP-CRM')

@section('content')
@php
        $money = fn ($amount) => 'Rs. '.number_format((float) ($amount ?? 0), 2);
    @endphp

    <div class="blade-workspace" aria-labelledby="maintenance-dues-title">
        <header class="blade-workspace-header">
            <div>
                <p class="blade-dashboard-eyebrow">Maintenance Billing</p>
                <h1 id="maintenance-dues-title">Maintenance Dues</h1>
                <p>
                    Workspace for raising unit-wise maintenance dues, buyer reminders,
                    part/full payment recording, balance tracking and collection activity history.
                </p>
            </div>
            <nav class="blade-workspace-actions" aria-label="Workspace actions">
                <a href="{{ route('builder360.dashboard') }}">Dashboard</a>
                <a href="{{ route('maintenance.societies.index') }}">Societies</a>
                <a href="{{ route('maintenance.handover-items.index') }}">Common Area Handover</a>
                <a href="{{ route('finance.collections.index') }}">Collections</a>
                <a href="{{ route('maintenance.dues.index') }}">Reset filters</a>
            </nav>
        </header>

        @if (session('status'))
            <section class="blade-alert blade-alert-success" role="status">{{ session('status') }}</section>
        @endif

        @if ($errors->any())
            <section class="blade-alert blade-alert-error" role="alert">
                <strong>Maintenance due action was not saved.</strong>
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
                        <h2>Raise maintenance due</h2>
                    </div>
                    <small>{{ $canCreateDue ? 'Authorized' : 'Read only' }}</small>
                </div>

                @if ($canCreateDue)
                    <form method="POST" action="{{ route('maintenance.dues.store') }}" class="blade-form-grid">
                        @csrf

                        <label class="blade-form-wide">
                            Booking / unit
                            <select name="booking_id" required>
                                <option value="">Select active booking</option>
                                @foreach ($bookings as $booking)
                                    <option value="{{ $booking->id }}" @selected((string) old('booking_id') === (string) $booking->id)>
                                        {{ $booking->booking_code }} - {{ $booking->customer?->name ?? 'Customer missing' }} - {{ $booking->project?->code ?? 'No project' }} - {{ $booking->unit?->unit_code ?? 'No unit' }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label>
                            Period start
                            <input type="date" name="period_start_on" value="{{ old('period_start_on', now()->startOfMonth()->toDateString()) }}" required>
                        </label>

                        <label>
                            Period end
                            <input type="date" name="period_end_on" value="{{ old('period_end_on', now()->endOfMonth()->toDateString()) }}" required>
                        </label>

                        <label>
                            Due on
                            <input type="date" name="due_on" value="{{ old('due_on', now()->addDays(15)->toDateString()) }}" required>
                        </label>

                        <label>
                            Amount
                            <input type="number" name="amount" value="{{ old('amount') }}" min="1" step="0.01" required>
                        </label>

                        <button type="submit" class="blade-primary-action">Raise due</button>
                    </form>
                @else
                    <p class="blade-workspace-note">Your role can view maintenance dues but cannot raise new dues.</p>
                @endif
            </article>

            <article class="blade-dashboard-card">
                <div class="blade-dashboard-section-title">
                    <div>
                        <span class="blade-dashboard-label">Controls</span>
                        <h2>Due filters</h2>
                    </div>
                    <small>{{ $dues->total() }} record(s)</small>
                </div>

                <form method="GET" action="{{ route('maintenance.dues.index') }}" class="blade-filter-grid blade-filter-grid-compact">
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
                        Status
                        <select name="status">
                            <option value="">All statuses</option>
                            @foreach ($statuses as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button type="submit" class="blade-secondary-action">Apply filters</button>
                </form>
            </article>
        </section>

        <section class="blade-dashboard-card">
            <div class="blade-dashboard-section-title">
                <div>
                    <span class="blade-dashboard-label">Register</span>
                    <h2>Maintenance due register</h2>
                </div>
                <small>{{ $dues->firstItem() ?? 0 }}-{{ $dues->lastItem() ?? 0 }} of {{ $dues->total() }}</small>
            </div>

            <div class="blade-dashboard-table-wrap">
                <table class="blade-dashboard-table">
                    <thead>
                        <tr>
                            <th scope="col">Due</th>
                            <th scope="col">Booking / customer</th>
                            <th scope="col">Period</th>
                            <th scope="col">Amount</th>
                            <th scope="col">Workflow</th>
                            <th scope="col">Status</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($dues as $due)
                            <tr>
                                <td>
                                    <strong>{{ $due->due_number }}</strong>
                                    <span>{{ $due->project?->code ?? 'No project' }}</span>
                                    <span>{{ $due->unit?->unit_code ?? 'No unit' }}</span>
                                </td>
                                <td>
                                    <strong>{{ $due->booking?->booking_code ?? 'Booking missing' }}</strong>
                                    <span>{{ $due->customer?->name ?? 'Customer missing' }}</span>
                                </td>
                                <td>
                                    <strong>{{ $due->period_start_on?->format('d M Y') }} to {{ $due->period_end_on?->format('d M Y') }}</strong>
                                    <span>Due {{ $due->due_on?->format('d M Y') ?? 'Due date missing' }}</span>
                                    <span>Reminder {{ $due->last_reminded_at?->format('d M Y H:i') ?? 'Not sent' }}</span>
                                </td>
                                <td>
                                    <strong>{{ $money($due->amount) }}</strong>
                                    <span>Paid {{ $money($due->paid_amount) }}</span>
                                    <span>Balance {{ $money($due->balance_amount) }}</span>
                                    <span>{{ $due->payment_reference ?? 'Payment reference pending' }}</span>
                                </td>
                                <td>
                                    <strong>Raised by {{ $due->raisedBy?->name ?? 'User missing' }}</strong>
                                    <span>Paid by {{ $due->paidBy?->name ?? 'Pending' }}</span>
                                    <span>{{ $due->paid_at?->format('d M Y H:i') ?? 'Payment pending' }}</span>
                                </td>
                                <td>{{ $statuses[$due->status] ?? str($due->status)->headline() }}</td>
                                <td>
                                    @can('remind', $due)
                                        <details class="blade-row-actions">
                                            <summary>Remind</summary>
                                            <form method="POST" action="{{ route('maintenance.dues.remind', $due) }}" class="blade-inline-form">
                                                @csrf
                                                @method('PATCH')
                                                <textarea name="note" maxlength="1000" rows="2" placeholder="Reminder note"></textarea>
                                                <button type="submit" class="blade-secondary-action">Record reminder</button>
                                            </form>
                                        </details>
                                    @endcan

                                    @can('markPaid', $due)
                                        <details class="blade-row-actions">
                                            <summary>Mark paid</summary>
                                            <form method="POST" action="{{ route('maintenance.dues.mark-paid', $due) }}" class="blade-inline-form">
                                                @csrf
                                                @method('PATCH')
                                                <input type="number" name="paid_amount" value="{{ $due->balance_amount }}" min="0.01" max="{{ $due->balance_amount }}" step="0.01" required>
                                                <input type="text" name="payment_reference" required maxlength="120" placeholder="Payment reference / UTR">
                                                <input type="date" name="paid_at" max="{{ now()->toDateString() }}">
                                                <textarea name="note" maxlength="1000" rows="2" placeholder="Payment note"></textarea>
                                                <button type="submit" class="blade-primary-action">Record payment</button>
                                            </form>
                                        </details>
                                    @endcan

                                    @cannot('remind', $due)
                                        @cannot('markPaid', $due)
                                            <span>No action</span>
                                        @endcannot
                                    @endcannot
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">No maintenance dues match the selected filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="blade-pagination">{{ $dues->links() }}</div>
        </section>
    </div>
@endsection
