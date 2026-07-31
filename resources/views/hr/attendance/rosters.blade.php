@extends('layouts.builder360-classic')

@section('title', 'Shifts & Rosters - Builder360 ERP-CRM')

@section('content')
<x-hr.people-workspace
    title="Shifts & Rosters"
    description="Effective assignments, dated rosters, reusable rotations, governed swaps, and payroll-ready attendance locks."
    active="shifts"
>
    <x-slot:actions>
        @if ($abilities['canManage'])
            <a class="people-button is-primary" href="#roster-create"><i class="fa-solid fa-plus" aria-hidden="true"></i> New roster</a>
        @endif
    </x-slot:actions>

    @if (session('status'))
        <section class="people-alert is-success" role="status">{{ session('status') }}</section>
    @endif

    @if ($errors->any())
        <section class="people-alert is-danger" role="alert" tabindex="-1">
            <strong>Please correct the highlighted roster fields.</strong>
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </section>
    @endif

    <nav class="people-ops-tabs" aria-label="Attendance Management sections">
        @can('viewAny', \App\Models\AttendanceRegularizationRequest::class)
            <a href="{{ route('hr.attendance-records.index') }}"><i class="fa-solid fa-table-list" aria-hidden="true"></i> Attendance register</a>
            <a href="{{ route('hr.attendance-records.index', ['view' => 'exceptions']) }}"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> Exceptions &amp; basis</a>
            <a href="{{ route('hr.attendance-regularizations.index') }}"><i class="fa-solid fa-clipboard-check" aria-hidden="true"></i> Regularizations</a>
            <a href="{{ route('hr.attendance-shifts.index') }}"><i class="fa-regular fa-clock" aria-hidden="true"></i> Shift definitions</a>
        @endcan
        <a class="is-active" href="{{ route('hr.attendance-rosters.index') }}" aria-current="page"><i class="fa-solid fa-calendar-days" aria-hidden="true"></i> Rosters &amp; rotations</a>
    </nav>

    @php($activeView = $filters['view'] ?? 'rosters')
    @php($rosterViews = ['rosters' => ['calendar-days', 'Rosters'], 'rotations' => ['rotate', 'Rotations'], 'swaps' => ['right-left', 'Shift swaps']])
    @if ($abilities['canViewPeriods'])
        @php($rosterViews['periods'] = ['lock', 'Attendance periods'])
    @endif
    <nav class="people-ops-tabs is-secondary" aria-label="Roster workspace sections">
        @foreach ($rosterViews as $view => [$icon, $label])
            <a href="{{ route('hr.attendance-rosters.index', ['view' => $view]) }}" @class(['is-active' => $activeView === $view]) @if($activeView === $view) aria-current="page" @endif>
                <i class="fa-solid fa-{{ $icon }}" aria-hidden="true"></i> {{ $label }}
            </a>
        @endforeach
    </nav>

    @include('hr.attendance.rosters.'.$activeView)
</x-hr.people-workspace>
@endsection
