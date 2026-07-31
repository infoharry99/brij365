@extends('layouts.builder360-classic')

@section('title', 'HR Settings - Builder360 ERP-CRM')

@section('content')
@php
    $settings = $workspace['settings'];
    $filters = $workspace['filters'];
    $summary = $workspace['summary'];
    $tabs = $workspace['tabs'];
    $activeTab = $filters['tab'] ?? 'overview';
    $activeFilters = collect(['search' => 'Search', 'status' => 'Status'])->filter(fn (string $label, string $key): bool => filled($filters[$key] ?? null));
@endphp

<x-hr.people-workspace
    title="HR Settings"
    description="Review HR, payroll, and approval-workflow rules governed through maker-checker System Settings."
    active="settings"
>
    <x-slot:actions>
        @if ($workspace['canViewRoles'])
            <a class="people-button" href="{{ route('admin.roles.index') }}">
                <i class="fa-solid fa-user-shield" aria-hidden="true"></i> Role permissions
            </a>
        @endif
        <a class="people-button{{ $workspace['canManage'] ? ' is-primary' : '' }}" href="{{ route('settings.system-settings.index') }}">
            <i class="fa-solid fa-sliders" aria-hidden="true"></i> {{ $workspace['canManage'] ? 'Create governed draft' : 'Open governed register' }}
        </a>
    </x-slot:actions>

    @if (session('status'))
        <section class="people-alert is-success" role="status">{{ session('status') }}</section>
    @endif

    <nav class="people-ops-tabs" aria-label="HR setting categories">
        @foreach ($tabs as $key => $label)
            <a
                href="{{ route('hr.settings.index', array_filter(['tab' => $key, 'search' => $filters['search'] ?? null, 'status' => $filters['status'] ?? null])) }}"
                @class(['is-active' => $activeTab === $key])
                @if ($activeTab === $key) aria-current="page" @endif
            >{{ $label }}</a>
        @endforeach
    </nav>

    <section class="people-ops-kpis is-four" aria-label="HR setting summary">
        <article class="people-ops-kpi is-info"><span class="people-ops-kpi-icon"><i class="fa-solid fa-sliders" aria-hidden="true"></i></span><span>Visible versions</span><strong>{{ number_format((int) ($summary['total'] ?? 0)) }}</strong><small>Within the selected category and search</small></article>
        <article class="people-ops-kpi is-warning"><span class="people-ops-kpi-icon"><i class="fa-solid fa-pen-ruler" aria-hidden="true"></i></span><span>Draft</span><strong>{{ number_format((int) ($summary['draft'] ?? 0)) }}</strong><small>Awaiting governed review</small></article>
        <article class="people-ops-kpi is-success"><span class="people-ops-kpi-icon"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></span><span>Active</span><strong>{{ number_format((int) ($summary['active'] ?? 0)) }}</strong><small>Effective approved versions</small></article>
        <article class="people-ops-kpi"><span class="people-ops-kpi-icon"><i class="fa-solid fa-box-archive" aria-hidden="true"></i></span><span>Archived</span><strong>{{ number_format((int) ($summary['archived'] ?? 0)) }}</strong><small>Retained configuration history</small></article>
    </section>

    <section class="people-alert" role="note">
        <strong>One-company governed configuration.</strong>
        This hub does not edit settings in browser state. Changes are created as drafts and become active only through the existing authorized approval workflow.
    </section>

    <section class="people-ops-panel has-mobile-cards" aria-labelledby="hr-settings-register-title">
        <header class="people-ops-panel-head">
            <div><h2 id="hr-settings-register-title">HR configuration register</h2><p>{{ number_format($settings->total()) }} setting version{{ $settings->total() === 1 ? '' : 's' }} match the current filters.</p></div>
            @if ($workspace['canApprove'])<span class="people-count">Approver access</span>@endif
        </header>

        <form method="GET" action="{{ route('hr.settings.index') }}" class="people-ops-filterbar" aria-label="Filter HR settings">
            <input type="hidden" name="tab" value="{{ $activeTab }}">
            <label class="people-field">
                <span>Search settings</span>
                <input class="people-control" type="search" name="search" maxlength="120" value="{{ $filters['search'] ?? '' }}" placeholder="Label, key or description">
            </label>
            <label class="people-field">
                <span>Status</span>
                <select class="people-control" name="status">
                    <option value="">All statuses</option>
                    @foreach (['draft' => 'Draft', 'active' => 'Active', 'archived' => 'Archived'] as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <div class="people-page-actions">
                <button class="people-button" type="submit"><i class="fa-solid fa-filter" aria-hidden="true"></i> Apply</button>
                <a class="people-button" href="{{ route('hr.settings.index', ['tab' => $activeTab]) }}">Clear</a>
            </div>
        </form>

        @if ($activeFilters->isNotEmpty())
            <nav class="people-filter-chips" aria-label="Active HR setting filters">
                <span>Active filters</span>
                @foreach ($activeFilters as $key => $label)
                    <a class="people-filter-chip" href="{{ route('hr.settings.index', request()->except([$key, 'page'])) }}" aria-label="Remove {{ $label }} filter">
                        {{ $label }}: {{ $filters[$key] }} <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </a>
                @endforeach
                <a class="people-filter-chip" href="{{ route('hr.settings.index', ['tab' => $activeTab]) }}">Clear all</a>
            </nav>
        @endif

        <div class="people-ops-table-wrap">
            <table class="people-ops-table">
                <caption>HR, payroll, and approval workflow setting versions</caption>
                <thead><tr><th scope="col">Setting</th><th scope="col">Category / scope</th><th scope="col">Version / effective</th><th scope="col">Configuration</th><th scope="col">Maker / checker</th><th scope="col">Status</th><th scope="col" class="is-actions">Action</th></tr></thead>
                <tbody>
                    @forelse ($settings as $setting)
                        <tr>
                            <td><strong>{{ $setting->label }}</strong><small>{{ $setting->settingKey }}</small></td>
                            <td>{{ str($setting->settingGroup)->headline() }}<small>{{ $setting->scopeLabel }}</small></td>
                            <td>{{ $setting->versionLabel }}<small>{{ $setting->effectiveLabel }}</small></td>
                            <td>{{ $setting->typeLabel }}<small>{{ $setting->valueSummary }}</small></td>
                            <td>{{ $setting->makerLabel }}<small>{{ $setting->checkerLabel }}</small></td>
                            <td><span class="people-status {{ $setting->statusTone }}">{{ $setting->statusLabel }}</span></td>
                            <td class="is-actions"><a class="people-button" href="{{ route('settings.system-settings.index', ['setting_key' => $setting->settingKey, 'status' => $setting->status === 'draft' ? 'draft' : null]) }}">{{ $setting->canApprove ? 'Review draft' : 'Open history' }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-hr.people-state type="filtered" icon="fa-sliders" title="No HR settings match these filters" message="Clear the filters or open the governed System Settings register if your role permits it." compact /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="people-ops-mobile-list" aria-label="HR setting cards">
            @foreach ($settings as $setting)
                <article class="people-ops-mobile-card">
                    <div class="people-ops-mobile-card-head"><strong>{{ $setting->label }}</strong><span class="people-status {{ $setting->statusTone }}">{{ $setting->statusLabel }}</span></div>
                    <dl class="people-ops-mobile-facts">
                        <div><dt>Key</dt><dd>{{ $setting->settingKey }}</dd></div>
                        <div><dt>Scope</dt><dd>{{ $setting->scopeLabel }}</dd></div>
                        <div><dt>Version</dt><dd>{{ $setting->versionLabel }} &middot; {{ $setting->effectiveLabel }}</dd></div>
                        <div><dt>Configuration</dt><dd>{{ $setting->valueSummary }}</dd></div>
                    </dl>
                    <div class="people-card-action"><a class="people-button" href="{{ route('settings.system-settings.index', ['setting_key' => $setting->settingKey, 'status' => $setting->status === 'draft' ? 'draft' : null]) }}">{{ $setting->canApprove ? 'Review draft' : 'Open history' }}</a></div>
                </article>
            @endforeach
        </div>

        <footer class="people-pagination">
            <span>Showing {{ $settings->firstItem() ?? 0 }}&ndash;{{ $settings->lastItem() ?? 0 }} of {{ number_format($settings->total()) }}</span>
            {{ $settings->links() }}
        </footer>
    </section>
</x-hr.people-workspace>
@endsection
