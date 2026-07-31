@extends('layouts.builder360-classic')

@section('title', 'HR Command Center - Builder360 ERP-CRM')

@section('content')
@php
    $summary = $dashboard['summary'] ?? [];
    $approvalInbox = $dashboard['approvalInbox'] ?? [];
    $departmentHeadcount = $dashboard['departmentHeadcount'] ?? [];
    $lifecycleDue = $dashboard['lifecycleDue'] ?? [];
    $complianceRisk = $dashboard['complianceRisk'] ?? [];
    $abilities = $dashboard['abilities'] ?? [];
    // Presentation fails closed if the read-model contract is ever incomplete.
    // The command-center Action remains the authority that supplies these flags.
    $canViewAttendance = (bool) ($abilities['canViewAttendance'] ?? false);
    $canViewPayroll = (bool) ($abilities['canViewPayroll'] ?? false);
    $canViewRecruitment = (bool) ($abilities['canViewRecruitment'] ?? false);
    $canViewCompliance = (bool) ($abilities['canViewCompliance'] ?? false);
    $canViewLifecycle = (bool) (($abilities['canViewConfirmations'] ?? false) || ($abilities['canViewSettlements'] ?? false));
@endphp

<x-hr.people-workspace
    title="HR Command Center"
    description="Company-scoped workforce operations, approvals, lifecycle work, and compliance signals."
    active="dashboard"
