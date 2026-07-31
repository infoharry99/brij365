@extends('layouts.builder360-classic')

@section('title', 'Procurement Workspace - Builder360 ERP-CRM')

@section('content')
<div class="blade-workspace" aria-labelledby="procurement-workspace-title">
        <header class="blade-workspace-header">
            <div>
                <p class="blade-dashboard-eyebrow">Material, Store and Procurement</p>
                <h1 id="procurement-workspace-title">Procurement Workspace</h1>
                <p>
                    Workspace for vendor master, material requisitions, approval workflow,
                    stock register, low-stock visibility, pending delivery and store valuation.
                </p>
            </div>
            <nav class="blade-workspace-actions" aria-label="Workspace actions">
                <a href="{{ route('builder360.dashboard') }}">Dashboard</a>
                <a href="{{ route('procurement.dashboard') }}">Procurement dashboard</a>
                <a href="{{ route('procurement.vendors.index') }}">Vendors</a>
                <a href="{{ route('procurement.requisitions.index') }}">Requisitions</a>
                <a href="{{ route('procurement.stock-items.index') }}">Stock</a>
            </nav>
        </header>

        @if (session('status'))
            <section class="blade-alert blade-alert-success" role="status">
                {{ session('status') }}
            </section>
        @endif

        @if ($errors->any())
            <section class="blade-alert blade-alert-error" role="alert">
                <strong>Procurement action was not completed.</strong>
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
                        <span class="blade-dashboard-label">Procurement Dashboard</span>
                        <h2>Material and purchase summary</h2>
                    </div>
                    <small>Live records</small>
                </div>

                <div class="blade-dashboard-metrics">
                    <div class="blade-dashboard-metric">
                        <span>Active vendors</span>
                        <strong>{{ number_format((int) data_get($dashboard, 'summary.active_vendors', 0)) }}</strong>
                    </div>
                    <div class="blade-dashboard-metric">
                        <span>Submitted PRs</span>
                        <strong>{{ number_format((int) data_get($dashboard, 'summary.requisitions.submitted', 0)) }}</strong>
                    </div>
                    <div class="blade-dashboard-metric">
                        <span>PO total</span>
                        <strong>{{ number_format((float) data_get($dashboard, 'summary.purchase_orders.total_amount', 0), 2) }}</strong>
                    </div>
                    <div class="blade-dashboard-metric">
                        <span>Stock value</span>
                        <strong>{{ number_format((float) data_get($dashboard, 'summary.stock.stock_value', 0), 2) }}</strong>
                    </div>
                    <div class="blade-dashboard-metric">
                        <span>Low stock</span>
                        <strong>{{ number_format((int) data_get($dashboard, 'summary.stock.low_stock_items', 0)) }}</strong>
                    </div>
                    <div class="blade-dashboard-metric">
                        <span>Pending delivery</span>
                        <strong>{{ number_format((float) data_get($dashboard, 'summary.pending_delivery.amount', 0), 2) }}</strong>
                    </div>
                </div>
            </article>

            <article class="blade-dashboard-card">
                <div class="blade-dashboard-section-title">
                    <div>
                        <span class="blade-dashboard-label">Controls</span>
                        <h2>Dashboard filters</h2>
                    </div>
                    <small>Company-level</small>
                </div>

                <form method="GET" action="{{ route('procurement.dashboard') }}" class="blade-filter-grid blade-filter-grid-compact">
                    <label>
                        Project
                        <select name="project_id">
                            <option value="">All projects</option>
                            @foreach ($projects as $project)
                                <option value="{{ $project->id }}" @selected((string) ($filters['project_id'] ?? '') === (string) $project->id)>
                                    {{ $project->code }} &middot; {{ $project->name }}
                                </option>
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

                    <button type="submit" class="blade-secondary-action">Apply filters</button>
                </form>

                <p class="blade-workspace-note">
                    Dashboard figures come from vendors, purchase requisitions, purchase orders, goods receipts and stock items available to the selected company.
                </p>
            </article>
        </section>

        <section class="blade-workspace-grid">
            <article class="blade-dashboard-card">
                <div class="blade-dashboard-section-title">
                    <div>
                        <span class="blade-dashboard-label">Create</span>
                        <h2>Vendor master</h2>
                    </div>
                    <small>{{ $canCreateVendor ? 'Authorized' : 'Read only' }}</small>
                </div>

                @if ($canCreateVendor)
                    <form method="POST" action="{{ route('procurement.vendors.store') }}" class="blade-form-grid">
                        @csrf

                        <x-forms.company-context :companies="$companies" placeholder="Use my company" />

                        <label>
                            Vendor code
                            <input type="text" name="vendor_code" value="{{ old('vendor_code') }}" maxlength="40" required>
                        </label>

                        <label>
                            Vendor name
                            <input type="text" name="name" value="{{ old('name') }}" maxlength="255" required>
                        </label>

                        <label>
                            Vendor type
                            <select name="vendor_type" required>
                                @foreach ($vendorTypes as $value => $label)
                                    <option value="{{ $value }}" @selected(old('vendor_type', 'material') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label>
                            Contact person
                            <input type="text" name="contact_name" value="{{ old('contact_name') }}" maxlength="120">
                        </label>

                        <label>
                            Email
                            <input type="email" name="email" value="{{ old('email') }}" maxlength="255">
                        </label>

                        <label>
                            Phone
                            <input type="text" name="phone" value="{{ old('phone') }}" maxlength="30">
                        </label>

                        <label>
                            GSTIN
                            <input type="text" name="gstin" value="{{ old('gstin') }}" maxlength="15">
                        </label>

                        <label>
                            PAN
                            <input type="text" name="pan" value="{{ old('pan') }}" maxlength="10">
                        </label>

                        <label>
                            City
                            <input type="text" name="address[city]" value="{{ old('address.city') }}" maxlength="120">
                        </label>

                        <label>
                            State
                            <input type="text" name="address[state]" value="{{ old('address.state') }}" maxlength="120">
                        </label>

                        <button type="submit" class="blade-primary-action">Create vendor</button>
                    </form>
                @else
                    <p class="blade-workspace-note">Your role can view vendors but cannot create vendor masters.</p>
                @endif
            </article>

            <article class="blade-dashboard-card">
                <div class="blade-dashboard-section-title">
                    <div>
                        <span class="blade-dashboard-label">Submit</span>
                        <h2>Purchase requisition</h2>
                    </div>
                    <small>{{ $canCreateRequisition ? 'Authorized' : 'Read only' }}</small>
                </div>

                @if ($canCreateRequisition)
                    <form method="POST" action="{{ route('procurement.requisitions.store') }}" class="blade-form-grid">
                        @csrf

                        <label>
                            Project
                            <select name="project_id" required>
                                <option value="">Select project</option>
                                @foreach ($projects as $project)
                                    <option value="{{ $project->id }}" @selected((string) old('project_id', $projects->first()?->id) === (string) $project->id)>
                                        {{ $project->code }} &middot; {{ $project->name }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label>
                            Department
                            <input type="text" name="department" value="{{ old('department', 'Construction') }}" maxlength="120" required>
                        </label>

                        <label>
                            Required by
                            <input type="date" name="required_by" value="{{ old('required_by', now()->addDays(7)->toDateString()) }}" min="{{ now()->toDateString() }}" required>
                        </label>

                        <label>
                            Priority
                            <select name="priority" required>
                                @foreach ($priorities as $value => $label)
                                    <option value="{{ $value }}" @selected(old('priority', 'normal') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label>
                            Item code
                            <input type="text" name="items[0][item_code]" value="{{ old('items.0.item_code') }}" maxlength="80" required>
                        </label>

                        <label>
                            Description
                            <input type="text" name="items[0][description]" value="{{ old('items.0.description') }}" maxlength="255" required>
                        </label>

                        <label>
                            Unit
                            <input type="text" name="items[0][unit]" value="{{ old('items.0.unit', 'nos') }}" maxlength="40" required>
                        </label>

                        <label>
                            Quantity
                            <input type="number" name="items[0][quantity]" value="{{ old('items.0.quantity') }}" min="0.01" step="0.001" required>
                        </label>

                        <label>
                            Estimated rate
                            <input type="number" name="items[0][estimated_rate]" value="{{ old('items.0.estimated_rate') }}" min="0" step="0.01" required>
                        </label>

                        <label>
                            Purpose
                            <textarea name="purpose" maxlength="5000">{{ old('purpose') }}</textarea>
                        </label>

                        <button type="submit" class="blade-primary-action">Submit requisition</button>
                    </form>
                @else
                    <p class="blade-workspace-note">Your role can view purchase requisitions but cannot submit new requisitions.</p>
                @endif
            </article>
        </section>

        <section class="blade-dashboard-card">
            <div class="blade-dashboard-section-title">
                <div>
                    <span class="blade-dashboard-label">Controls</span>
                    <h2>Vendor filters</h2>
                </div>
                <small>{{ $vendors->total() }} vendor record(s)</small>
            </div>

            <form method="GET" action="{{ route('procurement.vendors.index') }}" class="blade-filter-grid blade-filter-grid-compact">
                <label>
                    Search
                    <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" maxlength="120" placeholder="Vendor name, code or GSTIN">
                </label>

                <label>
                    Type
                    <select name="vendor_type">
                        <option value="">All types</option>
                        @foreach ($vendorTypes as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['vendor_type'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label>
                    Status
                    <select name="status">
                        <option value="">All statuses</option>
                        @foreach ($vendorStatuses as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <button type="submit" class="blade-secondary-action">Apply filters</button>
            </form>
        </section>

        <section class="blade-dashboard-card">
            <div class="blade-dashboard-section-title">
                <div>
                    <span class="blade-dashboard-label">Register</span>
                    <h2>Vendor master</h2>
                </div>
                <small>{{ $vendors->firstItem() ?? 0 }}-{{ $vendors->lastItem() ?? 0 }} of {{ $vendors->total() }}</small>
            </div>

            <div class="blade-dashboard-table-wrap">
                <table class="blade-dashboard-table">
                    <thead>
                        <tr>
                            <th scope="col">Vendor</th>
                            <th scope="col">Type</th>
                            <th scope="col">Contact</th>
                            <th scope="col">Tax references</th>
                            <th scope="col">Status</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($vendors as $vendor)
                            <tr>
                                <td>
                                    <strong>{{ $vendor->vendor_code }}</strong>
                                    <span>{{ $vendor->name }}</span>
                                    @if ($vendorScore = ($vendorScores[$vendor->id] ?? null))
                                        <span>Performance: {{ $vendorScore->score }} / 100 · {{ str($vendorScore->band)->headline() }}</span>
                                        <span>Rule v{{ $vendorScore->ruleVersion }} · {{ $vendorScore->calculatedAt->format('d M Y H:i') }}</span>
                                    @else
                                        <span>Performance score not calculated</span>
                                    @endif
                                </td>
                                <td>{{ $vendorTypes[$vendor->vendor_type] ?? str($vendor->vendor_type)->headline() }}</td>
                                <td>
                                    <strong>{{ $vendor->contact_name ?? 'Contact pending' }}</strong>
                                    <span>{{ $vendor->phone ?? $vendor->email ?? 'No contact' }}</span>
                                </td>
                                <td>
                                    <strong>{{ $vendor->gstin ?? 'GSTIN pending' }}</strong>
                                    <span>PAN last 4: {{ $vendor->pan_last4 ?? 'NA' }}</span>
                                </td>
                                <td>{{ $vendorStatuses[$vendor->status] ?? str($vendor->status)->headline() }}</td>
                                <td>
                                    @can('update', $vendor)
                                        <details class="blade-row-actions blade-scoring-evidence">
                                            <summary>Performance evidence</summary>
                                            <form method="POST" action="{{ route('procurement.vendors.performance-score.update', $vendor) }}" class="blade-inline-form blade-scoring-evidence-form">
                                                @csrf
                                                @method('PATCH')
                                                @php
                                                    $vendorInputs = $vendor->scoring_inputs ?? [];
                                                    $vendorEvidenceFields = [
                                                        'acceptance_rate' => 'Acceptance rate',
                                                        'quality' => 'Quality',
                                                        'on_time_delivery' => 'On-time delivery',
                                                        'fulfillment' => 'Fulfillment',
                                                        'price_competitiveness' => 'Price competitiveness',
                                                        'documentation' => 'Documentation compliance',
                                                        'responsiveness' => 'Service responsiveness',
                                                        'issue_resolution' => 'Issue resolution',
                                                    ];
                                                @endphp
                                                @foreach ($vendorEvidenceFields as $evidenceKey => $evidenceLabel)
                                                    <label>
                                                        {{ $evidenceLabel }}
                                                        <input type="number" name="{{ $evidenceKey }}" value="{{ $vendorInputs[$evidenceKey] ?? '' }}" min="0" max="100" step="0.01" required>
                                                    </label>
                                                @endforeach
                                                <p class="blade-workspace-note">Enter verified values from 0 to 100. Saving recalculates the active Vendor Performance rule.</p>
                                                <button type="submit" class="blade-secondary-action">Calculate vendor score</button>
                                            </form>
                                        </details>
                                    @endcan

                                    @can('updateStatus', $vendor)
                                        <form method="POST" action="{{ route('procurement.vendors.status.update', $vendor) }}" class="blade-inline-form">
                                            @csrf
                                            @method('PATCH')
                                            <label>
                                                Status
                                                <select name="status" required>
                                                    @foreach ($vendorStatuses as $value => $label)
                                                        <option value="{{ $value }}" @selected($vendor->status === $value)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </label>
                                            <input type="text" name="reason" value="{{ old('reason') }}" maxlength="1000" placeholder="Reason required unless active" aria-label="Vendor status reason">
                                            <button type="submit" class="blade-secondary-action">Update status</button>
                                        </form>
                                    @else
                                        <span>Read only</span>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">No vendors match the selected filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="blade-pagination">
                {{ $vendors->links() }}
            </div>
        </section>

        <section class="blade-dashboard-card">
            <div class="blade-dashboard-section-title">
                <div>
                    <span class="blade-dashboard-label">Controls</span>
                    <h2>Requisition filters</h2>
                </div>
                <small>{{ $requisitions->total() }} requisition record(s)</small>
            </div>

            <form method="GET" action="{{ route('procurement.requisitions.index') }}" class="blade-filter-grid blade-filter-grid-compact">
                <label>
                    Project
                    <select name="project_id">
                        <option value="">All projects</option>
                        @foreach ($projects as $project)
                            <option value="{{ $project->id }}" @selected((string) ($filters['project_id'] ?? '') === (string) $project->id)>
                                {{ $project->code }} &middot; {{ $project->name }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label>
                    Status
                    <select name="status">
                        <option value="">All statuses</option>
                        @foreach ($requisitionStatuses as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <button type="submit" class="blade-secondary-action">Apply filters</button>
            </form>
        </section>

        <section class="blade-dashboard-card">
            <div class="blade-dashboard-section-title">
                <div>
                    <span class="blade-dashboard-label">Register</span>
                    <h2>Purchase requisitions</h2>
                </div>
                <small>{{ $requisitions->firstItem() ?? 0 }}-{{ $requisitions->lastItem() ?? 0 }} of {{ $requisitions->total() }}</small>
            </div>

            <div class="blade-dashboard-table-wrap">
                <table class="blade-dashboard-table">
                    <thead>
                        <tr>
                            <th scope="col">Requisition</th>
                            <th scope="col">Project</th>
                            <th scope="col">Need</th>
                            <th scope="col">Items</th>
                            <th scope="col">Estimate</th>
                            <th scope="col">Status</th>
                            <th scope="col">Requester / Approver</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($requisitions as $requisition)
                            @php
                                $firstItem = collect($requisition->items ?? [])->first();
                            @endphp
                            <tr>
                                <td>
                                    <strong>{{ $requisition->requisition_number }}</strong>
                                    <span>{{ str($requisition->priority)->headline() }} priority</span>
                                </td>
                                <td>
                                    <strong>{{ $requisition->project?->code ?? 'No project' }}</strong>
                                    <span>{{ $requisition->project?->name ?? 'Project missing' }}</span>
                                </td>
                                <td>
                                    <strong>{{ $requisition->department }}</strong>
                                    <span>Required {{ $requisition->required_by?->format('d M Y') }}</span>
                                </td>
                                <td>
                                    @if ($firstItem)
                                        <strong>{{ $firstItem['item_code'] ?? 'Item' }}</strong>
                                        <span>{{ $firstItem['quantity'] ?? 0 }} {{ $firstItem['unit'] ?? 'unit' }} &middot; {{ $firstItem['description'] ?? '' }}</span>
                                    @else
                                        No items
                                    @endif
                                </td>
                                <td>{{ number_format((float) $requisition->estimated_total, 2) }}</td>
                                <td>{{ $requisitionStatuses[$requisition->status] ?? str($requisition->status)->headline() }}</td>
                                <td>
                                    <strong>{{ $requisition->requestedBy?->name ?? 'Unknown' }}</strong>
                                    <span>{{ $requisition->approvedBy?->name ?? 'Approval pending' }}</span>
                                </td>
                                <td>
                                    @can('approve', $requisition)
                                        <form method="POST" action="{{ route('procurement.requisitions.approve', $requisition) }}" class="blade-inline-form">
                                            @csrf
                                            @method('PATCH')
                                            <input type="text" name="note" value="{{ old('note') }}" maxlength="1000" placeholder="Approval note" aria-label="Purchase requisition approval note">
                                            <button type="submit" class="blade-primary-action">Approve PR</button>
                                        </form>
                                    @else
                                        <span>{{ $requisition->status === 'submitted' ? 'Approval unavailable' : 'Closed' }}</span>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8">No purchase requisitions match the selected filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="blade-pagination">
                {{ $requisitions->links() }}
            </div>
        </section>

        <section class="blade-dashboard-card">
            <div class="blade-dashboard-section-title">
                <div>
                    <span class="blade-dashboard-label">Controls</span>
                    <h2>Stock filters</h2>
                </div>
                <small>{{ $stockItems->total() }} stock item(s)</small>
            </div>

            <form method="GET" action="{{ route('procurement.stock-items.index') }}" class="blade-filter-grid blade-filter-grid-compact">
                <label>
                    Project
                    <select name="project_id">
                        <option value="">All projects</option>
                        @foreach ($projects as $project)
                            <option value="{{ $project->id }}" @selected((string) ($filters['project_id'] ?? '') === (string) $project->id)>
                                {{ $project->code }} &middot; {{ $project->name }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label>
                    Store type
                    <select name="store_type">
                        <option value="">All stores</option>
                        @foreach ($storeTypes as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['store_type'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label>
                    Item code
                    <input type="search" name="item_code" value="{{ $filters['item_code'] ?? '' }}" maxlength="80">
                </label>

                <label>
                    Low stock only
                    <select name="low_stock">
                        <option value="">No</option>
                        <option value="1" @selected((string) ($filters['low_stock'] ?? '') === '1')>Yes</option>
                    </select>
                </label>

                <button type="submit" class="blade-secondary-action">Apply filters</button>
            </form>
        </section>

        <section class="blade-dashboard-card">
            <div class="blade-dashboard-section-title">
                <div>
                    <span class="blade-dashboard-label">Register</span>
                    <h2>Stock register</h2>
                </div>
                <small>{{ $stockItems->firstItem() ?? 0 }}-{{ $stockItems->lastItem() ?? 0 }} of {{ $stockItems->total() }}</small>
            </div>

            <div class="blade-dashboard-table-wrap">
                <table class="blade-dashboard-table">
                    <thead>
                        <tr>
                            <th scope="col">Item</th>
                            <th scope="col">Project</th>
                            <th scope="col">Store</th>
                            <th scope="col">On hand</th>
                            <th scope="col">Minimum</th>
                            <th scope="col">Average rate</th>
                            <th scope="col">Stock value</th>
                            <th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($stockItems as $stockItem)
                            <tr>
                                <td>
                                    <strong>{{ $stockItem->item_code }}</strong>
                                    <span>{{ $stockItem->description }}</span>
                                </td>
                                <td>
                                    <strong>{{ $stockItem->project?->code ?? 'No project' }}</strong>
                                    <span>{{ $stockItem->project?->name ?? 'Project missing' }}</span>
                                </td>
                                <td>{{ $storeTypes[$stockItem->store_type] ?? str($stockItem->store_type)->headline() }}</td>
                                <td>{{ number_format((float) $stockItem->on_hand_quantity, 3) }} {{ $stockItem->unit }}</td>
                                <td>{{ number_format((float) $stockItem->minimum_stock_quantity, 3) }} {{ $stockItem->unit }}</td>
                                <td>{{ number_format((float) $stockItem->average_rate, 4) }}</td>
                                <td>{{ number_format((float) $stockItem->stock_value, 2) }}</td>
                                <td>
                                    <strong>{{ $stockStatuses[$stockItem->status] ?? str($stockItem->status)->headline() }}</strong>
                                    @if ($stockItem->isBelowMinimum())
                                        <span>Low stock alert</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8">No stock items match the selected filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="blade-pagination">
                {{ $stockItems->links() }}
            </div>
        </section>
    </div>
@endsection
