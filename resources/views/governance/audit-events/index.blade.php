@extends('layouts.builder360-classic')

@section('title', 'Audit Trail - Builder360 ERP-CRM')

@section('content')
@php
        $writeCount = $events->getCollection()->whereIn('request_method', ['POST', 'PATCH', 'PUT', 'DELETE'])->count();
        $userCount = $events->getCollection()->pluck('user_id')->filter()->unique()->count();
        $auditableCount = $events->getCollection()->whereNotNull('auditable_type')->count();
    @endphp

    <div class="blade-workspace" aria-labelledby="audit-trail-title">
        <header class="blade-workspace-header">
            <div>
                <p class="blade-dashboard-eyebrow">Administration</p>
                <h1 id="audit-trail-title">Audit Trail</h1>
                <p>
                    Audit register for critical business events, actor tracking,
                    request evidence, record linkage, filterable review and CSV export.
                </p>
            </div>
            <nav class="blade-workspace-actions" aria-label="Audit navigation">
                <a href="{{ url('/') }}">Dashboard</a>
                <a href="{{ route('admin.users.index') }}">Users</a>
                <a href="{{ route('settings.system-settings.index') }}">Settings</a>
                <a href="{{ route('governance.audit-events.export', $filters) }}">Export CSV</a>
                <a href="{{ route('governance.audit-events.index') }}">Reset filters</a>
            </nav>
        </header>

        @if ($errors->any())
            <div class="blade-alert blade-alert-danger">
                <strong>Check the highlighted inputs.</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="blade-dashboard-kpis" aria-label="Audit KPIs">
            <article class="blade-dashboard-kpi">
                <span>Total Events</span>
                <strong>{{ number_format($events->total()) }}</strong>
                <small>Activity register</small>
            </article>
            <article class="blade-dashboard-kpi">
                <span>Write Actions</span>
                <strong>{{ number_format($writeCount) }}</strong>
                <small>Current page</small>
            </article>
            <article class="blade-dashboard-kpi">
                <span>Actors</span>
                <strong>{{ number_format($userCount) }}</strong>
                <small>Current page</small>
            </article>
            <article class="blade-dashboard-kpi">
                <span>Linked Records</span>
                <strong>{{ number_format($auditableCount) }}</strong>
                <small>Current page</small>
            </article>
        </section>

        <section class="blade-dashboard-card">
            <div class="blade-dashboard-section-title">
                <div>
                    <span class="blade-dashboard-label">Controls</span>
                    <h2>Audit filters</h2>
                </div>
                <small>{{ number_format($events->total()) }} event(s)</small>
            </div>

            <form method="GET" action="{{ route('governance.audit-events.index') }}" class="blade-filter-grid blade-filter-grid-compact">
                <label>
                    Event type
                    <select name="event_type">
                        <option value="">All event types</option>
                        @foreach ($eventTypes as $eventType)
                            <option value="{{ $eventType }}" @selected(($filters['event_type'] ?? null) === $eventType)>{{ $eventType }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    Actor
                    <select name="user_id">
                        <option value="">All users</option>
                        @foreach ($users as $actor)
                            <option value="{{ $actor->id }}" @selected(($filters['user_id'] ?? null) == $actor->id)>{{ $actor->name }} · {{ $actor->email }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    Auditable type
                    <select name="auditable_type">
                        <option value="">All record types</option>
                        @foreach ($auditableTypes as $auditableType)
                            <option value="{{ $auditableType }}" @selected(($filters['auditable_type'] ?? null) === $auditableType)>{{ class_basename($auditableType) }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    Auditable ID
                    <input type="number" name="auditable_id" min="1" value="{{ $filters['auditable_id'] ?? '' }}">
                </label>
                <label>
                    Method
                    <select name="request_method">
                        <option value="">All methods</option>
                        @foreach ($requestMethods as $value => $label)
                            <option value="{{ $value }}" @selected(strtoupper($filters['request_method'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    Request ID
                    <input type="text" name="request_id" value="{{ $filters['request_id'] ?? '' }}" maxlength="120">
                </label>
                <label>
                    From
                    <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
                </label>
                <label>
                    To
                    <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
                </label>
                <label>
                    Search
                    <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" maxlength="120" placeholder="Event or action">
                </label>
                <button type="submit" class="blade-secondary-action">Apply filters</button>
            </form>
        </section>

        <section class="blade-dashboard-card">
            <div class="blade-dashboard-section-title">
                <div>
                    <span class="blade-dashboard-label">Evidence</span>
                    <h2>Audit event register</h2>
                </div>
                <small>{{ $events->firstItem() ?? 0 }}-{{ $events->lastItem() ?? 0 }} of {{ $events->total() }}</small>
            </div>

            <div class="blade-dashboard-table-wrap">
                <table class="blade-dashboard-table">
                    <thead>
                        <tr>
                            <th scope="col">Event</th>
                            <th scope="col">Actor</th>
                            <th scope="col">Record</th>
                            <th scope="col">Request</th>
                            <th scope="col">Metadata</th>
                            <th scope="col">When</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($events as $event)
                            <tr>
                                <td>
                                    <strong>{{ $event->event_type }}</strong>
                                    <span>{{ $event->action }}</span>
                                </td>
                                <td>
                                    <span>{{ $event->user?->name ?? 'System' }}</span>
                                    <small>{{ $event->user?->email }}</small>
                                    <small>{{ $event->user?->role?->slug }}</small>
                                </td>
                                <td>
                                    @if ($event->auditable_type)
                                        <span>{{ class_basename($event->auditable_type) }} #{{ $event->auditable_id }}</span>
                                        <small>{{ $event->auditable_type }}</small>
                                    @else
                                        <span class="blade-muted">No record link</span>
                                    @endif
                                </td>
                                <td>
                                    <span>{{ $event->request_method ?? 'N/A' }} {{ $event->request_path }}</span>
                                    <small>{{ $event->request_id }}</small>
                                    <small>{{ $event->ip_address }}</small>
                                </td>
                                <td>
                                    <small>{{ \Illuminate\Support\Str::limit(json_encode($event->metadata ?? [], JSON_UNESCAPED_SLASHES), 160) }}</small>
                                </td>
                                <td>{{ $event->created_at?->format('d M Y, h:i A') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">No audit events found for the selected filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $events->links() }}
        </section>
    </div>
@endsection