>
    <x-slot:actions>
        <a class="people-button" href="{{ route('hr.employees.index') }}">
            <i class="fa-solid fa-address-card" aria-hidden="true"></i> Employee Master
        </a>
    </x-slot:actions>

    @if (session('status'))
        <section class="people-alert is-success" role="status">{{ session('status') }}</section>
    @endif

    <section class="people-command-kpis" aria-label="HR command summary">
        <article class="people-command-kpi is-accent">
            <span class="people-command-kpi-icon"><i class="fa-solid fa-user-group" aria-hidden="true"></i></span>
            <div><span>Total Employees</span><strong>{{ number_format((int) ($summary['total_headcount'] ?? 0)) }}</strong><small>{{ number_format((int) ($summary['active_headcount'] ?? 0)) }} active</small></div>
        </article>

        <article class="people-command-kpi is-info">
            <span class="people-command-kpi-icon"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i></span>
            <div>
                <span>Attendance Today</span>
                @if ($canViewAttendance && ($summary['attendance_today_percent'] ?? null) !== null)
                    <strong>{{ number_format((float) $summary['attendance_today_percent'], 1) }}%</strong>
                    <small>{{ number_format((int) ($summary['attendance_present_today'] ?? 0)) }} of {{ number_format((int) ($summary['attendance_marked_today'] ?? 0)) }} marked records</small>
                @elseif ($canViewAttendance)
                    <strong>-</strong><small>No attendance marked today</small>
                @else
                    <strong class="people-restricted-value"><i class="fa-solid fa-lock" aria-hidden="true"></i></strong><small>Restricted for this role</small>
                @endif
            </div>
        </article>

        <article class="people-command-kpi is-success">
            <span class="people-command-kpi-icon"><i class="fa-solid fa-money-check-dollar" aria-hidden="true"></i></span>
            <div>
                <span>Latest Payroll</span>
                @if ($canViewPayroll && array_key_exists('latest_payroll_net_payable', $summary))
                    <strong>{{ $summary['latest_payroll_net_payable'] !== null ? 'INR '.number_format((float) $summary['latest_payroll_net_payable'], 2) : '-' }}</strong>
                    <small>{{ $summary['latest_payroll_label'] ?? 'No approved payroll run' }}</small>
                @else
                    <strong class="people-restricted-value"><i class="fa-solid fa-lock" aria-hidden="true"></i></strong><small>Restricted for this role</small>
                @endif
            </div>
        </article>

        <article class="people-command-kpi is-warning">
            <span class="people-command-kpi-icon"><i class="fa-solid fa-list-check" aria-hidden="true"></i></span>
            <div><span>Pending Approvals</span><strong>{{ number_format((int) ($summary['pending_approvals'] ?? 0)) }}</strong><small>Items visible to your role</small></div>
        </article>

        <article class="people-command-kpi is-purple">
            <span class="people-command-kpi-icon"><i class="fa-solid fa-briefcase" aria-hidden="true"></i></span>
            <div>
                <span>Open Positions</span>
                @if ($canViewRecruitment)
                    <strong>{{ number_format((int) ($summary['open_positions'] ?? 0)) }}</strong><small>Authorized active job openings</small>
                @else
                    <strong class="people-restricted-value"><i class="fa-solid fa-lock" aria-hidden="true"></i></strong><small>Restricted for this role</small>
                @endif
            </div>
        </article>

        <article class="people-command-kpi is-danger">
            <span class="people-command-kpi-icon"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i></span>
            <div>
                <span>Compliance Alerts</span>
                @if ($canViewCompliance)
                    <strong>{{ number_format((int) ($summary['compliance_alerts'] ?? 0)) }}</strong><small>Required active-setting exceptions</small>
                @else
                    <strong class="people-restricted-value"><i class="fa-solid fa-lock" aria-hidden="true"></i></strong><small>Restricted for this role</small>
                @endif
            </div>
        </article>
    </section>

    <section class="people-command-layout">
        <article class="people-command-panel is-approvals" aria-labelledby="approval-inbox-title">
            <header class="people-command-panel-head">
                <div><span class="people-panel-icon is-warning"><i class="fa-solid fa-list-check" aria-hidden="true"></i></span><div><h2 id="approval-inbox-title">Approval Inbox</h2><p>Open HR decisions available to your role.</p></div></div>
                <span class="people-count">{{ number_format(count($approvalInbox)) }}</span>
            </header>
            @forelse ($approvalInbox as $item)
                @php $itemUrl = $item['url'] ?? null; @endphp
                @if ($itemUrl)
                    <a href="{{ $itemUrl }}" class="people-command-row">
                @else
                    <div class="people-command-row">
                @endif
                    <span class="people-command-row-icon"><i class="fa-solid {{ $item['icon'] ?? 'fa-inbox' }}" aria-hidden="true"></i></span>
                    <span class="people-command-row-copy">
                        <strong>{{ $item['subject'] ?? $item['title'] ?? $item['reference'] ?? 'Approval item' }}</strong>
                        <small>{{ $item['type'] ?? 'HR' }}@if(filled($item['owner'] ?? null)) &middot; {{ $item['owner'] }}@endif</small>
                    </span>
                    <span class="people-command-row-meta"><span class="people-status is-warning">{{ str_replace('_', ' ', ucfirst($item['status'] ?? 'pending')) }}</span><small>{{ $item['relative_time'] ?? $item['age'] ?? '' }}</small></span>
                @if ($itemUrl)
                    </a>
                @else
                    </div>
                @endif
            @empty
                <x-hr.people-state title="No pending approvals" message="New items that require your decision will appear here." icon="fa-circle-check" />
            @endforelse
        </article>

        <article class="people-command-panel" aria-labelledby="department-headcount-title">
            <header class="people-command-panel-head">
                <div><span class="people-panel-icon"><i class="fa-solid fa-chart-column" aria-hidden="true"></i></span><div><h2 id="department-headcount-title">Department Headcount</h2><p>Active workforce distribution.</p></div></div>
            </header>
            <div class="people-headcount-list">
                @forelse ($departmentHeadcount as $row)
                    <div class="people-headcount-row">
                        <div><strong>{{ $row['department'] ?? 'Unassigned' }}</strong><span>{{ number_format((int) ($row['employees'] ?? 0)) }} employees</span></div>
                        <progress value="{{ (int) ($row['employees'] ?? 0) }}" max="{{ max(1, (int) ($summary['total_headcount'] ?? 1)) }}" aria-label="{{ $row['department'] ?? 'Unassigned' }} headcount"></progress>
                    </div>
                @empty
                    <x-hr.people-state title="No department records" message="Department distribution will appear when employees are available." icon="fa-users-slash" />
                @endforelse
            </div>
        </article>

        <article class="people-command-panel" aria-labelledby="lifecycle-due-title">
            <header class="people-command-panel-head">
                <div><span class="people-panel-icon is-purple"><i class="fa-solid fa-arrows-spin" aria-hidden="true"></i></span><div><h2 id="lifecycle-due-title">Lifecycle Due</h2><p>Confirmation and separation work requiring attention.</p></div></div>
            </header>
            @if (! $canViewLifecycle)
                <x-hr.people-state type="restricted" title="Lifecycle data is restricted" message="Your current role cannot view these records." />
            @else
                @forelse ($lifecycleDue as $item)
                    @php $itemUrl = $item['url'] ?? null; @endphp
                    @if ($itemUrl)
                        <a href="{{ $itemUrl }}" class="people-command-row is-compact">
                    @else
                        <div class="people-command-row is-compact">
                    @endif
                        <span class="people-command-row-icon"><i class="fa-solid {{ $item['icon'] ?? 'fa-user-clock' }}" aria-hidden="true"></i></span>
                        <span class="people-command-row-copy"><strong>{{ $item['employee'] ?? 'Employee' }}</strong><small>{{ $item['event'] ?? 'Lifecycle event' }}@if(filled($item['owner'] ?? null)) &middot; {{ $item['owner'] }}@endif</small></span>
                        <span class="people-command-row-meta"><strong>{{ $item['due_label'] ?? $item['due'] ?? 'No due date' }}</strong><small>{{ str_replace('_', ' ', ucfirst($item['status'] ?? 'open')) }}</small></span>
                    @if ($itemUrl)
                        </a>
                    @else
                        </div>
                    @endif
                @empty
                    <x-hr.people-state title="No lifecycle work due" message="Upcoming confirmation and separation items will appear here." icon="fa-calendar-check" />
                @endforelse
            @endif
        </article>

        <article class="people-command-panel" aria-labelledby="compliance-risk-title">
            <header class="people-command-panel-head">
                <div><span class="people-panel-icon is-danger"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i></span><div><h2 id="compliance-risk-title">Compliance &amp; Risk</h2><p>Configuration health, not statutory calculation advice.</p></div></div>
            </header>
            @if (! $canViewCompliance)
                <x-hr.people-state type="restricted" title="Compliance data is restricted" message="Your current role cannot view these records." />
            @else
                @forelse ($complianceRisk as $item)
                    @php
                        $riskTone = in_array(($item['tone'] ?? ''), ['success', 'warning', 'danger', 'info'], true) ? $item['tone'] : (($item['verification'] ?? '') === 'active' ? 'success' : 'danger');
                    @endphp
                    <div class="people-command-row is-compact">
                        <span class="people-command-row-icon is-{{ $riskTone }}"><i class="fa-solid {{ $riskTone === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation' }}" aria-hidden="true"></i></span>
                        <span class="people-command-row-copy"><strong>{{ $item['name'] ?? $item['key'] ?? 'Configuration item' }}</strong><small>{{ $item['company'] ?? 'Company' }}@if(filled($item['effective'] ?? null)) &middot; {{ $item['effective'] }}@endif</small></span>
                        <span class="people-command-row-meta"><span class="people-status is-{{ $riskTone }}">{{ str_replace('_', ' ', ucfirst($item['verification'] ?? 'review')) }}</span><small>{{ $item['version'] ?? '' }}</small></span>
                    </div>
                @empty
                    <x-hr.people-state title="No compliance exceptions" message="No missing active HR settings were found for your company scope." icon="fa-shield-circle-check" />
                @endforelse
            @endif
        </article>
    </section>
</x-hr.people-workspace>
@endsection
