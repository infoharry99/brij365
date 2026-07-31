@extends('layouts.builder360-classic')

@section('title', 'Possession Handovers - Builder360 ERP-CRM')

@section('content')
@php
        $money = fn ($amount) => 'Rs. '.number_format((float) ($amount ?? 0), 2);
    @endphp

    <div class="blade-workspace" aria-labelledby="possession-handovers-title">
        <header class="blade-workspace-header">
            <div>
                <p class="blade-dashboard-eyebrow">Possession and Handover</p>
                <h1 id="possession-handovers-title">Possession Handovers</h1>
                <p>
                    Workspace for possession eligibility, final payment checks,
                    handover checklist, possession letter issue, snag blockers and completion status.
                </p>
            </div>
            <nav class="blade-workspace-actions" aria-label="Workspace actions">
                <a href="{{ route('builder360.dashboard') }}">Dashboard</a>
                <a href="{{ route('sales.bookings.index') }}">Bookings</a>
                <a href="{{ route('finance.collections.index') }}">Collections</a>
                <a href="{{ route('possession.snags.index') }}">Snags</a>
                <a href="{{ route('possession.handovers.index') }}">Reset filters</a>
            </nav>
        </header>

        @if (session('status'))
            <section class="blade-alert blade-alert-success" role="status">{{ session('status') }}</section>
        @endif

        @if ($errors->any())
            <section class="blade-alert blade-alert-error" role="alert">
                <strong>Possession handover action was not saved.</strong>
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
                        <h2>Initiate possession handover</h2>
                    </div>
                    <small>{{ $canCreateHandover ? 'Authorized' : 'Read only' }}</small>
                </div>

                @if ($canCreateHandover)
                    <form method="POST" action="{{ route('possession.handovers.store') }}" class="blade-form-grid">
                        @csrf

                        <label class="blade-form-wide">
                            Confirmed booking
                            <select name="booking_id" required>
                                <option value="">Select booking without existing handover</option>
                                @foreach ($bookings as $booking)
                                    <option value="{{ $booking->id }}" @selected((string) old('booking_id') === (string) $booking->id)>
                                        {{ $booking->booking_code }} - {{ $booking->customer?->name ?? 'Customer missing' }} - {{ $booking->project?->code ?? 'No project' }} - {{ $booking->unit?->unit_code ?? 'No unit' }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label>
                            Target handover date
                            <input type="date" name="target_handover_on" value="{{ old('target_handover_on') }}">
                        </label>

                        <p class="blade-form-wide blade-workspace-note">
                            Initial handover uses the configured default checklist. Update checklist after initiation once finance, document, inspection and key-readiness checks are confirmed.
                        </p>

                        <button type="submit" class="blade-primary-action">Initiate handover</button>
                    </form>
                @else
                    <p class="blade-workspace-note">Your role can view handovers but cannot initiate new handovers.</p>
                @endif
            </article>

            <article class="blade-dashboard-card">
                <div class="blade-dashboard-section-title">
                    <div>
                        <span class="blade-dashboard-label">Controls</span>
                        <h2>Handover filters</h2>
                    </div>
                    <small>{{ $handovers->total() }} record(s)</small>
                </div>

                <form method="GET" action="{{ route('possession.handovers.index') }}" class="blade-filter-grid blade-filter-grid-compact">
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

                <p class="blade-workspace-note">
                    Completion is blocked until financial outstanding is zero, required checklist items are completed, open snags are resolved and possession letter reference matches.
                </p>
            </article>
        </section>

        <section class="blade-dashboard-card">
            <div class="blade-dashboard-section-title">
                <div>
                    <span class="blade-dashboard-label">Register</span>
                    <h2>Handover register</h2>
                </div>
                <small>{{ $handovers->firstItem() ?? 0 }}-{{ $handovers->lastItem() ?? 0 }} of {{ $handovers->total() }}</small>
            </div>

            <div class="blade-dashboard-table-wrap">
                <table class="blade-dashboard-table">
                    <thead>
                        <tr>
                            <th scope="col">Handover</th>
                            <th scope="col">Booking / customer</th>
                            <th scope="col">Eligibility</th>
                            <th scope="col">Checklist</th>
                            <th scope="col">Workflow</th>
                            <th scope="col">Status</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($handovers as $handover)
                            <tr>
                                <td>
                                    <strong>{{ $handover->handover_number }}</strong>
                                    <span>Target {{ $handover->target_handover_on?->format('d M Y') ?? 'Not set' }}</span>
                                    <span>Actual {{ $handover->actual_handover_on?->format('d M Y') ?? 'Pending' }}</span>
                                    <span>Letter {{ $handover->possession_letter_reference ?? 'Not issued' }}</span>
                                </td>
                                <td>
                                    <strong>{{ $handover->booking?->booking_code ?? 'Booking missing' }}</strong>
                                    <span>{{ $handover->customer?->name ?? 'Customer missing' }}</span>
                                    <span>{{ $handover->project?->code ?? 'No project' }} / {{ $handover->unit?->unit_code ?? 'No unit' }}</span>
                                </td>
                                <td>
                                    <strong>Outstanding {{ $money($handover->financial_outstanding) }}</strong>
                                    @forelse (($handover->blockers ?? []) as $blocker)
                                        <span>{{ $blocker['message'] ?? $blocker['code'] ?? 'Blocker' }}</span>
                                    @empty
                                        <span>No blockers</span>
                                    @endforelse
                                    <span>Open snags {{ $handover->snags->where('status', 'open')->count() }}</span>
                                </td>
                                <td>
                                    @forelse (($handover->checklist ?? []) as $item)
                                        <span>
                                            {{ ($item['completed'] ?? false) ? '✓' : '□' }}
                                            {{ $item['label'] ?? $item['code'] ?? 'Checklist item' }}
                                            {{ ($item['required'] ?? false) ? '(Required)' : '(Optional)' }}
                                        </span>
                                    @empty
                                        <span>No checklist captured</span>
                                    @endforelse
                                </td>
                                <td>
                                    <strong>Initiated by {{ $handover->initiatedBy?->name ?? 'User missing' }}</strong>
                                    <span>Completed by {{ $handover->completedBy?->name ?? 'Pending' }}</span>
                                    <span>{{ $handover->completed_at?->format('d M Y H:i') ?? 'Completion pending' }}</span>
                                </td>
                                <td>{{ $statuses[$handover->status] ?? str($handover->status)->headline() }}</td>
                                <td>
                                    @can('update', $handover)
                                        <details class="blade-row-actions">
                                            <summary>Checklist</summary>
                                            <form method="POST" action="{{ route('possession.handovers.checklist.update', $handover) }}" class="blade-inline-form">
                                                @csrf
                                                @method('PATCH')
                                                @foreach (($handover->checklist ?? []) as $index => $item)
                                                    <input type="hidden" name="checklist[{{ $index }}][code]" value="{{ $item['code'] ?? 'item_'.$index }}">
                                                    <input type="hidden" name="checklist[{{ $index }}][label]" value="{{ $item['label'] ?? 'Checklist item '.($index + 1) }}">
                                                    <input type="hidden" name="checklist[{{ $index }}][required]" value="{{ (int) (bool) ($item['required'] ?? true) }}">
                                                    <input type="hidden" name="checklist[{{ $index }}][completed]" value="0">
                                                    <label class="blade-checkbox-row">
                                                        <input type="checkbox" name="checklist[{{ $index }}][completed]" value="1" @checked((bool) ($item['completed'] ?? false))>
                                                        {{ $item['label'] ?? $item['code'] ?? 'Checklist item' }}
                                                    </label>
                                                @endforeach
                                                <button type="submit" class="blade-primary-action">Update checklist</button>
                                            </form>
                                        </details>
                                    @endcan

                                    @can('issueLetter', $handover)
                                        <details class="blade-row-actions">
                                            <summary>Issue letter</summary>
                                            <form method="POST" action="{{ route('possession.handovers.letter.issue', $handover) }}" class="blade-inline-form">
                                                @csrf
                                                @method('PATCH')
                                                <input type="text" name="possession_letter_reference" required maxlength="255" placeholder="Possession letter reference">
                                                <button type="submit" class="blade-primary-action">Issue possession letter</button>
                                            </form>
                                        </details>
                                    @endcan

                                    @can('complete', $handover)
                                        <details class="blade-row-actions">
                                            <summary>Complete</summary>
                                            <form method="POST" action="{{ route('possession.handovers.complete', $handover) }}" class="blade-inline-form">
                                                @csrf
                                                @method('PATCH')
                                                <input type="date" name="actual_handover_on" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" required>
                                                <input type="text" name="possession_letter_reference" value="{{ $handover->possession_letter_reference }}" required maxlength="255" placeholder="Issued possession letter reference">
                                                <button type="submit" class="blade-primary-action">Complete handover</button>
                                            </form>
                                        </details>
                                    @endcan

                                    @cannot('update', $handover)
                                        @cannot('issueLetter', $handover)
                                            @cannot('complete', $handover)
                                                <span>No action</span>
                                            @endcannot
                                        @endcannot
                                    @endcannot
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">No handovers match the selected filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="blade-pagination">{{ $handovers->links() }}</div>
        </section>
    </div>
@endsection
