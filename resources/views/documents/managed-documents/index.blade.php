@extends('layouts.builder360-classic')

@section('title', 'Document Repository - Builder360 ERP-CRM')

@section('content')
@php
        $submittedCount = $documents->getCollection()->where('status', 'submitted')->count();
        $approvedCount = $documents->getCollection()->where('status', 'approved')->count();
        $expiringCount = $documents->getCollection()->filter(fn ($document) => $document->isExpiringWithin(30))->count();
        $expiredCount = $documents->getCollection()->filter(fn ($document) => $document->isExpired())->count();
    @endphp

    <div class="blade-workspace" aria-labelledby="document-repository-title">
        <header class="blade-workspace-header">
            <div>
                <p class="blade-dashboard-eyebrow">Document Management</p>
                <h1 id="document-repository-title">Document Repository</h1>
                <p>
                    Secure document repository for controlled document uploads, category rules,
                    versioning, expiry tracking, role-based approval, secure downloads and activity history.
                </p>
            </div>
            <nav class="blade-workspace-actions" aria-label="Document navigation">
                <a href="{{ url('/') }}">Dashboard</a>
                <a href="{{ route('documents.categories.index') }}">Document Categories</a>
                <a href="{{ route('documents.index', ['expires_within_days' => 30]) }}">Expiring in 30 days</a>
                <a href="{{ route('documents.index') }}">Reset filters</a>
            </nav>
        </header>

        @if (session('status'))
            <div class="blade-alert blade-alert-success">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="blade-alert blade-alert-danger">
                <strong>Check the highlighted inputs.</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="blade-dashboard-kpis" aria-label="Document KPIs">
            <article class="blade-dashboard-kpi">
                <span>Total Documents</span>
                <strong>{{ number_format($documents->total()) }}</strong>
                <small>Document register</small>
            </article>
            <article class="blade-dashboard-kpi">
                <span>Submitted</span>
                <strong>{{ number_format($submittedCount) }}</strong>
                <small>Current page</small>
            </article>
            <article class="blade-dashboard-kpi">
                <span>Approved</span>
                <strong>{{ number_format($approvedCount) }}</strong>
                <small>Current page</small>
            </article>
            <article class="blade-dashboard-kpi">
                <span>Expiry Attention</span>
                <strong>{{ number_format($expiringCount + $expiredCount) }}</strong>
                <small>Expiring/expired on page</small>
            </article>
        </section>

        <section class="blade-workspace-grid">
            <article class="blade-dashboard-card">
                <div class="blade-dashboard-section-title">
                    <div>
                        <span class="blade-dashboard-label">Upload</span>
                        <h2>Submit managed document</h2>
                    </div>
                    <small>{{ $canCreateDocument ? 'Authorized' : 'Read only' }}</small>
                </div>

                @if ($canCreateDocument)
                    <form method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data" class="blade-form-grid">
                        @csrf
                        <input type="hidden" name="_return_to" value="documents.index">
                        <input type="hidden" name="storage_disk" value="local">
                        <input type="hidden" name="metadata[source]" value="document_repository_blade">
                        <label class="blade-form-wide">
                            Document category
                            <select name="document_category_id" required>
                                <option value="">Select category</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}" @selected((int) old('document_category_id') === (int) $category->id)>
                                        {{ $category->code }} · {{ $category->name }} · {{ $category->owner_type }}
                                        @if ($category->expiry_required)
                                            · expiry required
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                        </label>
                        <label class="blade-form-wide">
                            Title
                            <input type="text" name="title" value="{{ old('title') }}" maxlength="255" required>
                        </label>
                        <label>
                            Owner type
                            <select name="owner_type" required>
                                <option value="">Select owner type</option>
                                @foreach ($ownerTypes as $value => $label)
                                    <option value="{{ $value }}" @selected(old('owner_type') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>
                            Owner record
                            <select name="owner_id" required>
                                <option value="">Select owner after matching owner type</option>
                                <optgroup label="Projects">
                                    @foreach ($projects as $project)
                                        <option value="{{ $project->id }}" @selected((int) old('owner_id') === (int) $project->id && old('owner_type') === 'project')>
                                            #{{ $project->id }} · {{ $project->code }} · {{ $project->name }}
                                        </option>
                                    @endforeach
                                </optgroup>
                                <optgroup label="Bookings">
                                    @foreach ($bookings as $booking)
                                        <option value="{{ $booking->id }}" @selected((int) old('owner_id') === (int) $booking->id && old('owner_type') === 'booking')>
                                            #{{ $booking->id }} · {{ $booking->booking_code }} @if($booking->customer) · {{ $booking->customer->name }} @endif
                                        </option>
                                    @endforeach
                                </optgroup>
                                <optgroup label="Customers">
                                    @foreach ($customers as $customer)
                                        <option value="{{ $customer->id }}" @selected((int) old('owner_id') === (int) $customer->id && old('owner_type') === 'customer')>
                                            #{{ $customer->id }} · {{ $customer->code }} · {{ $customer->name }}
                                        </option>
                                    @endforeach
                                </optgroup>
                                <optgroup label="Employees">
                                    @foreach ($employees as $employee)
                                        <option value="{{ $employee->id }}" @selected((int) old('owner_id') === (int) $employee->id && old('owner_type') === 'employee')>
                                            #{{ $employee->id }} · {{ $employee->employee_code }} · {{ $employee->name }}
                                        </option>
                                    @endforeach
                                </optgroup>
                            </select>
                            <small>Owner type and owner record must match; validation blocks mismatches.</small>
                        </label>
                        <label>
                            Issue date
                            <input type="date" name="issue_date" value="{{ old('issue_date') }}">
                        </label>
                        <label>
                            Expiry date
                            <input type="date" name="expires_on" value="{{ old('expires_on') }}">
                        </label>
                        <label class="blade-form-wide">
                            Document file
                            <input type="file" name="document_file" required>
                            <small>Allowed file type and size are enforced by configured document policy.</small>
                        </label>
                        <button type="submit" class="blade-primary-action">Submit document</button>
                    </form>
                @else
                    <p class="blade-muted">This role can view documents but cannot submit new managed documents.</p>
                @endif
            </article>

            <article class="blade-dashboard-card">
                <div class="blade-dashboard-section-title">
                    <div>
                        <span class="blade-dashboard-label">Controls</span>
                        <h2>Repository filters</h2>
                    </div>
                    <small>{{ number_format($documents->total()) }} record(s)</small>
                </div>

                <form method="GET" action="{{ route('documents.index') }}" class="blade-filter-grid blade-filter-grid-compact">
                    <label>
                        Owner type
                        <select name="owner_type">
                            <option value="">All owner types</option>
                            @foreach ($ownerTypes as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['owner_type'] ?? null) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        Category
                        <select name="document_category_id">
                            <option value="">All categories</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected(($filters['document_category_id'] ?? null) == $category->id)>{{ $category->code }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        Status
                        <select name="status">
                            <option value="">All statuses</option>
                            @foreach ($statuses as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['status'] ?? null) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        Versions
                        <select name="current_only">
                            <option value="1" @selected((string) ($filters['current_only'] ?? '1') === '1')>Current only</option>
                            <option value="0" @selected((string) ($filters['current_only'] ?? '1') === '0')>All versions</option>
                        </select>
                    </label>
                    <label>
                        Expires within days
                        <input type="number" name="expires_within_days" min="1" max="3650" value="{{ $filters['expires_within_days'] ?? '' }}">
                    </label>
                    <button type="submit" class="blade-secondary-action">Apply filters</button>
                </form>
            </article>
        </section>

        <section class="blade-dashboard-card">
            <div class="blade-dashboard-section-title">
                <div>
                    <span class="blade-dashboard-label">Repository</span>
                    <h2>Document register</h2>
                </div>
                <small>{{ $documents->firstItem() ?? 0 }}-{{ $documents->lastItem() ?? 0 }} of {{ $documents->total() }}</small>
            </div>

            <div class="blade-dashboard-table-wrap">
                <table class="blade-dashboard-table">
                    <thead>
                        <tr>
                            <th scope="col">Document</th>
                            <th scope="col">Owner</th>
                            <th scope="col">Category</th>
                            <th scope="col">Version</th>
                            <th scope="col">Expiry</th>
                            <th scope="col">Status</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($documents as $document)
                            <tr>
                                <td>
                                    <strong>{{ $document->document_number }}</strong>
                                    <span>{{ $document->title }}</span>
                                    <small>{{ $document->original_filename }} · {{ number_format((int) $document->file_size_bytes) }} bytes</small>
                                </td>
                                <td>
                                    <span>{{ $ownerTypes[$document->owner_type] ?? $document->owner_type }}</span>
                                    <small>Record #{{ $document->owner_id }}</small>
                                </td>
                                <td>
                                    <span>{{ $document->category?->code ?? 'Uncategorised' }}</span>
                                    <small>{{ $document->category?->name }}</small>
                                </td>
                                <td>
                                    <span>v{{ $document->version }}</span>
                                    <small>{{ $document->is_current ? 'Current' : 'Historical' }}</small>
                                </td>
                                <td>
                                    @if ($document->expires_on)
                                        <span>{{ $document->expires_on->format('d M Y') }}</span>
                                        @if ($document->isExpired())
                                            <small>Expired</small>
                                        @elseif ($document->isExpiringWithin(30))
                                            <small>Expiring within 30 days</small>
                                        @endif
                                    @else
                                        <span>No expiry</span>
                                    @endif
                                </td>
                                <td><span class="blade-status-pill">{{ $statuses[$document->status] ?? $document->status }}</span></td>
                                <td>
                                    @can('view', $document)
                                        <a href="{{ route('documents.download', $document) }}" class="blade-secondary-action">Download</a>
                                    @endcan
                                    @can('approve', $document)
                                        <form method="POST" action="{{ route('documents.approve', $document) }}" class="blade-inline-form">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="_return_to" value="documents.index">
                                            <input type="text" name="approval_note" placeholder="Approval note" maxlength="2000">
                                            <button type="submit" class="blade-primary-action">Approve</button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">No documents found for the selected filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $documents->links() }}
        </section>
    </div>
@endsection
