@extends('layouts.builder360-classic')

@section('title', 'Common Area Handover - Builder360 ERP-CRM')

@section('content')
<div class="blade-workspace" aria-labelledby="handover-items-title">
        <header class="blade-workspace-header">
            <div>
                <p class="blade-dashboard-eyebrow">Society Operations</p>
                <h1 id="handover-items-title">Common Area Handover</h1>
                <p>
                    Workspace for common-area facility checklist progress,
                    snag summaries, responsible users and sign-off before society handover.
                </p>
            </div>
            <nav class="blade-workspace-actions" aria-label="Workspace actions">
                <a href="{{ route('builder360.dashboard') }}">Dashboard</a>
                <a href="{{ route('maintenance.societies.index') }}">Societies</a>
                <a href="{{ route('maintenance.dues.index') }}">Maintenance Dues</a>
                <a href="{{ route('maintenance.handover-items.index') }}">Reset filters</a>
            </nav>
        </header>

        @if (session('status'))
            <section class="blade-alert blade-alert-success" role="status">{{ session('status') }}</section>
        @endif

        @if ($errors->any())
            <section class="blade-alert blade-alert-error" role="alert">
                <strong>Common-area handover action was not saved.</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="blade-dashboard-card">
            <div class="blade-dashboard-section-title">
                <div>
                    <span class="blade-dashboard-label">Controls</span>
                    <h2>Handover item filters</h2>
                </div>
                <small>{{ $items->total() }} record(s)</small>
            </div>

            <form method="GET" action="{{ route('maintenance.handover-items.index') }}" class="blade-filter-grid blade-filter-grid-compact">
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
                    Society
                    <select name="society_formation_id">
                        <option value="">All societies</option>
                        @foreach ($societies as $society)
                            <option value="{{ $society->id }}" @selected((string) ($filters['society_formation_id'] ?? '') === (string) $society->id)>
                                {{ $society->formation_number }} - {{ $society->society_name }}
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
                <button type="submit" class="blade-secondary-action">Apply filters</button>
            </form>
        </section>

        <section class="blade-dashboard-card">
            <div class="blade-dashboard-section-title">
                <div>
                    <span class="blade-dashboard-label">Register</span>
                    <h2>Common-area handover checklist</h2>
                </div>
                <small>{{ $items->firstItem() ?? 0 }}-{{ $items->lastItem() ?? 0 }} of {{ $items->total() }}</small>
            </div>

            <div class="blade-dashboard-table-wrap">
                <table class="blade-dashboard-table">
                    <thead>
                        <tr>
                            <th scope="col">Item</th>
                            <th scope="col">Project / society</th>
                            <th scope="col">Checklist</th>
                            <th scope="col">Snags</th>
                            <th scope="col">Ownership</th>
                            <th scope="col">Status</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($items as $item)
                            <tr>
                                <td>
                                    <strong>{{ $item->item_number }}</strong>
                                    <span>{{ $item->facility_name }}</span>
                                    <span>{{ $item->category }}</span>
                                    <span>Target {{ $item->target_completion_on?->format('d M Y') ?? 'Not set' }}</span>
                                </td>
                                <td>
                                    <strong>{{ $item->project?->code ?? 'Project missing' }}</strong>
                                    <span>{{ $item->societyFormation?->formation_number ?? 'Society missing' }}</span>
                                    <span>{{ $item->societyFormation?->society_name ?? 'Society missing' }}</span>
                                </td>
                                <td>
                                    <strong>{{ $item->checklist_completed }} / {{ $item->checklist_total }}</strong>
                                    <span>{{ $item->checklist_total > 0 ? round(($item->checklist_completed / $item->checklist_total) * 100) : 0 }}% complete</span>
                                </td>
                                <td>
                                    @forelse (($item->snag_summary ?? []) as $key => $value)
                                        <span>{{ str($key)->headline() }}: {{ is_scalar($value) ? $value : json_encode($value) }}</span>
                                    @empty
                                        <span>No snag summary</span>
                                    @endforelse
                                </td>
                                <td>
                                    <strong>Responsible {{ $item->responsibleUser?->name ?? 'Unassigned' }}</strong>
                                    <span>Signed off by {{ $item->signedOffBy?->name ?? 'Pending' }}</span>
                                    <span>{{ $item->signed_off_on?->format('d M Y') ?? 'Sign-off pending' }}</span>
                                </td>
                                <td>{{ $statuses[$item->status] ?? str($item->status)->headline() }}</td>
                                <td>
                                    @can('update', $item)
                                        <details class="blade-row-actions">
                                            <summary>Update</summary>
                                            <form method="POST" action="{{ route('maintenance.handover-items.update', $item) }}" class="blade-inline-form">
                                                @csrf
                                                @method('PATCH')
                                                <input type="number" name="checklist_completed" value="{{ $item->checklist_completed }}" min="0" max="{{ $item->checklist_total }}" required>
                                                <select name="status" required>
                                                    @foreach ($statuses as $value => $label)
                                                        <option value="{{ $value }}" @selected($item->status === $value)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                                <textarea name="note" maxlength="1000" rows="2" placeholder="Checklist update note"></textarea>
                                                <button type="submit" class="blade-primary-action">Update item</button>
                                            </form>
                                        </details>
                                    @endcan

                                    @can('signOff', $item)
                                        <details class="blade-row-actions">
                                            <summary>Sign off</summary>
                                            <form method="POST" action="{{ route('maintenance.handover-items.sign-off', $item) }}" class="blade-inline-form">
                                                @csrf
                                                @method('PATCH')
                                                <textarea name="note" maxlength="1000" rows="2" placeholder="Sign-off note"></textarea>
                                                <button type="submit" class="blade-primary-action">Sign off</button>
                                            </form>
                                        </details>
                                    @endcan

                                    @cannot('update', $item)
                                        @cannot('signOff', $item)
                                            <span>No action</span>
                                        @endcannot
                                    @endcannot
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">No common-area handover items match the selected filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="blade-pagination">{{ $items->links() }}</div>
        </section>
    </div>
@endsection
