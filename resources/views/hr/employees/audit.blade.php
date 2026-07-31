@extends('layouts.builder360-classic')
@section('title', 'Employee Activity History - Builder360 ERP-CRM')
@section('content')
<x-hr.people-workspace
    title="Employee Activity History"
    :description="$employee->employee_code.' - '.$employee->name.' - '.$employee->designation"
    eyebrow="People / Employee 360"
    active="employees"
>
    <x-slot:actions><a class="people-button" href="{{ route('hr.employees.index') }}"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Employee directory</a></x-slot:actions>
    <x-hr.employee-profile-navigation :links="$profileNavigation" active="audit" />
    <section class="blade-card"><div class="blade-card-header"><div><p class="blade-eyebrow">Traceable changes</p><h2>Employee-related events</h2></div></div><form method="GET" action="{{ route('hr.employees.audit-events.index',$employee) }}" class="blade-filter-grid"><label>Event type<input name="event_type" value="{{ request('event_type') }}" placeholder="hr.employee.updated"></label><label>Date from<input type="date" name="date_from" value="{{ request('date_from') }}"></label><label>Date to<input type="date" name="date_to" value="{{ request('date_to') }}"></label><button class="blade-secondary-action">Apply filters</button></form><div class="blade-table-wrap"><table class="blade-table"><caption class="sr-only">Audit events for {{ $employee->name }}</caption><thead><tr><th scope="col">Date and time</th><th scope="col">Action</th><th scope="col">Event</th><th scope="col">User</th><th scope="col">Request context</th></tr></thead><tbody>@forelse($events as $event)<tr><td>{{ $event->created_at?->format('d M Y H:i:s') }}</td><td>{{ $event->action }}</td><td>{{ $event->event_type }}</td><td>{{ $event->user?->name??'System process' }}<br><span>{{ $event->user?->role?->name }}</span></td><td>{{ $event->request_method }} {{ $event->request_path }}<br><span>{{ $event->request_id }}</span></td></tr>@empty<tr><td colspan="5">No employee activity events are available for the selected filters.</td></tr>@endforelse</tbody></table></div>{{ $events->withQueryString()->links() }}</section>
</x-hr.people-workspace>
@endsection
