@extends('layouts.builder360-classic')

@section('title', 'Leave Management - Builder360 ERP-CRM')

@section('content')
<x-hr.people-workspace
    title="Leave Management"
    description="Leave Workspace for requests, balances, governed processing, encashment, and active policy controls."
    active="leave"
>
    <x-slot:actions>
        @if ($activeRegister === 'requests' && $abilities['canCreateLeaveRequest'])
            <a class="people-button is-primary" href="#leave-request-form">
                <i class="fa-solid fa-plus" aria-hidden="true"></i> New leave request
            </a>
        @endif
    </x-slot:actions>

    @if (session('status'))
        <section class="people-alert is-success" role="status">{{ session('status') }}</section>
    @endif

    @if ($errors->any())
        <section class="people-alert is-danger" role="alert" tabindex="-1">
            <strong>Please correct the highlighted leave fields.</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    <nav class="people-ops-tabs" aria-label="Leave Management sections">
        <a href="{{ route('hr.leave-requests.index') }}" @class(['is-active' => $activeRegister === 'requests']) @if($activeRegister === 'requests') aria-current="page" @endif>
            <i class="fa-regular fa-calendar-check" aria-hidden="true"></i> Requests
        </a>
        <a href="{{ route('hr.leave-balances.index') }}" aria-label="Leave balances" @class(['is-active' => $activeRegister === 'balances']) @if($activeRegister === 'balances') aria-current="page" @endif>
            <i class="fa-solid fa-scale-balanced" aria-hidden="true"></i> Balances
        </a>
        <a href="{{ route('hr.leave-processing-runs.index') }}" @class(['is-active' => $activeRegister === 'processing']) @if($activeRegister === 'processing') aria-current="page" @endif>
            <i class="fa-solid fa-gears" aria-hidden="true"></i> Processing
        </a>
        <a href="{{ route('hr.leave-encashments.index') }}" @class(['is-active' => $activeRegister === 'encashments']) @if($activeRegister === 'encashments') aria-current="page" @endif>
            <i class="fa-solid fa-wallet" aria-hidden="true"></i> Encashment
        </a>
        <a href="{{ route('hr.leave-types.index') }}" aria-label="Leave types policy controls" @class(['is-active' => $activeRegister === 'types']) @if($activeRegister === 'types') aria-current="page" @endif>
            <i class="fa-solid fa-sliders" aria-hidden="true"></i> Policy controls
        </a>
    </nav>

    <section class="people-ops-kpis is-four" aria-label="Leave operations summary">
        <article class="people-ops-kpi is-warning">
            <span class="people-ops-kpi-icon"><i class="fa-solid fa-hourglass-half" aria-hidden="true"></i></span>
            <span>Pending requests</span>
            <strong>{{ $summary->pendingRequests }}</strong>
            <small>Submitted requests in your authorized scope</small>
        </article>
        <article class="people-ops-kpi is-info">
            <span class="people-ops-kpi-icon"><i class="fa-solid fa-person-walking-luggage" aria-hidden="true"></i></span>
            <span>On leave today</span>
            <strong>{{ $summary->onLeaveToday }}</strong>
            <small>Approved leave intersecting today</small>
        </article>
        <article class="people-ops-kpi is-purple">
            <span class="people-ops-kpi-icon"><i class="fa-regular fa-calendar" aria-hidden="true"></i></span>
            <span>Upcoming</span>
            <strong>{{ $summary->upcoming }}</strong>
            <small>Future submitted or approved requests</small>
        </article>
        <article class="people-ops-kpi is-success">
            <span class="people-ops-kpi-icon"><i class="fa-solid fa-indian-rupee-sign" aria-hidden="true"></i></span>
            <span>Encashment pending</span>
            <strong>{{ $summary->pendingEncashments }}</strong>
            <small>Submitted requests awaiting a decision</small>
        </article>
    </section>

    @include('hr.leave.partials.'.$activeRegister)
</x-hr.people-workspace>
@endsection
