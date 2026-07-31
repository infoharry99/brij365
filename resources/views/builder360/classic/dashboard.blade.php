@extends('layouts.builder360-classic')

@php
    use App\Support\Builder360ModuleNavigation;

    $dashboard = $page->dashboard;
    $navigationContext = $page->navigationContext;
    $period = $dashboard['period'] ?? ($navigationContext['active_dashboard_period'] ?? []);
    $stats = collect($dashboard['stats'] ?? []);
    $quickActions = collect($dashboard['quick_actions'] ?? []);
    $sections = collect($dashboard['sections'] ?? []);
    $alerts = collect($dashboard['alerts'] ?? []);
    $tables = collect($dashboard['tables'] ?? []);

    $dashboardUrl = function (?string $route, array $filters = []) use ($navigationContext): ?string {
        $url = Builder360ModuleNavigation::urlFor($route, $navigationContext);

        if (! $url) {
            return null;
        }

        $filters = array_filter($filters, fn ($value) => $value !== null && $value !== '');

        return $filters ? $url.(str_contains($url, '?') ? '&' : '?').http_build_query($filters) : $url;
    };
@endphp

@section('title', ($dashboard['title'] ?? 'Dashboard').' | Builder360')
<style>
    .shell { height: 100vh; overflow: hidden; display: flex; }
