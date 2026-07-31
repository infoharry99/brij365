@extends('layouts.builder360-classic')

@section('title', 'Site Visits - Builder360 ERP-CRM')

@section('content')
@php
        $projectOptions = $leads
            ->pluck('project')
            ->filter()
            ->unique('id')
            ->sortBy('code')
            ->values();
    @endphp

    <div class="blade-workspace" aria-labelledby="site-visits-title">
        <header class="blade-workspace-header">
            <div>
                <p class="blade-dashboard-eyebrow">Sales and CRM</p>
                <h1 id="site-visits-title">Site Visits</h1>
                <p>
                    Workspace for site visit planning, assignee conflict validation,
                    attendee capture, completion outcomes, cancellation and follow-up updates.
                </p>
            </div>
            <nav class="blade-workspace-actions" aria-label="Workspace actions">
                <a href="{{ route('builder360.dashboard') }}">Dashboard</a>
                <a href="{{ route('crm.leads.index') }}">Lead Management</a>
                <a href="{{ route('crm.lead-qualifications.index') }}">Lead Qualification</a>
                <a href="{{ route('crm.site-visits.index') }}">Reset filters</a>
            </nav>
        </header>

        @if (session('status'))
            <section class="blade-alert blade-alert-success" role="status">
                {{ session('status') }}
            </section>
        @endif

        @if ($errors->any())
            <section class="blade-alert blade-alert-error" role="alert">
                <strong>Site visit action was not saved.</strong>
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
                        <h2>Schedule site visit</h2>
                    </div>
                    <small>{{ $canSchedule ? 'Authorized' : 'Read only' }}</small>
                </div>

                @if ($canSchedule)
                    <form method="POST" action="{{ route('crm.site-visits.store') }}" class="blade-form-grid">
                        @csrf

                        <label>
                            Lead
                            <select name="lead_id" required>
                                <option value="">Select lead</option>
                                @foreach ($leads as $lead)
                                    <option value="{{ $lead->id }}" @selected((string) old('lead_id', $filters['lead_id'] ?? '') === (string) $lead->id)>
                                        {{ $lead->lead_code }} · {{ $lead->customer?->name ?? 'Customer pending' }} · {{ $lead->project?->code ?? 'No project' }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label>
                            Assigned to
                            <select name="assigned_to_user_id">
                                <option value="">Assign to me</option>
                                @foreach ($assignees as $assignee)
                                    <option value="{{ $assignee->id }}" @selected((string) old('assigned_to_user_id') === (string) $assignee->id)>
                                        {{ $assignee->name }} · {{ $assignee->email }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label>
                            Scheduled at
                            <input type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at') }}" required>
                        </label>

                        <label>
                            Duration minutes
                            <input type="number" name="duration_minutes" value="{{ old('duration_minutes', 60) }}" min="15" max="480" step="15">
                        </label>

                        <label>
                            Visit mode
                            <select name="visit_mode" required>
                                @foreach ($visitModes as $value => $label)
                                    <option value="{{ $value }}" @selected(old('visit_mode', 'site') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label>
                            Location
                            <input type="text" name="meeting_location" value="{{ old('meeting_location') }}" maxlength="255" placeholder="Site office / sales gallery">
                        </label>

                        <label class="blade-form-wide">
                            Meeting URL
                            <input type="url" name="meeting_url" value="{{ old('meeting_url') }}" maxlength="1024" placeholder="For virtual meetings">
                        </label>

                        <label class="blade-form-wide">
                            Agenda
                            <textarea name="agenda" maxlength="5000" rows="3" placeholder="Visit agenda, inventory to show, discussion points.">{{ old('agenda') }}</textarea>
                        </label>

                        <label>
                            Attendee name
                            <input type="text" name="attendees[0][name]" value="{{ old('attendees.0.name') }}" maxlength="255" placeholder="Customer / family / advisor">
                        </label>

                        <label>
                            Attendee phone
                            <input type="text" name="attendees[0][phone]" value="{{ old('attendees.0.phone') }}" maxlength="40">
                        </label>

                        <label>
                            Attendee role
                            <input type="text" name="attendees[0][role]" value="{{ old('attendees.0.role', 'Buyer') }}" maxlength="80">
                        </label>

                        <button type="submit" class="blade-primary-action">Schedule visit</button>
                    </form>
                @else
                    <p class="blade-workspace-note">Your role can view site visits but cannot schedule new visits.</p>
                @endif
            </article>

            <article class="blade-dashboard-card">
                <div class="blade-dashboard-section-title">
                    <div>
                        <span class="blade-dashboard-label">Controls</span>
                        <h2>Visit filters</h2>
                    </div>
                    <small>{{ $visits->total() }} record(s)</small>
                </div>

                <form method="GET" action="{{ route('crm.site-visits.index') }}" class="blade-filter-grid blade-filter-grid-compact">
                    <label>
                        Lead
                        <select name="lead_id">
                            <option value="">All leads</option>
                            @foreach ($leads as $lead)
                                <option value="{{ $lead->id }}" @selected((string) ($filters['lead_id'] ?? '') === (string) $lead->id)>
                                    {{ $lead->lead_code }} · {{ $lead->customer?->name ?? 'Customer pending' }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label>
                        Project
                        <select name="project_id">
                            <option value="">All projects</option>
                            @foreach ($projectOptions as $project)
                                <option value="{{ $project->id }}" @selected((string) ($filters['project_id'] ?? '') === (string) $project->id)>
                                    {{ $project->code }} · {{ $project->name }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label>
                        Assignee
                        <select name="assigned_to_user_id">
                            <option value="">All assignees</option>
                            @foreach ($assignees as $assignee)
                                <option value="{{ $assignee->id }}" @selected((string) ($filters['assigned_to_user_id'] ?? '') === (string) $assignee->id)>
                                    {{ $assignee->name }}
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

                    <label>
                        Mode
                        <select name="visit_mode">
                            <option value="">All modes</option>
                            @foreach ($visitModes as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['visit_mode'] ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <button type="submit" class="blade-secondary-action">Apply filters</button>
                </form>

                <div class="blade-workspace-note">
                    Scheduling, rescheduling and completion use the configured workflow engine, including assignee time-conflict validation.
                </div>
            </article>
        </section>

        <section class="blade-dashboard-card">
            <div class="blade-dashboard-section-title">
                <div>
                    <span class="blade-dashboard-label">Register</span>
                    <h2>Site visit calendar/list</h2>
                </div>
                <small>{{ $visits->firstItem() ?? 0 }}-{{ $visits->lastItem() ?? 0 }} of {{ $visits->total() }}</small>
            </div>

            <div class="blade-dashboard-table-wrap">
                <table class="blade-dashboard-table">
                    <thead>
                        <tr>
                            <th scope="col">Visit</th>
                            <th scope="col">Lead / customer</th>
                            <th scope="col">Schedule</th>
                            <th scope="col">Mode</th>
                            <th scope="col">Assigned</th>
                            <th scope="col">Status</th>
                            <th scope="col">Outcome</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($visits as $visit)
                            <tr>
                                <td>
                                    <strong>{{ $visit->visit_number }}</strong>
                                    <span>{{ $visit->project?->code ?? 'No project' }}</span>
                                </td>
                                <td>
                                    <strong>{{ $visit->lead?->lead_code ?? 'Lead missing' }}</strong>
                                    <span>{{ $visit->customer?->name ?? 'Customer pending' }}</span>
                                </td>
                                <td>
                                    <strong>{{ $visit->scheduled_at?->format('d M Y H:i') ?? 'Not scheduled' }}</strong>
                                    <span>{{ $visit->duration_minutes ?? 60 }} minutes</span>
                                </td>
                                <td>
                                    <strong>{{ $visitModes[$visit->visit_mode] ?? str($visit->visit_mode)->headline() }}</strong>
                                    <span>{{ $visit->meeting_location ?? $visit->meeting_url ?? 'Venue pending' }}</span>
                                </td>
                                <td>{{ $visit->assignedTo?->name ?? 'Self / unassigned' }}</td>
                                <td>{{ $statuses[$visit->status] ?? str($visit->status)->headline() }}</td>
                                <td>
                                    <strong>{{ $visit->outcome ? str($visit->outcome)->headline() : 'Pending' }}</strong>
                                    <span>{{ $visit->next_follow_up_at?->format('d M Y H:i') ?? 'No next follow-up' }}</span>
                                </td>
                                <td>
                                    @can('update', $visit)
                                        <details class="blade-row-actions">
                                            <summary>Update</summary>
                                            <form method="POST" action="{{ route('crm.site-visits.update', $visit) }}" class="blade-inline-form">
                                                @csrf
                                                @method('PATCH')
                                                <select name="assigned_to_user_id">
                                                    <option value="">Assign to me</option>
                                                    @foreach ($assignees as $assignee)
                                                        <option value="{{ $assignee->id }}" @selected($visit->assigned_to_user_id === $assignee->id)>{{ $assignee->name }}</option>
                                                    @endforeach
                                                </select>
                                                <input type="datetime-local" name="scheduled_at" value="{{ $visit->scheduled_at?->format('Y-m-d\TH:i') }}" required>
                                                <input type="number" name="duration_minutes" value="{{ $visit->duration_minutes ?? 60 }}" min="15" max="480" step="15">
                                                <select name="visit_mode" required>
                                                    @foreach ($visitModes as $value => $label)
                                                        <option value="{{ $value }}" @selected($visit->visit_mode === $value)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                                <input type="text" name="meeting_location" value="{{ $visit->meeting_location }}" maxlength="255" placeholder="Location">
                                                <textarea name="agenda" maxlength="5000" rows="2" placeholder="Agenda">{{ $visit->agenda }}</textarea>
                                                <button type="submit" class="blade-secondary-action">Save</button>
                                            </form>
                                        </details>
                                    @endcan

                                    @can('complete', $visit)
                                        <details class="blade-row-actions">
                                            <summary>Complete</summary>
                                            <form method="POST" action="{{ route('crm.site-visits.complete', $visit) }}" class="blade-inline-form">
                                                @csrf
                                                @method('PATCH')
                                                <select name="outcome" required>
                                                    @foreach ($outcomes as $value => $label)
                                                        <option value="{{ $value }}">{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                                <textarea name="outcome_notes" required maxlength="5000" rows="2" placeholder="Outcome notes"></textarea>
                                                <input type="datetime-local" name="next_follow_up_at">
                                                <button type="submit" class="blade-primary-action">Complete</button>
                                            </form>
                                        </details>
                                    @endcan

                                    @can('cancel', $visit)
                                        <details class="blade-row-actions">
                                            <summary>Cancel</summary>
                                            <form method="POST" action="{{ route('crm.site-visits.cancel', $visit) }}" class="blade-inline-form">
                                                @csrf
                                                @method('PATCH')
                                                <textarea name="reason" required maxlength="1000" rows="2" placeholder="Cancellation reason"></textarea>
                                                <button type="submit" class="blade-secondary-action">Cancel visit</button>
                                            </form>
                                        </details>
                                    @endcan

                                    @cannot('update', $visit)
                                        @cannot('complete', $visit)
                                            @cannot('cancel', $visit)
                                                <span>No action</span>
                                            @endcannot
                                        @endcannot
                                    @endcannot
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8">No site visits match the selected filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="blade-pagination">
                {{ $visits->links() }}
            </div>
        </section>
    </div>
@endsection
