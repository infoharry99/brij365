@extends('layouts.builder360-classic')

@section('title', 'Construction Progress - Builder360 ERP-CRM')

@section('content')
<div class="blade-workspace" aria-labelledby="construction-progress-title">
        <header class="blade-workspace-header">
            <div>
                <p class="blade-dashboard-eyebrow">Construction Operations</p>
                <h1 id="construction-progress-title">Construction Progress Workspace</h1>
                <p>
                    Workspace for project milestones, planned versus actual progress,
                    daily site reports, manpower, safety observations, blockers and approval control.
                </p>
            </div>
            <nav class="blade-workspace-actions" aria-label="Workspace actions">
                <a href="{{ route('builder360.dashboard') }}">Dashboard</a>
                <a href="{{ route('construction.milestones.index') }}">Milestones</a>
                <a href="{{ route('construction.daily-progress-reports.index') }}">Daily reports</a>
            </nav>
        </header>

        @if (session('status'))
            <section class="blade-alert blade-alert-success" role="status">
                {{ session('status') }}
            </section>
        @endif

        @if ($errors->any())
            <section class="blade-alert blade-alert-error" role="alert">
                <strong>Construction action was not completed.</strong>
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
                        <span class="blade-dashboard-label">Milestones</span>
                        <h2>Progress summary</h2>
                    </div>
                    <small>{{ $milestones->total() }} milestone record(s)</small>
                </div>

                <div class="blade-dashboard-metrics">
                    @foreach ($milestoneStatuses as $status => $label)
                        <div class="blade-dashboard-metric">
                            <span>{{ $label }}</span>
                            <strong>{{ number_format((int) ($milestoneMetrics[$status] ?? 0)) }}</strong>
                        </div>
                    @endforeach
                </div>
            </article>

            <article class="blade-dashboard-card">
                <div class="blade-dashboard-section-title">
                    <div>
                        <span class="blade-dashboard-label">Daily Progress Reports</span>
                        <h2>Approval summary</h2>
                    </div>
                    <small>{{ $dailyReports->total() }} DPR record(s)</small>
                </div>

                <div class="blade-dashboard-metrics">
                    @foreach ($dailyReportStatuses as $status => $label)
                        <div class="blade-dashboard-metric">
                            <span>{{ $label }}</span>
                            <strong>{{ number_format((int) ($dailyReportMetrics[$status] ?? 0)) }}</strong>
                        </div>
                    @endforeach
                </div>
            </article>
        </section>

        <section class="blade-workspace-grid">
            <article class="blade-dashboard-card">
                <div class="blade-dashboard-section-title">
                    <div>
                        <span class="blade-dashboard-label">Create</span>
                        <h2>New construction milestone</h2>
                    </div>
                    <small>{{ $canCreateMilestone ? 'Authorized' : 'Read only' }}</small>
                </div>

                @if ($canCreateMilestone)
                    <form method="POST" action="{{ route('construction.milestones.store') }}" class="blade-form-grid">
                        @csrf

                        <label>
                            Project
                            <select name="project_id" required>
                                <option value="">Select project</option>
                                @foreach ($projects as $project)
                                    <option value="{{ $project->id }}" @selected((string) old('project_id', $projects->first()?->id) === (string) $project->id)>
                                        {{ $project->code }} &middot; {{ $project->name }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label>
                            Milestone code
                            <input type="text" name="milestone_code" value="{{ old('milestone_code') }}" maxlength="40" required>
                        </label>

                        <label>
                            Milestone name
                            <input type="text" name="name" value="{{ old('name') }}" maxlength="255" required>
                        </label>

                        <label>
                            Phase
                            <input type="text" name="phase" value="{{ old('phase') }}" maxlength="120" required>
                        </label>

                        <label>
                            Planned start
                            <input type="date" name="planned_start_on" value="{{ old('planned_start_on') }}" required>
                        </label>

                        <label>
                            Planned end
                            <input type="date" name="planned_end_on" value="{{ old('planned_end_on') }}" required>
                        </label>

                        <label>
                            Weight %
                            <input type="number" name="weight_percent" value="{{ old('weight_percent') }}" min="0" max="100" step="0.01" required>
                        </label>

                        <button type="submit" class="blade-primary-action">Create milestone</button>
                    </form>
                @else
                    <p class="blade-workspace-note">Your role can view construction milestones but cannot create new milestones.</p>
                @endif
            </article>

            <article class="blade-dashboard-card">
                <div class="blade-dashboard-section-title">
                    <div>
                        <span class="blade-dashboard-label">Submit</span>
                        <h2>Daily progress report</h2>
                    </div>
                    <small>{{ $canCreateDailyReport ? 'Authorized' : 'Read only' }}</small>
                </div>

                @if ($canCreateDailyReport)
                    <form method="POST" action="{{ route('construction.daily-progress-reports.store') }}" class="blade-form-grid">
                        @csrf

                        <label>
                            Project
                            <select name="project_id" required>
                                <option value="">Select project</option>
                                @foreach ($projects as $project)
                                    <option value="{{ $project->id }}" @selected((string) old('project_id', $projects->first()?->id) === (string) $project->id)>
                                        {{ $project->code }} &middot; {{ $project->name }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label>
                            Report date
                            <input type="date" name="report_date" value="{{ old('report_date', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required>
                        </label>

                        <label>
                            Weather
                            <input type="text" name="weather" value="{{ old('weather') }}" maxlength="120" placeholder="Clear / rainy / cloudy">
                        </label>

                        <label>
                            Manpower count
                            <input type="number" name="manpower_count" value="{{ old('manpower_count', 0) }}" min="0" max="50000" required>
                        </label>

                        <label>
                            Milestone
                            <select name="progress_items[0][milestone_id]" required>
                                <option value="">Select milestone</option>
                                @foreach ($milestoneOptions as $milestone)
                                    <option value="{{ $milestone->id }}" @selected((string) old('progress_items.0.milestone_id') === (string) $milestone->id)>
                                        {{ $milestone->milestone_code }} &middot; {{ $milestone->name }} &middot; {{ number_format((float) $milestone->progress_percent, 2) }}%
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label>
                            Progress %
                            <input type="number" name="progress_items[0][progress_percent]" value="{{ old('progress_items.0.progress_percent') }}" min="0" max="100" step="0.01" required>
                        </label>

                        <label>
                            Work done
                            <textarea name="progress_items[0][work_done]" maxlength="1000" required>{{ old('progress_items.0.work_done') }}</textarea>
                        </label>

                        <label>
                            Work summary
                            <textarea name="work_summary" maxlength="8000" required>{{ old('work_summary') }}</textarea>
                        </label>

                        <label>
                            Safety observations
                            <textarea name="safety_observations" maxlength="5000">{{ old('safety_observations') }}</textarea>
                        </label>

                        <label>
                            Quality observations
                            <textarea name="quality_observations" maxlength="5000">{{ old('quality_observations') }}</textarea>
                        </label>

                        <label>
                            Blockers / delay reasons
                            <textarea name="blockers" maxlength="5000">{{ old('blockers') }}</textarea>
                        </label>

                        <button type="submit" class="blade-primary-action">Submit DPR</button>
                    </form>
                @else
                    <p class="blade-workspace-note">Your role can view daily reports but cannot submit new reports.</p>
                @endif
            </article>
        </section>

        <section class="blade-dashboard-card">
            <div class="blade-dashboard-section-title">
                <div>
                    <span class="blade-dashboard-label">Controls</span>
                    <h2>Milestone filters</h2>
                </div>
                <small>Company-level</small>
            </div>

            <form method="GET" action="{{ route('construction.milestones.index') }}" class="blade-filter-grid blade-filter-grid-compact">
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
                    Status
                    <select name="status">
                        <option value="">All statuses</option>
                        @foreach ($milestoneStatuses as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label>
                    Phase
                    <select name="phase">
                        <option value="">All phases</option>
                        @foreach ($phases as $phase)
                            <option value="{{ $phase }}" @selected(($filters['phase'] ?? '') === $phase)>{{ $phase }}</option>
                        @endforeach
                    </select>
                </label>

                <button type="submit" class="blade-secondary-action">Apply filters</button>
            </form>
        </section>

        <section class="blade-dashboard-card">
            <div class="blade-dashboard-section-title">
                <div>
                    <span class="blade-dashboard-label">Register</span>
                    <h2>Construction milestones</h2>
                </div>
                <small>{{ $milestones->firstItem() ?? 0 }}-{{ $milestones->lastItem() ?? 0 }} of {{ $milestones->total() }}</small>
            </div>

            <div class="blade-dashboard-table-wrap">
                <table class="blade-dashboard-table">
                    <thead>
                        <tr>
                            <th scope="col">Milestone</th>
                            <th scope="col">Project</th>
                            <th scope="col">Phase</th>
                            <th scope="col">Plan</th>
                            <th scope="col">Actual</th>
                            <th scope="col">Weight</th>
                            <th scope="col">Progress</th>
                            <th scope="col">Owner</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($milestones as $milestone)
                            <tr>
                                <td>
                                    <strong>{{ $milestone->milestone_code }}</strong>
                                    <span>{{ $milestone->name }}</span>
                                </td>
                                <td>
                                    <strong>{{ $milestone->project?->code ?? 'No project' }}</strong>
                                    <span>{{ $milestone->project?->name ?? 'Project missing' }}</span>
                                </td>
                                <td>{{ $milestone->phase }}</td>
                                <td>
                                    <strong>{{ $milestone->planned_start_on?->format('d M Y') }}</strong>
                                    <span>{{ $milestone->planned_end_on?->format('d M Y') }}</span>
                                </td>
                                <td>
                                    <strong>{{ $milestone->actual_start_on?->format('d M Y') ?? 'Not started' }}</strong>
                                    <span>{{ $milestone->actual_end_on?->format('d M Y') ?? 'Not completed' }}</span>
                                </td>
                                <td>{{ number_format((float) $milestone->weight_percent, 2) }}%</td>
                                <td>
                                    <strong>{{ number_format((float) $milestone->progress_percent, 2) }}%</strong>
                                    <span>{{ $milestoneStatuses[$milestone->status] ?? str($milestone->status)->headline() }}</span>
                                </td>
                                <td>{{ $milestone->createdBy?->name ?? 'System' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8">No milestones match the selected filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="blade-pagination">
                {{ $milestones->links() }}
            </div>
        </section>

        <section class="blade-dashboard-card">
            <div class="blade-dashboard-section-title">
                <div>
                    <span class="blade-dashboard-label">Controls</span>
                    <h2>Daily report filters</h2>
                </div>
                <small>Approval workflow</small>
            </div>

            <form method="GET" action="{{ route('construction.daily-progress-reports.index') }}" class="blade-filter-grid blade-filter-grid-compact">
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
                    Status
                    <select name="status">
                        <option value="">All statuses</option>
                        @foreach ($dailyReportStatuses as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label>
                    From
                    <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
                </label>

                <label>
                    To
                    <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
                </label>

                <button type="submit" class="blade-secondary-action">Apply filters</button>
            </form>
        </section>

        <section class="blade-dashboard-card">
            <div class="blade-dashboard-section-title">
                <div>
                    <span class="blade-dashboard-label">Register</span>
                    <h2>Daily progress reports</h2>
                </div>
                <small>{{ $dailyReports->firstItem() ?? 0 }}-{{ $dailyReports->lastItem() ?? 0 }} of {{ $dailyReports->total() }}</small>
            </div>

            <div class="blade-dashboard-table-wrap">
                <table class="blade-dashboard-table">
                    <thead>
                        <tr>
                            <th scope="col">DPR</th>
                            <th scope="col">Project</th>
                            <th scope="col">Summary</th>
                            <th scope="col">Manpower</th>
                            <th scope="col">Progress line</th>
                            <th scope="col">Status</th>
                            <th scope="col">Prepared / Approved</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($dailyReports as $report)
                            @php
                                $firstProgress = collect($report->progress_items ?? [])->first();
                            @endphp
                            <tr>
                                <td>
                                    <strong>{{ $report->report_number }}</strong>
                                    <span>{{ $report->report_date?->format('d M Y') }}</span>
                                </td>
                                <td>
                                    <strong>{{ $report->project?->code ?? 'No project' }}</strong>
                                    <span>{{ $report->project?->name ?? 'Project missing' }}</span>
                                </td>
                                <td>
                                    <strong>{{ str($report->work_summary)->limit(80) }}</strong>
                                    @if ($report->blockers)
                                        <span>Blocker: {{ str($report->blockers)->limit(70) }}</span>
                                    @endif
                                </td>
                                <td>{{ number_format($report->manpower_count) }}</td>
                                <td>
                                    @if ($firstProgress)
                                        <strong>{{ $firstProgress['milestone_code'] ?? 'Milestone' }} &middot; {{ number_format((float) ($firstProgress['progress_percent'] ?? 0), 2) }}%</strong>
                                        <span>{{ str($firstProgress['work_done'] ?? 'Work details unavailable')->limit(80) }}</span>
                                    @else
                                        No progress line
                                    @endif
                                </td>
                                <td>{{ $dailyReportStatuses[$report->status] ?? str($report->status)->headline() }}</td>
                                <td>
                                    <strong>{{ $report->preparedBy?->name ?? 'Unknown' }}</strong>
                                    <span>{{ $report->approvedBy?->name ?? 'Approval pending' }}</span>
                                </td>
                                <td>
                                    @can('approve', $report)
                                        <div class="blade-table-action-stack">
                                            <form method="POST" action="{{ route('construction.daily-progress-reports.approve', $report) }}" class="blade-inline-form">
                                                @csrf
                                                @method('PATCH')
                                                <input type="text" name="note" value="{{ old('note') }}" maxlength="1000" placeholder="Approval note">
                                                <button type="submit" class="blade-primary-action">Approve DPR</button>
                                            </form>

                                            <form method="POST" action="{{ route('construction.daily-progress-reports.reject', $report) }}" class="blade-inline-form">
                                                @csrf
                                                @method('PATCH')
                                                <input type="text" name="reason" value="{{ old('reason') }}" maxlength="2000" placeholder="Required rejection reason" required>
                                                <button type="submit" class="blade-secondary-action">Reject DPR</button>
                                            </form>
                                        </div>
                                    @else
                                        <span>{{ $report->status === 'submitted' ? 'Approval unavailable' : 'Closed' }}</span>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8">No daily progress reports match the selected filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="blade-pagination">
                {{ $dailyReports->links() }}
            </div>
        </section>
    </div>
@endsection