.main { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
.content { flex: 1; overflow-y: auto; }
    </style>
@section('content')
    <section class="b360-page-head">
        <div>
            <h1>{{ $dashboard['title'] ?? 'Dashboard' }}</h1>
            <p>{{ $dashboard['subtitle'] ?? 'Live business view for your role.' }}</p>
        </div>

        <div class="b360-head-actions">
            <form method="POST" action="{{ route('builder360.dashboard-context.store') }}" class="b360-period-form" x-data="periodSelector" data-period-key="{{ $period['key'] ?? 'current_month' }}">
                @csrf
                <label for="dashboard-period-key">Period</label>
                <select id="dashboard-period-key" name="period_key" x-model="periodKey">
                    @foreach (($period['options'] ?? []) as $periodOption)
                        <option value="{{ $periodOption['key'] }}" @selected(($period['key'] ?? 'current_month') === $periodOption['key'])>
                            {{ $periodOption['label'] }}
                        </option>
                    @endforeach
                </select>
                <span class="b360-custom-period" x-show="customPeriod" x-cloak>
                    <input type="date" name="date_from" value="{{ ($period['key'] ?? '') === 'custom' ? ($period['date_from'] ?? '') : '' }}" x-bind:required="customPeriod" aria-label="Period start date">
                    <input type="date" name="date_to" value="{{ ($period['key'] ?? '') === 'custom' ? ($period['date_to'] ?? '') : '' }}" x-bind:required="customPeriod" aria-label="Period end date">
                </span>
                <button type="submit">Apply</button>
            </form>

            @if (! empty($dashboard['primary_route']))
                @php $primaryUrl = $dashboardUrl($dashboard['primary_route'], []); @endphp
                @if ($primaryUrl)
                    <a class="b360-primary-btn" href="{{ $primaryUrl }}">
                        <i class="fa-solid fa-chevron-right"></i>
                        {{ $dashboard['primary_label'] ?? 'Open' }}
                    </a>
                @endif
            @endif
        </div>
    </section>

    @if ($quickActions->isNotEmpty())
        <section class="b360-quick-actions" aria-label="Quick actions">
            @foreach ($quickActions as $action)
                @php $actionUrl = $dashboardUrl($action['route'] ?? null, $action['route_filter'] ?? []); @endphp
                @if ($actionUrl)
                    <a href="{{ $actionUrl }}" class="b360-quick-action">
                        <i class="fa-solid fa-arrow-right"></i>
                        {{ $action['label'] ?? 'Open' }}
                    </a>
                @endif
            @endforeach
        </section>
    @endif

    <section class="b360-stat-grid" aria-label="Dashboard metrics">
        @forelse ($stats as $stat)
            @php $statUrl = ! empty($stat['is_actionable']) ? $dashboardUrl($stat['route'] ?? null, $stat['route_filter'] ?? []) : null; @endphp
            @if ($statUrl)
                <a class="b360-stat-card" href="{{ $statUrl }}">
            @else
                <article class="b360-stat-card">
            @endif
                    <span class="b360-card-icon {{ $stat['tone'] ?? 'b-blue' }}">
                        <i class="fa-solid fa-chart-simple"></i>
                    </span>
                    <span class="b360-stat-label">{{ $stat['label'] ?? 'Metric' }}</span>
                    <strong>{{ $stat['value'] ?? '—' }}</strong>
                    <small>{{ $stat['sub'] ?? '' }}</small>
            @if ($statUrl)
                </a>
            @else
                </article>
            @endif
        @empty
            <article class="b360-empty">No dashboard metrics are available for this role.</article>
        @endforelse
    </section>

    <section class="b360-dashboard-grid">
        @foreach ($sections as $section)
            <article class="b360-panel">
                <header class="b360-panel-head">
                    <div>
                        <h2>{{ $section['title'] ?? 'Records' }}</h2>
                        <p>{{ $section['sub'] ?? '' }}</p>
                    </div>
                    @php $sectionUrl = $dashboardUrl($section['route'] ?? null, $section['route_filter'] ?? []); @endphp
                    @if ($sectionUrl)
                        <a href="{{ $sectionUrl }}">View all <i class="fa-solid fa-chevron-right"></i></a>
                    @endif
                </header>

                <div class="b360-row-list">
                    @forelse (($section['rows'] ?? []) as $row)
                        @php $rowUrl = ! empty($row['is_actionable']) ? $dashboardUrl($row['route'] ?? null, $row['route_filter'] ?? []) : null; @endphp
                        @if ($rowUrl)
                            <a class="b360-data-row" href="{{ $rowUrl }}">
                        @else
                            <div class="b360-data-row">
                        @endif
                                <span>
                                    <strong>{{ $row['label'] ?? 'Record' }}</strong>
                                    <small>{{ $row['sub'] ?? '' }}</small>
                                </span>
                                <em>{{ $row['value'] ?? '' }}</em>
                        @if ($rowUrl)
                            </a>
                        @else
                            </div>
                        @endif
                    @empty
                        <div class="b360-empty">{{ $section['empty'] ?? 'No records are available for your selected view.' }}</div>
                    @endforelse
                </div>
            </article>
        @endforeach

        @if ($alerts->isNotEmpty())
            <article class="b360-panel">
                <header class="b360-panel-head">
                    <div>
                        <h2>Alerts</h2>
                        <p>Items that may need attention.</p>
                    </div>
                </header>
                <div class="b360-row-list">
                    @foreach ($alerts as $alert)
                        <div class="b360-data-row">
                            <span>
                                <strong>{{ $alert['label'] ?? 'Alert' }}</strong>
                                <small>{{ $alert['sub'] ?? '' }}</small>
                            </span>
                            <em>{{ $alert['value'] ?? '' }}</em>
                        </div>
                    @endforeach
                </div>
            </article>
        @endif
    </section>

    @foreach ($tables as $table)
        <section class="b360-panel b360-table-panel">
            <header class="b360-panel-head">
                <div>
                    <h2>{{ $table['title'] ?? 'Table' }}</h2>
                    <p>{{ $table['sub'] ?? '' }}</p>
                </div>
            </header>

            <div class="table-responsive">
                <table class="table b360-table align-middle">
                    <thead>
                        <tr>
                            @foreach (($table['columns'] ?? ['Item', 'Details', 'Value']) as $column)
                                <th>{{ $column }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse (($table['rows'] ?? []) as $row)
                            <tr>
                                <td>
                                    <strong>{{ $row['label'] ?? 'Record' }}</strong>
                                </td>
                                <td>{{ $row['sub'] ?? '' }}</td>
                                <td>{{ $row['value'] ?? '' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($table['columns'] ?? ['Item', 'Details', 'Value']) }}">{{ $table['empty'] ?? 'No records are available for your selected view.' }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endforeach
@endsection


