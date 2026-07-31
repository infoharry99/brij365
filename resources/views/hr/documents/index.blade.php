@extends('layouts.builder360-classic')

@section('title', 'Employee Documents - Builder360 ERP-CRM')

@section('content')
<x-hr.people-workspace
    title="Employee Documents"
    :description="$employee ? $employee->employee_code.' · '.$employee->name : 'Track private employee document versions, approvals, and expiry dates.'"
    active="documents"
>
    <x-slot:actions>
        @if ($employee)<a class="people-button" href="{{ route('hr.employees.show', $employee) }}"><i class="fa-solid fa-user" aria-hidden="true"></i> Employee 360</a>@endif
        <a class="people-button" href="{{ route('documents.index') }}"><i class="fa-solid fa-folder-tree" aria-hidden="true"></i> Document workspace</a>
    </x-slot:actions>

    @if (session('status'))<section class="people-alert is-success" role="status">{{ session('status') }}</section>@endif
    @if ($errors->any())
        <section class="people-alert is-danger" role="alert" aria-labelledby="document-errors-title" tabindex="-1"><strong id="document-errors-title">Please correct the highlighted document fields.</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></section>
    @endif

    <section class="people-ops-kpis" aria-label="Document summary">
        @foreach ([
            ['Total records', $summary->total, 'fa-folder-open', ''],
            ['Awaiting approval', $summary->submitted, 'fa-hourglass-half', 'is-warning'],
            ['Approved', $summary->approved, 'fa-circle-check', 'is-success'],
            ['Expiring in 30 days', $summary->expiringSoon, 'fa-calendar-exclamation', 'is-warning'],
            ['Expired', $summary->expired, 'fa-triangle-exclamation', 'is-danger'],
        ] as [$label, $value, $icon, $tone])
            <article class="people-ops-kpi {{ $tone }}"><span class="people-ops-kpi-icon"><i class="fa-solid {{ $icon }}" aria-hidden="true"></i></span><span>{{ $label }}</span><strong>{{ number_format($value) }}</strong><small>Authorized document scope</small></article>
        @endforeach
    </section>

    @if ($employee && $abilities['canSubmit'])
        <details class="people-edit-details" @if($errors->any()) open @endif>
            <summary>Upload employee document</summary>
            <form method="POST" action="{{ route('hr.employees.documents.store', $employee) }}" enctype="multipart/form-data" class="people-form-grid people-edit-form" x-data="serverFormState" x-on:submit="beginSubmit" x-bind:aria-busy="busyAria" data-idle-label="Submit document" data-busy-label="Uploading…">
                @csrf
                <label class="people-field"><span>Category</span><select class="people-control" name="document_category_id" required><option value="">Select category</option>@foreach ($categories as $category)<option value="{{ $category->id }}" @selected((string) old('document_category_id') === (string) $category->id)>{{ $category->code }} · {{ $category->name }}{{ $category->expiry_required ? ' / expiry required' : '' }}</option>@endforeach</select>@error('document_category_id')<small class="people-field-error">{{ $message }}</small>@enderror</label>
                <label class="people-field"><span>Document title</span><input class="people-control" name="title" value="{{ old('title') }}" maxlength="255" required>@error('title')<small class="people-field-error">{{ $message }}</small>@enderror</label>
                <label class="people-field"><span>Issue date</span><input class="people-control" type="date" name="issue_date" value="{{ old('issue_date') }}" max="{{ now()->toDateString() }}">@error('issue_date')<small class="people-field-error">{{ $message }}</small>@enderror</label>
                <label class="people-field"><span>Expiry date</span><input class="people-control" type="date" name="expires_on" value="{{ old('expires_on') }}">@error('expires_on')<small class="people-field-error">{{ $message }}</small>@enderror</label>
                <label class="people-field is-wide"><span>Private document file</span><input class="people-control" type="file" name="document_file" required><small>Downloads remain protected by the document visibility policy.</small>@error('document_file')<small class="people-field-error">{{ $message }}</small>@enderror</label>
                <div class="people-modal-actions is-wide"><button class="people-button is-primary" type="submit" x-bind:disabled="busy"><span x-text="submitLabel">Submit document</span></button></div>
            </form>
        </details>
    @endif

    <section class="people-ops-panel has-mobile-cards" aria-labelledby="document-register-title">
        <header class="people-ops-panel-head"><div><h2 id="document-register-title">Document register</h2><p>{{ number_format($documents->total()) }} document{{ $documents->total() === 1 ? '' : 's' }} match the current filters.</p></div></header>
        <form method="GET" action="{{ $employee ? route('hr.employees.documents.index', $employee) : route('hr.employee-documents.index') }}" class="people-ops-filterbar" aria-label="Filter employee documents">
            @unless ($employee)
                <label class="people-field"><span>Search</span><input class="people-control" type="search" name="search" value="{{ request('search') }}" placeholder="Document, employee or department"></label>
                <label class="people-field"><span>Employee</span><select class="people-control" name="employee_id"><option value="">All visible employees</option>@foreach ($employees as $row)<option value="{{ $row->id }}" @selected((string) request('employee_id') === (string) $row->id)>{{ $row->employee_code }} · {{ $row->name }}</option>@endforeach</select></label>
                <label class="people-field"><span>Category</span><select class="people-control" name="document_category_id"><option value="">All categories</option>@foreach ($categories as $category)<option value="{{ $category->id }}" @selected((string) request('document_category_id') === (string) $category->id)>{{ $category->name }}</option>@endforeach</select></label>
            @endunless
            <label class="people-field"><span>Status</span><select class="people-control" name="status"><option value="">All statuses</option>@foreach (['submitted' => 'Submitted', 'approved' => 'Approved', 'rejected' => 'Rejected', 'archived' => 'Archived'] as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select></label>
            <label class="people-field"><span>Versions</span><select class="people-control" name="current_only"><option value="1" @selected(request('current_only', '1') === '1')>Current only</option><option value="0" @selected(request('current_only') === '0')>Current and previous</option></select></label>
            <label class="people-field"><span>Expiry window</span><select class="people-control" name="expires_within_days"><option value="">Any expiry</option>@foreach([30, 60, 90] as $days)<option value="{{ $days }}" @selected(request('expires_within_days') === (string) $days)>Next {{ $days }} days</option>@endforeach</select></label>
            <div class="people-modal-actions"><button class="people-button" type="submit">Apply filters</button><a class="people-button" href="{{ $employee ? route('hr.employees.documents.index', $employee) : route('hr.employee-documents.index') }}">Clear</a></div>
        </form>

        <div class="people-ops-table-wrap">
            <table class="people-ops-table"><caption>Private employee document register</caption><thead><tr><th scope="col">Document</th>@unless($employee)<th scope="col">Employee</th>@endunless<th scope="col">Category / version</th><th scope="col">Issue / expiry</th><th scope="col">Private file</th><th scope="col">Status</th><th scope="col" class="is-actions">Action</th></tr></thead><tbody>
            @forelse ($documents as $document)
                <tr>
                    <td><strong>{{ $document->documentNumber }}</strong><small>{{ $document->title }}</small></td>
                    @unless($employee)<td><div class="people-ops-identity"><span class="people-avatar">{{ $document->employeeInitial }}</span><div><strong>{{ $document->employeeName }}</strong><small>{{ $document->employeeCode }} / {{ $document->employeeContext }}</small></div></div></td>@endunless
                    <td>{{ $document->category }}<small>v{{ $document->version }} / {{ $document->isCurrent ? 'Current' : 'Previous' }}</small></td>
                    <td>{{ $document->issueDate }}<small><span class="people-status {{ $document->expiryTone }}">{{ $document->expiryState }}</span> {{ $document->expiryDate }}</small></td>
                    <td>{{ $document->filename }}<small>{{ $document->fileSize }}</small></td>
                    <td><span class="people-status {{ $document->statusTone }}">{{ $document->statusLabel }}</span></td>
                    <td class="is-actions">@include('hr.documents.partials.document-actions', ['document' => $document, 'actionContext' => 'desktop'])</td>
                </tr>
            @empty<tr><td colspan="{{ $employee ? 6 : 7 }}"><div class="people-ops-empty"><i class="fa-solid fa-folder-open" aria-hidden="true"></i><strong>No employee documents found</strong><span>Clear the filters or submit an authorized employee document.</span></div></td></tr>@endforelse
            </tbody></table>
        </div>
        <div class="people-ops-mobile-list">@foreach($documents as $document)<article class="people-ops-mobile-card"><div class="people-ops-mobile-card-head"><strong>{{ $document->documentNumber }} / {{ $document->title }}</strong><span class="people-status {{ $document->statusTone }}">{{ $document->statusLabel }}</span></div><dl class="people-ops-mobile-facts"><div><dt>Employee</dt><dd>{{ $document->employeeName }}</dd></div><div><dt>Category</dt><dd>{{ $document->category }} / v{{ $document->version }}</dd></div><div><dt>Expiry</dt><dd>{{ $document->expiryDate }} / {{ $document->expiryState }}</dd></div><div><dt>File</dt><dd>{{ $document->filename }} / {{ $document->fileSize }}</dd></div></dl><div class="people-ops-mobile-actions">@include('hr.documents.partials.document-actions', ['document' => $document, 'actionContext' => 'mobile'])</div></article>@endforeach</div>
        <div class="people-pagination">{{ $documents->withQueryString()->links() }}</div>
    </section>
</x-hr.people-workspace>
@endsection
