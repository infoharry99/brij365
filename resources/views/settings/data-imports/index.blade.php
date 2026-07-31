@extends('layouts.builder360-classic')

@section('title', 'Data Imports - Builder360 ERP-CRM')

@section('content')
@php
        $previewCount = $batches->getCollection()->where('status', \App\Models\DataImportBatch::STATUS_PREVIEW)->count();
        $postedCount = $batches->getCollection()->where('status', \App\Models\DataImportBatch::STATUS_POSTED)->count();
        $invalidRows = $batches->getCollection()->sum('invalid_rows');
    @endphp

    <div class="blade-workspace" aria-labelledby="data-imports-title">
        <header class="blade-workspace-header">
            <div>
                <p class="blade-dashboard-eyebrow">Administration</p>
                <h1 id="data-imports-title">Data Import Center</h1>
                <p>
                    Workspace for CSV import preview, row validation,
                    duplicate/file checksum control, reconciliation summary, error reporting and controlled posting.
                </p>
            </div>
            <nav class="blade-workspace-actions" aria-label="Settings navigation">
                <a href="{{ url('/') }}">Dashboard</a>
                <a href="{{ route('settings.system-settings.index') }}">System Settings</a>
                <a href="{{ route('admin.users.index') }}">Users</a>
                <a href="{{ route('governance.audit-events.index') }}">Activity History</a>
                <a href="{{ route('settings.data-imports.index') }}">Reset filters</a>
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

        <section class="blade-dashboard-kpis" aria-label="Import KPIs">
            <article class="blade-dashboard-kpi">
                <span>Total Batches</span>
                <strong>{{ number_format($batches->total()) }}</strong>
                <small>Import register</small>
            </article>
            <article class="blade-dashboard-kpi">
                <span>Preview</span>
                <strong>{{ number_format($previewCount) }}</strong>
                <small>Awaiting post/review</small>
            </article>
            <article class="blade-dashboard-kpi">
                <span>Posted</span>
                <strong>{{ number_format($postedCount) }}</strong>
                <small>Current page</small>
            </article>
            <article class="blade-dashboard-kpi">
                <span>Invalid Rows</span>
                <strong>{{ number_format($invalidRows) }}</strong>
                <small>Current page</small>
            </article>
        </section>

        <section class="blade-workspace-grid">
            <article class="blade-dashboard-card">
                <div class="blade-dashboard-section-title">
                    <div>
                        <span class="blade-dashboard-label">Preview</span>
                        <h2>Upload CSV for validation</h2>
                    </div>
                    <small>{{ $canCreateImport ? 'Authorized' : 'Read only' }}</small>
                </div>

                @if ($canCreateImport)
                    <form method="POST" action="{{ route('settings.data-imports.preview') }}" enctype="multipart/form-data" class="blade-form-grid">
                        @csrf
                        <x-forms.company-context :companies="$companies" placeholder="Select company if required" />
                        <label>
                            Import type
                            <select name="import_type" required>
                                @foreach ($importTypes as $value => $label)
                                    <option value="{{ $value }}" @selected(old('import_type') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="blade-form-wide">
                            CSV source file
                            <input type="file" name="source_file" accept=".csv,.txt,text/csv,text/plain" required>
                            <small>CSV/TXT only. Max 512 KB. Preview validates every row before posting.</small>
                        </label>
                        <label class="blade-form-wide">
                            Note
                            <textarea name="note" rows="3" maxlength="1000">{{ old('note') }}</textarea>
                        </label>
                        <button type="submit" class="blade-primary-action">Generate preview</button>
                    </form>
                @else
                    <p class="blade-muted">This role can view imports but cannot upload new data batches.</p>
                @endif
            </article>

            <article class="blade-dashboard-card">
                <div class="blade-dashboard-section-title">
                    <div>
                        <span class="blade-dashboard-label">Controls</span>
                        <h2>Import filters</h2>
                    </div>
                    <small>{{ number_format($batches->total()) }} batch(es)</small>
                </div>

                <form method="GET" action="{{ route('settings.data-imports.index') }}" class="blade-filter-grid blade-filter-grid-compact">
                    <x-forms.company-context :companies="$companies" :selected="$filters['company_id'] ?? null" placeholder="All companies" />
                    <label>
                        Import type
                        <select name="import_type">
                            <option value="">All types</option>
                            @foreach ($importTypes as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['import_type'] ?? null) === $value)>{{ $label }}</option>
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
                    <button type="submit" class="blade-secondary-action">Apply filters</button>
                </form>
            </article>
        </section>

        <section class="blade-dashboard-card">
            <div class="blade-dashboard-section-title">
                <div>
                    <span class="blade-dashboard-label">Imports</span>
                    <h2>Import batch register</h2>
                </div>
                <small>{{ $batches->firstItem() ?? 0 }}-{{ $batches->lastItem() ?? 0 }} of {{ $batches->total() }}</small>
            </div>

            <div class="blade-dashboard-table-wrap">
                <table class="blade-dashboard-table">
                    <thead>
                        <tr>
                            <th scope="col">Batch</th>
                            <th scope="col">Rows</th>
                            <th scope="col">Reconciliation</th>
                            <th scope="col">Errors</th>
                            <th scope="col">Status</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($batches as $batch)
                            <tr>
                                <td>
                                    <strong>{{ $batch->import_number }}</strong>
                                    <span>{{ $importTypes[$batch->import_type] ?? $batch->import_type }}</span>
                                    <small>{{ $batch->source_filename }} · {{ $batch->company?->code }}</small>
                                    <small>Created by {{ $batch->createdBy?->name ?? 'System' }}</small>
                                </td>
                                <td>
                                    <span>Total: {{ number_format((int) $batch->total_rows) }}</span>
                                    <small>Valid: {{ number_format((int) $batch->valid_rows) }} · Invalid: {{ number_format((int) $batch->invalid_rows) }}</small>
                                </td>
                                <td>
                                    <small>{{ \Illuminate\Support\Str::limit(json_encode($batch->reconciliation_summary ?? [], JSON_UNESCAPED_SLASHES), 180) }}</small>
                                </td>
                                <td>
                                    @if (($batch->error_report ?? []) !== [])
                                        @foreach (collect($batch->error_report)->take(2) as $error)
                                            <small>Row {{ $error['row_number'] ?? '?' }}: {{ \Illuminate\Support\Str::limit(implode('; ', $error['errors'] ?? []), 100) }}</small>
                                        @endforeach
                                    @else
                                        <span class="blade-muted">No row errors</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="blade-status-pill">{{ $statuses[$batch->status] ?? $batch->status }}</span>
                                    @if ($batch->posted_at)
                                        <small>Posted {{ $batch->posted_at->format('d M Y, h:i A') }}</small>
                                    @endif
                                </td>
                                <td>
                                    @can('post', $batch)
                                        @if ($batch->status === \App\Models\DataImportBatch::STATUS_PREVIEW && (int) $batch->invalid_rows === 0)
                                            <form method="POST" action="{{ route('settings.data-imports.post', $batch) }}" class="blade-inline-form">
                                                @csrf
                                                <input type="text" name="note" placeholder="Posting note" maxlength="1000">
                                                <button type="submit" class="blade-primary-action">Post import</button>
                                            </form>
                                        @elseif ($batch->status === \App\Models\DataImportBatch::STATUS_PREVIEW)
                                            <span class="blade-muted">Resolve invalid rows before posting</span>
                                        @else
                                            <span class="blade-muted">No posting action</span>
                                        @endif
                                    @else
                                        <span class="blade-muted">No post access</span>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">No import batches found for the selected filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $batches->links() }}
        </section>
    </div>
@endsection
