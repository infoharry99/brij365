@extends('layouts.builder360-classic')

@section('title', 'GST Return Periods - Builder360 ERP-CRM')

@section('content')
@php
        $money = fn ($amount) => 'Rs. '.number_format((float) ($amount ?? 0), 2);
    @endphp

    <div class="blade-workspace" aria-labelledby="gst-return-periods-title">
        <header class="blade-workspace-header">
            <div>
                <p class="blade-dashboard-eyebrow">Finance and Compliance</p>
                <h1 id="gst-return-periods-title">GST Return Periods</h1>
                <p>
                    Workspace for preparing GST monthly return periods from approved entries,
                    approving them through maker-checker workflow and locking periods after filing review.
                </p>
            </div>
            <nav class="blade-workspace-actions" aria-label="Workspace actions">
                <a href="{{ route('builder360.dashboard') }}">Dashboard</a>
                <a href="{{ route('finance.dashboard') }}">Finance Dashboard</a>
                <a href="{{ route('finance.gst-entries.index') }}">GST Entries</a>
                <a href="{{ route('finance.gst-return-periods.index') }}">Reset filters</a>
            </nav>
        </header>

        @if (session('status'))
            <section class="blade-alert blade-alert-success" role="status">{{ session('status') }}</section>
        @endif

        @if ($errors->any())
            <section class="blade-alert blade-alert-error" role="alert">
                <strong>GST return action was not saved.</strong>
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
                        <span class="blade-dashboard-label">Prepare</span>
                        <h2>Create return period</h2>
                    </div>
                    <small>{{ $canPreparePeriod ? 'Authorized' : 'Read only' }}</small>
                </div>

                @if ($canPreparePeriod)
                    <form method="POST" action="{{ route('finance.gst-return-periods.store') }}" class="blade-form-grid">
                        @csrf

                        <label>
                            Period year
                            <input type="number" name="period_year" value="{{ old('period_year', now()->year) }}" min="2020" max="2100" required>
                        </label>

                        <label>
                            Period month
                            <input type="number" name="period_month" value="{{ old('period_month', now()->month) }}" min="1" max="12" required>
                        </label>

                        <label class="blade-form-wide">
                            Preparation note
                            <textarea name="note" maxlength="500" rows="3" placeholder="Return preparation context, reconciliation note or filing reference.">{{ old('note') }}</textarea>
                        </label>

                        <button type="submit" class="blade-primary-action">Prepare GST return</button>
                    </form>
                @else
                    <p class="blade-workspace-note">Your role can view GST return periods but cannot prepare new periods.</p>
                @endif
            </article>

            <article class="blade-dashboard-card">
                <div class="blade-dashboard-section-title">
                    <div>
                        <span class="blade-dashboard-label">Controls</span>
                        <h2>Return period filters</h2>
                    </div>
                    <small>{{ $periods->total() }} record(s)</small>
                </div>

                <form method="GET" action="{{ route('finance.gst-return-periods.index') }}" class="blade-filter-grid blade-filter-grid-compact">
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
                        Year
                        <input type="number" name="period_year" value="{{ $filters['period_year'] ?? '' }}" min="2020" max="2100">
                    </label>
                    <label>
                        Month
                        <input type="number" name="period_month" value="{{ $filters['period_month'] ?? '' }}" min="1" max="12">
                    </label>
                    <button type="submit" class="blade-secondary-action">Apply filters</button>
                </form>

                <p class="blade-workspace-note">
                    Preparing a return requires approved GST entries for the selected month.
                    Locking a period also locks approved entries for that month.
                </p>
            </article>
        </section>

        <section class="blade-dashboard-card">
            <div class="blade-dashboard-section-title">
                <div>
                    <span class="blade-dashboard-label">Register</span>
                    <h2>GST return period register</h2>
                </div>
                <small>{{ $periods->firstItem() ?? 0 }}-{{ $periods->lastItem() ?? 0 }} of {{ $periods->total() }}</small>
            </div>

            <div class="blade-dashboard-table-wrap">
                <table class="blade-dashboard-table">
                    <thead>
                        <tr>
                            <th scope="col">Return</th>
                            <th scope="col">Period</th>
                            <th scope="col">Entries</th>
                            <th scope="col">Tax summary</th>
                            <th scope="col">Workflow</th>
                            <th scope="col">Status</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($periods as $period)
                            <tr>
                                <td>
                                    <strong>{{ $period->return_number }}</strong>
                                    <span>{{ $period->period_start?->format('d M Y') }} to {{ $period->period_end?->format('d M Y') }}</span>
                                </td>
                                <td>
                                    <strong>{{ sprintf('%04d-%02d', $period->period_year, $period->period_month) }}</strong>
                                    <span>Company {{ $period->company?->code ?? $period->company_id }}</span>
                                </td>
                                <td>
                                    <strong>{{ $period->entry_count }} approved entry(s)</strong>
                                    <span>Output {{ $period->summary['output_entry_count'] ?? 0 }}</span>
                                    <span>Input {{ $period->summary['input_entry_count'] ?? 0 }}</span>
                                </td>
                                <td>
                                    <strong>Payable {{ $money($period->net_tax_payable) }}</strong>
                                    <span>Output {{ $money($period->output_tax_total) }}</span>
                                    <span>ITC {{ $money($period->input_tax_credit_total) }}</span>
                                </td>
                                <td>
                                    <strong>Prepared by {{ $period->preparedBy?->name ?? 'User missing' }}</strong>
                                    <span>Approved by {{ $period->approvedBy?->name ?? 'Pending' }}</span>
                                    <span>Locked by {{ $period->lockedBy?->name ?? 'Pending' }}</span>
                                </td>
                                <td>{{ $statuses[$period->status] ?? str($period->status)->headline() }}</td>
                                <td>
                                    @can('approve', $period)
                                        <details class="blade-row-actions">
                                            <summary>Approve</summary>
                                            <form method="POST" action="{{ route('finance.gst-return-periods.approve', $period) }}" class="blade-inline-form">
                                                @csrf
                                                @method('PATCH')
                                                <textarea name="note" maxlength="500" rows="2" placeholder="Approval note"></textarea>
                                                <button type="submit" class="blade-primary-action">Approve return</button>
                                            </form>
                                        </details>
                                    @endcan

                                    @can('lock', $period)
                                        <details class="blade-row-actions">
                                            <summary>Lock</summary>
                                            <form method="POST" action="{{ route('finance.gst-return-periods.lock', $period) }}" class="blade-inline-form">
                                                @csrf
                                                @method('PATCH')
                                                <textarea name="note" maxlength="500" rows="2" placeholder="Lock note"></textarea>
                                                <button type="submit" class="blade-primary-action">Lock return</button>
                                            </form>
                                        </details>
                                    @endcan

                                    @cannot('approve', $period)
                                        @cannot('lock', $period)
                                            <span>No action</span>
                                        @endcannot
                                    @endcannot
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">No GST return periods match the selected filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="blade-pagination">{{ $periods->links() }}</div>
        </section>
    </div>
@endsection
