@extends('layouts.builder360-classic')

@section('title', 'Society Formation - Builder360 ERP-CRM')

@section('content')
<div class="blade-workspace" aria-labelledby="societies-title">
        <header class="blade-workspace-header">
            <div>
                <p class="blade-dashboard-eyebrow">After-Sales and Society Operations</p>
                <h1 id="societies-title">Society Formation</h1>
                <p>
                    Workspace for society or association formation, registration progress,
                    committee details, handover stage tracking and status update history.
                </p>
            </div>
            <nav class="blade-workspace-actions" aria-label="Workspace actions">
                <a href="{{ route('builder360.dashboard') }}">Dashboard</a>
                <a href="{{ route('maintenance.handover-items.index') }}">Common Area Handover</a>
                <a href="{{ route('maintenance.dues.index') }}">Maintenance Dues</a>
                <a href="{{ route('possession.handovers.index') }}">Possession</a>
                <a href="{{ route('maintenance.societies.index') }}">Reset filters</a>
            </nav>
        </header>

        @if (session('status'))
            <section class="blade-alert blade-alert-success" role="status">{{ session('status') }}</section>
        @endif

        @if ($errors->any())
            <section class="blade-alert blade-alert-error" role="alert">
                <strong>Society action was not saved.</strong>
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
                        <h2>Create society formation</h2>
                    </div>
                    <small>{{ $canCreateSociety ? 'Authorized' : 'Read only' }}</small>
                </div>

                @if ($canCreateSociety)
                    <form method="POST" action="{{ route('maintenance.societies.store') }}" class="blade-form-grid">
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

                        <label class="blade-form-wide">
                            Society / association name
                            <input type="text" name="society_name" value="{{ old('society_name') }}" maxlength="255" required>
                        </label>

                        <label>
                            Association type
                            <select name="association_type">
                                @foreach ($associationTypes as $value => $label)
                                    <option value="{{ $value }}" @selected(old('association_type', 'cooperative_society') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label>
                            Total units
                            <input type="number" name="total_units" value="{{ old('total_units') }}" min="1" max="10000" required>
                        </label>

                        <label>
                            Occupied units
                            <input type="number" name="occupied_units" value="{{ old('occupied_units', 0) }}" min="0" max="10000">
                        </label>

                        <label>
                            Status
                            <select name="status">
                                @foreach ($statuses as $value => $label)
                                    <option value="{{ $value }}" @selected(old('status', 'draft') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label>
                            Progress %
                            <input type="number" name="progress_percent" value="{{ old('progress_percent') }}" min="0" max="100">
                        </label>

                        <label>
                            Application filed on
                            <input type="date" name="application_filed_on" value="{{ old('application_filed_on') }}" max="{{ now()->toDateString() }}">
                        </label>

                        <label>
                            Registered on
                            <input type="date" name="registered_on" value="{{ old('registered_on') }}" max="{{ now()->toDateString() }}">
                        </label>

                        <label>
                            Target handover on
                            <input type="date" name="target_handover_on" value="{{ old('target_handover_on') }}">
                        </label>

                        <label>
                            Registration number
                            <input type="text" name="registration_number" value="{{ old('registration_number') }}" maxlength="120">
                        </label>

                        <label>
                            Current stage
                            <input type="text" name="current_stage" value="{{ old('current_stage') }}" maxlength="120">
                        </label>

                        <label class="blade-form-wide">
                            Next step
                            <input type="text" name="next_step" value="{{ old('next_step') }}" maxlength="255">
                        </label>

                        <button type="submit" class="blade-primary-action">Create society</button>
                    </form>
                @else
                    <p class="blade-workspace-note">Your role can view society records but cannot create them.</p>
                @endif
            </article>

            <article class="blade-dashboard-card">
                <div class="blade-dashboard-section-title">
                    <div>
                        <span class="blade-dashboard-label">Controls</span>
                        <h2>Society filters</h2>
                    </div>
                    <small>{{ $societies->total() }} record(s)</small>
                </div>

                <form method="GET" action="{{ route('maintenance.societies.index') }}" class="blade-filter-grid blade-filter-grid-compact">
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
                    <button type="submit" class="blade-secondary-action">Apply filters</button>
                </form>
            </article>
        </section>

        <section class="blade-dashboard-card">
            <div class="blade-dashboard-section-title">
                <div>
                    <span class="blade-dashboard-label">Register</span>
                    <h2>Society formation register</h2>
                </div>
                <small>{{ $societies->firstItem() ?? 0 }}-{{ $societies->lastItem() ?? 0 }} of {{ $societies->total() }}</small>
            </div>

            <div class="blade-dashboard-table-wrap">
                <table class="blade-dashboard-table">
                    <thead>
                        <tr>
                            <th scope="col">Formation</th>
                            <th scope="col">Project</th>
                            <th scope="col">Progress</th>
                            <th scope="col">Registration</th>
                            <th scope="col">Workflow</th>
                            <th scope="col">Status</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($societies as $society)
                            <tr>
                                <td>
                                    <strong>{{ $society->formation_number }}</strong>
                                    <span>{{ $society->society_name }}</span>
                                    <span>{{ $associationTypes[$society->association_type] ?? str($society->association_type)->headline() }}</span>
                                </td>
                                <td>
                                    <strong>{{ $society->project?->code ?? 'Project missing' }}</strong>
                                    <span>{{ $society->project?->name ?? 'Project missing' }}</span>
                                    <span>{{ $society->occupied_units }} / {{ $society->total_units }} occupied</span>
                                </td>
                                <td>
                                    <strong>{{ $society->progress_percent }}%</strong>
                                    <span>{{ $society->current_stage }}</span>
                                    <span>{{ $society->next_step ?? 'Next step not captured' }}</span>
                                </td>
                                <td>
                                    <strong>{{ $society->registration_number ?? 'Registration pending' }}</strong>
                                    <span>Filed {{ $society->application_filed_on?->format('d M Y') ?? 'Not filed' }}</span>
                                    <span>Registered {{ $society->registered_on?->format('d M Y') ?? 'Pending' }}</span>
                                    <span>Target handover {{ $society->target_handover_on?->format('d M Y') ?? 'Not set' }}</span>
                                </td>
                                <td>
                                    <strong>Created by {{ $society->createdBy?->name ?? 'User missing' }}</strong>
                                    <span>Updated by {{ $society->updatedBy?->name ?? 'Pending' }}</span>
                                </td>
                                <td>{{ $statuses[$society->status] ?? str($society->status)->headline() }}</td>
                                <td>
                                    @can('update', $society)
                                        <details class="blade-row-actions">
                                            <summary>Update status</summary>
                                            <form method="POST" action="{{ route('maintenance.societies.status', $society) }}" class="blade-inline-form">
                                                @csrf
                                                @method('PATCH')
                                                <select name="status" required>
                                                    @foreach ($statuses as $value => $label)
                                                        <option value="{{ $value }}" @selected($society->status === $value)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                                <input type="number" name="progress_percent" value="{{ $society->progress_percent }}" min="0" max="100" required>
                                                <input type="text" name="current_stage" value="{{ $society->current_stage }}" maxlength="120" placeholder="Current stage">
                                                <input type="text" name="next_step" value="{{ $society->next_step }}" maxlength="255" placeholder="Next step">
                                                <input type="text" name="registration_number" value="{{ $society->registration_number }}" maxlength="120" placeholder="Registration number">
                                                <textarea name="note" maxlength="1000" rows="2" placeholder="Status update note"></textarea>
                                                <button type="submit" class="blade-primary-action">Update society</button>
                                            </form>
                                        </details>
                                    @else
                                        <span>No action</span>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">No society records match the selected filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="blade-pagination">{{ $societies->links() }}</div>
        </section>
    </div>
@endsection
