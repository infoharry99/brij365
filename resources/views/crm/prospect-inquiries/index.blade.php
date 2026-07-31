@extends('layouts.builder360-classic')

@section('title', 'Prospect Inquiries - Builder360 ERP-CRM')

@section('content')
<div class="blade-workspace" aria-labelledby="prospect-inquiries-title">
        <header class="blade-workspace-header">
            <div>
                <p class="blade-dashboard-eyebrow">Customer Channels</p>
                <h1 id="prospect-inquiries-title">Prospect Inquiry Management</h1>
                <p>
                    Workspace for website, mobile, phone, email and partner inquiries with
                    company-level filtering, duplicate review, sales assignment, lead conversion and closure control.
                </p>
            </div>
            <nav class="blade-workspace-actions" aria-label="Workspace actions">
                <a href="{{ route('builder360.dashboard') }}">Dashboard</a>
                <a href="{{ route('crm.leads.index') }}">Lead Management</a>
                <a href="{{ route('crm.prospect-inquiries.index') }}">Reset filters</a>
            </nav>
        </header>

        @if (session('status'))
            <section class="blade-alert blade-alert-success" role="status">
                {{ session('status') }}
            </section>
        @endif

        @if ($errors->any())
            <section class="blade-alert blade-alert-error" role="alert">
                <strong>Prospect inquiry action was not completed.</strong>
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
                        <span class="blade-dashboard-label">Live Register</span>
                        <h2>Inquiry status summary</h2>
                    </div>
                    <small>{{ $inquiries->total() }} filtered record(s)</small>
                </div>

                <div class="blade-dashboard-metrics">
                    @foreach ($statuses as $status => $label)
                        <div class="blade-dashboard-metric">
                            <span>{{ $label }}</span>
                            <strong>{{ number_format((int) ($metrics[$status] ?? 0)) }}</strong>
                        </div>
                    @endforeach
                </div>

                <p class="blade-workspace-note">
                    Counts are shown from available prospect inquiries. Authorized CRM users can assign,
                    convert and close records from this workspace.
                </p>
            </article>

            <article class="blade-dashboard-card">
                <div class="blade-dashboard-section-title">
                    <div>
                        <span class="blade-dashboard-label">Controls</span>
                        <h2>Inquiry filters</h2>
                    </div>
                    <small>Company-level</small>
                </div>

                <form method="GET" action="{{ route('crm.prospect-inquiries.index') }}" class="blade-filter-grid blade-filter-grid-compact">
                    <label>
                        Search
                        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="120" placeholder="Inquiry no, name, email or phone">
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
                                    {{ $project->code }} &middot; {{ $project->name }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label>
                        Assigned to
                        <select name="assigned_to_user_id">
                            <option value="">Anyone</option>
                            @foreach ($assignees as $assignee)
                                <option value="{{ $assignee->id }}" @selected((string) ($filters['assigned_to_user_id'] ?? '') === (string) $assignee->id)>
                                    {{ $assignee->name }}
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
                        Channel
                        <select name="channel">
                            <option value="">All channels</option>
                            @foreach ($channels as $channel)
                                <option value="{{ $channel }}" @selected(($filters['channel'] ?? '') === $channel)>{{ str($channel)->replace('_', ' ')->headline() }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label>
                        Created from
                        <input type="date" name="created_from" value="{{ $filters['created_from'] ?? '' }}">
                    </label>

                    <label>
                        Created to
                        <input type="date" name="created_to" value="{{ $filters['created_to'] ?? '' }}">
                    </label>

                    <button type="submit" class="blade-secondary-action">Apply filters</button>
                </form>

                <div class="blade-workspace-note">
                    Only projects and assignees available to your company can be selected.
                </div>
            </article>
        </section>

        <section class="blade-dashboard-card">
            <div class="blade-dashboard-section-title">
                <div>
                    <span class="blade-dashboard-label">Register</span>
                    <h2>Prospect inquiry queue</h2>
                </div>
                <small>{{ $inquiries->firstItem() ?? 0 }}-{{ $inquiries->lastItem() ?? 0 }} of {{ $inquiries->total() }}</small>
            </div>

            <div class="blade-dashboard-table-wrap">
                <table class="blade-dashboard-table">
                    <thead>
                        <tr>
                            <th scope="col">Inquiry</th>
                            <th scope="col">Prospect</th>
                            <th scope="col">Project</th>
                            <th scope="col">Source</th>
                            <th scope="col">Budget</th>
                            <th scope="col">Status</th>
                            <th scope="col">Owner</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($inquiries as $inquiry)
                            @php
                                $isClosed = $inquiry->isClosed();
                                $canAct = $canManage && ! $isClosed;
                            @endphp
                            <tr>
                                <td>
                                    <strong>{{ $inquiry->inquiry_number }}</strong>
                                    <span>{{ $inquiry->company?->code ?? 'Company missing' }} &middot; {{ $inquiry->created_at?->format('d M Y H:i') }}</span>
                                </td>
                                <td>
                                    <strong>{{ $inquiry->name }}</strong>
                                    <span>{{ $inquiry->phone ?? $inquiry->email ?? 'Contact pending' }}</span>
                                    @if ($inquiry->message)
                                        <span>{{ str($inquiry->message)->limit(90) }}</span>
                                    @endif
                                </td>
                                <td>
                                    <strong>{{ $inquiry->project?->code ?? 'No project' }}</strong>
                                    <span>{{ $inquiry->project?->name ?? 'Project not selected' }}</span>
                                </td>
                                <td>
                                    <strong>{{ $inquiry->source }}</strong>
                                    <span>{{ str($inquiry->channel)->replace('_', ' ')->headline() }}</span>
                                </td>
                                <td>
                                    <strong>{{ $inquiry->budget_max ? number_format((float) $inquiry->budget_max, 2) : 'Max NA' }}</strong>
                                    <span>{{ $inquiry->budget_min ? number_format((float) $inquiry->budget_min, 2) : 'Min NA' }}</span>
                                </td>
                                <td>
                                    <strong>{{ $statuses[$inquiry->status] ?? str($inquiry->status)->headline() }}</strong>
                                    @if ($inquiry->duplicateOf)
                                        <span>Duplicate of {{ $inquiry->duplicateOf->inquiry_number }}</span>
                                    @endif
                                    @if ($inquiry->convertedLead)
                                        <span>Lead {{ $inquiry->convertedLead->lead_code }}</span>
                                    @endif
                                </td>
                                <td>{{ $inquiry->assignedTo?->name ?? 'Unassigned' }}</td>
                                <td>
                                    @if ($canAct)
                                        <div class="blade-table-action-stack">
                                            <form method="POST" action="{{ route('crm.prospect-inquiries.assign', $inquiry) }}" class="blade-inline-form">
                                                @csrf
                                                @method('PATCH')
                                                <label>
                                                    Assign owner
                                                    <select name="assigned_to_user_id" required>
                                                        <option value="">Select user</option>
                                                        @foreach ($assignees as $assignee)
                                                            @if ((int) $assignee->company_id === (int) $inquiry->company_id)
                                                                <option value="{{ $assignee->id }}" @selected((int) old('assigned_to_user_id', $inquiry->assigned_to_user_id) === (int) $assignee->id)>
                                                                    {{ $assignee->name }}
                                                                </option>
                                                            @endif
                                                        @endforeach
                                                    </select>
                                                </label>
                                                <input type="text" name="note" value="{{ old('note') }}" maxlength="1000" placeholder="Assignment note">
                                                <button type="submit" class="blade-secondary-action">Assign</button>
                                            </form>

                                            @if ($inquiry->status !== \App\Models\ProspectInquiry::STATUS_DUPLICATE)
                                                <form method="POST" action="{{ route('crm.prospect-inquiries.convert', $inquiry) }}" class="blade-inline-form">
                                                    @csrf
                                                    @method('PATCH')
                                                    <label>
                                                        Expected value
                                                        <input type="number" name="expected_value" value="{{ old('expected_value', $inquiry->budget_max ?? $inquiry->budget_min ?? 0) }}" min="0" step="0.01">
                                                    </label>
                                                    <label>
                                                        Lead stage
                                                        <select name="stage">
                                                            @foreach (['New', 'Qualified', 'Site Visit Planned', 'Negotiation'] as $stage)
                                                                <option value="{{ $stage }}" @selected(old('stage', 'New') === $stage)>{{ $stage }}</option>
                                                            @endforeach
                                                        </select>
                                                    </label>
                                                    <label>
                                                        Follow-up
                                                        <input type="datetime-local" name="follow_up_at" value="{{ old('follow_up_at') }}">
                                                    </label>
                                                    <label>
                                                        Campaign
                                                        <select name="marketing_campaign_id">
                                                            <option value="">No attribution</option>
                                                            @foreach ($campaigns as $campaign)
                                                                @if ((int) $campaign->company_id === (int) $inquiry->company_id && ($campaign->project_id === null || (int) $campaign->project_id === (int) $inquiry->project_id))
                                                                    <option value="{{ $campaign->id }}" @selected((string) old('marketing_campaign_id') === (string) $campaign->id)>
                                                                        {{ $campaign->campaign_code }} &middot; {{ $campaign->name }}
                                                                    </option>
                                                                @endif
                                                            @endforeach
                                                        </select>
                                                    </label>
                                                    <input type="text" name="note" value="{{ old('note') }}" maxlength="1000" placeholder="Conversion note">
                                                    <button type="submit" class="blade-primary-action">Convert to lead</button>
                                                </form>
                                            @else
                                                <p class="blade-workspace-note">
                                                    Duplicate inquiries must be reviewed or closed before conversion.
                                                </p>
                                            @endif

                                            <form method="POST" action="{{ route('crm.prospect-inquiries.close', $inquiry) }}" class="blade-inline-form">
                                                @csrf
                                                @method('PATCH')
                                                <label>
                                                    Closure status
                                                    <select name="status" required>
                                                        <option value="{{ \App\Models\ProspectInquiry::STATUS_CLOSED_UNQUALIFIED }}">Closed - unqualified</option>
                                                        <option value="{{ \App\Models\ProspectInquiry::STATUS_CLOSED_DUPLICATE }}">Closed - duplicate</option>
                                                    </select>
                                                </label>
                                                <input type="text" name="reason" value="{{ old('reason') }}" maxlength="1000" placeholder="Required closure reason" required>
                                                <button type="submit" class="blade-secondary-action">Close</button>
                                            </form>
                                        </div>
                                    @else
                                        <span>{{ $isClosed ? 'Closed' : 'Read only' }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8">No prospect inquiries match the selected filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="blade-pagination">
                {{ $inquiries->links() }}
            </div>
        </section>
    </div>
@endsection
