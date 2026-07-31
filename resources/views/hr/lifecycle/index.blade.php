@extends('layouts.builder360-classic')

@section('title', 'Employee Lifecycle - Builder360 ERP-CRM')

@section('content')
<x-hr.people-workspace
    title="Employee Lifecycle"
    description="Track persisted employee movements, confirmation, separation, and exit interview milestones in your authorized scope."
    active="lifecycle"
>
    @include('hr.lifecycle.partials.navigation', ['activeLifecycleSection' => 'tracker'])

    <section class="people-ops-kpis" aria-label="Employee Lifecycle summary">
        <article class="people-ops-kpi is-info"><span class="people-ops-kpi-icon"><i class="fa-solid fa-route" aria-hidden="true"></i></span><span>Lifecycle events</span><strong>{{ $lifecycleSummary->totalEvents }}</strong><small>Persisted events in the selected scope</small></article>
        <article class="people-ops-kpi is-purple"><span class="people-ops-kpi-icon"><i class="fa-solid fa-arrows-left-right" aria-hidden="true"></i></span><span>Pending movements</span><strong>{{ $lifecycleSummary->pendingMovements }}</strong><small>Awaiting an authorized decision</small></article>
        <article class="people-ops-kpi is-warning"><span class="people-ops-kpi-icon"><i class="fa-solid fa-user-clock" aria-hidden="true"></i></span><span>Open confirmations</span><strong>{{ $lifecycleSummary->openConfirmations }}</strong><small>Not confirmed or rejected</small></article>
        <article class="people-ops-kpi is-danger"><span class="people-ops-kpi-icon"><i class="fa-solid fa-person-walking-arrow-right" aria-hidden="true"></i></span><span>Open settlements</span><strong>{{ $lifecycleSummary->openSeparations }}</strong><small>Full &amp; Final is not completed</small></article>
        <article class="people-ops-kpi is-success"><span class="people-ops-kpi-icon"><i class="fa-regular fa-comments" aria-hidden="true"></i></span><span>Open exit interviews</span><strong>{{ $lifecycleSummary->openExitInterviews }}</strong><small>Scheduled or submitted interviews</small></article>
    </section>

    <section class="people-ops-panel" aria-labelledby="lifecycle-tracker-heading">
        <header class="people-ops-panel-head">
            <div><h2 id="lifecycle-tracker-heading">Lifecycle tracker</h2><p>Every row is derived from an existing authorized HR record.</p></div>
        </header>
        <div class="people-ops-panel-body">
            <form method="GET" action="{{ route('hr.lifecycle.index') }}" class="people-ops-filterbar" aria-label="Filter lifecycle events">
                <label class="people-field"><span>Stage</span><select class="people-control" name="stage">
                    @foreach(['all' => 'All stages', 'movements' => 'Movements', 'confirmation' => 'Confirmation', 'separation' => 'Full & Final', 'exit' => 'Exit interviews'] as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['stage'] ?? 'all') === $value)>{{ $label }}</option>
                    @endforeach
                </select></label>
                <label class="people-field"><span>Employee</span><select class="people-control" name="employee_id"><option value="">All employees</option>@foreach($employees as $employee)<option value="{{ $employee['id'] }}" @selected((string)($filters['employee_id'] ?? '') === (string)$employee['id'])>{{ $employee['label'] }}</option>@endforeach</select></label>
                <label class="people-field"><span>Department</span><select class="people-control" name="department"><option value="">All departments</option>@foreach($departments as $department)<option value="{{ $department }}" @selected(($filters['department'] ?? '') === $department)>{{ $department }}</option>@endforeach</select></label>
                <button class="people-button is-primary" type="submit"><i class="fa-solid fa-filter" aria-hidden="true"></i> Apply</button>
                <a class="people-button" href="{{ route('hr.lifecycle.index') }}">Clear all</a>
            </form>

            @if($lifecycleEvents->isEmpty())
                <div class="people-ops-empty" role="status"><i class="fa-solid fa-route" aria-hidden="true"></i><strong>No lifecycle events found</strong><span>Change the filters or create records through the authorized lifecycle workflows.</span></div>
            @else
                <div class="people-ops-table-wrap">
                    <table class="people-ops-table">
                        <caption class="sr-only">Authorized employee lifecycle events</caption>
                        <thead><tr><th scope="col">Employee</th><th scope="col">Milestone</th><th scope="col">Reference</th><th scope="col">Date</th><th scope="col">Status</th><th scope="col">Action</th></tr></thead>
                        <tbody>
                        @foreach($lifecycleEvents as $event)
                            <tr>
                                <td><span class="people-ops-identity"><strong>{{ $event->employeeName }}</strong><small>{{ $event->employeeCode }} · {{ $event->designation }} · {{ $event->department }}</small></span></td>
                                <td>{{ $event->eventTypeLabel }}</td>
                                <td>{{ $event->number }}</td>
                                <td><time datetime="{{ $event->eventDate }}">{{ $event->eventDateLabel }}</time></td>
                                <td><span class="people-status is-{{ $event->statusTone }}">{{ $event->statusLabel }}</span></td>
                                <td><a class="people-ops-action-link" href="{{ $event->url }}" aria-label="Open {{ $event->eventTypeLabel }} for {{ $event->employeeName }}">Open <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="people-ops-mobile-list" aria-label="Employee lifecycle event cards">
                    @foreach($lifecycleEvents as $event)
                        <article class="people-ops-mobile-card">
                            <header class="people-ops-mobile-card-head"><span class="people-ops-identity"><strong>{{ $event->employeeName }}</strong><small>{{ $event->employeeCode }} · {{ $event->department }}</small></span><span class="people-status is-{{ $event->statusTone }}">{{ $event->statusLabel }}</span></header>
                            <dl class="people-ops-mobile-facts"><div><dt>Milestone</dt><dd>{{ $event->eventTypeLabel }}</dd></div><div><dt>Reference</dt><dd>{{ $event->number }}</dd></div><div><dt>Date</dt><dd>{{ $event->eventDateLabel }}</dd></div></dl>
                            <a class="people-ops-action-link" href="{{ $event->url }}">Open workflow <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                        </article>
                    @endforeach
                </div>

                {{ $lifecycleEvents->links() }}
            @endif
        </div>
    </section>
</x-hr.people-workspace>
@endsection
