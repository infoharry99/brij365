@extends('layouts.builder360-classic')

@section('title', 'Handover Snags - Builder360 ERP-CRM')

@section('content')
<div class="blade-workspace" aria-labelledby="handover-snags-title">
        <header class="blade-workspace-header">
            <div>
                <p class="blade-dashboard-eyebrow">Possession and Snag Management</p>
                <h1 id="handover-snags-title">Handover Snags</h1>
                <p>
                    Workspace for reporting possession snags, severity tracking,
                    target resolution dates, resolution notes and automatic handover readiness refresh.
                </p>
            </div>
            <nav class="blade-workspace-actions" aria-label="Workspace actions">
                <a href="{{ route('builder360.dashboard') }}">Dashboard</a>
                <a href="{{ route('possession.handovers.index') }}">Handovers</a>
                <a href="{{ route('maintenance.societies.index') }}">Society Ops</a>
                <a href="{{ route('possession.snags.index') }}">Reset filters</a>
            </nav>
        </header>

        @if (session('status'))
            <section class="blade-alert blade-alert-success" role="status">{{ session('status') }}</section>
        @endif

        @if ($errors->any())
            <section class="blade-alert blade-alert-error" role="alert">
                <strong>Snag action was not saved.</strong>
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
                        <h2>Report handover snag</h2>
                    </div>
                    <small>{{ $canReportSnag ? 'Authorized' : 'Read only' }}</small>
                </div>

                @if ($canReportSnag)
                    <form method="POST" action="{{ route('possession.snags.store') }}" class="blade-form-grid">
                        @csrf

                        <label class="blade-form-wide">
                            Open handover
                            <select name="possession_handover_id" required>
                                <option value="">Select handover</option>
                                @foreach ($handovers as $handover)
                                    <option value="{{ $handover->id }}" @selected((string) old('possession_handover_id') === (string) $handover->id)>
                                        {{ $handover->handover_number }} - {{ $handover->booking?->booking_code ?? 'Booking missing' }} - {{ $handover->customer?->name ?? 'Customer missing' }} - {{ $handover->unit?->unit_code ?? 'No unit' }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label>
                            Area
                            <input type="text" name="area" value="{{ old('area') }}" maxlength="120" required placeholder="Living Room, Bedroom, Balcony">
                        </label>

                        <label>
                            Category
                            <input type="text" name="category" value="{{ old('category') }}" maxlength="120" required placeholder="Civil, Electrical, Plumbing">
                        </label>

                        <label>
                            Severity
                            <select name="severity" required>
                                @foreach ($severities as $value => $label)
                                    <option value="{{ $value }}" @selected(old('severity', 'medium') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label>
                            Target resolution date
                            <input type="date" name="target_resolution_on" value="{{ old('target_resolution_on') }}" min="{{ now()->toDateString() }}">
                        </label>

                        <label class="blade-form-wide">
                            Description
                            <textarea name="description" required maxlength="5000" rows="3" placeholder="Describe the snag and verification expectation.">{{ old('description') }}</textarea>
                        </label>

                        <button type="submit" class="blade-primary-action">Report snag</button>
                    </form>
                @else
                    <p class="blade-workspace-note">Your role can view handover snags but cannot report new snags.</p>
                @endif
            </article>

            <article class="blade-dashboard-card">
                <div class="blade-dashboard-section-title">
                    <div>
                        <span class="blade-dashboard-label">Controls</span>
                        <h2>Snag filters</h2>
                    </div>
                    <small>{{ $snags->total() }} record(s)</small>
                </div>

                <form method="GET" action="{{ route('possession.snags.index') }}" class="blade-filter-grid blade-filter-grid-compact">
                    <label>
                        Handover
                        <select name="possession_handover_id">
                            <option value="">All handovers</option>
                            @foreach ($handovers as $handover)
                                <option value="{{ $handover->id }}" @selected((string) ($filters['possession_handover_id'] ?? '') === (string) $handover->id)>{{ $handover->handover_number }}</option>
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
                        Severity
                        <select name="severity">
                            <option value="">All severities</option>
                            @foreach ($severities as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['severity'] ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button type="submit" class="blade-secondary-action">Apply filters</button>
                </form>

                <p class="blade-workspace-note">
                    Open snags block possession handover letter issue and completion until resolved.
                </p>
            </article>
        </section>

        <section class="blade-dashboard-card">
            <div class="blade-dashboard-section-title">
                <div>
                    <span class="blade-dashboard-label">Register</span>
                    <h2>Snag register</h2>
                </div>
                <small>{{ $snags->firstItem() ?? 0 }}-{{ $snags->lastItem() ?? 0 }} of {{ $snags->total() }}</small>
            </div>

            <div class="blade-dashboard-table-wrap">
                <table class="blade-dashboard-table">
                    <thead>
                        <tr>
                            <th scope="col">Snag</th>
                            <th scope="col">Handover</th>
                            <th scope="col">Issue</th>
                            <th scope="col">Resolution</th>
                            <th scope="col">People</th>
                            <th scope="col">Status</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($snags as $snag)
                            <tr>
                                <td>
                                    <strong>{{ $snag->snag_number }}</strong>
                                    <span>{{ $snag->area }}</span>
                                    <span>{{ $snag->category }}</span>
                                    <span>{{ $severities[$snag->severity] ?? str($snag->severity)->headline() }}</span>
                                </td>
                                <td>
                                    <strong>{{ $snag->handover?->handover_number ?? 'Handover missing' }}</strong>
                                    <span>{{ $snag->handover?->booking?->booking_code ?? 'Booking missing' }}</span>
                                    <span>{{ $snag->handover?->unit?->unit_code ?? 'No unit' }}</span>
                                </td>
                                <td>
                                    <strong>{{ $snag->description }}</strong>
                                    <span>Target {{ $snag->target_resolution_on?->format('d M Y') ?? 'Not set' }}</span>
                                </td>
                                <td>
                                    <strong>{{ $snag->resolution_notes ?? 'Resolution pending' }}</strong>
                                    <span>{{ $snag->resolved_at?->format('d M Y H:i') ?? 'Open' }}</span>
                                </td>
                                <td>
                                    <strong>Reported by {{ $snag->reportedBy?->name ?? 'User missing' }}</strong>
                                    <span>Resolved by {{ $snag->resolvedBy?->name ?? 'Pending' }}</span>
                                </td>
                                <td>{{ $statuses[$snag->status] ?? str($snag->status)->headline() }}</td>
                                <td>
                                    @can('resolve', $snag)
                                        <details class="blade-row-actions">
                                            <summary>Resolve</summary>
                                            <form method="POST" action="{{ route('possession.snags.resolve', $snag) }}" class="blade-inline-form">
                                                @csrf
                                                @method('PATCH')
                                                <textarea name="resolution_notes" required maxlength="5000" rows="2" placeholder="Resolution notes"></textarea>
                                                <button type="submit" class="blade-primary-action">Resolve snag</button>
                                            </form>
                                        </details>
                                    @else
                                        <span>No action</span>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">No snags match the selected filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="blade-pagination">{{ $snags->links() }}</div>
        </section>
    </div>
@endsection
