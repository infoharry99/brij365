@extends('layouts.builder360-classic')

@section('title', 'Compliance Obligations - Builder360 ERP-CRM')

@section('content')
<div class="blade-workspace" aria-labelledby="compliance-obligations-title">
        <header class="blade-workspace-header">
            <div>
                <p class="blade-dashboard-eyebrow">Legal Compliance Calendar</p>
                <h1 id="compliance-obligations-title">Compliance Obligations</h1>
                <p>
                    Workspace for project and company compliance tasks,
                    due-date monitoring, priority, assignment, evidence capture, completion workflow and audit history.
                    This is a tracking register only and is not legal, tax or labour-law advice.
                </p>
            </div>
            <nav class="blade-workspace-actions" aria-label="Workspace actions">
                <a href="{{ route('builder360.dashboard') }}">Dashboard</a>
                <a href="{{ route('legal.rera-registrations.index') }}">RERA</a>
                <a href="{{ route('legal.project-approvals.index') }}">Project Approvals</a>
                <a href="{{ route('documents.index') }}">Documents</a>
                <a href="{{ route('legal.compliance-obligations.index') }}">Reset filters</a>
            </nav>
        </header>

        @if (session('status'))
            <section class="blade-alert blade-alert-success" role="status">{{ session('status') }}</section>
        @endif

        @if ($errors->any())
            <section class="blade-alert blade-alert-error" role="alert">
                <strong>Compliance obligation action was not saved.</strong>
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
                        <h2>Create compliance obligation</h2>
                    </div>
                    <small>{{ $canCreateObligation ? 'Authorized' : 'Read only' }}</small>
                </div>

                @if ($canCreateObligation)
                    <form method="POST" action="{{ route('legal.compliance-obligations.store') }}" class="blade-form-grid">
                        @csrf

                        <label>
                            Project
                            <select name="project_id">
                                <option value="">Company-level obligation</option>
                                @foreach ($projects as $project)
                                    <option value="{{ $project->id }}" @selected((string) old('project_id') === (string) $project->id)>
                                        {{ $project->code }} - {{ $project->name }}
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
                                        {{ $assignee->name }} - {{ $assignee->email }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label class="blade-form-wide">
                            Title
                            <input type="text" name="title" value="{{ old('title') }}" maxlength="255" required>
                        </label>

                        <label>
                            Compliance type
                            <input type="text" name="compliance_type" value="{{ old('compliance_type', 'RERA Quarterly Filing') }}" maxlength="120" required>
                        </label>

                        <label>
                            Due on
                            <input type="date" name="due_on" value="{{ old('due_on', now()->addDays(30)->toDateString()) }}" required>
                        </label>

                        <label>
                            Frequency
                            <select name="frequency" required>
                                @foreach ($frequencies as $value => $label)
                                    <option value="{{ $value }}" @selected(old('frequency', 'one_time') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label>
                            Priority
                            <select name="priority" required>
                                @foreach ($priorities as $value => $label)
                                    <option value="{{ $value }}" @selected(old('priority', 'normal') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="blade-form-wide">
                            Notes
                            <textarea name="notes" maxlength="5000" rows="3" placeholder="Requirement, authority, filing or internal instruction.">{{ old('notes') }}</textarea>
                        </label>

                        <button type="submit" class="blade-primary-action">Create obligation</button>
                    </form>
                @else
                    <p class="blade-workspace-note">Your role can view obligations but cannot create new obligations.</p>
                @endif
            </article>

            <article class="blade-dashboard-card">
                <div class="blade-dashboard-section-title">
                    <div>
                        <span class="blade-dashboard-label">Controls</span>
                        <h2>Compliance filters</h2>
                    </div>
                    <small>{{ $obligations->total() }} record(s)</small>
                </div>

                <form method="GET" action="{{ route('legal.compliance-obligations.index') }}" class="blade-filter-grid blade-filter-grid-compact">
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
                        Status
                        <select name="status">
                            <option value="">All statuses</option>
                            @foreach ($statuses as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        Compliance type
                        <input type="text" name="compliance_type" value="{{ $filters['compliance_type'] ?? '' }}" maxlength="120">
                    </label>
                    <label>
                        Due within days
                        <input type="number" name="due_within_days" value="{{ $filters['due_within_days'] ?? '' }}" min="0" max="3650">
                    </label>
                    <button type="submit" class="blade-secondary-action">Apply filters</button>
                </form>

                <p class="blade-workspace-note">
                    Completion requires an evidence document/reference. Use the Documents module for versioned document storage.
                </p>
            </article>
        </section>

        <section class="blade-dashboard-card">
            <div class="blade-dashboard-section-title">
                <div>
                    <span class="blade-dashboard-label">Register</span>
                    <h2>Compliance obligation register</h2>
                </div>
                <small>{{ $obligations->firstItem() ?? 0 }}-{{ $obligations->lastItem() ?? 0 }} of {{ $obligations->total() }}</small>
            </div>

            <div class="blade-dashboard-table-wrap">
                <table class="blade-dashboard-table">
                    <thead>
                        <tr>
                            <th scope="col">Obligation</th>
                            <th scope="col">Scope</th>
                            <th scope="col">Due / priority</th>
                            <th scope="col">Assignment</th>
                            <th scope="col">Evidence</th>
                            <th scope="col">Status</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($obligations as $obligation)
                            <tr>
                                <td>
                                    <strong>{{ $obligation->obligation_number }}</strong>
                                    <span>{{ $obligation->title }}</span>
                                    <span>{{ $obligation->compliance_type }}</span>
                                </td>
                                <td>
                                    <strong>{{ $obligation->project?->code ?? 'Company level' }}</strong>
                                    <span>{{ $obligation->project?->name ?? 'No project scope' }}</span>
                                </td>
                                <td>
                                    <strong>{{ $obligation->due_on?->format('d M Y') ?? 'Due date missing' }}</strong>
                                    <span>{{ $frequencies[$obligation->frequency] ?? str($obligation->frequency)->headline() }}</span>
                                    <span>{{ $priorities[$obligation->priority] ?? str($obligation->priority)->headline() }}</span>
                                </td>
                                <td>
                                    <strong>{{ $obligation->assignedTo?->name ?? 'Unassigned' }}</strong>
                                    <span>Completed by {{ $obligation->completedBy?->name ?? 'Pending' }}</span>
                                    <span>{{ $obligation->completed_at?->format('d M Y H:i') ?? 'Open' }}</span>
                                </td>
                                <td>
                                    <strong>{{ $obligation->evidence_document_reference ?? 'Evidence pending' }}</strong>
                                    <span>{{ $obligation->notes ?? 'No notes' }}</span>
                                </td>
                                <td>{{ $statuses[$obligation->status] ?? str($obligation->status)->headline() }}</td>
                                <td>
                                    @can('complete', $obligation)
                                        <details class="blade-row-actions">
                                            <summary>Complete</summary>
                                            <form method="POST" action="{{ route('legal.compliance-obligations.complete', $obligation) }}" class="blade-inline-form">
                                                @csrf
                                                @method('PATCH')
                                                <input type="text" name="evidence_document_reference" required maxlength="255" placeholder="Evidence document/reference">
                                                <textarea name="notes" maxlength="5000" rows="2" placeholder="Completion notes"></textarea>
                                                <button type="submit" class="blade-primary-action">Complete obligation</button>
                                            </form>
                                        </details>
                                    @else
                                        <span>No action</span>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">No compliance obligations match the selected filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="blade-pagination">{{ $obligations->links() }}</div>
        </section>
    </div>
@endsection
