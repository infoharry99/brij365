@extends('layouts.builder360-classic')

@section('title', 'Payroll - Builder360 ERP-CRM')

@section('content')
<x-hr.people-workspace
    title="Payroll Workspace"
    description="Run payroll, control approved bank batches, and review the active salary masters available to your role."
    active="payroll"
>
    <x-slot:actions>
        @if ($activeRegister !== 'runs' && $abilities['canGenerateRun'])
            <a class="people-button is-primary" href="{{ route('payroll.runs.index') }}#generate-payroll-run">
                <i class="fa-solid fa-play" aria-hidden="true"></i>
                <span>Generate payroll</span>
            </a>
        @endif
    </x-slot:actions>

    @if (session('status'))
        <div class="people-alert is-success" role="status">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <section class="people-alert is-danger" role="alert" aria-labelledby="payroll-errors-title" tabindex="-1">
            <strong id="payroll-errors-title">Please correct the highlighted payroll fields.</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    <nav class="people-ops-tabs" aria-label="Payroll registers">
        <a href="{{ route('payroll.runs.index') }}" @class(['is-active' => $activeRegister === 'runs']) @if($activeRegister === 'runs') aria-current="page" @endif>
            <i class="fa-solid fa-calculator" aria-hidden="true"></i><span>Payroll runs</span>
        </a>
        <a href="{{ route('payroll.bank-transfer-batches.index') }}" @class(['is-active' => $activeRegister === 'bank_batches']) @if($activeRegister === 'bank_batches') aria-current="page" @endif>
            <i class="fa-solid fa-building-columns" aria-hidden="true"></i><span>Bank transfers</span>
        </a>
        <a href="{{ route('payroll.salary-structures.index') }}" @class(['is-active' => $activeRegister === 'structures']) @if($activeRegister === 'structures') aria-current="page" @endif>
            <i class="fa-solid fa-layer-group" aria-hidden="true"></i><span>Salary structures</span>
        </a>
        <a href="{{ route('payroll.components.index') }}" @class(['is-active' => $activeRegister === 'components']) @if($activeRegister === 'components') aria-current="page" @endif>
            <i class="fa-solid fa-list-check" aria-hidden="true"></i><span>Payroll components</span>
        </a>
        <a href="{{ route('payroll.commission-rules.index') }}" @class(['is-active' => $activeRegister === 'commission_rules']) @if($activeRegister === 'commission_rules') aria-current="page" @endif>
            <i class="fa-solid fa-percent" aria-hidden="true"></i><span>Commission rules</span>
        </a>
        <a href="{{ route('payroll.commission-runs.index') }}" @class(['is-active' => $activeRegister === 'commission_runs']) @if($activeRegister === 'commission_runs') aria-current="page" @endif>
            <i class="fa-solid fa-coins" aria-hidden="true"></i><span>Commission runs</span>
        </a>
        @can('viewAny', \App\Models\EmployeeTaxProfile::class)
            <a href="{{ route('payroll.employee-tax-profiles.index') }}">
                <i class="fa-solid fa-file-shield" aria-hidden="true"></i><span>Employee tax inputs</span>
            </a>
        @endcan
    </nav>

    <section class="people-ops-kpis is-four" aria-label="Payroll summary">
        <article class="people-ops-kpi is-info">
            <span class="people-ops-kpi-icon"><i class="fa-solid fa-receipt" aria-hidden="true"></i></span>
            <span>Total payroll runs</span>
            <strong>{{ number_format($summary->totalRuns) }}</strong>
            <small>Across the complete authorized company register.</small>
        </article>
        <article class="people-ops-kpi is-warning">
            <span class="people-ops-kpi-icon"><i class="fa-solid fa-hourglass-half" aria-hidden="true"></i></span>
            <span>Awaiting approval</span>
            <strong>{{ number_format($summary->generatedRuns) }}</strong>
            <small>Generated payroll runs that have not been approved.</small>
        </article>
        <article class="people-ops-kpi is-success">
            <span class="people-ops-kpi-icon"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></span>
            <span>Approved payroll runs</span>
            <strong>{{ number_format($summary->approvedRuns) }}</strong>
            <small>Approved net payable: {{ $summary->approvedNetPayable }}</small>
        </article>
        <article class="people-ops-kpi is-purple">
            <span class="people-ops-kpi-icon"><i class="fa-solid fa-money-check-dollar" aria-hidden="true"></i></span>
            <span>Bank batches</span>
            <strong>{{ number_format($summary->preparedBatches + $summary->releasedBatches) }}</strong>
            <small>{{ $summary->preparedBatches }} prepared and {{ $summary->releasedBatches }} released.</small>
        </article>
    </section>

    @include('payroll.workspace.partials.'.$activeRegister)
</x-hr.people-workspace>
@endsection
