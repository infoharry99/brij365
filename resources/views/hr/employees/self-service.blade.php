@extends('layouts.builder360-classic')

@section('title', 'Employee Self Service - Builder360 ERP-CRM')

@section('content')
@php
    $summary = $selfService['summary'] ?? [];
    $recentAttendance = $selfService['recentAttendance'] ?? [];
    $myActions = $selfService['myActions'] ?? [];
    $quickActions = $selfService['quickActions'] ?? [];
    $leaveBalances = $selfService['leaveBalances'] ?? [];
    $abilities = $selfService['abilities'] ?? [];
@endphp

<x-hr.people-workspace
    title="Employee Self Service"
    :description="'Welcome back, '.$employee->name.'. Your attendance, leave, payroll, and HR actions in one place.'"
    eyebrow="My workplace"
    active="employees"
    :self-service="true"
>
    <x-slot:actions>
        <a class="people-button" href="{{ route('hr.employees.me.tax-inputs.edit') }}"><i class="fa-solid fa-file-invoice" aria-hidden="true"></i> Tax declarations</a>
        <a class="people-button" href="{{ route('hr.employees.me.profile') }}"><i class="fa-solid fa-id-card" aria-hidden="true"></i> View my profile</a>
    </x-slot:actions>

    @if (session('status'))
        <section class="people-alert is-success" role="status">{{ session('status') }}</section>
    @endif

    <section class="people-ess-hero" aria-labelledby="ess-employee-name">
        <x-ui.user-avatar :user="$employee->user" :label="$employee->name" class="people-ess-avatar" />
        <div class="people-ess-identity">
            <span>Employee dashboard</span>
            <h2 id="ess-employee-name">{{ $employee->name }}</h2>
            <p>{{ $employee->employee_code }} &middot; {{ $employee->designation }} &middot; {{ $employee->department }}</p>
        </div>
        <span class="people-status is-{{ ['active' => 'success', 'probation' => 'warning', 'on_notice' => 'warning', 'separated' => 'danger'][$employee->status] ?? 'muted' }}">{{ str_replace('_', ' ', ucfirst($employee->status)) }}</span>
    </section>

    <section class="people-ess-kpis" aria-label="My employment summary">
        <article class="people-ess-kpi is-info">
            <span class="people-command-kpi-icon"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i></span>
            <div><span>Attendance</span><strong>{{ ($summary['attendance_percent'] ?? null) !== null ? number_format((float) $summary['attendance_percent'], 1).'%' : '-' }}</strong><small>{{ (int) ($summary['attendance_marked_days'] ?? 0) > 0 ? number_format((int) ($summary['attendance_present_days'] ?? 0)).' of '.number_format((int) $summary['attendance_marked_days']).' recorded days' : 'No records this month' }}</small></div>
        </article>
        <article class="people-ess-kpi is-success">
            <span class="people-command-kpi-icon"><i class="fa-solid fa-plane-departure" aria-hidden="true"></i></span>
            <div><span>Leave Available</span><strong>{{ number_format((float) ($summary['leave_available_days'] ?? 0), 2) }}</strong><small>Days across active leave types</small></div>
        </article>
        <article class="people-ess-kpi is-purple">
            <span class="people-command-kpi-icon"><i class="fa-solid fa-file-invoice-dollar" aria-hidden="true"></i></span>
            <div><span>Latest Payslip</span><strong>{{ $summary['latest_payslip_period'] ?? '-' }}</strong><small>{{ $summary['latest_payslip_status'] ?? 'No approved payroll' }}</small></div>
        </article>
        <article class="people-ess-kpi is-warning">
            <span class="people-command-kpi-icon"><i class="fa-solid fa-inbox" aria-hidden="true"></i></span>
            <div><span>Open Requests</span><strong>{{ number_format((int) ($summary['open_requests'] ?? 0)) }}</strong><small>Items awaiting completion</small></div>
        </article>
    </section>

    <section class="people-ess-section" aria-labelledby="quick-actions-title">
        <header class="people-ess-section-head"><div><h2 id="quick-actions-title">Quick Actions</h2><p>Start an authorized self-service request.</p></div></header>
        @if ($quickActions !== [])
            <div class="people-ess-quick-actions">
                @foreach ($quickActions as $action)
                    @continue(blank($action['url'] ?? null))
                    <a href="{{ $action['url'] }}">
                        <span><i class="fa-solid {{ $action['icon'] ?? 'fa-arrow-up-right-from-square' }}" aria-hidden="true"></i></span>
                        <strong>{{ $action['label'] ?? 'Open workspace' }}</strong>
                        <small>{{ $action['description'] ?? 'Continue in Builder360' }}</small>
                        <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                    </a>
                @endforeach
            </div>
        @else
            <x-hr.people-state type="restricted" title="No self-service actions available" message="Your current role has no request actions enabled." :compact="true" />
        @endif
    </section>

    <section class="people-ess-grid">
        <article class="people-ess-section" aria-labelledby="my-attendance-title">
            <header class="people-ess-section-head"><div><h2 id="my-attendance-title">My Attendance</h2><p>Your most recent recorded attendance days.</p></div>@if(($abilities['canViewAttendanceRegularizations'] ?? false))<a href="{{ route('hr.attendance-records.index') }}">View attendance <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>@endif</header>
            @if ($recentAttendance !== [])
                <div class="people-attendance-strip" role="list" aria-label="Recent attendance">
                    @foreach ($recentAttendance as $record)
                        @php
                            $attendanceTone = in_array(($record['tone'] ?? ''), ['success', 'warning', 'danger', 'info', 'muted'], true)
                                ? $record['tone']
                                : match ($record['status'] ?? '') { 'present', 'holiday', 'weekly_off' => 'success', 'late', 'half_day', 'on_leave' => 'warning', 'absent' => 'danger', default => 'muted' };
                        @endphp
                        <div class="people-attendance-day is-{{ $attendanceTone }}" role="listitem">
                            <span>{{ $record['day_label'] ?? $record['work_date'] ?? 'Day' }}</span>
                            <strong>{{ $record['status_code'] ?? strtoupper(substr((string) ($record['status'] ?? '-'), 0, 2)) }}</strong>
                            <small>{{ str_replace('_', ' ', ucfirst($record['status'] ?? 'not marked')) }}</small>
                        </div>
                    @endforeach
                </div>
            @else
                <x-hr.people-state title="No attendance records yet" message="Attendance entries will appear here after they are recorded for you." icon="fa-calendar-xmark" />
            @endif
        </article>

        <article class="people-ess-section" aria-labelledby="my-actions-title">
            <header class="people-ess-section-head"><div><h2 id="my-actions-title">My Actions</h2><p>Items that need your attention.</p></div></header>
            @forelse ($myActions as $action)
                @php $actionUrl = $action['url'] ?? null; @endphp
                @if ($actionUrl)
                    <a href="{{ $actionUrl }}" class="people-ess-action-row">
                @else
                    <div class="people-ess-action-row">
                @endif
                    <span class="people-command-row-icon"><i class="fa-solid {{ $action['icon'] ?? 'fa-circle-exclamation' }}" aria-hidden="true"></i></span>
                    <span><strong>{{ $action['title'] ?? $action['label'] ?? 'Action required' }}</strong><small>{{ $action['description'] ?? $action['meta'] ?? '' }}</small></span>
                    @if(filled($action['status'] ?? null))<span class="people-status is-{{ in_array(($action['tone'] ?? ''), ['success', 'warning', 'danger', 'info'], true) ? $action['tone'] : 'warning' }}">{{ str_replace('_', ' ', ucfirst($action['status'])) }}</span>@elseif($actionUrl)<i class="fa-solid fa-chevron-right" aria-hidden="true"></i>@endif
                @if ($actionUrl)
                    </a>
                @else
                    </div>
                @endif
            @empty
                <x-hr.people-state title="No pending actions" message="You have no employee actions waiting for attention." icon="fa-circle-check" />
            @endforelse
        </article>

        <article class="people-ess-section is-wide" aria-labelledby="leave-balances-title">
            <header class="people-ess-section-head"><div><h2 id="leave-balances-title">Leave Balances</h2><p>Current-year availability from your authorized leave ledger.</p></div>@if(($abilities['canViewLeaveRequests'] ?? false))<a href="{{ route('hr.leave-balances.index') }}">View balances <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>@endif</header>
            @if ($leaveBalances !== [])
                <div class="people-leave-balance-grid">
                    @foreach ($leaveBalances as $balance)
                        <article><span>{{ $balance['code'] ?? 'Leave' }}</span><strong>{{ number_format((float) ($balance['available_days'] ?? 0), 2) }}</strong><small>{{ $balance['name'] ?? 'Available days' }}@if((float) ($balance['pending_days'] ?? 0) > 0) &middot; {{ number_format((float) $balance['pending_days'], 2) }} pending @endif</small></article>
                    @endforeach
                </div>
            @else
                <x-hr.people-state title="No leave balances available" message="Balances will appear after a leave ledger is available for the current year." icon="fa-calendar-minus" :compact="true" />
            @endif
        </article>
    </section>
</x-hr.people-workspace>
@endsection
