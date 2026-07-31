@extends('layouts.builder360-classic')

@section('title', 'Marketing Campaigns - Builder360 ERP-CRM')

@section('content')
<div class="blade-workspace" aria-labelledby="marketing-campaigns-title">
    <header class="blade-workspace-header">
        <div>
            <p class="blade-dashboard-eyebrow">Sales &amp; CRM</p>
            <h1 id="marketing-campaigns-title">Marketing Campaigns</h1>
            <p>Plan campaigns, monitor lead and booking outcomes, and control campaign status from one workspace.</p>
        </div>
        <nav class="blade-workspace-actions" aria-label="Marketing workspace actions">
            <a href="{{ route('crm.leads.index') }}">Lead Management</a>
            <a href="{{ route('crm.lead-activities.index') }}">Lead Activities</a>
            <a href="{{ route('crm.campaigns.index') }}">Reset filters</a>
        </nav>
    </header>

    @if (session('status'))
        <section class="blade-alert blade-alert-success" role="status">{{ session('status') }}</section>
    @endif

    @if ($errors->any())
        <section class="blade-alert blade-alert-error" role="alert">
            <strong>The campaign action was not completed.</strong>
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </section>
    @endif

    <section class="blade-dashboard-card">
        <div class="blade-dashboard-section-title">
            <div><span class="blade-dashboard-label">Campaign Register</span><h2>Current campaign position</h2></div>
            <small>{{ $campaigns->total() }} filtered record(s)</small>
        </div>
        <div class="blade-dashboard-metrics">
            <div class="blade-dashboard-metric"><span>Total campaigns</span><strong>{{ number_format($summary['total']) }}</strong></div>
            <div class="blade-dashboard-metric"><span>Active</span><strong>{{ number_format($summary['active']) }}</strong></div>
            <div class="blade-dashboard-metric"><span>Draft</span><strong>{{ number_format($summary['draft']) }}</strong></div>
            <div class="blade-dashboard-metric"><span>Planned budget</span><strong>₹{{ number_format($summary['budget'], 0) }}</strong></div>
        </div>
    </section>

    <section class="blade-workspace-grid">
        <article class="blade-dashboard-card">
            <div class="blade-dashboard-section-title"><div><span class="blade-dashboard-label">Filters</span><h2>Find campaigns</h2></div></div>
            <form method="GET" action="{{ route('crm.campaigns.index') }}" class="blade-filter-grid blade-filter-grid-compact">
                <label>Search<input type="search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="120" placeholder="Code, campaign or source"></label>
                <label>Status<select name="status"><option value="">All statuses</option>@foreach ($statuses as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></label>
                <label>Channel<select name="channel"><option value="">All channels</option>@foreach ($channels as $value => $label)<option value="{{ $value }}" @selected(($filters['channel'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></label>
                <label>Project<select name="project_id"><option value="">All projects</option>@foreach ($projects as $project)<option value="{{ $project->id }}" @selected((string) ($filters['project_id'] ?? '') === (string) $project->id)>{{ $project->code }} · {{ $project->name }}</option>@endforeach</select></label>
                <label>Start from<input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"></label>
                <label>Start to<input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"></label>
                <button type="submit" class="blade-secondary-action">Apply filters</button>
            </form>
        </article>

        @if ($canCreate)
            <article class="blade-dashboard-card">
                <div class="blade-dashboard-section-title"><div><span class="blade-dashboard-label">New Campaign</span><h2>Create campaign</h2></div></div>
                <form method="POST" action="{{ route('crm.campaigns.store') }}" class="blade-filter-grid blade-filter-grid-compact">
                    @csrf
                    <label>Company<select name="company_id" required>@foreach ($companies as $company)<option value="{{ $company->id }}" @selected((string) old('company_id', $companies->first()?->id) === (string) $company->id)>{{ $company->code }} · {{ $company->name }}</option>@endforeach</select></label>
                    <label>Project<select name="project_id"><option value="">Company-wide</option>@foreach ($projects as $project)<option value="{{ $project->id }}" @selected((string) old('project_id') === (string) $project->id)>{{ $project->code }} · {{ $project->name }}</option>@endforeach</select></label>
                    <label>Campaign name<input name="name" value="{{ old('name') }}" maxlength="255" required></label>
                    <label>Channel<select name="channel" required>@foreach ($channels as $value => $label)<option value="{{ $value }}" @selected(old('channel') === $value)>{{ $label }}</option>@endforeach</select></label>
                    <label>Source<input name="source" value="{{ old('source') }}" maxlength="80" required></label>
                    <label>Status<select name="status"><option value="draft">Draft</option><option value="active" @selected(old('status') === 'active')>Active</option></select></label>
                    <label>Start date<input type="date" name="start_on" value="{{ old('start_on', now()->toDateString()) }}" required></label>
                    <label>End date<input type="date" name="end_on" value="{{ old('end_on') }}"></label>
                    <label>Budget<input type="number" name="budget_amount" value="{{ old('budget_amount', 0) }}" min="0" step="0.01"></label>
                    <label>Target leads<input type="number" name="target_leads" value="{{ old('target_leads', 0) }}" min="0"></label>
                    <label>Target bookings<input type="number" name="target_bookings" value="{{ old('target_bookings', 0) }}" min="0"></label>
                    <button type="submit" class="blade-primary-action">Create campaign</button>
                </form>
            </article>
        @endif
    </section>

    <section class="blade-dashboard-card">
        <div class="blade-dashboard-section-title"><div><span class="blade-dashboard-label">Performance</span><h2>Campaign outcomes</h2></div><small>{{ $campaigns->firstItem() ?? 0 }}-{{ $campaigns->lastItem() ?? 0 }} of {{ $campaigns->total() }}</small></div>
        <div class="blade-dashboard-table-wrap">
            <table class="blade-dashboard-table">
                <thead><tr><th>Campaign</th><th>Project</th><th>Channel</th><th>Status</th><th>Leads</th><th>Bookings</th><th>Conversion</th><th>Budget</th><th>Action</th></tr></thead>
                <tbody>
                    @forelse ($campaigns as $campaign)
                        <tr>
                            <td><strong>{{ $campaign->campaign_code }}</strong><br><small>{{ $campaign->name }}</small></td>
                            <td>{{ $campaign->project?->code ?? 'Company-wide' }}</td>
                            <td>{{ $channels[$campaign->channel] ?? ucfirst(str_replace('_', ' ', $campaign->channel)) }}<br><small>{{ $campaign->source }}</small></td>
                            <td><span class="blade-status-pill">{{ $statuses[$campaign->status] ?? ucfirst($campaign->status) }}</span></td>
                            <td>{{ number_format($campaign->metrics['total_leads']) }}</td>
                            <td>{{ number_format($campaign->metrics['bookings']) }}</td>
                            <td>{{ number_format($campaign->metrics['conversion_rate'], 1) }}%</td>
                            <td>₹{{ number_format((float) $campaign->budget_amount, 0) }}</td>
                            <td>
                                @can('update', $campaign)
                                    @if ($campaign->status !== 'archived')
                                        <form method="POST" action="{{ route('crm.campaigns.status.update', $campaign) }}" class="blade-inline-form">
                                            @csrf @method('PATCH')
                                            <select name="status" aria-label="New status for {{ $campaign->campaign_code }}">
                                                @foreach (['active' => 'Active', 'paused' => 'Paused', 'completed' => 'Completed', 'archived' => 'Archived'] as $value => $label)<option value="{{ $value }}" @selected($campaign->status === $value)>{{ $label }}</option>@endforeach
                                            </select>
                                            <button type="submit">Update</button>
                                        </form>
                                    @else
                                        <span class="blade-muted">Closed</span>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><div class="blade-empty-state"><strong>No campaigns found</strong><p>Adjust the filters or create a campaign.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $campaigns->links() }}
    </section>
</div>
@endsection
