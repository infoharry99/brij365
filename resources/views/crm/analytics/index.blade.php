@extends('layouts.builder360-classic')

@section('title', 'Sales Analytics - Builder360 ERP-CRM')

@section('content')
<div class="blade-workspace" aria-labelledby="sales-analytics-title">
    <header class="blade-workspace-header">
        <div>
            <p class="blade-dashboard-eyebrow">Sales &amp; CRM</p>
            <h1 id="sales-analytics-title">Sales Funnel &amp; Performance</h1>
            <p>Conversion, source, project, campaign and team performance from available sales records.</p>
        </div>
        <nav class="blade-workspace-actions" aria-label="Sales analytics actions">
            <a href="{{ route('crm.leads.index') }}">Lead Management</a>
            <a href="{{ route('crm.campaigns.index') }}">Marketing</a>
            <a href="{{ route('sales.bookings.index') }}">Bookings</a>
        </nav>
    </header>

    <section class="blade-dashboard-card">
        <div class="blade-dashboard-section-title"><div><span class="blade-dashboard-label">Period &amp; Project</span><h2>Analytics filters</h2></div></div>
        <form method="GET" action="{{ route('crm.analytics.index') }}" class="blade-filter-grid blade-filter-grid-compact">
            <label>Project<select name="project_id"><option value="">All projects</option>@foreach ($projects as $project)<option value="{{ $project->id }}" @selected((string) ($filters['project_id'] ?? '') === (string) $project->id)>{{ $project->code }} · {{ $project->name }}</option>@endforeach</select></label>
            <label>Date from<input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"></label>
            <label>Date to<input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"></label>
            <button type="submit" class="blade-secondary-action">Apply filters</button>
            <a href="{{ route('crm.analytics.index') }}" class="blade-secondary-action">Reset</a>
        </form>
    </section>

    <section class="blade-dashboard-card">
        <div class="blade-dashboard-section-title"><div><span class="blade-dashboard-label">Conversion Overview</span><h2>Current sales position</h2></div></div>
        <div class="blade-dashboard-metrics">
            <div class="blade-dashboard-metric"><span>Total leads</span><strong>{{ number_format($report['summary']['leads']) }}</strong></div>
            <div class="blade-dashboard-metric"><span>Qualified</span><strong>{{ number_format($report['summary']['qualified']) }}</strong><small>{{ number_format($report['summary']['qualification_rate'], 1) }}%</small></div>
            <div class="blade-dashboard-metric"><span>Site visits</span><strong>{{ number_format($report['summary']['site_visits']) }}</strong></div>
            <div class="blade-dashboard-metric"><span>Bookings</span><strong>{{ number_format($report['summary']['bookings']) }}</strong><small>{{ number_format($report['summary']['booking_conversion'], 1) }}% conversion</small></div>
        </div>
    </section>

    <section class="blade-workspace-grid">
        <article class="blade-dashboard-card">
            <div class="blade-dashboard-section-title"><div><span class="blade-dashboard-label">Sales Funnel</span><h2>Stage conversion</h2></div></div>
            <div class="blade-dashboard-table-wrap">
                <table class="blade-dashboard-table"><thead><tr><th>Stage</th><th>Records</th><th>Conversion from leads</th></tr></thead><tbody>
                @foreach ($report['funnel'] as $row)<tr><td><strong>{{ $row['label'] }}</strong></td><td>{{ number_format($row['value']) }}</td><td>{{ number_format($row['rate'], 1) }}%</td></tr>@endforeach
                </tbody></table>
            </div>
        </article>

        <article class="blade-dashboard-card">
            <div class="blade-dashboard-section-title"><div><span class="blade-dashboard-label">Sources</span><h2>Source conversion</h2></div></div>
            <div class="blade-dashboard-table-wrap">
                <table class="blade-dashboard-table"><thead><tr><th>Source</th><th>Leads</th><th>Won</th><th>Conversion</th></tr></thead><tbody>
                @forelse ($report['sources'] as $row)<tr><td><strong>{{ $row['label'] }}</strong></td><td>{{ $row['leads'] }}</td><td>{{ $row['won'] }}</td><td>{{ number_format($row['conversion'], 1) }}%</td></tr>@empty<tr><td colspan="4">No source records are available for the selected filters.</td></tr>@endforelse
                </tbody></table>
            </div>
        </article>
    </section>

    <section class="blade-workspace-grid">
        <article class="blade-dashboard-card">
            <div class="blade-dashboard-section-title"><div><span class="blade-dashboard-label">Team Performance</span><h2>Lead owners</h2></div></div>
            <div class="blade-dashboard-table-wrap">
                <table class="blade-dashboard-table"><thead><tr><th>Owner</th><th>Leads</th><th>Won</th><th>Conversion</th><th>Pipeline</th></tr></thead><tbody>
                @forelse ($report['team'] as $row)<tr><td><strong>{{ $row['label'] }}</strong></td><td>{{ $row['leads'] }}</td><td>{{ $row['won'] }}</td><td>{{ number_format($row['conversion'], 1) }}%</td><td>₹{{ number_format($row['pipeline'], 0) }}</td></tr>@empty<tr><td colspan="5">No team performance records are available.</td></tr>@endforelse
                </tbody></table>
            </div>
        </article>

        <article class="blade-dashboard-card">
            <div class="blade-dashboard-section-title"><div><span class="blade-dashboard-label">Project Conversion</span><h2>Project performance</h2></div></div>
            <div class="blade-dashboard-table-wrap">
                <table class="blade-dashboard-table"><thead><tr><th>Project</th><th>Leads</th><th>Won</th><th>Conversion</th></tr></thead><tbody>
                @forelse ($report['projects'] as $row)<tr><td><strong>{{ $row['code'] }}</strong><br><small>{{ $row['label'] }}</small></td><td>{{ $row['leads'] }}</td><td>{{ $row['won'] }}</td><td>{{ number_format($row['conversion'], 1) }}%</td></tr>@empty<tr><td colspan="4">No project conversion records are available.</td></tr>@endforelse
                </tbody></table>
            </div>
        </article>
    </section>

    <section class="blade-dashboard-card">
        <div class="blade-dashboard-section-title"><div><span class="blade-dashboard-label">Marketing Metrics</span><h2>Campaign conversion</h2></div><a href="{{ route('crm.campaigns.index') }}">Open campaigns</a></div>
        <div class="blade-dashboard-table-wrap">
            <table class="blade-dashboard-table"><thead><tr><th>Campaign</th><th>Source</th><th>Status</th><th>Leads</th><th>Won</th><th>Conversion</th></tr></thead><tbody>
            @forelse ($report['campaigns'] as $row)<tr><td><strong>{{ $row['code'] }}</strong><br><small>{{ $row['label'] }}</small></td><td>{{ $row['source'] }}</td><td><span class="blade-status-pill">{{ ucfirst($row['status']) }}</span></td><td>{{ $row['leads'] }}</td><td>{{ $row['won'] }}</td><td>{{ number_format($row['conversion'], 1) }}%</td></tr>@empty<tr><td colspan="6">No campaign metrics are available for the selected filters.</td></tr>@endforelse
            </tbody></table>
        </div>
    </section>
</div>
@endsection
