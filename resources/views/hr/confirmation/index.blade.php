@extends('layouts.builder360-classic')

@section('title', 'Employee Confirmation - Builder360 ERP-CRM')

@section('content')
<x-hr.people-workspace
    title="Employee Confirmation"
    description="Track probation reviews, manager recommendations, and authorized HR confirmation decisions."
    active="lifecycle"
>
    <x-slot:actions>
        @if($abilities['canCreate'])<a class="people-button is-primary" href="#create-confirmation-case"><i class="fa-solid fa-plus" aria-hidden="true"></i> New case</a>@endif
    </x-slot:actions>

    @include('hr.lifecycle.partials.navigation', ['activeLifecycleSection' => 'confirmation'])

    @if(session('status'))<section class="people-alert is-success" role="status">{{ session('status') }}</section>@endif
    @if($errors->any())<section class="people-alert is-danger" role="alert" tabindex="-1"><strong>Please correct the highlighted confirmation fields.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></section>@endif

    @if($abilities['canCreate'])
        <details class="people-ops-panel" id="create-confirmation-case" @if($errors->any()) open @endif>
            <summary class="people-ops-panel-head"><div><h2>Create confirmation case</h2><p>Start a governed probation review for an authorized employee.</p></div></summary>
            <div class="people-ops-panel-body">
                <form method="POST" action="{{ route('hr.confirmation-cases.store') }}" class="people-form-grid">@csrf
                    <label class="people-field"><span>Employee</span><select class="people-control" name="employee_id" required><option value="">Select employee</option>@foreach($employees as $employee)<option value="{{ $employee->id }}" @selected((string)old('employee_id')===(string)$employee->id)>{{ $employee->employee_code }} · {{ $employee->name }}{{ $employee->department ? ' · '.$employee->department : '' }}</option>@endforeach</select>@error('employee_id')<span class="people-field-error">{{ $message }}</span>@enderror</label>
                    <label class="people-field"><span>Confirmation manager</span><select class="people-control" name="manager_employee_id"><option value="">Use reporting manager</option>@foreach($employees as $manager)<option value="{{ $manager->id }}" @selected((string)old('manager_employee_id')===(string)$manager->id)>{{ $manager->employee_code }} · {{ $manager->name }}</option>@endforeach</select>@error('manager_employee_id')<span class="people-field-error">{{ $message }}</span>@enderror</label>
                    <label class="people-field"><span>Probation starts on</span><input class="people-control" type="date" name="probation_starts_on" value="{{ old('probation_starts_on') }}">@error('probation_starts_on')<span class="people-field-error">{{ $message }}</span>@enderror</label>
                    <label class="people-field"><span>Probation ends on</span><input class="people-control" type="date" name="probation_ends_on" value="{{ old('probation_ends_on') }}" required>@error('probation_ends_on')<span class="people-field-error">{{ $message }}</span>@enderror</label>
                    <label class="people-field"><span>Review due on</span><input class="people-control" type="date" name="review_due_on" value="{{ old('review_due_on') }}">@error('review_due_on')<span class="people-field-error">{{ $message }}</span>@enderror</label>
                    <button class="people-button is-primary" type="submit">Create case</button>
                </form>
            </div>
        </details>
    @endif

    <section class="people-ops-panel has-mobile-cards" aria-labelledby="confirmation-register-title">
        <header class="people-ops-panel-head"><div><h2 id="confirmation-register-title">Probation and confirmation cases</h2><p>{{ $cases->total() }} authorized case{{ $cases->total() === 1 ? '' : 's' }}.</p></div></header>
        <div class="people-ops-panel-body">
            <form method="GET" action="{{ route('hr.confirmation-cases.index') }}" class="people-ops-filterbar" aria-label="Filter confirmation cases">
                <label class="people-field"><span>Employee</span><select class="people-control" name="employee_id"><option value="">All visible employees</option>@foreach($employees as $employee)<option value="{{ $employee->id }}" @selected((string)request('employee_id')===(string)$employee->id)>{{ $employee->employee_code }} · {{ $employee->name }}</option>@endforeach</select></label>
                <label class="people-field"><span>Department</span><select class="people-control" name="department"><option value="">All departments</option>@foreach($departments as $department)<option value="{{ $department }}" @selected(request('department')===$department)>{{ $department }}</option>@endforeach</select></label>
                <label class="people-field"><span>Status</span><select class="people-control" name="status"><option value="">All statuses</option>@foreach(['due'=>'Due','manager_recommended'=>'Manager recommended','confirmed'=>'Confirmed','extended'=>'Extended','rejected'=>'Rejected'] as $value=>$label)<option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>@endforeach</select></label>
                <label class="people-field"><span>Due from</span><input class="people-control" type="date" name="due_from" value="{{ request('due_from') }}"></label>
                <label class="people-field"><span>Due to</span><input class="people-control" type="date" name="due_to" value="{{ request('due_to') }}"></label>
                <button class="people-button is-primary">Apply</button><a class="people-button" href="{{ route('hr.confirmation-cases.index') }}">Clear</a>
            </form>
        </div>

        <div class="people-ops-table-wrap"><table class="people-ops-table"><caption>Employee confirmation cases</caption><thead><tr><th scope="col">Case</th><th scope="col">Employee</th><th scope="col">Probation</th><th scope="col">Manager</th><th scope="col">Decision</th><th scope="col">Status</th><th scope="col">Action</th></tr></thead><tbody>
        @forelse($cases as $case) @php($actions=$caseActions[$case->id]??[])
            <tr>
                <td><strong>{{ $case->case_number }}</strong><small>Review due {{ $case->review_due_on?->format('d M Y') ?? 'not set' }}</small></td>
                <td><span class="people-ops-identity"><strong>{{ $case->employee?->name }}</strong><small>{{ $case->employee?->employee_code }} · {{ $case->employee?->department ?: 'No department' }}</small></span></td>
                <td>{{ $case->probation_starts_on?->format('d M Y') ?? 'Not set' }}<small>to {{ $case->probation_ends_on?->format('d M Y') ?? 'Not set' }}</small></td>
                <td>{{ $case->managerEmployee?->name ?? 'Not assigned' }}</td>
                <td>{{ $case->manager_recommendation ? ucfirst($case->manager_recommendation) : 'Awaiting manager' }}<small>{{ $case->hr_decision ? 'HR: '.ucfirst($case->hr_decision) : 'HR decision pending' }}</small></td>
                <td><span class="people-status is-{{ in_array($case->status,['confirmed'],true) ? 'success' : (in_array($case->status,['rejected'],true) ? 'danger' : 'warning') }}">{{ str_replace('_',' ',ucfirst($case->status)) }}</span></td>
                <td>@include('hr.confirmation.partials.case-actions', ['case' => $case, 'actions' => $actions, 'mobile' => false])</td>
            </tr>
        @empty<tr><td colspan="7"><div class="people-ops-empty"><strong>No confirmation cases found</strong><span>Clear the filters or create an authorized case.</span></div></td></tr>@endforelse
        </tbody></table></div>

        <div class="people-ops-mobile-list">@forelse($cases as $case) @php($actions=$caseActions[$case->id]??[])<article class="people-ops-mobile-card"><header class="people-ops-mobile-card-head"><span class="people-ops-identity"><strong>{{ $case->employee?->name }}</strong><small>{{ $case->case_number }} · {{ $case->employee?->employee_code }}</small></span><span class="people-status is-info">{{ str_replace('_',' ',ucfirst($case->status)) }}</span></header><dl class="people-ops-mobile-facts"><div><dt>Review due</dt><dd>{{ $case->review_due_on?->format('d M Y') ?? 'Not set' }}</dd></div><div><dt>Manager</dt><dd>{{ $case->managerEmployee?->name ?? 'Not assigned' }}</dd></div><div><dt>Recommendation</dt><dd>{{ $case->manager_recommendation ? ucfirst($case->manager_recommendation) : 'Pending' }}</dd></div></dl>@include('hr.confirmation.partials.case-actions', ['case' => $case, 'actions' => $actions, 'mobile' => true])</article>@empty<div class="people-ops-empty"><strong>No confirmation cases found</strong></div>@endforelse</div>
        {{ $cases->withQueryString()->links() }}
    </section>
</x-hr.people-workspace>
@endsection
