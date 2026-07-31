@extends('layouts.builder360-classic')
@section('title', 'Policy Acknowledgements - Builder360 ERP-CRM')
@section('content')
<x-hr.people-workspace
    title="Policy Acknowledgements"
    description="Review active employee policies and retain traceable acknowledgement records by version."
    eyebrow="People / Policies"
    active="compliance"
    :self-service="$selfService"
>
    <x-slot:actions>
        @unless($selfService)<a class="people-button" href="{{ route('hr.compliance-rule-settings.index') }}"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Compliance rules</a>@endunless
        @if($currentEmployee)<a class="people-button" href="{{ route('hr.employees.me') }}"><i class="fa-solid fa-id-card" aria-hidden="true"></i> Self Service</a>@endif
    </x-slot:actions>
    @if(session('status'))<section class="blade-alert blade-alert-success" role="status">{{ session('status') }}</section>@endif
    @if($errors->any())<section class="blade-alert blade-alert-danger" role="alert" tabindex="-1"><strong>Please correct the highlighted acknowledgement fields.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></section>@endif

    @if($policies!==[])
    <section class="blade-workspace-grid" aria-label="Policies available to acknowledge">
        @foreach($policies as $policy)
        <article class="blade-card"><div class="blade-card-header"><div><p class="blade-eyebrow">Version {{ $policy['policy_version'] }}</p><h2>{{ $policy['policy_title'] }}</h2></div><span class="blade-status-pill">{{ ucfirst($policy['status']) }}</span></div><p>{{ $policy['summary'] }}</p><dl class="blade-profile-list"><div><dt>Effective from</dt><dd>{{ $policy['effective_from'] }}</dd></div><div><dt>Status</dt><dd>{{ ucfirst($policy['status']) }}</dd></div></dl>
            @if($abilities['canAcknowledge']&&$policy['status']!=='acknowledged')<form method="POST" action="{{ route('hr.policy-acknowledgements.store') }}" class="blade-inline-form">@csrf<input type="hidden" name="employee_id" value="{{ $currentEmployee->id }}"><input type="hidden" name="policy_key" value="{{ $policy['policy_key'] }}"><input type="hidden" name="policy_version" value="{{ $policy['policy_version'] }}"><textarea name="acknowledgement_note" maxlength="1000" placeholder="Optional acknowledgement note"></textarea><button class="blade-primary-action">Acknowledge policy</button></form>@endif
        </article>
        @endforeach
    </section>
    @endif

    <section class="blade-card"><div class="blade-card-header"><div><p class="blade-eyebrow">Acknowledgement register</p><h2>Policy history</h2></div></div>
        <form method="GET" action="{{ route('hr.policy-acknowledgements.index') }}" class="blade-filter-grid">@if($employees->count()>1)<label>Employee<select name="employee_id"><option value="">All visible employees</option>@foreach($employees as $employee)<option value="{{ $employee->id }}" @selected((string)request('employee_id')===(string)$employee->id)>{{ $employee->employee_code }} &middot; {{ $employee->name }}</option>@endforeach</select></label>@endif<label>Policy key<input name="policy_key" value="{{ request('policy_key') }}" placeholder="Policy identifier"></label><label>Status<select name="status"><option value="">All statuses</option><option value="pending" @selected(request('status')==='pending')>Pending</option><option value="acknowledged" @selected(request('status')==='acknowledged')>Acknowledged</option></select></label><button class="blade-secondary-action">Apply filters</button></form>
        <div class="blade-table-wrap"><table class="blade-table"><caption class="sr-only">Policy acknowledgement history for employees visible to your role</caption><thead><tr><th scope="col">Policy</th><th scope="col">Employee</th><th scope="col">Version</th><th scope="col">Status</th><th scope="col">Acknowledged by</th><th scope="col">Date</th><th scope="col">Note</th></tr></thead><tbody>@forelse($acknowledgements as $acknowledgement)<tr><td><strong>{{ $acknowledgement->policy_title }}</strong><br><span>{{ $acknowledgement->policy_key }}</span></td><td>{{ $acknowledgement->employee?->employee_code }}<br><span>{{ $acknowledgement->employee?->name }}</span></td><td>v{{ $acknowledgement->policy_version }}</td><td><span class="blade-status-pill">{{ ucfirst($acknowledgement->status) }}</span></td><td>{{ $acknowledgement->acknowledgedBy?->name??'Pending' }}</td><td>{{ $acknowledgement->acknowledged_at?->format('d M Y H:i')??'—' }}</td><td>{{ $acknowledgement->acknowledgement_note??'—' }}</td></tr>@empty<tr><td colspan="7">No policy acknowledgement records are available for the selected filters.</td></tr>@endforelse</tbody></table></div>{{ $acknowledgements->withQueryString()->links() }}
    </section>
</x-hr.people-workspace>
@endsection
