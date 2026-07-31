@extends('layouts.builder360-classic')

@section('title', 'Project Approvals - Builder360 ERP-CRM')

@section('content')
<div class="blade-workspace" aria-labelledby="project-approvals-title">
        <header class="blade-workspace-header">
            <div>
                <p class="blade-dashboard-eyebrow">Legal and Approvals</p>
                <h1 id="project-approvals-title">Project Approval Register</h1>
                <p>
                    Workspace for authority approvals, application numbers,
                    approval validity, document references, required-for usage and independent verification.
                    This is an operational tracking register only and is not legal advice.
                </p>
            </div>
            <nav class="blade-workspace-actions" aria-label="Workspace actions">
                <a href="{{ route('builder360.dashboard') }}">Dashboard</a>
                <a href="{{ route('legal.rera-registrations.index') }}">RERA</a>
                <a href="{{ route('legal.compliance-obligations.index') }}">Compliance Calendar</a>
                <a href="{{ route('documents.index') }}">Documents</a>
                <a href="{{ route('legal.project-approvals.index') }}">Reset filters</a>
            </nav>
        </header>

        @if (session('status'))
            <section class="blade-alert blade-alert-success" role="status">{{ session('status') }}</section>
        @endif

        @if ($errors->any())
            <section class="blade-alert blade-alert-error" role="alert">
                <strong>Project approval action was not saved.</strong>
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
                        <h2>Record project approval</h2>
                    </div>
                    <small>{{ $canCreateApproval ? 'Authorized' : 'Read only' }}</small>
                </div>

                @if ($canCreateApproval)
                    <form method="POST" action="{{ route('legal.project-approvals.store') }}" class="blade-form-grid">
                        @csrf

                        <label class="blade-form-wide">
                            Project
                            <select name="project_id" required>
                                <option value="">Select active project</option>
                                @foreach ($projects as $project)
                                    <option value="{{ $project->id }}" @selected((string) old('project_id') === (string) $project->id)>
                                        {{ $project->code }} - {{ $project->name }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label>
                            Approval code
                            <input type="text" name="approval_code" value="{{ old('approval_code') }}" maxlength="80" required>
                        </label>

                        <label>
                            Approval type
                            <select name="approval_type" required>
                                @foreach ($approvalTypes as $value => $label)
                                    <option value="{{ $value }}" @selected(old('approval_type', 'Commencement Certificate') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label>
                            Authority name
                            <input type="text" name="authority_name" value="{{ old('authority_name') }}" maxlength="160" required>
                        </label>

                        <label>
                            Application number
                            <input type="text" name="application_number" value="{{ old('application_number') }}" maxlength="120">
                        </label>

                        <label>
                            Status
                            <select name="status" required>
                                <option value="applied" @selected(old('status', 'applied') === 'applied')>Applied</option>
                                <option value="approved" @selected(old('status') === 'approved')>Approved</option>
                            </select>
                        </label>

                        <label>
                            Applied on
                            <input type="date" name="applied_on" value="{{ old('applied_on') }}">
                        </label>

                        <label>
                            Approved on
                            <input type="date" name="approved_on" value="{{ old('approved_on') }}">
                        </label>

                        <label>
                            Expires on
                            <input type="date" name="expires_on" value="{{ old('expires_on') }}">
                        </label>

                        <label>
                            Required for
                            <input type="text" name="required_for" value="{{ old('required_for') }}" maxlength="160" placeholder="Occupation certificate, launch, handover">
                        </label>

                        <label class="blade-form-wide">
                            Document reference
                            <input type="text" name="document_reference" value="{{ old('document_reference') }}" maxlength="255">
                        </label>

                        <fieldset class="blade-form-wide blade-fieldset">
                            <legend>Conditions</legend>
                            <div class="blade-form-grid">
                                <label>
                                    Condition 1
                                    <input type="text" name="conditions[0]" value="{{ old('conditions.0') }}" maxlength="500">
                                </label>
                                <label>
                                    Condition 2
                                    <input type="text" name="conditions[1]" value="{{ old('conditions.1') }}" maxlength="500">
                                </label>
                            </div>
                        </fieldset>

                        <button type="submit" class="blade-primary-action">Record approval</button>
                    </form>
                @else
                    <p class="blade-workspace-note">Your role can view project approvals but cannot create approval records.</p>
                @endif
            </article>

            <article class="blade-dashboard-card">
                <div class="blade-dashboard-section-title">
                    <div>
                        <span class="blade-dashboard-label">Controls</span>
                        <h2>Approval filters</h2>
                    </div>
                    <small>{{ $approvals->total() }} record(s)</small>
                </div>

                <form method="GET" action="{{ route('legal.project-approvals.index') }}" class="blade-filter-grid blade-filter-grid-compact">
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
                        Approval type
                        <input type="text" name="approval_type" value="{{ $filters['approval_type'] ?? '' }}" maxlength="120">
                    </label>
                    <label>
                        Expiring within days
                        <input type="number" name="expires_within_days" value="{{ $filters['expires_within_days'] ?? '' }}" min="0" max="3650">
                    </label>
                    <button type="submit" class="blade-secondary-action">Apply filters</button>
                </form>

                <p class="blade-workspace-note">Verification is blocked for the same user who recorded the approval.</p>
            </article>
        </section>

        <section class="blade-dashboard-card">
            <div class="blade-dashboard-section-title">
                <div>
                    <span class="blade-dashboard-label">Register</span>
                    <h2>Project approvals</h2>
                </div>
                <small>{{ $approvals->firstItem() ?? 0 }}-{{ $approvals->lastItem() ?? 0 }} of {{ $approvals->total() }}</small>
            </div>

            <div class="blade-dashboard-table-wrap">
                <table class="blade-dashboard-table">
                    <thead>
                        <tr>
                            <th scope="col">Approval</th>
                            <th scope="col">Project</th>
                            <th scope="col">Dates</th>
                            <th scope="col">Document / conditions</th>
                            <th scope="col">Workflow</th>
                            <th scope="col">Status</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($approvals as $approval)
                            <tr>
                                <td>
                                    <strong>{{ $approval->approval_code }}</strong>
                                    <span>{{ $approval->approval_type }}</span>
                                    <span>{{ $approval->authority_name }}</span>
                                    <span>{{ $approval->application_number ?? 'No application number' }}</span>
                                </td>
                                <td>
                                    <strong>{{ $approval->project?->code ?? 'Project missing' }}</strong>
                                    <span>{{ $approval->project?->name ?? 'Project missing' }}</span>
                                    <span>Required for: {{ $approval->required_for ?? 'Not specified' }}</span>
                                </td>
                                <td>
                                    <strong>Applied {{ $approval->applied_on?->format('d M Y') ?? 'Not captured' }}</strong>
                                    <span>Approved {{ $approval->approved_on?->format('d M Y') ?? 'Not captured' }}</span>
                                    <span>Expires {{ $approval->expires_on?->format('d M Y') ?? 'Not captured' }}</span>
                                </td>
                                <td>
                                    <strong>{{ $approval->document_reference ?? 'No document reference' }}</strong>
                                    @forelse (($approval->conditions ?? []) as $condition)
                                        <span>{{ $condition }}</span>
                                    @empty
                                        <span>No conditions captured</span>
                                    @endforelse
                                </td>
                                <td>
                                    <strong>Responsible {{ $approval->responsibleUser?->name ?? 'User missing' }}</strong>
                                    <span>Verified by {{ $approval->verifiedBy?->name ?? 'Pending' }}</span>
                                    <span>{{ $approval->verified_at?->format('d M Y H:i') ?? 'Decision pending' }}</span>
                                </td>
                                <td>{{ $statuses[$approval->status] ?? str($approval->status)->headline() }}</td>
                                <td>
                                    @can('verify', $approval)
                                        <details class="blade-row-actions">
                                            <summary>Verify</summary>
                                            <form method="POST" action="{{ route('legal.project-approvals.verify', $approval) }}" class="blade-inline-form">
                                                @csrf
                                                @method('PATCH')
                                                <textarea name="verification_note" maxlength="2000" rows="2" placeholder="Verification note"></textarea>
                                                <button type="submit" class="blade-primary-action">Verify approval</button>
                                            </form>
                                        </details>
                                    @else
                                        <span>No action</span>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">No project approvals match the selected filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="blade-pagination">{{ $approvals->links() }}</div>
        </section>
    </div>
@endsection
