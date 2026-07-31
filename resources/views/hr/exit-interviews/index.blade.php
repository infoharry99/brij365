@extends('layouts.builder360-classic')

@section('title', 'Exit Interviews - Builder360 ERP-CRM')

@section('content')
<x-hr.people-workspace
    title="Exit Interviews"
    description="Schedule employee feedback, protect confidential responses, and track authorized HR follow-up actions."
    active="lifecycle"
>
    <x-slot:actions>
        @if($abilities['canCreate'])<a class="people-button is-primary" href="#schedule-exit-interview"><i class="fa-solid fa-plus" aria-hidden="true"></i> Schedule interview</a>@endif
    </x-slot:actions>

    @include('hr.lifecycle.partials.navigation', ['activeLifecycleSection' => 'exit'])

    @if(session('status'))<section class="people-alert is-success" role="status">{{ session('status') }}</section>@endif
    @if($errors->any())<section class="people-alert is-danger" role="alert" tabindex="-1"><strong>Please correct the highlighted exit interview fields.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></section>@endif

    <section class="people-ops-kpis" aria-label="Exit interview summary">
        <article class="people-ops-kpi"><span class="people-ops-kpi-icon is-indigo"><i class="fa-solid fa-comments" aria-hidden="true"></i></span><div><strong>{{ $summary['total'] }}</strong><span>Total interviews</span></div></article>
        <article class="people-ops-kpi"><span class="people-ops-kpi-icon is-blue"><i class="fa-solid fa-calendar-day" aria-hidden="true"></i></span><div><strong>{{ $summary['status_counts']['scheduled'] ?? 0 }}</strong><span>Scheduled</span></div></article>
        <article class="people-ops-kpi"><span class="people-ops-kpi-icon is-amber"><i class="fa-solid fa-hourglass-half" aria-hidden="true"></i></span><div><strong>{{ $summary['status_counts']['submitted'] ?? 0 }}</strong><span>Awaiting HR review</span></div></article>
        <article class="people-ops-kpi"><span class="people-ops-kpi-icon is-green"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></span><div><strong>{{ $summary['status_counts']['reviewed'] ?? 0 }}</strong><span>Reviewed</span></div></article>
        <article class="people-ops-kpi"><span class="people-ops-kpi-icon is-red"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i></span><div><strong>{{ $summary['open_action_items'] }}</strong><span>Open actions</span></div></article>
    </section>

    @if($abilities['canCreate'])
        <details class="people-ops-panel" id="schedule-exit-interview" @if($errors->any()) open @endif>
            <summary class="people-ops-panel-head"><div><h2>Schedule exit interview</h2><p>Link the interview to an authorized employee and, when available, their final settlement.</p></div></summary>
            <div class="people-ops-panel-body"><form method="POST" action="{{ route('hr.exit-interviews.store') }}" class="people-form-grid">@csrf
                <label class="people-field"><span>Employee</span><select class="people-control" name="employee_id" required><option value="">Select employee</option>@foreach($employees as $employee)<option value="{{ $employee->id }}" @selected((string)old('employee_id')===(string)$employee->id)>{{ $employee->employee_code }} · {{ $employee->name }}{{ $employee->department ? ' · '.$employee->department : '' }}</option>@endforeach</select>@error('employee_id')<span class="people-field-error">{{ $message }}</span>@enderror</label>
                <label class="people-field"><span>Final settlement</span><select class="people-control" name="employee_separation_settlement_id"><option value="">No linked settlement</option>@foreach($settlements as $settlement)<option value="{{ $settlement->id }}" @selected((string)old('employee_separation_settlement_id')===(string)$settlement->id)>{{ $settlement->settlement_number }} · {{ $settlement->employee?->name }}</option>@endforeach</select>@error('employee_separation_settlement_id')<span class="people-field-error">{{ $message }}</span>@enderror</label>
                <label class="people-field"><span>Interview due on</span><input class="people-control" type="date" name="interview_due_on" value="{{ old('interview_due_on') }}" required>@error('interview_due_on')<span class="people-field-error">{{ $message }}</span>@enderror</label>
                <label class="people-field is-wide"><span>Scheduling note</span><textarea class="people-control" name="note" maxlength="1000">{{ old('note') }}</textarea>@error('note')<span class="people-field-error">{{ $message }}</span>@enderror</label>
                <button class="people-button is-primary" type="submit">Schedule interview</button>
            </form></div>
        </details>
    @endif

    <section class="people-ops-panel has-mobile-cards" aria-labelledby="exit-interview-register-title">
        <header class="people-ops-panel-head"><div><h2 id="exit-interview-register-title">Exit interview register</h2><p>{{ $interviews->total() }} authorized interview{{ $interviews->total() === 1 ? '' : 's' }}.</p></div><a class="people-button" href="{{ route('hr.exit-interviews.summary', request()->query()) }}">Summary data</a></header>
        <div class="people-ops-panel-body"><form method="GET" action="{{ route('hr.exit-interviews.index') }}" class="people-ops-filterbar" aria-label="Filter exit interviews">
            <label class="people-field"><span>Employee</span><select class="people-control" name="employee_id"><option value="">All visible employees</option>@foreach($employees as $employee)<option value="{{ $employee->id }}" @selected((string)request('employee_id')===(string)$employee->id)>{{ $employee->employee_code }} · {{ $employee->name }}</option>@endforeach</select></label>
            <label class="people-field"><span>Status</span><select class="people-control" name="status"><option value="">All statuses</option>@foreach(['scheduled'=>'Scheduled','submitted'=>'Submitted','reviewed'=>'Reviewed','archived'=>'Archived'] as $value=>$label)<option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>@endforeach</select></label>
            <label class="people-field"><span>Reason</span><select class="people-control" name="separation_reason"><option value="">All reasons</option>@foreach(['career_growth','compensation','relocation','manager_issue','work_environment','health','retirement','contract_end','personal','other'] as $value)<option value="{{ $value }}" @selected(request('separation_reason')===$value)>{{ str_replace('_',' ',ucfirst($value)) }}</option>@endforeach</select></label>
            <label class="people-field"><span>From</span><input class="people-control" type="date" name="from" value="{{ request('from') }}"></label>
            <label class="people-field"><span>To</span><input class="people-control" type="date" name="to" value="{{ request('to') }}"></label>
            <button class="people-button is-primary">Apply</button><a class="people-button" href="{{ route('hr.exit-interviews.index') }}">Clear</a>
        </form></div>

        <div class="people-ops-table-wrap"><table class="people-ops-table"><caption>Employee exit interviews</caption><thead><tr><th scope="col">Interview</th><th scope="col">Employee</th><th scope="col">Schedule</th><th scope="col">Ratings</th><th scope="col">Reason / rehire</th><th scope="col">Status</th><th scope="col">Action</th></tr></thead><tbody>
        @forelse($interviews as $interview) @php($actions=$interviewActions[$interview->id]??[])
            <tr>
                <td><strong>{{ $interview->interview_number }}</strong><small>{{ count($interview->risk_flags ?? []) }} risk flag(s)</small></td>
                <td><span class="people-ops-identity"><strong>{{ $interview->employee?->name }}</strong><small>{{ $interview->employee?->employee_code }} · {{ $interview->employee?->department ?: 'No department' }}</small></span></td>
                <td>{{ $interview->interview_due_on?->format('d M Y') ?? 'Not set' }}<small>{{ $interview->submitted_at?->format('d M Y H:i') ?? 'Not submitted' }}</small></td>
                <td>Overall {{ $interview->overall_experience_rating ?? 'Not rated' }}<small>Manager {{ $interview->manager_relationship_rating ?? 'Not rated' }}</small></td>
                <td>{{ $interview->separation_reason ? str_replace('_',' ',ucfirst($interview->separation_reason)) : 'Awaiting response' }}<small>Rehire: {{ $interview->rehire_recommendation ? ucfirst($interview->rehire_recommendation) : 'Not recorded' }}</small></td>
                <td><span class="people-status is-{{ $interview->status === 'reviewed' ? 'success' : ($interview->status === 'archived' ? 'neutral' : 'warning') }}">{{ ucfirst($interview->status) }}</span></td>
                <td>
                    @if($actions['canSubmit']??false)<details><summary class="people-ops-action-link">Submit feedback</summary>@include('hr.exit-interviews.partials.submit-form', ['interview' => $interview])</details>@endif
                    @if($actions['canReview']??false)<details><summary class="people-ops-action-link">HR review</summary>@include('hr.exit-interviews.partials.review-form', ['interview' => $interview])</details>@endif
                    @unless(($actions['canSubmit']??false)||($actions['canReview']??false))<span class="people-subtext">No action</span>@endunless
                </td>
            </tr>
        @empty<tr><td colspan="7"><div class="people-ops-empty"><strong>No exit interviews found</strong><span>Clear the filters or schedule an authorized interview.</span></div></td></tr>@endforelse
        </tbody></table></div>

        <div class="people-ops-mobile-list">@forelse($interviews as $interview) @php($actions=$interviewActions[$interview->id]??[])<article class="people-ops-mobile-card"><header class="people-ops-mobile-card-head"><span class="people-ops-identity"><strong>{{ $interview->employee?->name }}</strong><small>{{ $interview->interview_number }} · {{ $interview->employee?->employee_code }}</small></span><span class="people-status is-info">{{ ucfirst($interview->status) }}</span></header><dl class="people-ops-mobile-facts"><div><dt>Due</dt><dd>{{ $interview->interview_due_on?->format('d M Y') ?? 'Not set' }}</dd></div><div><dt>Reason</dt><dd>{{ $interview->separation_reason ? str_replace('_',' ',ucfirst($interview->separation_reason)) : 'Pending' }}</dd></div><div><dt>Open actions</dt><dd>{{ count($interview->action_items ?? []) }}</dd></div></dl>@if(($actions['canSubmit']??false)||($actions['canReview']??false))<div class="people-ops-mobile-actions">@if($actions['canSubmit']??false)<details><summary class="people-button">Submit feedback</summary>@include('hr.exit-interviews.partials.submit-form', ['interview' => $interview])</details>@endif @if($actions['canReview']??false)<details><summary class="people-button">HR review</summary>@include('hr.exit-interviews.partials.review-form', ['interview' => $interview])</details>@endif</div>@endif</article>@empty<div class="people-ops-empty"><strong>No exit interviews found</strong></div>@endforelse</div>
        {{ $interviews->withQueryString()->links() }}
    </section>
</x-hr.people-workspace>
@endsection
