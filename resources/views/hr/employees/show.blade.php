@extends('layouts.builder360-classic')

@section('title', $employee->name.' - Employee Profile - Builder360 ERP-CRM')

@section('content')
<x-hr.people-workspace
    title="Employee 360"
    :description="$selfService ? 'Your administrator-managed employment information and authorized records.' : 'A permission-aware view of employee placement, lifecycle, and related records.'"
    :eyebrow="$selfService ? 'My workplace' : 'People / Employee profile'"
    active="employees"
    :self-service="$selfService"
>
    <x-slot:actions>
        @if ($selfService)
            <a class="people-button" href="{{ route('hr.employees.me') }}"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Self Service</a>
        @else
            <a class="people-button" href="{{ route('hr.employees.index') }}"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Employee directory</a>
        @endif
    </x-slot:actions>

    @if (session('status'))<section class="people-alert is-success" role="status">{{ session('status') }}</section>@endif
    @if ($errors->any())
        <section class="people-alert is-danger" role="alert" tabindex="-1" aria-labelledby="profile-errors-title">
            <strong id="profile-errors-title">Please correct the highlighted employee fields.</strong>
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </section>
    @endif

    <section class="people-profile-hero" aria-labelledby="employee-profile-name">
        <x-ui.user-avatar :user="$employee->user" :label="$employee->name" class="people-profile-avatar" />
        <div class="people-profile-copy">
            <h2 id="employee-profile-name">{{ $employee->name }}</h2>
            <p>{{ $employee->employee_code }} - {{ $employee->designation }} - {{ $employee->department }}</p>
            <p>{{ $employee->company?->name }}{{ $employee->branch ? ' / '.$employee->branch->name : '' }}</p>
        </div>
        <span class="people-status is-{{ ['active' => 'success', 'on_notice' => 'warning', 'separated' => 'danger'][$employee->status] ?? 'muted' }}">{{ $statuses[$employee->status] ?? ucfirst($employee->status) }}</span>
    </section>

    <x-hr.employee-profile-navigation :links="$profileNavigation" active="overview" />

    @if ($selfService)
        <section class="people-quick-actions" aria-label="Employee quick actions">
            <a href="{{ route('hr.attendance-regularizations.index') }}"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i><strong>Regularize attendance</strong><span>Request a correction</span></a>
            <a href="{{ route('hr.leave-requests.index') }}"><i class="fa-solid fa-plane-departure" aria-hidden="true"></i><strong>Apply leave</strong><span>View balances and requests</span></a>
            <a href="{{ route('hr.expense-claims.index') }}"><i class="fa-solid fa-receipt" aria-hidden="true"></i><strong>New claim</strong><span>Submit an expense claim</span></a>
            <a href="{{ route('hr.helpdesk-tickets.index') }}"><i class="fa-solid fa-headset" aria-hidden="true"></i><strong>HR request</strong><span>Contact the HR service desk</span></a>
        </section>
    @endif

    <section class="people-kpis" aria-label="Employee record summary">
        <article class="people-kpi"><span>Direct reports</span><strong>{{ $employee->direct_reports_count }}</strong><small>Employees reporting here</small></article>
        <article class="people-kpi"><span>Attendance records</span><strong>{{ $employee->attendance_records_count }}</strong><small>Recorded work days</small></article>
        <article class="people-kpi"><span>Leave requests</span><strong>{{ $employee->leave_requests_count }}</strong><small>Leave history records</small></article>
        <article class="people-kpi"><span>Performance reviews</span><strong>{{ $employee->performance_reviews_count }}</strong><small>Review records</small></article>
    </section>

    <section class="people-profile-grid">
        <article class="people-panel">
            <header class="people-panel-head"><h2>Current placement</h2><i class="fa-solid fa-id-card" aria-hidden="true"></i></header>
            <dl class="people-facts">
                <div><dt>Employment type</dt><dd>{{ $employmentTypes[$employee->employment_type] ?? str_replace('_', ' ', ucfirst($employee->employment_type)) }}</dd></div>
                <div><dt>Branch</dt><dd>{{ $employee->branch?->name ?? 'Not assigned' }}</dd></div>
                <div><dt>Primary project</dt><dd>{{ $employee->project?->name ?? 'All projects' }}</dd></div>
                <div><dt>Reporting manager</dt><dd>{{ $employee->manager?->name ?? 'Not assigned' }}</dd></div>
                <div><dt>Joining date</dt><dd>{{ $employee->joined_on?->format('d M Y') ?? 'Not recorded' }}</dd></div>
                <div><dt>Grade</dt><dd>{{ $employee->grade ?? 'Not recorded' }}</dd></div>
                <div><dt>Login account</dt><dd>{{ $employee->user?->email ?? 'No login linked' }}</dd></div>
                @if ($abilities['canViewPayroll'])<div><dt>Monthly CTC</dt><dd>{{ $employee->monthly_ctc !== null ? 'INR '.number_format((float) $employee->monthly_ctc, 2) : 'Not recorded' }}</dd></div>@endif
            </dl>
        </article>

        <article class="people-panel">
            <header class="people-panel-head"><h2>Employee lifecycle</h2><i class="fa-solid fa-arrows-spin" aria-hidden="true"></i></header>
            <dl class="people-facts">
                <div><dt>Documents</dt><dd>{{ $employee->managed_documents_count }}</dd></div>
                <div><dt>Assigned assets</dt><dd>{{ $employee->assets_count }}</dd></div>
                <div><dt>Confirmation cases</dt><dd>{{ $employee->confirmation_cases_count }}</dd></div>
                <div><dt>Separation records</dt><dd>{{ $employee->separation_settlements_count }}</dd></div>
                <div><dt>Expense claims</dt><dd>{{ $employee->expense_claims_count }}</dd></div>
                <div><dt>Loans</dt><dd>{{ $employee->loans_count }}</dd></div>
                @if ($abilities['canViewPayroll'])<div><dt>Payroll records</dt><dd>{{ $employee->payroll_run_items_count }}</dd></div>@endif
            </dl>
        </article>
    </section>

    @if ($abilities['canUpdate'])
        <details class="people-edit-details" @if($errors->any()) open @endif>
            <summary>Update employee record</summary>
            <form
                method="POST"
                action="{{ route('hr.employees.update', $employee) }}"
                class="people-form-grid people-edit-form"
                x-data="serverFormState"
                x-on:submit="beginSubmit"
                x-bind:aria-busy="busyAria"
                data-idle-label="Save changes"
                data-busy-label="Saving changes…"
            >
                @csrf
                @method('PATCH')
                <input type="hidden" name="lock_version" value="{{ $employee->lock_version }}">
                @error('lock_version')<div class="people-field is-wide"><span class="people-field-error" role="alert" tabindex="-1">{{ $message }}</span></div>@enderror
                <label class="people-field" for="profile-code"><span>Employee code *</span><input id="profile-code" class="people-control" name="employee_code" value="{{ old('employee_code', $employee->employee_code) }}" maxlength="32" required @if($errors->has('employee_code')) aria-invalid="true" aria-describedby="profile-code-error" @endif>@error('employee_code')<span class="people-field-error" id="profile-code-error">{{ $message }}</span>@enderror</label>
                <label class="people-field" for="profile-name"><span>Employee name *</span><input id="profile-name" class="people-control" name="name" value="{{ old('name', $employee->name) }}" maxlength="255" required @if($errors->has('name')) aria-invalid="true" aria-describedby="profile-name-error" @endif>@error('name')<span class="people-field-error" id="profile-name-error">{{ $message }}</span>@enderror</label>
                <label class="people-field" for="profile-designation"><span>Designation *</span><input id="profile-designation" class="people-control" name="designation" value="{{ old('designation', $employee->designation) }}" maxlength="120" required @if($errors->has('designation')) aria-invalid="true" aria-describedby="profile-designation-error" @endif>@error('designation')<span class="people-field-error" id="profile-designation-error">{{ $message }}</span>@enderror</label>
                <label class="people-field" for="profile-department"><span>Department *</span><input id="profile-department" class="people-control" name="department" value="{{ old('department', $employee->department) }}" maxlength="120" required @if($errors->has('department')) aria-invalid="true" aria-describedby="profile-department-error" @endif>@error('department')<span class="people-field-error" id="profile-department-error">{{ $message }}</span>@enderror</label>
                <label class="people-field" for="profile-branch"><span>Branch</span><select id="profile-branch" class="people-control" name="branch_id" @if($errors->has('branch_id')) aria-invalid="true" aria-describedby="profile-branch-error" @endif><option value="">No branch assignment</option>@foreach ($branches as $branch)<option value="{{ $branch->id }}" @selected((string) old('branch_id', $employee->branch_id) === (string) $branch->id)>{{ $branch->code }} - {{ $branch->name }}</option>@endforeach</select>@error('branch_id')<span class="people-field-error" id="profile-branch-error">{{ $message }}</span>@enderror</label>
                <label class="people-field" for="profile-project"><span>Primary project</span><select id="profile-project" class="people-control" name="project_id" @if($errors->has('project_id')) aria-invalid="true" aria-describedby="profile-project-error" @endif><option value="">All-project employee</option>@foreach ($projects as $project)<option value="{{ $project->id }}" @selected((string) old('project_id', $employee->project_id) === (string) $project->id)>{{ $project->code }} - {{ $project->name }}</option>@endforeach</select>@error('project_id')<span class="people-field-error" id="profile-project-error">{{ $message }}</span>@enderror</label>
                <label class="people-field" for="profile-manager"><span>Reporting manager</span><select id="profile-manager" class="people-control" name="manager_employee_id" @if($errors->has('manager_employee_id')) aria-invalid="true" aria-describedby="profile-manager-error" @endif><option value="">No reporting manager</option>@foreach ($managers as $manager)<option value="{{ $manager->id }}" @selected((string) old('manager_employee_id', $employee->manager_employee_id) === (string) $manager->id)>{{ $manager->employee_code }} - {{ $manager->name }}</option>@endforeach</select>@error('manager_employee_id')<span class="people-field-error" id="profile-manager-error">{{ $message }}</span>@enderror</label>
                <label class="people-field" for="profile-user"><span>Application user</span><select id="profile-user" class="people-control" name="user_id" @if($errors->has('user_id')) aria-invalid="true" aria-describedby="profile-user-error" @endif><option value="">No login linked</option>@if($employee->user)<option value="{{ $employee->user->id }}" selected>{{ $employee->user->name }} - {{ $employee->user->email }}</option>@endif @foreach($users as $user)@if($user->id !== $employee->user_id)<option value="{{ $user->id }}" @selected((string) old('user_id') === (string) $user->id)>{{ $user->name }} - {{ $user->email }}</option>@endif @endforeach</select>@error('user_id')<span class="people-field-error" id="profile-user-error">{{ $message }}</span>@enderror</label>
                <label class="people-field" for="profile-type"><span>Employment type *</span><select id="profile-type" class="people-control" name="employment_type" required @if($errors->has('employment_type')) aria-invalid="true" aria-describedby="profile-type-error" @endif>@foreach($employmentTypes as $value => $label)<option value="{{ $value }}" @selected(old('employment_type', $employee->employment_type) === $value)>{{ $label }}</option>@endforeach</select>@error('employment_type')<span class="people-field-error" id="profile-type-error">{{ $message }}</span>@enderror</label>
                <label class="people-field" for="profile-status"><span>Status *</span><select id="profile-status" class="people-control" name="status" required @if($errors->has('status')) aria-invalid="true" aria-describedby="profile-status-error" @endif>@foreach($statuses as $value => $label)<option value="{{ $value }}" @selected(old('status', $employee->status) === $value)>{{ $label }}</option>@endforeach</select>@error('status')<span class="people-field-error" id="profile-status-error">{{ $message }}</span>@enderror</label>
                <label class="people-field" for="profile-grade"><span>Grade</span><input id="profile-grade" class="people-control" name="grade" value="{{ old('grade', $employee->grade) }}" maxlength="16" @if($errors->has('grade')) aria-invalid="true" aria-describedby="profile-grade-error" @endif>@error('grade')<span class="people-field-error" id="profile-grade-error">{{ $message }}</span>@enderror</label>
                <label class="people-field" for="profile-joined"><span>Joining date</span><input id="profile-joined" type="date" class="people-control" name="joined_on" value="{{ old('joined_on', $employee->joined_on?->toDateString()) }}" max="{{ now()->toDateString() }}" @if($errors->has('joined_on')) aria-invalid="true" aria-describedby="profile-joined-error" @endif>@error('joined_on')<span class="people-field-error" id="profile-joined-error">{{ $message }}</span>@enderror</label>
                <label class="people-field" for="profile-state"><span>Statutory state code</span><input id="profile-state" class="people-control" name="statutory_state" value="{{ old('statutory_state', $employee->statutory_state) }}" maxlength="8" @if($errors->has('statutory_state')) aria-invalid="true" aria-describedby="profile-state-error" @endif>@error('statutory_state')<span class="people-field-error" id="profile-state-error">{{ $message }}</span>@enderror</label>
                @if($abilities['canViewPayroll'])<label class="people-field" for="profile-ctc"><span>Monthly CTC</span><input id="profile-ctc" type="number" class="people-control" name="monthly_ctc" value="{{ old('monthly_ctc', $employee->monthly_ctc) }}" min="0" step="0.01" @if($errors->has('monthly_ctc')) aria-invalid="true" aria-describedby="profile-ctc-error" @endif>@error('monthly_ctc')<span class="people-field-error" id="profile-ctc-error">{{ $message }}</span>@enderror</label>@endif
                <div class="people-field is-wide"><button type="submit" class="people-button is-primary" x-bind:disabled="busy"><span x-text="submitLabel">Save changes</span></button></div>
            </form>
        </details>
    @else
        <section class="people-alert" role="note"><i class="fa-solid fa-lock" aria-hidden="true"></i> This profile is available in read-only mode for your current access.</section>
    @endif
</x-hr.people-workspace>
@endsection
