@extends('layouts.builder360-classic')

@section('title', 'Reports & MIS - Builder360 ERP-CRM')

@section('content')
@php
    $reports = $catalog['reports'] ?? [];
    $reportCount = (int) ($catalog['reportCount'] ?? count($reports));
    $compensationVisible = (bool) ($catalog['compensationVisible'] ?? false);
    $availableFormats = collect($reports)
        ->flatMap(fn (array $report): array => array_values($report['formats'] ?? []))
        ->unique()
        ->values();
@endphp

<x-hr.people-workspace
    title="Reports & MIS"
    description="Run the HR exports that are implemented, permission-scoped, and auditable today."
    active="reports"
>
    <x-slot:actions>
        @can('viewAny', \App\Models\Employee::class)
            <a class="people-button" href="{{ route('hr.employees.index') }}">
                <i class="fa-solid fa-address-card" aria-hidden="true"></i> Employee Master
            </a>
        @endcan
    </x-slot:actions>

    <section class="people-ops-kpis is-four" aria-label="HR report catalog summary">
        <article class="people-ops-kpi is-info">
            <span class="people-ops-kpi-icon"><i class="fa-solid fa-file-lines" aria-hidden="true"></i></span>
            <span>Available reports</span><strong>{{ number_format($reportCount) }}</strong><small>Backed by implemented export routes</small>
        </article>
        <article class="people-ops-kpi is-success">
            <span class="people-ops-kpi-icon"><i class="fa-solid fa-file-export" aria-hidden="true"></i></span>
            <span>Export formats</span><strong>{{ number_format($availableFormats->count()) }}</strong><small>{{ $availableFormats->isEmpty() ? 'No export format is available' : $availableFormats->map(fn (string $format): string => strtoupper($format === 'xlsx' ? 'Excel' : $format))->join(', ') }}</small>
        </article>
        <article class="people-ops-kpi is-purple">
            <span class="people-ops-kpi-icon"><i class="fa-solid fa-building-shield" aria-hidden="true"></i></span>
            <span>Data scope</span><strong>Company</strong><small>Role and company filters remain authoritative</small>
        </article>
        <article class="people-ops-kpi is-warning">
            <span class="people-ops-kpi-icon"><i class="fa-solid fa-lock" aria-hidden="true"></i></span>
            <span>Employee compensation</span><strong>{{ $compensationVisible ? 'Permitted' : 'Restricted' }}</strong><small>{{ $compensationVisible ? 'Included only where the employee export policy permits it' : 'Masked in Employee Master exports' }}</small>
        </article>
    </section>

    <section class="people-alert" role="note" aria-label="Report security notice">
        <strong>Governed exports only.</strong>
        Each report keeps its existing company scope and authorization policy. Employee exports also retain Employee Master field-visibility rules. Every completed export is recorded in the audit trail.
    </section>

    <section class="people-ops-panel" aria-labelledby="hr-report-catalog-title">
        <header class="people-ops-panel-head">
            <div><h2 id="hr-report-catalog-title">Report catalog</h2><p>Only production-backed exports are listed; unavailable prototype reports are not shown.</p></div>
            <span class="people-count">{{ number_format($reportCount) }}</span>
        </header>

        @forelse ($reports as $report)
            <article class="people-command-row">
                <span class="people-command-row-icon"><i class="fa-solid {{ $report['icon'] }}" aria-hidden="true"></i></span>
                <span class="people-command-row-copy">
                    <strong>{{ $report['title'] }}</strong>
                    <small>{{ $report['description'] }} &middot; {{ $report['category'] }}</small>
                </span>
                <span class="people-page-actions" aria-label="Export {{ $report['title'] }}">
                    @foreach ($report['formats'] as $format => $label)
                        @php
                            $exportParameters = array_merge(
                                ['format' => $format],
                                $report['routeParameters'] ?? ['report_type' => $report['reportType'] ?? $report['title']],
                            );
                        @endphp
                        <a
                            class="people-button{{ $format === 'csv' ? ' is-primary' : '' }}"
                            href="{{ route($report['routeName'], $exportParameters) }}"
                            aria-label="Export {{ $report['title'] }} as {{ $label }}"
                        >
                            <i class="fa-solid fa-download" aria-hidden="true"></i> {{ $label }}
                        </a>
                    @endforeach
                </span>
            </article>
        @empty
            <x-hr.people-state
                type="restricted"
                icon="fa-file-circle-xmark"
                title="No HR reports are available"
                message="Your current role does not have access to an implemented HR export."
                compact
            />
        @endforelse
    </section>
</x-hr.people-workspace>
@endsection
