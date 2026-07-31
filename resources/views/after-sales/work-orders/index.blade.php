@extends('layouts.builder360-classic')

@section('title', 'Maintenance Work Orders - Builder360 ERP-CRM')

@section('content')
@php
        $workOrderCount = $workOrders->total();
        $scheduledCount = $workOrders->getCollection()->where('status', 'scheduled')->count();
        $plannedCount = $workOrders->getCollection()->where('status', 'planned')->count();
        $completedCount = $workOrders->getCollection()->where('status', 'completed')->count();
    @endphp

    <div class="blade-workspace" aria-labelledby="after-sales-work-orders-title">
        <header class="blade-workspace-header">
            <div>
                <p class="blade-dashboard-eyebrow">After-Sales & Maintenance</p>
                <h1 id="after-sales-work-orders-title">Maintenance Work Orders</h1>
                <p>
                    Workspace for creating, scheduling, assigning and completing maintenance
                    work orders linked to service tickets and project units.
                </p>
            </div>
            <nav class="blade-workspace-actions" aria-label="Workspace actions">
                <a href="{{ url('/') }}">Dashboard</a>
                <a href="{{ route('after-sales.tickets.index') }}">Service Tickets</a>
                <a href="{{ route('maintenance.handover-items.index') }}">Common Area Handover</a>
                <a href="{{ route('after-sales.work-orders.index') }}">Reset filters</a>
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

        <section class="blade-dashboard-kpis" aria-label="Work order KPIs">
            <article class="blade-dashboard-kpi">
                <span>Total Work Orders</span>
                <strong>{{ number_format($workOrderCount) }}</strong>
                <small>Work order register</small>
            </article>
            <article class="blade-dashboard-kpi">
                <span>Planned</span>
                <strong>{{ number_format($plannedCount) }}</strong>
                <small>Current page</small>
            </article>
            <article class="blade-dashboard-kpi">
                <span>Scheduled</span>
                <strong>{{ number_format($scheduledCount) }}</strong>
                <small>Current page</small>
            </article>
            <article class="blade-dashboard-kpi">
                <span>Completed</span>
                <strong>{{ number_format($completedCount) }}</strong>
                <small>Current page</small>
            </article>
        </section>

        <section class="blade-workspace-grid">
            <article class="blade-dashboard-card">
                <div class="blade-dashboard-section-title">
                    <div>
                        <span class="blade-dashboard-label">Create</span>
                        <h2>Create maintenance work order</h2>
                    </div>
                    <small>{{ $canCreateWorkOrder ? 'Authorized' : 'Read only' }}</small>
                </div>

                @if ($canCreateWorkOrder)
                    <form method="POST" action="{{ route('after-sales.work-orders.store') }}" class="blade-form-grid">
                        @csrf
                        <label class="blade-form-wide">
                            Active service ticket
                            <select name="service_ticket_id" required>
                                <option value="">Select open ticket</option>
                                @foreach ($tickets as $ticket)
                                    <option value="{{ $ticket->id }}" @selected((int) old('service_ticket_id') === (int) $ticket->id)>
                                        {{ $ticket->ticket_number }} · {{ $ticket->subject }}
                                        @if ($ticket->unit)
                                            · {{ $ticket->unit->unit_code }}
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label>
                            Assign to
                            <select name="assigned_to_user_id">
                                <option value="">Use ticket assignee</option>
                                @foreach ($assignees as $assignee)
                                    <option value="{{ $assignee->id }}" @selected((int) old('assigned_to_user_id') === (int) $assignee->id)>
                                        {{ $assignee->name }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label>
                            Vendor
                            <select name="vendor_id">
                                <option value="">Internal team</option>
                                @foreach ($vendors as $vendor)
                                    <option value="{{ $vendor->id }}" @selected((int) old('vendor_id') === (int) $vendor->id)>
                                        {{ $vendor->vendor_code }} · {{ $vendor->name }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label>
                            Scheduled on
                            <input type="date" name="scheduled_on" value="{{ old('scheduled_on', now()->addDay()->toDateString()) }}">
                        </label>

                        <label>
                            Estimated cost
                            <input type="number" name="estimated_cost" value="{{ old('estimated_cost', 0) }}" min="0" step="0.01">
                        </label>

                        <label class="blade-form-wide">
                            Scope of work
                            <textarea name="scope_of_work" rows="4" required>{{ old('scope_of_work') }}</textarea>
                        </label>

                        <button type="submit" class="blade-primary-action">Create work order</button>
                    </form>
                @else
                    <p class="blade-muted">You can view maintenance work orders but cannot create them from this role.</p>
                @endif
            </article>

            <article class="blade-dashboard-card">
                <div class="blade-dashboard-section-title">
                    <div>
                        <span class="blade-dashboard-label">Controls</span>
                        <h2>Work-order filters</h2>
                    </div>
                    <small>{{ number_format($workOrderCount) }} record(s)</small>
                </div>

                <form method="GET" action="{{ route('after-sales.work-orders.index') }}" class="blade-filter-grid blade-filter-grid-compact">
                    <label>
                        Service ticket
                        <select name="service_ticket_id">
                            <option value="">All tickets</option>
                            @foreach ($tickets as $ticket)
                                <option value="{{ $ticket->id }}" @selected(($filters['service_ticket_id'] ?? null) == $ticket->id)>
                                    {{ $ticket->ticket_number }} · {{ $ticket->subject }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        Assignee
                        <select name="assigned_to_user_id">
                            <option value="">All assignees</option>
                            @foreach ($assignees as $assignee)
                                <option value="{{ $assignee->id }}" @selected(($filters['assigned_to_user_id'] ?? null) == $assignee->id)>
                                    {{ $assignee->name }}
                                </option>
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
                    <span class="blade-dashboard-label">Register</span>
                    <h2>Maintenance work-order register</h2>
                </div>
                <small>{{ $workOrders->firstItem() ?? 0 }}-{{ $workOrders->lastItem() ?? 0 }} of {{ $workOrders->total() }}</small>
            </div>

            <div class="blade-dashboard-table-wrap">
                <table class="blade-dashboard-table">
                    <thead>
                        <tr>
                            <th scope="col">Work order</th>
                            <th scope="col">Ticket / unit</th>
                            <th scope="col">Ownership</th>
                            <th scope="col">Schedule</th>
                            <th scope="col">Cost</th>
                            <th scope="col">Status</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($workOrders as $workOrder)
                            <tr>
                                <td>
                                    <strong>{{ $workOrder->work_order_number }}</strong>
                                    <span>{{ $workOrder->scope_of_work }}</span>
                                </td>
                                <td>
                                    <strong>{{ $workOrder->serviceTicket?->ticket_number ?? '—' }}</strong>
                                    <span>{{ $workOrder->serviceTicket?->subject ?? '—' }}</span>
                                    <small>{{ $workOrder->unit?->unit_code ?? 'No unit' }}</small>
                                </td>
                                <td>
                                    <strong>{{ $workOrder->assignedTo?->name ?? 'Unassigned' }}</strong>
                                    <span>{{ $workOrder->vendor?->name ?? 'Internal team' }}</span>
                                </td>
                                <td>
                                    <strong>{{ $workOrder->scheduled_on?->format('d M Y') ?? 'Not scheduled' }}</strong>
                                    <span>Completed {{ $workOrder->completed_at?->format('d M Y H:i') ?? '—' }}</span>
                                </td>
                                <td>
                                    <strong>₹{{ number_format((float) $workOrder->estimated_cost, 2) }}</strong>
                                    <span>Actual ₹{{ number_format((float) $workOrder->actual_cost, 2) }}</span>
                                </td>
                                <td><span class="blade-status-pill">{{ $statuses[$workOrder->status] ?? ucfirst($workOrder->status) }}</span></td>
                                <td>
                                    @can('complete', $workOrder)
                                        <form method="POST" action="{{ route('after-sales.work-orders.complete', $workOrder) }}" class="blade-inline-form">
                                            @csrf
                                            @method('PATCH')
                                            <input type="text" name="completion_notes" placeholder="Completion notes" required>
                                            <input type="number" name="actual_cost" value="{{ $workOrder->estimated_cost }}" min="0" step="0.01">
                                            <button type="submit">Complete</button>
                                        </form>
                                    @else
                                        <span class="blade-muted">No action</span>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">No maintenance work orders match the selected filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="blade-pagination">{{ $workOrders->links() }}</div>
        </section>
    </div>
@endsection
