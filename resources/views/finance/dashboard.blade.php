@extends('layouts.builder360-classic')

@section('title', 'Finance Dashboard - Builder360 ERP-CRM')

@section('content')
@php
        $money = fn ($amount) => 'Rs. '.number_format((float) ($amount ?? 0), 2);
    @endphp

    <div class="blade-workspace" aria-labelledby="finance-dashboard-title">
        <header class="blade-workspace-header">
            <div>
                <p class="blade-dashboard-eyebrow">Finance and Operations</p>
                <h1 id="finance-dashboard-title">Finance Dashboard</h1>
                <p>
                    Dashboard for cash position, receivables, payables,
                    GST summary, approval counts, forecasts and recent finance activity.
                </p>
            </div>
            <nav class="blade-workspace-actions" aria-label="Workspace actions">
                <a href="{{ route('builder360.dashboard') }}">Dashboard</a>
                <a href="{{ route('finance.vouchers.index') }}">Financial Vouchers</a>
                <a href="{{ route('finance.collections.index') }}">Collections</a>
                <a href="{{ route('finance.gst-entries.index') }}">GST Entries</a>
                <a href="{{ route('finance.dashboard') }}">Reset filters</a>
            </nav>
        </header>

        @if ($errors->any())
            <section class="blade-alert blade-alert-error" role="alert">
                <strong>Finance dashboard filters were not applied.</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="blade-dashboard-card">
            <div class="blade-dashboard-section-title">
                <div>
                    <span class="blade-dashboard-label">Controls</span>
                    <h2>Finance filters</h2>
                </div>
                <small>Live finance records</small>
            </div>

            <form method="GET" action="{{ route('finance.dashboard') }}" class="blade-filter-grid blade-filter-grid-compact">
                <x-forms.company-context :companies="$companies" :selected="$filters['company_id'] ?? null" placeholder="All companies" />

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
                    From
                    <input type="date" name="date_from" value="{{ $filters['date_from'] ?? $dashboard['period']['date_from'] ?? '' }}">
                </label>

                <label>
                    To
                    <input type="date" name="date_to" value="{{ $filters['date_to'] ?? $dashboard['period']['date_to'] ?? '' }}">
                </label>

                <label>
                    Forecast days
                    <input type="number" name="forecast_days" value="{{ $filters['forecast_days'] ?? $dashboard['period']['forecast_days'] ?? 90 }}" min="1" max="180">
                </label>

                <button type="submit" class="blade-secondary-action">Apply filters</button>
            </form>
        </section>

        <section class="blade-dashboard-grid">
            <article class="blade-dashboard-card">
                <span class="blade-dashboard-label">Net cash position</span>
                <strong>{{ $money($dashboard['cash_position']['net_cash_position'] ?? 0) }}</strong>
                <small>As of {{ $dashboard['cash_position']['as_of_date'] ?? 'n/a' }}</small>
            </article>
            <article class="blade-dashboard-card">
                <span class="blade-dashboard-label">Period net flow</span>
                <strong>{{ $money($dashboard['period_summary']['net_period_flow'] ?? 0) }}</strong>
                <small>Collections + receipt vouchers - payment vouchers</small>
            </article>
            <article class="blade-dashboard-card">
                <span class="blade-dashboard-label">Schedule outstanding</span>
                <strong>{{ $money($dashboard['receivables']['schedule_outstanding'] ?? 0) }}</strong>
                <small>Due next 30 days: {{ $money($dashboard['receivables']['due_next_30_days'] ?? 0) }}</small>
            </article>
            <article class="blade-dashboard-card">
                <span class="blade-dashboard-label">Forecast outflow</span>
                <strong>{{ $money($dashboard['payables']['forecast_outflow'] ?? 0) }}</strong>
                <small>Submitted payment vouchers and approved liabilities</small>
            </article>
            <article class="blade-dashboard-card">
                <span class="blade-dashboard-label">GST tax amount</span>
                <strong>{{ $money($dashboard['gst']['total_tax_amount'] ?? 0) }}</strong>
                <small>{{ (int) ($dashboard['gst']['approved_entry_count'] ?? 0) }} approved GST entries</small>
            </article>
            <article class="blade-dashboard-card">
                <span class="blade-dashboard-label">Approval queue</span>
                <strong>{{ (int) ($dashboard['approvals']['submitted_finance_vouchers'] ?? 0) }}</strong>
                <small>Submitted finance vouchers</small>
            </article>
        </section>

        <section class="blade-workspace-grid">
            <article class="blade-dashboard-card">
                <div class="blade-dashboard-section-title">
                    <div>
                        <span class="blade-dashboard-label">Receivables</span>
                        <h2>Aging buckets</h2>
                    </div>
                </div>

                <div class="blade-dashboard-table-wrap">
                    <table class="blade-dashboard-table">
                        <tbody>
                            @foreach (($dashboard['receivables']['aging_buckets'] ?? []) as $bucket => $amount)
                                <tr>
                                    <th scope="row">{{ str($bucket)->headline() }}</th>
                                    <td>{{ $money($amount) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </article>

            <article class="blade-dashboard-card">
                <div class="blade-dashboard-section-title">
                    <div>
                        <span class="blade-dashboard-label">GST</span>
                        <h2>Approved GST by transaction type</h2>
                    </div>
                </div>

                <div class="blade-dashboard-table-wrap">
                    <table class="blade-dashboard-table">
                        <thead>
                            <tr>
                                <th scope="col">Type</th>
                                <th scope="col">Entries</th>
                                <th scope="col">Taxable</th>
                                <th scope="col">Tax</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse (($dashboard['gst']['by_transaction_type'] ?? []) as $row)
                                <tr>
                                    <td>{{ str($row['transaction_type'] ?? 'n/a')->headline() }}</td>
                                    <td>{{ (int) ($row['entry_count'] ?? 0) }}</td>
                                    <td>{{ $money($row['taxable_amount'] ?? 0) }}</td>
                                    <td>{{ $money($row['total_tax_amount'] ?? 0) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4">No approved GST entries in the selected period.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </article>
        </section>

        <section class="blade-workspace-grid">
            @foreach ([
                'collections' => 'Recent collections',
                'vouchers' => 'Recent vouchers',
                'payment_requests' => 'Recent payment requests',
            ] as $key => $title)
                <article class="blade-dashboard-card">
                    <div class="blade-dashboard-section-title">
                        <div>
                            <span class="blade-dashboard-label">Activity</span>
                            <h2>{{ $title }}</h2>
                        </div>
                    </div>

                    <div class="blade-dashboard-table-wrap">
                        <table class="blade-dashboard-table">
                            <tbody>
                                @forelse (($dashboard['recent_activity'][$key] ?? []) as $row)
                                    <tr>
                                        <td>
                                            <strong>{{ $row['receipt_number'] ?? $row['voucher_number'] ?? $row['request_number'] ?? 'Record' }}</strong>
                                            <span>{{ $row['status'] ?? 'n/a' }} / {{ $row['project'] ?? 'No project' }}</span>
                                        </td>
                                        <td>{{ $money($row['amount'] ?? 0) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="2">No recent activity.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </article>
            @endforeach
        </section>
    </div>
@endsection
