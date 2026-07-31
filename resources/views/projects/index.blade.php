@extends('layouts.builder360-classic')

@section('title', 'Project Master - Builder360 ERP-CRM')

@section('content')
@php
        $money = fn ($amount) => 'Rs. '.number_format((float) ($amount ?? 0), 2);
        $percent = fn ($amount) => rtrim(rtrim(number_format((float) ($amount ?? 0), 2), '0'), '.').'%';
    @endphp

    <div class="blade-workspace" aria-labelledby="project-master-title">
        <header class="blade-workspace-header">
            <div>
                <p class="blade-dashboard-eyebrow">Projects</p>
                <h1 id="project-master-title">Project Master</h1>
                <p>
                    Workspace for project master data, branch/company access,
                    dates, budget, ROI, team assignment, unit/bookings summary and cost-ROI export.
                </p>
            </div>
            <nav class="blade-workspace-actions" aria-label="Workspace actions">
                <a href="{{ route('builder360.dashboard') }}">Dashboard</a>
                <a href="{{ route('inventory.units.index') }}">Unit Inventory</a>
                <a href="{{ route('inventory.unit-price-versions.index') }}">Unit Pricing</a>
                <a href="{{ route('projects.cost-roi.export', array_merge(request()->query(), ['format' => 'csv'])) }}">Export Cost/ROI CSV</a>
                <a href="{{ route('projects.index') }}">Reset filters</a>
            </nav>
        </header>

        @if (session('status'))
            <section class="blade-alert blade-alert-success" role="status">
                {{ session('status') }}
            </section>
        @endif

        @if ($errors->any())
            <section class="blade-alert blade-alert-error" role="alert">
                <strong>Project action was not saved.</strong>
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
                        <h2>Create project master</h2>
                    </div>
                    <small>{{ $canCreateProject ? 'Authorized' : 'Read only' }}</small>
                </div>

                @if ($canCreateProject)
                    <form method="POST" action="{{ route('projects.store') }}" class="blade-form-grid">
                        @csrf

                        <x-forms.company-context :companies="$companies" required />

                        <label>
                            Branch
                            <select name="branch_id">
                                <option value="">No branch</option>
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->id }}" @selected((string) old('branch_id') === (string) $branch->id)>
                                        {{ $branch->code }} - {{ $branch->name }} - {{ $branch->city }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label>
                            Project code
                            <input type="text" name="code" value="{{ old('code') }}" maxlength="32" pattern="[A-Z0-9-]+" placeholder="SKY-PUN" required>
                        </label>

                        <label>
                            Project name
                            <input type="text" name="name" value="{{ old('name') }}" maxlength="255" required>
                        </label>

                        <label>
                            Project type
                            <select name="project_type" required>
                                @foreach ($projectTypes as $value => $label)
                                    <option value="{{ $value }}" @selected(old('project_type', 'residential') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label>
                            Status
                            <select name="status" required>
                                @foreach ($statuses as $value => $label)
                                    <option value="{{ $value }}" @selected(old('status', 'planned') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label>
                            City
                            <input type="text" name="city" value="{{ old('city') }}" maxlength="120" required>
                        </label>

                        <label>
                            State code
                            <input type="text" name="state" value="{{ old('state', 'MH') }}" maxlength="2" pattern="[A-Z]{2}" required>
                        </label>

                        <label>
                            Budget amount
                            <input type="number" name="budget_amount" value="{{ old('budget_amount', 0) }}" min="0" step="0.01">
                        </label>

                        <label>
                            Target ROI %
                            <input type="number" name="target_roi_percent" value="{{ old('target_roi_percent', 0) }}" min="0" max="999.99" step="0.01">
                        </label>

                        <label>
                            Starts on
                            <input type="date" name="starts_on" value="{{ old('starts_on') }}">
                        </label>

                        <label>
                            Ends on
                            <input type="date" name="ends_on" value="{{ old('ends_on') }}">
                        </label>

                        <button type="submit" class="blade-primary-action">Create project</button>
                    </form>
                @else
                    <p class="blade-workspace-note">Your role can view projects but cannot create master records.</p>
                @endif
            </article>

            <article class="blade-dashboard-card">
                <div class="blade-dashboard-section-title">
                    <div>
                        <span class="blade-dashboard-label">Controls</span>
                        <h2>Project filters</h2>
                    </div>
                    <small>{{ $projects->total() }} record(s)</small>
                </div>

                <form method="GET" action="{{ route('projects.index') }}" class="blade-filter-grid blade-filter-grid-compact">
                    <x-forms.company-context :companies="$companies" :selected="$filters['company_id'] ?? null" placeholder="All companies" />

                    <label>
                        Branch
                        <select name="branch_id">
                            <option value="">All branches</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" @selected((string) ($filters['branch_id'] ?? '') === (string) $branch->id)>
                                    {{ $branch->code }}
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
                        Type
                        <select name="project_type">
                            <option value="">All types</option>
                            @foreach ($projectTypes as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['project_type'] ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label>
                        Search
                        <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" maxlength="120" placeholder="Code, name, city">
                    </label>

                    <button type="submit" class="blade-secondary-action">Apply filters</button>
                </form>

                <p class="blade-workspace-note">
                    Cost/ROI export uses project, unit, booking, collection, procurement and construction data.
                </p>
            </article>
        </section>

        <section class="blade-dashboard-card">
            <div class="blade-dashboard-section-title">
                <div>
                    <span class="blade-dashboard-label">Register</span>
                    <h2>Project master register</h2>
                </div>
                <small>{{ $projects->firstItem() ?? 0 }}-{{ $projects->lastItem() ?? 0 }} of {{ $projects->total() }}</small>
            </div>

            <div class="blade-dashboard-table-wrap">
                <table class="blade-dashboard-table">
                    <thead>
                        <tr>
                            <th scope="col">Project</th>
                            <th scope="col">Scope</th>
                            <th scope="col">Timeline</th>
                            <th scope="col">Budget / ROI</th>
                            <th scope="col">Activity summary</th>
                            <th scope="col">Team</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($projects as $project)
                            <tr>
                                <td>
                                    <strong>{{ $project->code }}</strong>
                                    <span>{{ $project->name }}</span>
                                    <span>{{ $projectTypes[$project->project_type] ?? str($project->project_type)->headline() }} / {{ $statuses[$project->status] ?? str($project->status)->headline() }}</span>
                                </td>
                                <td>
                                    <strong>{{ $project->company?->code ?? 'Company missing' }}</strong>
                                    <span>{{ $project->branch?->code ?? 'No branch' }}</span>
                                    <span>{{ $project->city }}, {{ $project->state }}</span>
                                </td>
                                <td>
                                    <strong>{{ $project->starts_on?->format('d M Y') ?? 'Start pending' }}</strong>
                                    <span>{{ $project->ends_on?->format('d M Y') ?? 'End pending' }}</span>
                                </td>
                                <td>
                                    <strong>{{ $money($project->budget_amount) }}</strong>
                                    <span>Target ROI: {{ $percent($project->target_roi_percent) }}</span>
                                    @if ($healthScore = ($projectHealthScores[$project->id] ?? null))
                                        <span>Health: {{ $healthScore->score }} / 100 · {{ str($healthScore->band)->headline() }}</span>
                                        <span>Rule v{{ $healthScore->ruleVersion }} · {{ $healthScore->calculatedAt->format('d M Y H:i') }}</span>
                                    @else
                                        <span>Health score not calculated</span>
                                    @endif
                                </td>
                                <td>
                                    <strong>{{ (int) $project->units_count }} units</strong>
                                    <span>{{ (int) $project->bookings_count }} bookings</span>
                                    <span>Revenue: {{ $money($project->booked_revenue_sum) }}</span>
                                    <span>Collections: {{ $money($project->approved_collections_sum) }}</span>
                                </td>
                                <td>
                                    @forelse ($project->teamAssignments as $assignment)
                                        <span>
                                            {{ $assignment->user?->name ?? 'User missing' }} -
                                            {{ $assignment->role_label }} -
                                            {{ str($assignment->status)->headline() }}
                                        </span>
                                    @empty
                                        <span>No team assignment</span>
                                    @endforelse
                                </td>
                                <td>
                                    @can('update', $project)
                                        <details class="blade-row-actions blade-scoring-evidence">
                                            <summary>Health evidence</summary>
                                            <form method="POST" action="{{ route('projects.health-score.update', $project) }}" class="blade-inline-form blade-scoring-evidence-form">
                                                @csrf
                                                @method('PATCH')
                                                @php($healthInputs = $project->scoring_inputs ?? [])
                                                @foreach ([
                                                    'construction_progress' => 'Construction progress',
                                                    'sales_progress' => 'Sales progress',
                                                    'collection_progress' => 'Collection progress',
                                                    'budget_control' => 'Budget control',
                                                    'schedule_variance' => 'Schedule adherence',
                                                    'inventory_health' => 'Inventory health',
                                                    'approval_delays' => 'Approval timeliness',
                                                    'procurement_delays' => 'Procurement timeliness',
                                                    'receivables' => 'Receivables health',
                                                ] as $evidenceKey => $evidenceLabel)
                                                    <label>
                                                        {{ $evidenceLabel }}
                                                        <input type="number" name="{{ $evidenceKey }}" value="{{ $healthInputs[$evidenceKey] ?? '' }}" min="0" max="100" step="0.01" required>
                                                    </label>
                                                @endforeach
                                                <p class="blade-workspace-note">Enter verified values from 0 to 100. Saving recalculates the score using the active Project Health rule.</p>
                                                <button type="submit" class="blade-secondary-action">Calculate health score</button>
                                            </form>
                                        </details>

                                        <details class="blade-row-actions">
                                            <summary>Edit</summary>
                                            <form method="POST" action="{{ route('projects.update', $project) }}" class="blade-inline-form">
                                                @csrf
                                                @method('PATCH')
                                                <x-forms.company-context :companies="$companies" :selected="$project->company_id" required />
                                                <select name="branch_id">
                                                    <option value="">No branch</option>
                                                    @foreach ($branches as $branch)
                                                        <option value="{{ $branch->id }}" @selected($project->branch_id === $branch->id)>{{ $branch->code }} - {{ $branch->name }}</option>
                                                    @endforeach
                                                </select>
                                                <input type="text" name="code" value="{{ $project->code }}" maxlength="32" required>
                                                <input type="text" name="name" value="{{ $project->name }}" maxlength="255" required>
                                                <select name="project_type" required>
                                                    @foreach ($projectTypes as $value => $label)
                                                        <option value="{{ $value }}" @selected($project->project_type === $value)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                                <select name="status" required>
                                                    @foreach ($statuses as $value => $label)
                                                        <option value="{{ $value }}" @selected($project->status === $value)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                                <input type="text" name="city" value="{{ $project->city }}" maxlength="120" required>
                                                <input type="text" name="state" value="{{ $project->state }}" maxlength="2" required>
                                                <input type="number" name="budget_amount" value="{{ $project->budget_amount }}" min="0" step="0.01">
                                                <input type="number" name="target_roi_percent" value="{{ $project->target_roi_percent }}" min="0" max="999.99" step="0.01">
                                                <input type="date" name="starts_on" value="{{ $project->starts_on?->toDateString() }}">
                                                <input type="date" name="ends_on" value="{{ $project->ends_on?->toDateString() }}">
                                                <button type="submit" class="blade-secondary-action">Save project</button>
                                            </form>
                                        </details>
                                    @endcan

                                    @if ($canManageProjectTeam)
                                        <details class="blade-row-actions">
                                            <summary>Assign team</summary>
                                            <form method="POST" action="{{ route('projects.team-assignments.store', $project) }}" class="blade-inline-form">
                                                @csrf
                                                <select name="user_id" required>
                                                    <option value="">Select user</option>
                                                    @foreach ($assignableUsers as $assignableUser)
                                                        <option value="{{ $assignableUser->id }}">{{ $assignableUser->name }} - {{ $assignableUser->email }}</option>
                                                    @endforeach
                                                </select>
                                                <select name="employee_id">
                                                    <option value="">No employee profile</option>
                                                    @foreach ($employees as $employee)
                                                        <option value="{{ $employee->id }}">{{ $employee->employee_code }} - {{ $employee->name }}</option>
                                                    @endforeach
                                                </select>
                                                <input type="text" name="role_label" maxlength="120" placeholder="Role label" required>
                                                <input type="text" name="department" maxlength="120" placeholder="Department">
                                                <select name="access_level" required>
                                                    @foreach ($accessLevels as $value => $label)
                                                        <option value="{{ $value }}">{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                                <input type="date" name="starts_on">
                                                <input type="date" name="ends_on">
                                                <textarea name="notes" maxlength="2000" rows="2" placeholder="Assignment notes"></textarea>
                                                <button type="submit" class="blade-primary-action">Assign</button>
                                            </form>
                                        </details>
                                    @endif

                                    @foreach ($project->teamAssignments->where('status', 'active') as $assignment)
                                        @can('delete', $assignment)
                                            <form method="POST" action="{{ route('projects.team-assignments.destroy', [$project, $assignment]) }}" class="blade-inline-form">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="blade-secondary-action">Revoke {{ $assignment->user?->name }}</button>
                                            </form>
                                        @endcan
                                    @endforeach

                                    @cannot('update', $project)
                                        @if (! $canManageProjectTeam)
                                            <span>No action</span>
                                        @endif
                                    @endcannot
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">No projects match the selected filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="blade-pagination">
                {{ $projects->links() }}
            </div>
        </section>
    </div>
@endsection
