@extends('layouts.builder360-classic')

@section('title', 'Separation and Final Settlement - Builder360 ERP-CRM')

@section('content')
<x-hr.people-workspace
    title="Separation & Final Settlement"
    description="Manage separation dates, calculated dues and recoveries, independent approvals, and settlement completion."
    active="lifecycle"
>
    <x-slot:actions>@if($abilities['canCreate'])<a class="people-button is-primary" href="#initiate-settlement"><i class="fa-solid fa-plus" aria-hidden="true"></i> New settlement</a>@endif</x-slot:actions>
    @include('hr.lifecycle.partials.navigation', ['activeLifecycleSection' => 'separation'])
    @if(session('status'))<section class="people-alert is-success" role="status">{{ session('status') }}</section>@endif
    @if($errors->any())<section class="people-alert is-danger" role="alert" tabindex="-1"><strong>Please correct the highlighted settlement fields.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></section>@endif

    @if($abilities['canCreate'])
        <details class="people-ops-panel" id="initiate-settlement" @if($errors->any()) open @endif>
            <summary class="people-ops-panel-head"><div><h2>Initiate final settlement</h2><p>Start the existing governed HR and Finance approval workflow.</p></div></summary>
            <div class="people-ops-panel-body"><form method="POST" action="{{ route('hr.separation-settlements.store') }}" class="people-form-grid">@csrf
                <label class="people-field"><span>Employee</span><select class="people-control" name="employee_id" required><option value="">Select employee</option>@foreach($employees as $employee)<option value="{{ $employee->id }}" @selected((string)old('employee_id')===(string)$employee->id)>{{ $employee->employee_code }} · {{ $employee->name }}{{ $employee->department ? ' · '.$employee->department : '' }}</option>@endforeach</select>@error('employee_id')<span class="people-field-error">{{ $message }}</span>@enderror</label>
                <label class="people-field"><span>Separation type</span><select class="people-control" name="separation_type" required>@foreach(['resignation'=>'Resignation','termination'=>'Termination','retirement'=>'Retirement','contract_end'=>'Contract end'] as $value=>$label)<option value="{{ $value }}" @selected(old('separation_type')===$value)>{{ $label }}</option>@endforeach</select>@error('separation_type')<span class="people-field-error">{{ $message }}</span>@enderror</label>
                <label class="people-field"><span>Resignation date</span><input class="people-control" type="date" name="resignation_date" value="{{ old('resignation_date') }}">@error('resignation_date')<span class="people-field-error">{{ $message }}</span>@enderror</label>
                <label class="people-field"><span>Last working date</span><input class="people-control" type="date" name="last_working_date" value="{{ old('last_working_date') }}" required>@error('last_working_date')<span class="people-field-error">{{ $message }}</span>@enderror</label>
                <label class="people-field"><span>Settlement date</span><input class="people-control" type="date" name="settlement_date" value="{{ old('settlement_date') }}">@error('settlement_date')<span class="people-field-error">{{ $message }}</span>@enderror</label>
                @foreach(['bonus_amount'=>'Bonus amount','gratuity_amount'=>'Gratuity amount','notice_recovery_amount'=>'Notice recovery','tax_recovery_amount'=>'Tax recovery'] as $name=>$label)<label class="people-field"><span>{{ $label }}</span><input class="people-control" type="number" min="0" step="0.01" name="{{ $name }}" value="{{ old($name,0) }}">@error($name)<span class="people-field-error">{{ $message }}</span>@enderror</label>@endforeach
                <label class="people-field is-wide"><span>Reason</span><textarea class="people-control" name="reason" maxlength="2000">{{ old('reason') }}</textarea></label>
                <button class="people-button is-primary" type="submit">Initiate settlement</button>
            </form></div>
        </details>
    @endif

    <section class="people-ops-panel has-mobile-cards" aria-labelledby="settlement-register-title">
        <header class="people-ops-panel-head"><div><h2 id="settlement-register-title">Final settlement register</h2><p>{{ $settlements->total() }} authorized settlement{{ $settlements->total() === 1 ? '' : 's' }}.</p></div></header>
        <div class="people-ops-panel-body"><form method="GET" action="{{ route('hr.separation-settlements.index') }}" class="people-ops-filterbar" aria-label="Filter separation settlements">
            <label class="people-field"><span>Employee</span><select class="people-control" name="employee_id"><option value="">All visible employees</option>@foreach($employees as $employee)<option value="{{ $employee->id }}" @selected((string)request('employee_id')===(string)$employee->id)>{{ $employee->employee_code }} · {{ $employee->name }}</option>@endforeach</select></label>
            <label class="people-field"><span>Status</span><select class="people-control" name="status"><option value="">All statuses</option>@foreach(['initiated'=>'Initiated','hr_approved'=>'HR approved','finance_approved'=>'Finance approved','completed'=>'Completed'] as $value=>$label)<option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>@endforeach</select></label>
            <label class="people-field"><span>Type</span><select class="people-control" name="separation_type"><option value="">All types</option>@foreach(['resignation'=>'Resignation','termination'=>'Termination','retirement'=>'Retirement','contract_end'=>'Contract end'] as $value=>$label)<option value="{{ $value }}" @selected(request('separation_type')===$value)>{{ $label }}</option>@endforeach</select></label>
            <label class="people-field"><span>From</span><input class="people-control" type="date" name="from" value="{{ request('from') }}"></label><label class="people-field"><span>To</span><input class="people-control" type="date" name="to" value="{{ request('to') }}"></label>
            <button class="people-button is-primary">Apply</button><a class="people-button" href="{{ route('hr.separation-settlements.index') }}">Clear</a>
        </form></div>

        <div class="people-ops-table-wrap"><table class="people-ops-table"><caption>Employee separation and final settlements</caption><thead><tr><th scope="col">Settlement</th><th scope="col">Employee</th><th scope="col">Schedule</th><th scope="col">Amounts</th><th scope="col">Clearance</th><th scope="col">Status</th><th scope="col">Action</th></tr></thead><tbody>
        @forelse($settlements as $settlement) @php($actions=$settlementActions[$settlement->id]??[]) @php($canViewCompensation=$settlementCompensationVisibility[$settlement->id]??false)
            <tr>
                <td><strong>{{ $settlement->settlement_number }}</strong><small>{{ str_replace('_',' ',ucfirst($settlement->separation_type)) }}</small></td>
                <td><span class="people-ops-identity"><strong>{{ $settlement->employee?->name }}</strong><small>{{ $settlement->employee?->employee_code }} · {{ $settlement->employee?->department ?: 'No department' }}</small></span></td>
                <td>Last day {{ $settlement->last_working_date?->format('d M Y') ?? 'Not set' }}<small>Settlement {{ $settlement->settlement_date?->format('d M Y') ?? 'not set' }}</small></td>
                <td>@if($canViewCompensation)Gross INR {{ number_format((float)$settlement->gross_payable,2) }}<small>Recovery INR {{ number_format((float)$settlement->total_recoveries,2) }} · Net INR {{ number_format((float)$settlement->net_payable,2) }}</small>@else<span class="people-status is-muted">Restricted</span><small>Compensation details restricted</small>@endif</td>
                <td>{{ count($settlement->clearance_blockers??[]) }} blocker(s)</td>
                <td><span class="people-status is-{{ $settlement->status === 'completed' ? 'success' : 'warning' }}">{{ str_replace('_',' ',ucfirst($settlement->status)) }}</span></td>
                <td>@include('hr.separation.partials.settlement-actions', ['settlement' => $settlement, 'actions' => $actions, 'mobile' => false])</td>
            </tr>
        @empty<tr><td colspan="7"><div class="people-ops-empty"><strong>No settlements found</strong><span>Clear the filters or initiate an authorized settlement.</span></div></td></tr>@endforelse
        </tbody></table></div>

        <div class="people-ops-mobile-list">@forelse($settlements as $settlement) @php($actions=$settlementActions[$settlement->id]??[]) @php($canViewCompensation=$settlementCompensationVisibility[$settlement->id]??false)<article class="people-ops-mobile-card"><header class="people-ops-mobile-card-head"><span class="people-ops-identity"><strong>{{ $settlement->employee?->name }}</strong><small>{{ $settlement->settlement_number }}</small></span><span class="people-status is-info">{{ str_replace('_',' ',ucfirst($settlement->status)) }}</span></header><dl class="people-ops-mobile-facts"><div><dt>Last day</dt><dd>{{ $settlement->last_working_date?->format('d M Y') ?? 'Not set' }}</dd></div><div><dt>Settlement amounts</dt><dd>{{ $canViewCompensation ? 'INR '.number_format((float)$settlement->net_payable,2) : 'Restricted' }}</dd></div><div><dt>Clearance</dt><dd>{{ count($settlement->clearance_blockers??[]) }} blocker(s)</dd></div></dl>@include('hr.separation.partials.settlement-actions', ['settlement' => $settlement, 'actions' => $actions, 'mobile' => true])</article>@empty<div class="people-ops-empty"><strong>No settlements found</strong></div>@endforelse</div>
        {{ $settlements->withQueryString()->links() }}
    </section>
</x-hr.people-workspace>
@endsection
