@extends('layouts.builder360-classic')

@section('title', 'RERA Registrations - Builder360 ERP-CRM')

@section('content')
<div class="blade-workspace" aria-labelledby="rera-registrations-title">
        <header class="blade-workspace-header">
            <div>
                <p class="blade-dashboard-eyebrow">Legal and RERA Tracking</p>
                <h1 id="rera-registrations-title">RERA Registration Register</h1>
                <p>
                    Workspace for project-wise RERA registration tracking,
                    validity reminders, document references, maker-checker verification and audit history.
                    This is a tracking register only and is not legal advice.
                </p>
            </div>
            <nav class="blade-workspace-actions" aria-label="Workspace actions">
                <a href="{{ route('builder360.dashboard') }}">Dashboard</a>
                <a href="{{ route('legal.project-approvals.index') }}">Project Approvals</a>
                <a href="{{ route('legal.compliance-obligations.index') }}">Compliance Calendar</a>
                <a href="{{ route('documents.index') }}">Documents</a>
                <a href="{{ route('legal.rera-registrations.index') }}">Reset filters</a>
            </nav>
        </header>

        @if (session('status'))
            <section class="blade-alert blade-alert-success" role="status">{{ session('status') }}</section>
        @endif

        @if ($errors->any())
            <section class="blade-alert blade-alert-error" role="alert">
                <strong>RERA action was not saved.</strong>
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
                        <h2>Submit RERA registration</h2>
                    </div>
                    <small>{{ $canCreateRegistration ? 'Authorized' : 'Read only' }}</small>
                </div>

                @if ($canCreateRegistration)
                    <form method="POST" action="{{ route('legal.rera-registrations.store') }}" class="blade-form-grid">
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
                            Registration number
                            <input type="text" name="registration_number" value="{{ old('registration_number') }}" maxlength="80" required>
                        </label>

                        <label>
                            Authority name
                            <input type="text" name="authority_name" value="{{ old('authority_name', 'MahaRERA') }}" maxlength="160" required>
                        </label>

                        <label>
                            State code
                            <input type="text" name="state_code" value="{{ old('state_code', 'MH') }}" maxlength="10" required>
                        </label>

                        <label>
                            Registered on
                            <input type="date" name="registered_on" value="{{ old('registered_on', now()->toDateString()) }}" required>
                        </label>

                        <label>
                            Expires on
                            <input type="date" name="expires_on" value="{{ old('expires_on') }}">
                        </label>

                        <label class="blade-form-wide">
                            Document reference
                            <input type="text" name="document_reference" value="{{ old('document_reference') }}" maxlength="255" placeholder="Managed document reference or authority certificate number">
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

                        <button type="submit" class="blade-primary-action">Submit RERA registration</button>
                    </form>
                @else
                    <p class="blade-workspace-note">Your role can view RERA registrations but cannot submit new registrations.</p>
                @endif
            </article>

            <article class="blade-dashboard-card">
                <div class="blade-dashboard-section-title">
                    <div>
                        <span class="blade-dashboard-label">Controls</span>
                        <h2>RERA filters</h2>
                    </div>
                    <small>{{ $registrations->total() }} record(s)</small>
                </div>

                <form method="GET" action="{{ route('legal.rera-registrations.index') }}" class="blade-filter-grid blade-filter-grid-compact">
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
                        Expiring within days
                        <input type="number" name="expires_within_days" value="{{ $filters['expires_within_days'] ?? '' }}" min="0" max="3650">
                    </label>
                    <button type="submit" class="blade-secondary-action">Apply filters</button>
                </form>

                <p class="blade-workspace-note">
                    RERA dates, authority records and statutory correctness must be validated by the client or appointed legal expert before production reliance.
                </p>
            </article>
        </section>

        <section class="blade-dashboard-card">
            <div class="blade-dashboard-section-title">
                <div>
                    <span class="blade-dashboard-label">Register</span>
                    <h2>RERA registrations</h2>
                </div>
                <small>{{ $registrations->firstItem() ?? 0 }}-{{ $registrations->lastItem() ?? 0 }} of {{ $registrations->total() }}</small>
            </div>

            <div class="blade-dashboard-table-wrap">
                <table class="blade-dashboard-table">
                    <thead>
                        <tr>
                            <th scope="col">Registration</th>
                            <th scope="col">Project</th>
                            <th scope="col">Validity</th>
                            <th scope="col">Documents / conditions</th>
                            <th scope="col">Workflow</th>
                            <th scope="col">Status</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($registrations as $registration)
                            <tr>
                                <td>
                                    <strong>{{ $registration->registration_number }}</strong>
                                    <span>{{ $registration->authority_name }}</span>
                                    <span>State {{ $registration->state_code }}</span>
                                </td>
                                <td>
                                    <strong>{{ $registration->project?->code ?? 'Project missing' }}</strong>
                                    <span>{{ $registration->project?->name ?? 'Project missing' }}</span>
                                </td>
                                <td>
                                    <strong>Registered {{ $registration->registered_on?->format('d M Y') ?? 'Pending' }}</strong>
                                    <span>Expires {{ $registration->expires_on?->format('d M Y') ?? 'Not captured' }}</span>
                                </td>
                                <td>
                                    <strong>{{ $registration->document_reference ?? 'No document reference' }}</strong>
                                    @forelse (($registration->conditions ?? []) as $condition)
                                        <span>{{ $condition }}</span>
                                    @empty
                                        <span>No conditions captured</span>
                                    @endforelse
                                </td>
                                <td>
                                    <strong>Submitted by {{ $registration->createdBy?->name ?? 'User missing' }}</strong>
                                    <span>Verified by {{ $registration->verifiedBy?->name ?? 'Pending' }}</span>
                                    <span>{{ $registration->verified_at?->format('d M Y H:i') ?? 'Decision pending' }}</span>
                                </td>
                                <td>{{ $statuses[$registration->status] ?? str($registration->status)->headline() }}</td>
                                <td>
                                    @can('verify', $registration)
                                        <details class="blade-row-actions">
                                            <summary>Verify</summary>
                                            <form method="POST" action="{{ route('legal.rera-registrations.verify', $registration) }}" class="blade-inline-form">
                                                @csrf
                                                @method('PATCH')
                                                <textarea name="verification_note" maxlength="2000" rows="2" placeholder="Verification note" aria-label="RERA verification note"></textarea>
                                                <button type="submit" class="blade-primary-action">Verify RERA</button>
                                            </form>
                                        </details>
                                    @else
                                        <span>No action</span>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">No RERA registrations match the selected filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="blade-pagination">{{ $registrations->links() }}</div>
        </section>
    </div>
@endsection
