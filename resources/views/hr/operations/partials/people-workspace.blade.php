<x-hr.people-workspace
    :title="$workspaceTitle"
    :description="$workspaceDescription"
    eyebrow="Employee Operations Workspace"
    :active="$activeRegister"
>
    <x-slot:actions>
        @if ($activeRegister === 'assets' && $abilities['canCreateAsset'])
            <a class="people-button is-primary" href="#asset-form"><i class="fa-solid fa-plus" aria-hidden="true"></i> Register asset</a>
        @elseif ($activeRegister === 'claims' && $abilities['canCreateClaim'])
            <a class="people-button is-primary" href="#claim-form"><i class="fa-solid fa-plus" aria-hidden="true"></i> Submit expense claim</a>
        @elseif ($activeRegister === 'loans' && $abilities['canCreateLoan'])
            <a class="people-button is-primary" href="#loan-form"><i class="fa-solid fa-plus" aria-hidden="true"></i> Request employee loan</a>
        @elseif ($activeRegister === 'helpdesk' && $abilities['canCreateTicket'])
            <a class="people-button is-primary" href="#helpdesk-form"><i class="fa-solid fa-plus" aria-hidden="true"></i> Raise HR ticket</a>
        @endif
    </x-slot:actions>

    @if (session('status'))
        <section class="people-alert is-success" role="status">{{ session('status') }}</section>
    @endif

    @if ($errors->any())
        <section class="people-alert is-danger" role="alert" tabindex="-1">
            <strong>Please correct the highlighted employee operations fields.</strong>
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </section>
    @endif

    <nav class="people-ops-tabs" aria-label="Employee Operations sections">
        @can('viewAny', \App\Models\EmployeeAsset::class)
            <a href="{{ route('hr.assets.index') }}" @class(['is-active' => $activeRegister === 'assets']) @if($activeRegister === 'assets') aria-current="page" @endif><i class="fa-solid fa-laptop" aria-hidden="true"></i> Employee assets</a>
        @endcan
        @can('viewAny', \App\Models\ExpenseClaim::class)
            <a href="{{ route('hr.expense-claims.index') }}" @class(['is-active' => $activeRegister === 'claims']) @if($activeRegister === 'claims') aria-current="page" @endif><i class="fa-solid fa-receipt" aria-hidden="true"></i> Expense claims</a>
        @endcan
        @can('viewAny', \App\Models\EmployeeLoan::class)
            <a href="{{ route('hr.loans.index') }}" @class(['is-active' => $activeRegister === 'loans']) @if($activeRegister === 'loans') aria-current="page" @endif><i class="fa-solid fa-hand-holding-dollar" aria-hidden="true"></i> Employee loans</a>
        @endcan
        @can('viewAny', \App\Models\HrHelpdeskTicket::class)
            <a href="{{ route('hr.helpdesk-tickets.index') }}" @class(['is-active' => $activeRegister === 'helpdesk']) @if($activeRegister === 'helpdesk') aria-current="page" @endif><i class="fa-solid fa-headset" aria-hidden="true"></i> HR helpdesk tickets</a>
        @endcan
    </nav>

    @if ($activeRegister === 'assets' && $assetSummary)
        <section class="people-ops-kpis" aria-label="Employee asset summary">
            @foreach ([
                ['Total assets', $assetSummary->total, 'fa-laptop', ''],
                ['Available', $assetSummary->available, 'fa-box-open', 'is-success'],
                ['Assigned', $assetSummary->assigned, 'fa-user-check', 'is-info'],
                ['Recovered', $assetSummary->recovered, 'fa-rotate-left', 'is-success'],
                ['Lost', $assetSummary->lost, 'fa-triangle-exclamation', 'is-danger'],
            ] as [$label, $value, $icon, $tone])
                <article class="people-ops-kpi {{ $tone }}"><span class="people-ops-kpi-icon"><i class="fa-solid {{ $icon }}" aria-hidden="true"></i></span><span>{{ $label }}</span><strong>{{ $value }}</strong><small>Complete authorized register</small></article>
            @endforeach
        </section>
    @elseif ($activeRegister === 'claims' && $claimSummary)
        <section class="people-ops-kpis" aria-label="Expense claim summary">
            @foreach ([
                ['Total claims', $claimSummary->total, 'fa-receipt', ''],
                ['Submitted', $claimSummary->submitted, 'fa-hourglass-half', 'is-warning'],
                ['Approved', $claimSummary->approved, 'fa-circle-check', 'is-info'],
                ['Paid', $claimSummary->paid, 'fa-indian-rupee-sign', 'is-success'],
                ['Rejected', $claimSummary->rejected, 'fa-circle-xmark', 'is-danger'],
            ] as [$label, $value, $icon, $tone])
                <article class="people-ops-kpi {{ $tone }}"><span class="people-ops-kpi-icon"><i class="fa-solid {{ $icon }}" aria-hidden="true"></i></span><span>{{ $label }}</span><strong>{{ $value }}</strong><small>Complete authorized register</small></article>
            @endforeach
        </section>
    @elseif ($activeRegister === 'loans' && $loanSummary)
        <section class="people-ops-kpis" aria-label="Employee loan summary">
            @foreach ([
                ['Total loans', $loanSummary->total, 'fa-hand-holding-dollar', ''],
                ['Submitted', $loanSummary->submitted, 'fa-hourglass-half', 'is-warning'],
                ['Approved', $loanSummary->approved, 'fa-circle-check', 'is-info'],
                ['Disbursed', $loanSummary->disbursed, 'fa-money-bill-transfer', 'is-success'],
                ['Closed', $loanSummary->closed, 'fa-lock', ''],
            ] as [$label, $value, $icon, $tone])
                <article class="people-ops-kpi {{ $tone }}"><span class="people-ops-kpi-icon"><i class="fa-solid {{ $icon }}" aria-hidden="true"></i></span><span>{{ $label }}</span><strong>{{ $value }}</strong><small>Complete authorized register</small></article>
            @endforeach
        </section>
    @elseif ($activeRegister === 'helpdesk' && $helpdeskSummary)
        <section class="people-ops-kpis" aria-label="HR helpdesk summary">
            @foreach ([
                ['Total tickets', $helpdeskSummary->total, 'fa-headset', ''],
                ['Open', $helpdeskSummary->open, 'fa-inbox', ''],
                ['Assigned', $helpdeskSummary->assigned, 'fa-user-check', 'is-warning'],
                ['Resolved', $helpdeskSummary->resolved, 'fa-circle-check', 'is-info'],
                ['Critical', $helpdeskSummary->critical, 'fa-triangle-exclamation', 'is-danger'],
            ] as [$label, $value, $icon, $tone])
                <article class="people-ops-kpi {{ $tone }}"><span class="people-ops-kpi-icon"><i class="fa-solid {{ $icon }}" aria-hidden="true"></i></span><span>{{ $label }}</span><strong>{{ $value }}</strong><small>Complete authorized register</small></article>
            @endforeach
        </section>
    @endif

    @include('hr.operations.partials.'.$activeRegister)
</x-hr.people-workspace>
