@extends('layouts.builder360-classic')

@section('title', 'Document Categories - Builder360 ERP-CRM')

@section('content')
<div class="blade-workspace" aria-labelledby="document-categories-title">
    <header class="blade-workspace-header">
        <div>
            <p class="blade-dashboard-eyebrow">Document Management</p>
            <h1 id="document-categories-title">Document Categories</h1>
            <p>Review document ownership, expiry, reminder, and retention requirements used across the document repository.</p>
        </div>
        <nav class="blade-workspace-actions" aria-label="Document category navigation">
            <a href="{{ route('documents.index') }}">Document Repository</a>
            <a href="{{ route('documents.categories.index') }}" class="is-active" aria-current="page">Categories</a>
        </nav>
    </header>

    <section class="blade-card" aria-labelledby="category-filter-title">
        <div class="blade-card-header">
            <div>
                <p class="blade-dashboard-eyebrow">Category register</p>
                <h2 id="category-filter-title">Available categories</h2>
            </div>
            <small>{{ number_format($categories->total()) }} categor{{ $categories->total() === 1 ? 'y' : 'ies' }}</small>
        </div>

        <form method="GET" action="{{ route('documents.categories.index') }}" class="blade-filter-grid blade-filter-grid-compact">
            <label>
                Owner type
                <select name="owner_type">
                    <option value="">All owner types</option>
                    @foreach ($ownerTypes as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['owner_type'] ?? null) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <button type="submit" class="blade-secondary-action">Apply filter</button>
            <a href="{{ route('documents.categories.index') }}" class="blade-secondary-action">Reset</a>
        </form>

        <div class="blade-table-wrap">
            <table class="blade-table">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Owner</th>
                        <th>Expiry control</th>
                        <th>Reminder</th>
                        <th>Retention</th>
                        <th>Documents</th>
                        <th>Availability</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($categories as $category)
                        <tr>
                            <td><strong>{{ $category->code }}</strong><br><span>{{ $category->name }}</span></td>
                            <td>{{ $ownerTypes[$category->owner_type] ?? ucfirst($category->owner_type) }}</td>
                            <td>{{ $category->expiry_required ? 'Required' : 'Optional' }}</td>
                            <td>{{ number_format($category->reminder_days_before_expiry) }} day(s) before expiry</td>
                            <td>{{ number_format($category->retention_years) }} year(s)</td>
                            <td>{{ number_format($category->documents_count) }}</td>
                            <td>
                                <span class="blade-status-pill">
                                    {{ $category->company ? $category->company->code : 'Company-wide' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7">No document categories are available for the selected owner type.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $categories->withQueryString()->links() }}
    </section>
</div>
@endsection
