@extends('layouts.builder360-classic')

@section('title', 'Recruitment - Builder360 ERP-CRM')

@section('content')
<x-hr.people-workspace
    title="Recruitment"
    description="Manage authorized job openings, candidate pipelines, interviews, and offers from one company-scoped workspace."
    active="recruitment"
>
    <x-slot:actions>
        @php
            $createLabels = [
                'openings' => ['ability' => 'canCreateOpening', 'label' => 'New job opening', 'href' => '#recruitment-create'],
                'pipeline' => ['ability' => 'canCreateCandidate', 'label' => 'New candidate', 'href' => route('recruitment.candidates.index').'#recruitment-create'],
                'candidates' => ['ability' => 'canCreateCandidate', 'label' => 'New candidate', 'href' => '#recruitment-create'],
                'interviews' => ['ability' => 'canScheduleInterview', 'label' => 'Schedule interview', 'href' => '#recruitment-create'],
                'offers' => ['ability' => 'canCreateOffer', 'label' => 'New offer draft', 'href' => '#recruitment-create'],
            ];
            $createAction = $createLabels[$activeRegister] ?? null;
        @endphp
        @if ($createAction && ($abilities[$createAction['ability']] ?? false))
            <a class="people-button is-primary" href="{{ $createAction['href'] }}">
                <i class="fa-solid fa-plus" aria-hidden="true"></i> {{ $createAction['label'] }}
            </a>
        @endif
    </x-slot:actions>

    @if (session('status'))
        <section class="people-alert is-success" role="status">{{ session('status') }}</section>
    @endif

    @if ($errors->any())
        <section class="people-alert is-danger" role="alert" tabindex="-1">
            <strong>The recruitment action was not completed.</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    <nav class="people-ops-tabs" aria-label="Recruitment sections">
        <a href="{{ route('recruitment.pipeline.index') }}" @class(['is-active' => $activeRegister === 'pipeline']) @if($activeRegister === 'pipeline') aria-current="page" @endif>
            <i class="fa-solid fa-table-columns" aria-hidden="true"></i> Pipeline
        </a>
        <a href="{{ route('recruitment.job-openings.index') }}" @class(['is-active' => $activeRegister === 'openings']) @if($activeRegister === 'openings') aria-current="page" @endif>
            <i class="fa-solid fa-briefcase" aria-hidden="true"></i> Job openings
        </a>
        <a href="{{ route('recruitment.candidates.index') }}" @class(['is-active' => $activeRegister === 'candidates']) @if($activeRegister === 'candidates') aria-current="page" @endif>
            <i class="fa-solid fa-user-group" aria-hidden="true"></i> Candidates
        </a>
        <a href="{{ route('recruitment.interviews.index') }}" @class(['is-active' => $activeRegister === 'interviews']) @if($activeRegister === 'interviews') aria-current="page" @endif>
            <i class="fa-regular fa-calendar-check" aria-hidden="true"></i> Interviews
        </a>
        <a href="{{ route('recruitment.offers.index') }}" @class(['is-active' => $activeRegister === 'offers']) @if($activeRegister === 'offers') aria-current="page" @endif>
            <i class="fa-solid fa-file-signature" aria-hidden="true"></i> Offers
        </a>
    </nav>

    <section class="people-ops-kpis" aria-label="Recruitment summary">
        <article class="people-ops-kpi is-info"><span class="people-ops-kpi-icon"><i class="fa-solid fa-briefcase" aria-hidden="true"></i></span><span>Open requisitions</span><strong>{{ $summary->openRequisitions }}</strong><small>{{ $summary->openPositions }} approved positions</small></article>
        <article class="people-ops-kpi is-purple"><span class="people-ops-kpi-icon"><i class="fa-solid fa-user-group" aria-hidden="true"></i></span><span>Active candidates</span><strong>{{ $summary->activeCandidates }}</strong><small>Across your authorized company scope</small></article>
        <article class="people-ops-kpi is-warning"><span class="people-ops-kpi-icon"><i class="fa-regular fa-calendar-check" aria-hidden="true"></i></span><span>Scheduled interviews</span><strong>{{ $summary->scheduledInterviews }}</strong><small>Interview feedback remains workflow controlled</small></article>
        <article class="people-ops-kpi"><span class="people-ops-kpi-icon"><i class="fa-solid fa-file-signature" aria-hidden="true"></i></span><span>Draft offers</span><strong>{{ $summary->draftOffers }}</strong><small>Awaiting an authorized release</small></article>
        <article class="people-ops-kpi is-success"><span class="people-ops-kpi-icon"><i class="fa-solid fa-user-check" aria-hidden="true"></i></span><span>Converted</span><strong>{{ $summary->convertedCandidates }}</strong><small>Candidates converted to employees</small></article>
    </section>

    @include('recruitment.workspace.partials.'.$activeRegister)
</x-hr.people-workspace>
@endsection
