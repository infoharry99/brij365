@extends('layouts.builder360-classic')

@section('title', 'Employee Master - Builder360 ERP-CRM')

@section('content')
<x-hr.people-workspace
    title="Employee Master"
    description="Manage employee identity, work placement, reporting relationships, and authorized records."
    active="employees"
    :open-create="$errors->any()"
>
    <x-slot:actions>
        @if ($abilities['canExport'])
            <a class="people-button" href="{{ route('hr.employees.export', array_filter(request()->only(['company_id', 'branch_id', 'project_id', 'department', 'designation', 'status', 'search'])) + ['format' => 'csv']) }}">
                <i class="fa-solid fa-download" aria-hidden="true"></i> Export register
            </a>
        @endif
        @if ($abilities['canCreate'])
            <a href="#create-employee" class="people-button is-primary" x-on:click.prevent="openCreateEmployee">
                <i class="fa-solid fa-plus" aria-hidden="true"></i> Add employee
            </a>
        @endif
    </x-slot:actions>

    @if (session('status'))
        <section class="people-alert is-success" role="status">{{ session('status') }}</section>
    @endif

    @if ($errors->any())
        <section class="people-alert is-danger" role="alert" tabindex="-1" aria-labelledby="employee-errors-title">
            <strong id="employee-errors-title">Please correct the highlighted employee fields.</strong>
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </section>
    @endif

    <section class="people-directory" aria-labelledby="employee-register-title">
        <header class="people-directory-head">
            <div><h2 id="employee-register-title">Employee Directory</h2><p>Employees available within your company and role scope.</p></div>
            <span class="people-count">{{ number_format($employees->total()) }} {{ str('employee')->plural($employees->total()) }}</span>
        </header>

        <form method="GET" action="{{ route('hr.employees.index') }}" class="people-filter-form" aria-label="Filter employee directory">
            <label class="people-control-wrap">
                <span class="sr-only">Search employees</span><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input class="people-control" type="search" name="search" value="{{ request('search') }}" placeholder="Search name, code, email, role or department">
            </label>
            <label><span class="sr-only">Department</span><select class="people-control" name="department"><option value="">All departments</option>@foreach ($departments as $department)<option value="{{ $department }}" @selected(request('department') === $department)>{{ $department }}</option>@endforeach</select></label>
            <label><span class="sr-only">Designation</span><select class="people-control" name="designation"><option value="">All designations</option>@foreach ($designations as $designation)<option value="{{ $designation }}" @selected(request('designation') === $designation)>{{ $designation }}</option>@endforeach</select></label>
            <label><span class="sr-only">Branch</span><select class="people-control" name="branch_id"><option value="">All branches</option>@foreach ($branches as $branch)<option value="{{ $branch->id }}" @selected((string) request('branch_id') === (string) $branch->id)>{{ $branch->name }}</option>@endforeach</select></label>
            <label><span class="sr-only">Project</span><select class="people-control" name="project_id"><option value="">All projects</option>@foreach ($projects as $project)<option value="{{ $project->id }}" @selected((string) request('project_id') === (string) $project->id)>{{ $project->name }}</option>@endforeach</select></label>
            <label><span class="sr-only">Status</span><select class="people-control" name="status"><option value="">All statuses</option>@foreach ($statuses as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select></label>
            <button type="submit" class="people-button"><i class="fa-solid fa-filter" aria-hidden="true"></i> Apply</button>
        </form>

        @if ($activeFilters !== [])
            <nav class="people-filter-chips" aria-label="Active employee filters">
                <span>Active filters</span>
                @foreach ($activeFilters as $filter)
                    <a class="people-filter-chip" href="{{ route('hr.employees.index', request()->except([$filter->key, 'page'])) }}" aria-label="Remove {{ $filter->label }} filter">
                        {{ $filter->label }}: {{ $filter->value }} <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </a>
                @endforeach
                <a class="people-filter-chip" href="{{ route('hr.employees.index') }}">Clear all</a>
            </nav>
        @endif

        @if ($employees->isEmpty())
            <x-hr.people-state
                :type="$activeFilters !== [] ? 'filtered' : 'empty'"
                :icon="$activeFilters !== [] ? null : 'fa-user-group'"
                :title="$activeFilters !== [] ? 'No employees match these filters' : 'No employees are available'"
                :message="$activeFilters !== [] ? 'Clear or adjust the directory filters to broaden the results.' : 'Employee records will appear here when they are created within your company scope.'"
                :action-url="$activeFilters !== [] ? route('hr.employees.index') : null"
                :action-label="$activeFilters !== [] ? 'Clear filters' : null"
                aria-label="No employees found"
            />
        @else
            @include('hr.employees.partials.directory-register')
        @endif

        <footer class="people-pagination">
            <span>Showing {{ number_format($employees->firstItem() ?? 0) }}-{{ number_format($employees->lastItem() ?? 0) }} of {{ number_format($employees->total()) }}</span>
            {{ $employees->withQueryString()->links() }}
        </footer>
    </section>

    @if ($abilities['canCreate'])
        @include('hr.employees.partials.create-form')
    @endif
</x-hr.people-workspace>
@endsection
