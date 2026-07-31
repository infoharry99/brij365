@extends('layouts.builder360-classic')

@php
    use App\Support\Builder360ModuleNavigation;

    $summary = $approvalPayload['summary'] ?? [];
    $availableFilters = $approvalPayload['filters'] ?? [];
    $rows = collect($approvalPayload['rows'] ?? []);
    $pagination = $approvalPayload['pagination'] ?? [];
    $activeTab = $approvalFilters['tab'] ?? 'pending';
    $tabs = [
        'pending' => ['label' => 'Pending', 'count' => $summary['pending'] ?? 0],
        'high_priority' => ['label' => 'High Priority', 'count' => $summary['high_priority'] ?? 0],
        'actionable' => ['label' => 'Actionable', 'count' => $summary['actionable'] ?? 0],
        'restricted' => ['label' => 'Restricted', 'count' => $summary['restricted'] ?? 0],
        'approved' => ['label' => 'Approved', 'count' => $summary['approved'] ?? 0],
    ];
    $filterQuery = array_filter($approvalFilters, fn ($value) => $value !== null && $value !== '');
    $tabUrl = fn (string $tab): string => route('builder360.approvals.index', array_merge($filterQuery, ['tab' => $tab, 'page' => null]));
    $openUrl = function (array $row): ?string {
        $url = Builder360ModuleNavigation::urlFor($row['open_route'] ?? null, []);
        $filters = array_filter($row['open_route_filter'] ?? [], fn ($value) => $value !== null && $value !== '');

        return $url && $filters ? $url.(str_contains($url, '?') ? '&' : '?').http_build_query($filters) : $url;
    };
@endphp

@section('title', 'Approval Center | Builder360')

