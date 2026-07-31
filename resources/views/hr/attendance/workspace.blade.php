@extends('layouts.builder360-classic')

@section('title', 'Attendance Management - Builder360 ERP-CRM')

@section('content')
<x-hr.people-workspace
    title="Attendance Management"
    description="Attendance records, explainable exceptions, regularization approvals, shift definitions, and effective assignments."
    :active="in_array($activeRegister, ['shifts', 'assignments'], true) ? 'shifts' : 'attendance'"
>
    <x-slot:actions>
        @if ($activeRegister === 'regularizations' && $abilities['canCreateRegularization'])
            <a class="people-button is-primary" href="#regularization-form">
                <i class="fa-solid fa-plus" aria-hidden="true"></i> New regularization
            </a>
        @elseif ($activeRegister === 'shifts' && $abilities['canCreateShift'])
            <a class="people-button is-primary" href="#shift-form">
                <i class="fa-solid fa-plus" aria-hidden="true"></i> New shift
            </a>
        @endif
    </x-slot:actions>

    @if (session('status'))
        <section class="people-alert is-success" role="status">{{ session('status') }}</section>
    @endif

    @if ($errors->any())
        <section class="people-alert is-danger" role="alert" tabindex="-1">
            <strong>Please correct the highlighted attendance fields.</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    <nav class="people-ops-tabs" aria-label="Attendance Management sections">
        <a href="{{ route('hr.attendance-records.index') }}" @class(['is-active' => $activeRegister === 'records']) @if($activeRegister === 'records') aria-current="page" @endif>
            <i class="fa-solid fa-table-list" aria-hidden="true"></i> Attendance register
        </a>
        <a href="{{ route('hr.attendance-records.index', ['view' => 'exceptions']) }}" @class(['is-active' => $activeRegister === 'exceptions']) @if($activeRegister === 'exceptions') aria-current="page" @endif>
            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> Exceptions &amp; basis
        </a>
        <a href="{{ route('hr.attendance-records.index', ['view' => 'trace']) }}" @class(['is-active' => $activeRegister === 'trace']) @if($activeRegister === 'trace') aria-current="page" @endif>
            <i class="fa-solid fa-diagram-project" aria-hidden="true"></i> Calculation trace
        </a>
        <a href="{{ route('hr.attendance-regularizations.index') }}" @class(['is-active' => $activeRegister === 'regularizations']) @if($activeRegister === 'regularizations') aria-current="page" @endif>
            <i class="fa-solid fa-clipboard-check" aria-hidden="true"></i> Regularizations
        </a>
        <a href="{{ route('hr.attendance-shifts.index') }}" @class(['is-active' => $activeRegister === 'shifts']) @if($activeRegister === 'shifts') aria-current="page" @endif>
            <i class="fa-regular fa-clock" aria-hidden="true"></i> Shift definitions
        </a>
        <a href="{{ route('hr.attendance-shifts.index', ['view' => 'assignments']) }}" @class(['is-active' => $activeRegister === 'assignments']) @if($activeRegister === 'assignments') aria-current="page" @endif>
            <i class="fa-solid fa-user-clock" aria-hidden="true"></i> Assignments
        </a>
        <a href="{{ route('hr.attendance-rosters.index') }}">
            <i class="fa-solid fa-calendar-days" aria-hidden="true"></i> Rosters &amp; rotations
        </a>
    </nav>

    @include('hr.attendance.partials.'.$activeRegister)
</x-hr.people-workspace>
@endsection
