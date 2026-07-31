@extends('layouts.builder360-classic')

@section('title', 'GST Entries - Builder360 ERP-CRM')

@section('content')
@php
        $money = fn ($amount) => 'Rs. '.number_format((float) ($amount ?? 0), 2);
    @endphp

    <div class="blade-workspace" aria-labelledby="gst-entries-title">
        <header class="blade-workspace-header">
            <div>
                <p class="blade-dashboard-eyebrow">Finance and Compliance</p>
                <h1 id="gst-entries-title">GST Entry Register</h1>
                <p>
                    Workspace for GST input/output entries, period scoping,
                    maker-checker approval, locked-period protection and compliance activity history.
                </p>
            </div>
            <nav class="blade-workspace-actions" aria-label="Workspace actions">
                <a href="{{ route('builder360.dashboard') }}">Dashboard</a>
                <a href="{{ route('finance.dashboard') }}">Finance Dashboard</a>
                <a href="{{ route('finance.vouchers.index') }}">Vouchers</a>
                <a href="{{ route('finance.gst-return-periods.index') }}">Return Periods</a>
                <a href="{{ route('finance.gst-entries.index') }}">Reset filters</a>
            </nav>
        </header>

        @if (session('status'))
            <section class="blade-alert blade-alert-success" role="status">{{ session('status') }}</section>
        @endif

        @if ($errors->any())
            <section class="blade-alert blade-alert-error" role="alert">
                <strong>GST entry action was not saved.</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="blade-workspace-grid">
            <article class="blade-dashboard-card">
                <div class="blade-dashboard-section-title">
                    <div>
                        <span class="blade-dashboard-label">Create</span>
                        <h2>Submit GST entry</h2>
                    </div>
                    <small>{{ $canCreateEntry ? 'Authorized' : 'Read only' }}</small>
                </div>

                @if ($canCreateEntry)
                    <form method="POST" action="{{ route('finance.gst-entries.store') }}" class="blade-form-grid">
                        @csrf

                        <label>
                            Project
                            <select name="project_id">
                                <option value="">Company-level GST entry</option>
                                @foreach ($projects as $project)
                                    <option value="{{ $project->id }}" @selected((string) old('project_id') === (string) $project->id)>
                                        {{ $project->code }} - {{ $project->name }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label>
                            Document date
                            <input type="date" name="document_date" value="{{ old('document_date', now()->toDateString()) }}" required>
                        </label>

                        <label>
                            Document number
                            <input type="text" name="document_number" value="{{ old('document_number') }}" maxlength="80" required>
                        </label>

                        <label>
                            Transaction type
                            <select name="transaction_type" required>
                                @foreach ($transactionTypes as $value => $label)
                                    <option value="{{ $value }}" @selected(old('transaction_type', 'output') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label>
                            Party name
                            <input type="text" name="party_name" value="{{ old('party_name') }}" maxlength="180" required>
                        </label>

                        <label>
                            Party GSTIN
                            <input type="text" name="party_gstin" value="{{ old('party_gstin') }}" maxlength="20" placeholder="27AABCP9876H1Z7">
                        </label>

                        <label>
                            Place of supply state
                            <input type="text" name="place_of_supply_state" value="{{ old('place_of_supply_state', 'MH') }}" minlength="2" maxlength="2" required>
                        </label>

                        <label>
                            HSN / SAC
                            <input type="text" name="hsn_sac" value="{{ old('hsn_sac') }}" maxlength="20">
                        </label>

                        <label>
                            Taxable amount
                            <input type="number" name="taxable_amount" value="{{ old('taxable_amount') }}" min="0.01" step="0.01" required>
                        </label>

                        <label>
                            Tax rate %
                            <input type="number" name="tax_rate" value="{{ old('tax_rate', 18) }}" min="0" max="40" step="0.01" required>
                        </label>

                        <label>
                            CGST
                            <input type="number" name="cgst_amount" value="{{ old('cgst_amount', 0) }}" min="0" step="0.01">
                        </label>

                        <label>
                            SGST
                            <input type="number" name="sgst_amount" value="{{ old('sgst_amount', 0) }}" min="0" step="0.01">
                        </label>

                        <label>
                            IGST
                            <input type="number" name="igst_amount" value="{{ old('igst_amount', 0) }}" min="0" step="0.01">
                        </label>

                        <label>
                            Cess
                            <input type="number" name="cess_amount" value="{{ old('cess_amount', 0) }}" min="0" step="0.01">
                        </label>

                        <button type="submit" class="blade-primary-action">Submit GST entry</button>
                    </form>
                @else
                    <p class="blade-workspace-note">Your role can view GST entries but cannot create new entries.</p>
                @endif
            </article>

            <article class="blade-dashboard-card">
                <div class="blade-dashboard-section-title">
                    <div>
                        <span class="blade-dashboard-label">Controls</span>
                        <h2>GST filters</h2>
                    </div>
                    <small>{{ $entries->total() }} record(s)</small>
                </div>

                <form method="GET" action="{{ route('finance.gst-entries.index') }}" class="blade-filter-grid blade-filter-grid-compact">
                    <label>
                        Status
                        <select name="status">
                            <option value="">All statuses</option>
                            @foreach ($statuses as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        Type
                        <select name="transaction_type">
                            <option value="">All types</option>
                            @foreach ($transactionTypes as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['transaction_type'] ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        Project
                        <select name="project_id">
                            <option value="">All projects</option>
                            @foreach ($projects as $project)
                                <option value="{{ $project->id }}" @selected((string) ($filters['project_id'] ?? '') === (string) $project->id)>{{ $project->code }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        Year
                        <input type="number" name="period_year" value="{{ $filters['period_year'] ?? '' }}" min="2020" max="2100">
                    </label>
                    <label>
                        Month
                        <input type="number" name="period_month" value="{{ $filters['period_month'] ?? '' }}" min="1" max="12">
                    </label>
                    <label>
                        Search
                        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="120" placeholder="Entry, document, party, GSTIN">
                    </label>
                    <button type="submit" class="blade-secondary-action">Apply filters</button>
                </form>

                <p class="blade-workspace-note">
                    Component tax must match taxable amount and tax rate within configured tolerance.
                    Production filing remains subject to client-appointed tax expert validation.
                </p>
            </article>
        </section>

        <section class="blade-dashboard-card">
            <div class="blade-dashboard-section-title">
                <div>
                    <span class="blade-dashboard-label">Register</span>
                    <h2>GST entries</h2>
                </div>
                <small>{{ $entries->firstItem() ?? 0 }}-{{ $entries->lastItem() ?? 0 }} of {{ $entries->total() }}</small>
            </div>

            <div class="blade-dashboard-table-wrap">
                <table class="blade-dashboard-table">
                    <thead>
                        <tr>
                            <th scope="col">Entry</th>
                            <th scope="col">Party / document</th>
                            <th scope="col">Period / project</th>
                            <th scope="col">Tax values</th>
                            <th scope="col">Workflow</th>
                            <th scope="col">Status</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($entries as $entry)
                            <tr>
                                <td>
                                    <strong>{{ $entry->entry_number }}</strong>
                                    <span>{{ $transactionTypes[$entry->transaction_type] ?? str($entry->transaction_type)->headline() }}</span>
                                    <span>{{ $entry->document_date?->format('d M Y') ?? 'Date pending' }}</span>
                                </td>
                                <td>
                                    <strong>{{ $entry->party_name }}</strong>
                                    <span>{{ $entry->document_number }}</span>
                                    <span>{{ $entry->party_gstin ?? 'GSTIN not captured' }}</span>
                                </td>
                                <td>
                                    <strong>{{ sprintf('%04d-%02d', $entry->period_year, $entry->period_month) }}</strong>
                                    <span>{{ $entry->project?->code ?? 'Company level' }}</span>
                                    <span>State {{ $entry->place_of_supply_state }}</span>
                                </td>
                                <td>
                                    <strong>{{ $money($entry->total_tax_amount) }}</strong>
                                    <span>Taxable {{ $money($entry->taxable_amount) }}</span>
                                    <span>CGST {{ $money($entry->cgst_amount) }} / SGST {{ $money($entry->sgst_amount) }}</span>
                                    <span>IGST {{ $money($entry->igst_amount) }} / Cess {{ $money($entry->cess_amount) }}</span>
                                </td>
                                <td>
                                    <strong>Created by {{ $entry->createdBy?->name ?? 'User missing' }}</strong>
                                    <span>Approved by {{ $entry->approvedBy?->name ?? 'Pending' }}</span>
                                    <span>{{ $entry->approved_at?->format('d M Y H:i') ?? 'Decision pending' }}</span>
                                </td>
                                <td>{{ $statuses[$entry->status] ?? str($entry->status)->headline() }}</td>
                                <td>
                                    @can('approve', $entry)
                                        <details class="blade-row-actions">
                                            <summary>Approve</summary>
                                            <form method="POST" action="{{ route('finance.gst-entries.approve', $entry) }}" class="blade-inline-form">
                                                @csrf
                                                @method('PATCH')
                                                <textarea name="note" maxlength="500" rows="2" placeholder="Approval note"></textarea>
                                                <button type="submit" class="blade-primary-action">Approve GST entry</button>
                                            </form>
                                        </details>
                                    @else
                                        <span>No action</span>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">No GST entries match the selected filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="blade-pagination">{{ $entries->links() }}</div>
        </section>
    </div>
@endsection
