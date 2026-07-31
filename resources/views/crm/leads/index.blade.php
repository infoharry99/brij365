@extends('layouts.builder360-classic')

@section('title', 'Lead Management - Builder360 ERP-CRM')

@section('content')
<div class="blade-workspace" aria-labelledby="lead-management-title">
        <x-ui.page-header
            title="Lead Management"
            heading-id="lead-management-title"
            eyebrow="Sales and CRM"
            description="Workspace for lead capture, ownership, source tracking, project interest, budget tracking, follow-up control and CRM activity history."
        >
            <x-slot:actions>
                <x-ui.action :href="route('builder360.dashboard')">Dashboard</x-ui.action>
                <x-ui.action :href="route('crm.lead-qualifications.index')">Lead Qualification</x-ui.action>
                <x-ui.action :href="route('crm.leads.index')">Reset filters</x-ui.action>
            </x-slot:actions>
        </x-ui.page-header>

        @if (session('status'))
            <section class="blade-alert blade-alert-success" role="status">
                {{ session('status') }}
            </section>
        @endif

        @if ($errors->any())
            <section class="blade-alert blade-alert-error" role="alert">
                <strong>Lead was not saved.</strong>
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
                        <h2>Capture new lead</h2>
                    </div>
                    <small>{{ $canCreate ? 'Authorized' : 'Read only' }}</small>
                </div>

                @if ($canCreate)
                    <form method="POST" action="{{ route('crm.leads.store') }}" class="blade-form-grid">
                        @csrf

                        <x-forms.company-context :companies="$companies" :selected="$companies->first()?->id" required />

                        <label>
                            Project interest
                            <select name="project_id">
                                <option value="">No project selected</option>
                                @foreach ($projects as $project)
                                    <option value="{{ $project->id }}" @selected((string) old('project_id') === (string) $project->id)>
                                        {{ $project->code }} · {{ $project->name }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label>
                            Customer name
                            <input type="text" name="customer_name" value="{{ old('customer_name') }}" maxlength="255" required>
                        </label>

                        <label>
                            Customer email
                            <input type="email" name="customer_email" value="{{ old('customer_email') }}" maxlength="255" placeholder="Required if phone is blank">
                        </label>

                        <label>
                            Customer phone
                            <input type="text" name="customer_phone" value="{{ old('customer_phone') }}" maxlength="32" placeholder="Required if email is blank">
                        </label>

                        <label>
                            Source
                            <select name="source" required>
                                @foreach ($sources as $source)
                                    <option value="{{ $source }}" @selected(old('source', 'Channel walk-in') === $source)>{{ $source }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label>
                            Partner / broker
                            <select name="partner_id">
                                <option value="">No partner</option>
                                @foreach ($partners as $partner)
                                    <option value="{{ $partner->id }}" @selected((string) old('partner_id') === (string) $partner->id)>
                                        {{ $partner->code }} · {{ $partner->name }} · {{ str($partner->partner_type)->headline() }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label>
                            Marketing campaign
                            <select name="marketing_campaign_id">
                                <option value="">No campaign attribution</option>
                                @foreach ($campaigns as $campaign)
                                    <option value="{{ $campaign->id }}" @selected((string) old('marketing_campaign_id') === (string) $campaign->id)>
                                        {{ $campaign->campaign_code }} · {{ $campaign->name }} · {{ $campaign->source }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label>
                            Stage
                            <select name="stage" required>
                                @foreach ($stages as $stage)
                                    <option value="{{ $stage }}" @selected(old('stage', 'New') === $stage)>{{ $stage }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label>
                            Status
                            <select name="status">
                                @foreach ($statuses as $value => $label)
                                    <option value="{{ $value }}" @selected(old('status', 'open') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label>
                            Expected value
                            <input type="number" name="expected_value" value="{{ old('expected_value') }}" min="0" step="0.01" required>
                        </label>

                        <label>
                            Budget min
                            <input type="number" name="budget_min" value="{{ old('budget_min') }}" min="0" step="0.01">
                        </label>

                        <label>
                            Budget max
                            <input type="number" name="budget_max" value="{{ old('budget_max') }}" min="0" step="0.01">
                        </label>

                        <label>
                            Follow-up date
                            <input type="datetime-local" name="follow_up_at" value="{{ old('follow_up_at') }}">
                        </label>

                        <button type="submit" class="blade-primary-action">Save lead</button>
                    </form>
                @else
                    <p class="blade-workspace-note">
                        Your role can view leads but cannot create new leads.
                    </p>
                @endif
            </article>

            <article class="blade-dashboard-card">
                <div class="blade-dashboard-section-title">
                    <div>
                        <span class="blade-dashboard-label">Controls</span>
                        <h2>Lead filters</h2>
                    </div>
                    <small>{{ $leads->total() }} record(s)</small>
                </div>

                <form method="GET" action="{{ route('crm.leads.index') }}" class="blade-filter-grid blade-filter-grid-compact">
                    <label>
                        Stage
                        <select name="stage">
                            <option value="">All stages</option>
                            @foreach ($stages as $stage)
                                <option value="{{ $stage }}" @selected(($filters['stage'] ?? '') === $stage)>{{ $stage }}</option>
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
                        Project
                        <select name="project_id">
                            <option value="">All projects</option>
                            @foreach ($projects as $project)
                                <option value="{{ $project->id }}" @selected((string) ($filters['project_id'] ?? '') === (string) $project->id)>
                                    {{ $project->code }} · {{ $project->name }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label>
                        Source
                        <select name="source">
                            <option value="">All sources</option>
                            @foreach ($sources as $source)
                                <option value="{{ $source }}" @selected(($filters['source'] ?? '') === $source)>{{ $source }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label>
                        Campaign
                        <select name="marketing_campaign_id">
                            <option value="">All campaigns</option>
                            @foreach ($campaigns as $campaign)
                                <option value="{{ $campaign->id }}" @selected((string) ($filters['marketing_campaign_id'] ?? '') === (string) $campaign->id)>
                                    {{ $campaign->campaign_code }} · {{ $campaign->name }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <button type="submit" class="blade-secondary-action">Apply filters</button>
                </form>

                <div class="blade-workspace-note">
                    Filters are checked before matching project and campaign records are returned.
                </div>
            </article>
        </section>

        <section class="blade-dashboard-card">
            <div class="blade-dashboard-section-title">
                <div>
                    <span class="blade-dashboard-label">Register</span>
                    <h2>Lead master</h2>
                </div>
                <small>{{ $leads->firstItem() ?? 0 }}-{{ $leads->lastItem() ?? 0 }} of {{ $leads->total() }}</small>
            </div>

            <div class="blade-dashboard-table-wrap">
                <table class="blade-dashboard-table">
                    <thead>
                        <tr>
                            <th scope="col">Lead</th>
                            <th scope="col">Customer</th>
                            <th scope="col">Project</th>
                            <th scope="col">Source</th>
                            <th scope="col">Stage</th>
                            <th scope="col">Status</th>
                            <th scope="col">Budget</th>
                            <th scope="col">Follow-up</th>
                            <th scope="col">Owner</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($leads as $lead)
                            <tr>
                                <td>
                                    <strong>{{ $lead->lead_code }}</strong>
                                    <span>{{ $lead->company?->code ?? 'Company missing' }}</span>
                                </td>
                                <td>
                                    <strong>{{ $lead->customer?->name ?? 'Customer missing' }}</strong>
                                    <span>{{ $lead->customer?->phone ?? $lead->customer?->email ?? 'Contact pending' }}</span>
                                </td>
                                <td>
                                    <strong>{{ $lead->project?->code ?? 'No project' }}</strong>
                                    <span>{{ $lead->project?->name ?? 'Project not selected' }}</span>
                                </td>
                                <td>{{ $lead->source }}</td>
                                <td>{{ $lead->stage }}</td>
                                <td>{{ str($lead->status)->headline() }}</td>
                                <td>
                                    <strong>{{ number_format((float) $lead->expected_value, 2) }}</strong>
                                    <span>
                                        {{ $lead->budget_min ? number_format((float) $lead->budget_min, 2) : 'Min NA' }}
                                        -
                                        {{ $lead->budget_max ? number_format((float) $lead->budget_max, 2) : 'Max NA' }}
                                    </span>
                                </td>
                                <td>{{ $lead->follow_up_at?->format('d M Y H:i') ?? 'Not scheduled' }}</td>
                                <td>{{ $lead->owner?->name ?? 'Unassigned' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9">No leads match the selected filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="blade-pagination">
                {{ $leads->links() }}
            </div>
        </section>
    </div>
@endsection