@section('content')
    <section class="b360-page-head">
        <div>
            <p class="b360-eyebrow">Overview / Approvals</p>
            <h1>Approval Center</h1>
            <p>Review approval records available to your role and selected project.</p>
        </div>
        @if (empty($approvalPayload['restricted']))
            <div class="b360-head-actions">
                <a class="b360-secondary-btn" href="{{ route('builder360.approvals.index', $filterQuery) }}">
                    <i class="fa-solid fa-rotate"></i> Refresh
                </a>
                <a class="b360-secondary-btn" href="{{ route('builder360.approvals.export', $filterQuery) }}">
                    <i class="fa-solid fa-download"></i> Export CSV
                </a>
            </div>
        @endif
    </section>

    @if (! empty($approvalPayload['restricted']))
        <section class="b360-panel b360-restricted-panel">
            <span class="b360-card-icon b-red"><i class="fa-solid fa-shield-halved"></i></span>
            <h2>Approval Center is not available for this role</h2>
            <p>Use the available sidebar options for your current role.</p>
            <a class="b360-primary-btn" href="{{ route('builder360.dashboard') }}">Return to Dashboard</a>
        </section>
    @else
        <section class="b360-stat-grid b360-approval-stat-grid" aria-label="Approval metrics">
            @foreach ([
                ['tab' => 'pending', 'label' => 'Pending', 'value' => $summary['pending'] ?? 0, 'sub' => 'records awaiting decision', 'tone' => 'b-orange', 'icon' => 'fa-clock'],
                ['tab' => 'high_priority', 'label' => 'High Priority', 'value' => $summary['high_priority'] ?? 0, 'sub' => 'urgent approval records', 'tone' => 'b-red', 'icon' => 'fa-fire'],
                ['tab' => 'actionable', 'label' => 'Actionable', 'value' => $summary['actionable'] ?? 0, 'sub' => 'available actions for this role', 'tone' => 'b-green', 'icon' => 'fa-check'],
                ['tab' => 'approved', 'label' => 'Approved', 'value' => $summary['approved'] ?? 0, 'sub' => 'recently approved records', 'tone' => 'b-blue', 'icon' => 'fa-box-archive'],
            ] as $metric)
                <a href="{{ $tabUrl($metric['tab']) }}" class="b360-stat-card {{ $activeTab === $metric['tab'] ? 'is-selected' : '' }}">
                    <span class="b360-card-icon {{ $metric['tone'] }}"><i class="fa-solid {{ $metric['icon'] }}"></i></span>
                    <span class="b360-stat-label">{{ $metric['label'] }}</span>
                    <strong>{{ number_format((int) $metric['value']) }}</strong>
                    <small>{{ $metric['sub'] }}</small>
                </a>
            @endforeach
        </section>

        <section class="b360-panel b360-filter-panel">
            <form method="GET" action="{{ route('builder360.approvals.index') }}" class="b360-filter-grid">
                <input type="hidden" name="tab" value="{{ $activeTab }}">
                <label class="b360-search-field">
                    <span>Search</span>
                    <span class="b360-input-icon">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="search" name="q" value="{{ $approvalFilters['q'] ?? '' }}" placeholder="Search approvals, numbers, modules, people">
                    </span>
                </label>
                <label>
                    <span>Module</span>
                    <select name="module" class="form-select">
                        <option value="">All modules</option>
                        @foreach (($availableFilters['modules'] ?? []) as $module)
                            <option value="{{ $module }}" @selected(($approvalFilters['module'] ?? null) === $module)>{{ $module }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span>Priority</span>
                    <select name="priority" class="form-select">
                        <option value="">All priorities</option>
                        @foreach (['high' => 'High', 'med' => 'Medium', 'low' => 'Low'] as $value => $label)
                            <option value="{{ $value }}" @selected(($approvalFilters['priority'] ?? null) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span>Status</span>
                    <select name="status" class="form-select">
                        <option value="">All statuses</option>
                        @foreach (($availableFilters['statuses'] ?? []) as $status)
                            <option value="{{ $status }}" @selected(($approvalFilters['status'] ?? null) === $status)>{{ str($status)->headline() }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="b360-filter-actions">
                    <button class="b360-primary-btn" type="submit"><i class="fa-solid fa-filter"></i> Apply</button>
                    <a class="b360-secondary-btn" href="{{ route('builder360.approvals.index', ['tab' => $activeTab]) }}">Clear</a>
                </div>
            </form>
        </section>

        <nav class="b360-tabs" aria-label="Approval states">
            @foreach ($tabs as $tab => $definition)
                <a href="{{ $tabUrl($tab) }}" class="b360-tab {{ $activeTab === $tab ? 'is-active' : '' }}" @if ($activeTab === $tab) aria-current="page" @endif>
                    {{ $definition['label'] }}
                    <span>{{ number_format((int) $definition['count']) }}</span>
                </a>
            @endforeach
        </nav>

        <section class="b360-panel b360-table-panel">
            <div class="table-responsive">
                <table class="table b360-table b360-approval-table align-middle">
                    <thead>
                        <tr>
                            <th>Request</th>
                            <th>Type</th>
                            <th>Raised By</th>
                            <th>Amount</th>
                            <th>Age</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            @php $recordUrl = $openUrl($row); @endphp
                            <tr>
                                <td>
                                    @if ($recordUrl)
                                        <a class="b360-record-link" href="{{ $recordUrl }}">{{ $row['number'] ?? 'Approval' }}</a>
                                    @else
                                        <strong>{{ $row['number'] ?? 'Approval' }}</strong>
                                    @endif
                                    <small>{{ $row['description'] ?? '' }}</small>
                                </td>
                                <td><span class="b360-badge b-blue">{{ $row['type'] ?? 'Record' }}</span><small>{{ $row['source_module'] ?? '' }}</small></td>
                                <td>{{ $row['raised_by'] ?? '—' }}</td>
                                <td><strong>{{ $row['amount_display'] ?? '—' }}</strong></td>
                                <td>{{ $row['age'] ?? '—' }}</td>
                                <td><span class="b360-badge {{ ($row['priority'] ?? '') === 'high' ? 'b-red' : 'b-orange' }}">{{ str($row['priority'] ?? 'normal')->headline() }}</span></td>
                                <td><span class="b360-badge b-slate">{{ str($row['status'] ?? 'pending')->headline() }}</span></td>
                                <td>
                                    <div class="b360-row-actions justify-content-end">
                                        @if ($recordUrl)
                                            <a href="{{ $recordUrl }}" class="b360-small-btn">Open</a>
                                        @endif
                                        @if (! empty($row['can_approve']) || ! empty($row['can_reject']))
                                            <details class="b360-decision-menu">
                                                <summary class="b360-small-btn b360-small-btn-primary">Decide</summary>
                                                <div class="b360-decision-popover">
                                                    <strong>{{ $row['number'] ?? 'Approval' }}</strong>
                                                    <small>{{ $row['type'] ?? 'Approval record' }} · {{ $row['amount_display'] ?? 'No amount' }}</small>
                                                    @if (! empty($row['can_reject']) && ! empty($row['reject_url']))
                                                        <form method="POST" action="{{ $row['reject_url'] }}" class="b360-decision-form">
                                                            @csrf
                                                            @method('PATCH')
                                                            <label>
                                                                <span>Rejection note</span>
                                                                <input class="form-control" name="{{ $row['reject_payload_key'] ?? 'note' }}" maxlength="500" required>
                                                            </label>
                                                            <button class="b360-danger-btn" type="submit">Reject</button>
                                                        </form>
                                                    @endif
                                                    @if (! empty($row['can_approve']) && ! empty($row['approve_url']))
                                                        <form method="POST" action="{{ $row['approve_url'] }}" class="b360-decision-form">
                                                            @csrf
                                                            @method('PATCH')
                                                            <label>
                                                                <span>Approval note</span>
                                                                <input class="form-control" name="{{ $row['approve_payload_key'] ?? 'note' }}" maxlength="500">
                                                            </label>
                                                            <button class="b360-primary-btn" type="submit">Approve</button>
                                                        </form>
                                                    @endif
                                                </div>
                                            </details>
                                        @else
                                            <span class="b360-muted">View only</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>

                        @empty
                            <tr>
                                <td colspan="8">
                                    <div class="b360-empty">
                                        <i class="fa-solid fa-box-open"></i>
                                        <strong>No {{ str($activeTab)->replace('_', ' ') }} approvals</strong>
                                        <span>No approval records match the selected filters.</span>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        </section>

        @if (($pagination['last_page'] ?? 1) > 1)
            <nav class="b360-pagination" aria-label="Approval pagination">
                @for ($page = 1; $page <= (int) $pagination['last_page']; $page++)
                    <a href="{{ route('builder360.approvals.index', array_merge($filterQuery, ['page' => $page])) }}" class="{{ (int) ($pagination['page'] ?? 1) === $page ? 'is-active' : '' }}">{{ $page }}</a>
                @endfor
            </nav>
        @endif
    @endif
@endsection
