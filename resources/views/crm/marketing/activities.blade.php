@extends('layouts.builder360-classic')

@section('title', 'Lead Activities - Builder360 ERP-CRM')

@section('content')
<div class="blade-workspace" aria-labelledby="lead-activities-title">
    <header class="blade-workspace-header">
        <div>
            <p class="blade-dashboard-eyebrow">Sales &amp; CRM</p>
            <h1 id="lead-activities-title">Lead Activities</h1>
            <p>Record calls, emails, campaign responses and follow-ups against available leads.</p>
        </div>
        <nav class="blade-workspace-actions" aria-label="Lead activity workspace actions">
            <a href="{{ route('crm.campaigns.index') }}">Marketing Campaigns</a>
            <a href="{{ route('crm.leads.index') }}">Lead Management</a>
            <a href="{{ route('crm.lead-activities.index') }}">Reset filters</a>
        </nav>
    </header>

    @if (session('status'))<section class="blade-alert blade-alert-success" role="status">{{ session('status') }}</section>@endif
    @if ($errors->any())<section class="blade-alert blade-alert-error" role="alert"><strong>The activity was not recorded.</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></section>@endif

    <section class="blade-workspace-grid">
        <article class="blade-dashboard-card">
            <div class="blade-dashboard-section-title"><div><span class="blade-dashboard-label">Filters</span><h2>Activity register</h2></div><small>{{ $activities->total() }} record(s)</small></div>
            <form method="GET" action="{{ route('crm.lead-activities.index') }}" class="blade-filter-grid blade-filter-grid-compact">
                <label>Search<input type="search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="120" placeholder="Number, subject or details"></label>
                <label>Activity type<select name="activity_type"><option value="">All types</option>@foreach ($types as $value => $label)<option value="{{ $value }}" @selected(($filters['activity_type'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></label>
                <label>Project<select name="project_id"><option value="">All projects</option>@foreach ($projects as $project)<option value="{{ $project->id }}" @selected((string) ($filters['project_id'] ?? '') === (string) $project->id)>{{ $project->code }} · {{ $project->name }}</option>@endforeach</select></label>
                <label>Campaign<select name="marketing_campaign_id"><option value="">All campaigns</option>@foreach ($campaigns as $campaign)<option value="{{ $campaign->id }}" @selected((string) ($filters['marketing_campaign_id'] ?? '') === (string) $campaign->id)>{{ $campaign->campaign_code }} · {{ $campaign->name }}</option>@endforeach</select></label>
                <label>Date from<input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"></label>
                <label>Date to<input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"></label>
                <button type="submit" class="blade-secondary-action">Apply filters</button>
            </form>
        </article>

        @if ($canCreate)
            <article class="blade-dashboard-card">
                <div class="blade-dashboard-section-title"><div><span class="blade-dashboard-label">New Activity</span><h2>Record interaction</h2></div></div>
                <form method="POST" action="{{ route('crm.lead-activities.store') }}" class="blade-filter-grid blade-filter-grid-compact">
                    @csrf
                    <label>Lead<select name="lead_id" required><option value="">Select lead</option>@foreach ($leads as $lead)<option value="{{ $lead->id }}" @selected((string) old('lead_id') === (string) $lead->id)>{{ $lead->lead_code }} · {{ $lead->customer?->name ?? 'Customer unavailable' }}</option>@endforeach</select></label>
                    <label>Campaign<select name="marketing_campaign_id"><option value="">Use lead campaign</option>@foreach ($campaigns as $campaign)<option value="{{ $campaign->id }}" @selected((string) old('marketing_campaign_id') === (string) $campaign->id)>{{ $campaign->campaign_code }} · {{ $campaign->name }}</option>@endforeach</select></label>
                    <label>Type<select name="activity_type" required>@foreach (['note' => 'Note', 'call' => 'Call', 'email' => 'Email', 'campaign_response' => 'Campaign response', 'follow_up' => 'Follow-up'] as $value => $label)<option value="{{ $value }}" @selected(old('activity_type') === $value)>{{ $label }}</option>@endforeach</select></label>
                    <label>Activity time<input type="datetime-local" name="activity_at" value="{{ old('activity_at') }}"></label>
                    <label>Subject<input name="subject" value="{{ old('subject') }}" maxlength="255" required></label>
                    <label>Outcome<input name="outcome" value="{{ old('outcome') }}" maxlength="80"></label>
                    <label>Next follow-up<input type="datetime-local" name="next_follow_up_at" value="{{ old('next_follow_up_at') }}"></label>
                    <label class="blade-filter-span">Details<textarea name="description" maxlength="5000" rows="3">{{ old('description') }}</textarea></label>
                    <button type="submit" class="blade-primary-action">Record activity</button>
                </form>
            </article>
        @endif
    </section>

    <section class="blade-dashboard-card">
        <div class="blade-dashboard-section-title"><div><span class="blade-dashboard-label">Timeline</span><h2>Lead activity history</h2></div><small>{{ $activities->firstItem() ?? 0 }}-{{ $activities->lastItem() ?? 0 }} of {{ $activities->total() }}</small></div>
        <div class="blade-dashboard-table-wrap">
            <table class="blade-dashboard-table">
                <thead><tr><th>Activity</th><th>Lead</th><th>Type</th><th>Subject</th><th>Campaign</th><th>Owner</th><th>Follow-up</th></tr></thead>
                <tbody>
                    @forelse ($activities as $activity)
                        <tr>
                            <td><strong>{{ $activity->activity_number }}</strong><br><small>{{ $activity->activity_at?->format('d M Y, h:i A') }}</small></td>
                            <td>{{ $activity->lead?->lead_code }}<br><small>{{ $activity->lead?->customer?->name ?? 'Customer unavailable' }}</small></td>
                            <td><span class="blade-status-pill">{{ $types[$activity->activity_type] ?? ucfirst(str_replace('_', ' ', $activity->activity_type)) }}</span></td>
                            <td>{{ $activity->subject }}@if ($activity->outcome)<br><small>{{ $activity->outcome }}</small>@endif</td>
                            <td>{{ $activity->marketingCampaign?->campaign_code ?? '—' }}</td>
                            <td>{{ $activity->actor?->name ?? 'System' }}</td>
                            <td>{{ $activity->next_follow_up_at?->format('d M Y, h:i A') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="blade-empty-state"><strong>No lead activities found</strong><p>Adjust the filters or record the next customer interaction.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $activities->links() }}
    </section>
</div>
@endsection
