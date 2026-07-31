@extends('layouts.builder360-classic')

@php
    $counts = $summary['counts'] ?? [];
    $byCategory = collect($summary['by_category'] ?? []);
    $activeCategory = $filters['category'] ?? null;
    $activeStatus = $filters['status'] ?? null;
    $activeSeverity = $filters['severity'] ?? null;
    $baseFilters = array_filter($filters, fn ($value) => $value !== null && $value !== '');
    $filterUrl = fn (array $overrides = []): string => route('notifications.index', array_filter(array_merge($baseFilters, $overrides), fn ($value) => $value !== null && $value !== ''));
@endphp

@section('title', 'Notifications | Builder360')

@section('content')
    <section class="b360-page-head">
        <div>
            <p class="b360-eyebrow">Overview / Notifications</p>
            <h1 id="notification-center-title">Notifications</h1>
            <p>Secure workflow inbox for real workflow notifications, reminders and alerts.</p>
        </div>
        <div class="b360-head-actions">
            <a class="b360-secondary-btn" href="{{ route('notifications.index', $baseFilters) }}"><i class="fa-solid fa-rotate"></i> Refresh</a>
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                @method('PATCH')
                @foreach (['q', 'category', 'severity', 'date_from', 'date_to'] as $field)
                    @if (! empty($filters[$field]))
                        <input type="hidden" name="{{ $field }}" value="{{ $filters[$field] }}">
                    @endif
                @endforeach
                <button class="b360-secondary-btn" type="submit" @disabled(((int) ($counts['unread'] ?? 0)) === 0)>
                    <i class="fa-solid fa-check"></i>
                    {{ ((int) ($counts['unread'] ?? 0)) > 0 ? 'Mark all read' : 'All read' }}
                </button>
            </form>
        </div>
    </section>

    <section class="b360-stat-grid" aria-label="Notification metrics">
        @foreach ([
            ['label' => 'Total', 'value' => $counts['total'] ?? 0, 'sub' => 'all notifications', 'icon' => 'fa-bell', 'tone' => 'b-violet', 'url' => $filterUrl(['status' => null, 'severity' => null])],
            ['label' => 'Unread', 'value' => $counts['unread'] ?? 0, 'sub' => 'needs attention', 'icon' => 'fa-bell', 'tone' => 'b-orange', 'url' => $filterUrl(['status' => 'unread', 'severity' => null])],
            ['label' => 'Critical Unread', 'value' => $counts['critical_unread'] ?? 0, 'sub' => 'priority alerts', 'icon' => 'fa-triangle-exclamation', 'tone' => 'b-red', 'url' => $filterUrl(['status' => 'unread', 'severity' => 'critical'])],
            ['label' => 'Archived', 'value' => $counts['archived'] ?? 0, 'sub' => 'saved notifications', 'icon' => 'fa-box-archive', 'tone' => 'b-blue', 'url' => $filterUrl(['status' => 'archived', 'severity' => null])],
        ] as $metric)
            <a class="b360-stat-card" href="{{ $metric['url'] }}">
                <span class="b360-card-icon {{ $metric['tone'] }}"><i class="fa-solid {{ $metric['icon'] }}"></i></span>
                <span class="b360-stat-label">{{ $metric['label'] }}</span>
                <strong>{{ number_format((int) $metric['value']) }}</strong>
                <small>{{ $metric['sub'] }}</small>
            </a>
        @endforeach
    </section>

    <section class="b360-panel b360-filter-panel">
        <form method="GET" action="{{ route('notifications.index') }}" class="b360-filter-grid b360-notification-filter-grid">
            <label class="b360-search-field">
                <span>Search</span>
                <span class="b360-input-icon">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search notifications">
                </span>
            </label>
            <label>
                <span>Status</span>
                <select name="status" class="form-select">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected($activeStatus === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>Severity</span>
                <select name="severity" class="form-select">
                    <option value="">All severities</option>
                    @foreach ($severities as $value => $label)
                        <option value="{{ $value }}" @selected($activeSeverity === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>From</span>
                <input class="form-control" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
            </label>
            <label>
                <span>To</span>
                <input class="form-control" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
            </label>
            <div class="b360-filter-actions">
                <button class="b360-primary-btn" type="submit"><i class="fa-solid fa-filter"></i> Apply</button>
                <a class="b360-secondary-btn" href="{{ route('notifications.index') }}">Clear</a>
            </div>
        </form>
    </section>

    <nav class="b360-tabs" aria-label="Notification categories">
        <a href="{{ $filterUrl(['category' => null]) }}" class="b360-tab {{ $activeCategory === null ? 'is-active' : '' }}" @if ($activeCategory === null) aria-current="page" @endif>
            All <span>{{ number_format((int) ($counts['total'] ?? 0)) }}</span>
        </a>
        @foreach ($byCategory as $category)
            @php $categoryValue = $category['category'] ?? ''; @endphp
            <a href="{{ $filterUrl(['category' => $categoryValue]) }}" class="b360-tab {{ $activeCategory === $categoryValue ? 'is-active' : '' }}" @if ($activeCategory === $categoryValue) aria-current="page" @endif>
                {{ str($categoryValue)->headline() }} <span>{{ number_format((int) ($category['count'] ?? 0)) }}</span>
            </a>
        @endforeach
    </nav>

    <section class="b360-notification-list" aria-labelledby="notification-center-title">
        @forelse ($notifications as $notification)
            <article class="b360-notification-card {{ $notification->status === 'unread' ? 'is-unread' : '' }}">
                <span class="b360-card-icon {{ $notification->severity === 'critical' ? 'b-red' : ($notification->severity === 'warning' ? 'b-orange' : 'b-violet') }}">
                    <i class="fa-solid fa-bell"></i>
                </span>
                <div class="b360-notification-copy">
                    <div class="b360-notification-title">
                        <strong>{{ $notification->title }}</strong>
                        <span class="b360-badge b-slate">{{ str($notification->category)->headline() }}</span>
                    </div>
                    <p>{{ $notification->body }}</p>
                    <small>{{ $notification->notification_number }} · {{ $notification->created_at?->diffForHumans() ?? 'Recently' }}</small>
                </div>
                <div class="b360-notification-actions">
                    <span class="b360-badge {{ $notification->status === 'unread' ? 'b-orange' : 'b-slate' }}">{{ str($notification->status)->headline() }}</span>
                    @if ($notification->action_url)
                        <a class="b360-small-btn b360-small-btn-primary" href="{{ $notification->action_url }}">Open</a>
                    @endif
                    @if ($notification->status === 'unread')
                        <form method="POST" action="{{ route('notifications.read', $notification) }}">
                            @csrf
                            @method('PATCH')
                            <button class="b360-small-btn" type="submit">Mark read</button>
                        </form>
                    @endif
                    @if ($notification->status !== 'archived')
                        <form method="POST" action="{{ route('notifications.archive', $notification) }}">
                            @csrf
                            @method('PATCH')
                            <button class="b360-small-btn" type="submit">Archive</button>
                        </form>
                    @endif
                </div>
            </article>
        @empty
            <article class="b360-panel b360-empty b360-large-empty">
                <i class="fa-regular fa-bell-slash"></i>
                <strong>No notifications found</strong>
                <span>No notifications match the selected filters.</span>
            </article>
        @endforelse
    </section>

    @if ($notifications->hasPages())
        <nav class="b360-pagination" aria-label="Notification pagination">
            @if ($notifications->onFirstPage())
                <span aria-disabled="true">Previous</span>
            @else
                <a href="{{ $notifications->previousPageUrl() }}">Previous</a>
            @endif
            <span>Page {{ $notifications->currentPage() }} of {{ $notifications->lastPage() }}</span>
            @if ($notifications->hasMorePages())
                <a href="{{ $notifications->nextPageUrl() }}">Next</a>
            @else
                <span aria-disabled="true">Next</span>
            @endif
        </nav>
    @endif
@endsection
