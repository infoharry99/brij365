@extends('layouts.builder360-classic')

@section('title', 'Financial Vouchers - Builder360 ERP-CRM')

@section('content')
@php
        $money = fn ($amount) => 'Rs. '.number_format((float) ($amount ?? 0), 2);
    @endphp

    <div class="blade-workspace" aria-labelledby="financial-vouchers-title">
        <header class="blade-workspace-header">
            <div>
                <p class="blade-dashboard-eyebrow">Finance and Operations</p>
                <h1 id="financial-vouchers-title">Financial Vouchers</h1>
                <p>
                    Workspace for voucher entry, balanced debit/credit lines,
                    project/company access, approval/rejection workflow, tax summary and status transition history.
                </p>
            </div>
            <nav class="blade-workspace-actions" aria-label="Workspace actions">
                <a href="{{ route('builder360.dashboard') }}">Dashboard</a>
                <a href="{{ route('finance.dashboard') }}">Finance Dashboard</a>
                <a href="{{ route('finance.collections.index') }}">Collections</a>
                <a href="{{ route('finance.vouchers.index') }}">Reset filters</a>
            </nav>
        </header>

        @if (session('status'))
            <section class="blade-alert blade-alert-success" role="status">
                {{ session('status') }}
            </section>
        @endif

        @if ($errors->any())
            <section class="blade-alert blade-alert-error" role="alert">
                <strong>Voucher action was not saved.</strong>
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
                        <h2>Submit voucher</h2>
                    </div>
                    <small>{{ $canCreateVoucher ? 'Authorized' : 'Read only' }}</small>
                </div>

                @if ($canCreateVoucher)
                    <form method="POST" action="{{ route('finance.vouchers.store') }}" class="blade-form-grid">
                        @csrf

                        <x-forms.company-context :companies="$companies" placeholder="Use selected project or company" />

                        <label>
                            Project
                            <select name="project_id">
                                <option value="">Company-level voucher</option>
                                @foreach ($projects as $project)
                                    <option value="{{ $project->id }}" @selected((string) old('project_id') === (string) $project->id)>
                                        {{ $project->code }} - {{ $project->name }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label>
                            Voucher type
                            <select name="voucher_type" required>
                                @foreach ($voucherTypes as $value => $label)
                                    <option value="{{ $value }}" @selected(old('voucher_type', 'journal') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label>
                            Voucher date
                            <input type="date" name="voucher_date" value="{{ old('voucher_date', now()->toDateString()) }}" required>
                        </label>

                        <label>
                            Reference number
                            <input type="text" name="reference_number" value="{{ old('reference_number') }}" maxlength="120">
                        </label>

                        <label>
                            Currency
                            <input type="text" name="currency" value="{{ old('currency', 'INR') }}" maxlength="3">
                        </label>

                        <label class="blade-form-wide">
                            Narration
                            <textarea name="narration" maxlength="5000" rows="3" required>{{ old('narration') }}</textarea>
                        </label>

                        <fieldset class="blade-form-wide blade-fieldset">
                            <legend>Debit line</legend>
                            <div class="blade-form-grid">
                                <input type="hidden" name="lines[0][line_type]" value="debit">
                                <label>
                                    Account code
                                    <input type="text" name="lines[0][account_code]" value="{{ old('lines.0.account_code', 'EXPENSE') }}" maxlength="64" required>
                                </label>
                                <label>
                                    Account name
                                    <input type="text" name="lines[0][account_name]" value="{{ old('lines.0.account_name', 'Expense Account') }}" maxlength="255" required>
                                </label>
                                <label>
                                    Amount
                                    <input type="number" name="lines[0][amount]" value="{{ old('lines.0.amount') }}" min="0.01" step="0.01" required>
                                </label>
                                <label>
                                    Project
                                    <select name="lines[0][project_id]">
                                        <option value="">Use voucher project</option>
                                        @foreach ($projects as $project)
                                            <option value="{{ $project->id }}" @selected((string) old('lines.0.project_id') === (string) $project->id)>{{ $project->code }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label>
                                    Tax rate %
                                    <input type="number" name="lines[0][tax_rate]" value="{{ old('lines.0.tax_rate', 0) }}" min="0" max="100" step="0.01">
                                </label>
                                <label>
                                    Tax amount
                                    <input type="number" name="lines[0][tax_amount]" value="{{ old('lines.0.tax_amount', 0) }}" min="0" step="0.01">
                                </label>
                            </div>
                        </fieldset>

                        <fieldset class="blade-form-wide blade-fieldset">
                            <legend>Credit line</legend>
                            <div class="blade-form-grid">
                                <input type="hidden" name="lines[1][line_type]" value="credit">
                                <label>
                                    Account code
                                    <input type="text" name="lines[1][account_code]" value="{{ old('lines.1.account_code', 'BANK') }}" maxlength="64" required>
                                </label>
                                <label>
                                    Account name
                                    <input type="text" name="lines[1][account_name]" value="{{ old('lines.1.account_name', 'Bank Account') }}" maxlength="255" required>
                                </label>
                                <label>
                                    Amount
                                    <input type="number" name="lines[1][amount]" value="{{ old('lines.1.amount') }}" min="0.01" step="0.01" required>
                                </label>
                                <label>
                                    Project
                                    <select name="lines[1][project_id]">
                                        <option value="">Use voucher project</option>
                                        @foreach ($projects as $project)
                                            <option value="{{ $project->id }}" @selected((string) old('lines.1.project_id') === (string) $project->id)>{{ $project->code }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="blade-form-wide">
                                    Description
                                    <input type="text" name="lines[1][description]" value="{{ old('lines.1.description') }}" maxlength="1000">
                                </label>
                            </div>
                        </fieldset>

                        <button type="submit" class="blade-primary-action">Submit voucher</button>
                    </form>
                @else
                    <p class="blade-workspace-note">Your role can view vouchers but cannot create new vouchers.</p>
                @endif
            </article>

            <article class="blade-dashboard-card">
                <div class="blade-dashboard-section-title">
                    <div>
                        <span class="blade-dashboard-label">Controls</span>
                        <h2>Voucher filters</h2>
                    </div>
                    <small>{{ $vouchers->total() }} record(s)</small>
                </div>

                <form method="GET" action="{{ route('finance.vouchers.index') }}" class="blade-filter-grid blade-filter-grid-compact">
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
                        <select name="voucher_type">
                            <option value="">All types</option>
                            @foreach ($voucherTypes as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['voucher_type'] ?? '') === $value)>{{ $label }}</option>
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
                        From
                        <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
                    </label>
                    <label>
                        To
                        <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
                    </label>
                    <label>
                        Search
                        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="120" placeholder="Voucher, ref, narration">
                    </label>
                    <button type="submit" class="blade-secondary-action">Apply filters</button>
                </form>

                <p class="blade-workspace-note">
                    Voucher submission and approval use the configured finance workflow. Debit and credit totals must balance.
                </p>
            </article>
        </section>

        <section class="blade-dashboard-card">
            <div class="blade-dashboard-section-title">
                <div>
                    <span class="blade-dashboard-label">Register</span>
                    <h2>Voucher register</h2>
                </div>
                <small>{{ $vouchers->firstItem() ?? 0 }}-{{ $vouchers->lastItem() ?? 0 }} of {{ $vouchers->total() }}</small>
            </div>

            <div class="blade-dashboard-table-wrap">
                <table class="blade-dashboard-table">
                    <thead>
                        <tr>
                            <th scope="col">Voucher</th>
                            <th scope="col">Scope</th>
                            <th scope="col">Commercials</th>
                            <th scope="col">Lines</th>
                            <th scope="col">Workflow</th>
                            <th scope="col">Status</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($vouchers as $voucher)
                            <tr>
                                <td>
                                    <strong>{{ $voucher->voucher_number }}</strong>
                                    <span>{{ $voucherTypes[$voucher->voucher_type] ?? str($voucher->voucher_type)->headline() }}</span>
                                    <span>{{ $voucher->voucher_date?->format('d M Y') ?? 'Date pending' }}</span>
                                    <span>{{ $voucher->reference_number ?? 'No reference' }}</span>
                                </td>
                                <td>
                                    <strong>{{ $voucher->company?->code ?? 'Company missing' }}</strong>
                                    <span>{{ $voucher->project?->code ?? 'Company level' }}</span>
                                </td>
                                <td>
                                    <strong>Debit {{ $money($voucher->total_debit) }}</strong>
                                    <span>Credit {{ $money($voucher->total_credit) }}</span>
                                    <span>Tax {{ $money($voucher->tax_summary['total_tax_amount'] ?? 0) }}</span>
                                </td>
                                <td>
                                    @forelse ($voucher->lines as $line)
                                        <span>
                                            {{ $line->line_number }}.
                                            {{ str($line->line_type)->headline() }}
                                            {{ $line->account_code }} -
                                            {{ $money($line->amount) }}
                                        </span>
                                    @empty
                                        <span>No lines</span>
                                    @endforelse
                                </td>
                                <td>
                                    <strong>Created by {{ $voucher->createdBy?->name ?? 'User missing' }}</strong>
                                    <span>Approved by {{ $voucher->approvedBy?->name ?? 'Pending' }}</span>
                                    <span>{{ $voucher->approved_at?->format('d M Y H:i') ?? $voucher->rejected_at?->format('d M Y H:i') ?? 'Decision pending' }}</span>
                                </td>
                                <td>{{ $statuses[$voucher->status] ?? str($voucher->status)->headline() }}</td>
                                <td>
                                    @can('approve', $voucher)
                                        <details class="blade-row-actions">
                                            <summary>Approve</summary>
                                            <form method="POST" action="{{ route('finance.vouchers.approve', $voucher) }}" class="blade-inline-form">
                                                @csrf
                                                @method('PATCH')
                                                <textarea name="note" maxlength="1000" rows="2" placeholder="Approval note"></textarea>
                                                <button type="submit" class="blade-primary-action">Approve voucher</button>
                                            </form>
                                        </details>
                                    @endcan

                                    @can('reject', $voucher)
                                        <details class="blade-row-actions">
                                            <summary>Reject</summary>
                                            <form method="POST" action="{{ route('finance.vouchers.reject', $voucher) }}" class="blade-inline-form">
                                                @csrf
                                                @method('PATCH')
                                                <textarea name="reason" required maxlength="1000" rows="2" placeholder="Rejection reason"></textarea>
                                                <button type="submit" class="blade-secondary-action">Reject voucher</button>
                                            </form>
                                        </details>
                                    @endcan

                                    @cannot('approve', $voucher)
                                        @cannot('reject', $voucher)
                                            <span>No action</span>
                                        @endcannot
                                    @endcannot
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">No vouchers match the selected filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="blade-pagination">
                {{ $vouchers->links() }}
            </div>
        </section>
    </div>
@endsection
