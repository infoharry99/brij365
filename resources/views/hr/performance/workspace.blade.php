@extends('layouts.builder360-classic')

@section('title', 'Performance Management - Builder360 ERP-CRM')

@section('content')
<x-hr.people-workspace
    title="Performance Management"
    description="Run authorized review cycles and monitor persisted employee and department outcomes."
    active="performance"
>
    <x-slot:actions>
        @if($activeRegister === 'cycles' && $abilities['canCreateCycle'])
            <a class="people-button is-primary" href="#create-performance-cycle"><i class="fa-solid fa-plus" aria-hidden="true"></i> New cycle</a>
        @elseif($activeRegister === 'reviews' && $abilities['canCreateReview'])
            <a class="people-button is-primary" href="#create-performance-review"><i class="fa-solid fa-plus" aria-hidden="true"></i> New review</a>
        @endif
    </x-slot:actions>

    @if(session('status'))<section class="people-alert is-success" role="status">{{ session('status') }}</section>@endif
    @if($errors->any())
        <section class="people-alert is-danger" role="alert" tabindex="-1">
            <strong>Please correct the highlighted performance fields.</strong>
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </section>
    @endif

    <nav class="people-ops-tabs" aria-label="Performance Management sections">
        <a href="{{ route('hr.performance-dashboard.index') }}" @class(['is-active' => $activeRegister === 'dashboard']) @if($activeRegister === 'dashboard') aria-current="page" @endif><i class="fa-solid fa-chart-column" aria-hidden="true"></i> Department dashboard</a>
        <a href="{{ route('hr.performance-reviews.index') }}" @class(['is-active' => $activeRegister === 'reviews']) @if($activeRegister === 'reviews') aria-current="page" @endif><i class="fa-regular fa-star" aria-hidden="true"></i> Reviews</a>
        <a href="{{ route('hr.performance-cycles.index') }}" @class(['is-active' => $activeRegister === 'cycles']) @if($activeRegister === 'cycles') aria-current="page" @endif><i class="fa-regular fa-calendar" aria-hidden="true"></i> Cycles</a>
    </nav>

    <section class="people-ops-kpis" aria-label="Performance summary">
        <article class="people-ops-kpi is-info"><span class="people-ops-kpi-icon"><i class="fa-regular fa-calendar" aria-hidden="true"></i></span><span>Cycles</span><strong>{{ $summary->cycles }}</strong><small>{{ $summary->activeCycles }} active</small></article>
        <article class="people-ops-kpi is-purple"><span class="people-ops-kpi-icon"><i class="fa-solid fa-list-check" aria-hidden="true"></i></span><span>Reviews</span><strong>{{ $summary->reviews }}</strong><small>{{ $summary->openReviews }} open</small></article>
        <article class="people-ops-kpi is-success"><span class="people-ops-kpi-icon"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></span><span>Closed</span><strong>{{ $summary->closedReviews }}</strong><small>Persisted review closures</small></article>
        <article class="people-ops-kpi is-warning"><span class="people-ops-kpi-icon"><i class="fa-solid fa-arrow-trend-up" aria-hidden="true"></i></span><span>Average final score</span><strong>{{ $summary->averageFinalScore ?? '—' }}</strong><small>Closed reviews with a final score</small></article>
        <article class="people-ops-kpi is-danger"><span class="people-ops-kpi-icon"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i></span><span>PIP required</span><strong>{{ $summary->pipRequired }}</strong><small>Persisted review flag</small></article>
    </section>

    @include('hr.performance.partials.'.$activeRegister)
</x-hr.people-workspace>
@endsection
