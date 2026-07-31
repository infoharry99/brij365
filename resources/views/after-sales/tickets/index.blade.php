@extends('layouts.builder360-classic')

@section('title', 'After-Sales Tickets - Builder360 ERP-CRM')

@section('content')
@php
        $ticketCount = $tickets->total();
        $openCount = $tickets->getCollection()->whereIn('status', ['open', 'assigned', 'in_progress'])->count();
        $criticalCount = $tickets->getCollection()->where('priority', 'critical')->count();
        $overdueCount = $tickets->getCollection()
            ->filter(fn ($ticket) => in_array($ticket->status, ['open', 'assigned', 'in_progress'], true) && $ticket->sla_due_at && $ticket->sla_due_at->isPast())
            ->count();
    @endphp

    <div class="blade-workspace" aria-labelledby="after-sales-tickets-title">
        <header class="blade-workspace-header">
            <div>
                <p class="blade-dashboard-eyebrow">After-Sales & Maintenance</p>
                <h1 id="after-sales-tickets-title">Service Ticket SLA Workspace</h1>
                <p>
                    Workspace for complaint capture, buyer scoping, SLA monitoring,
                    assignment, resolution, closure and after-sales activity history.
                </p>
            </div>
            <nav class="blade-workspace-actions" aria-label="Workspace actions">
                <a href="{{ url('/') }}">Dashboard</a>
                <a href="{{ route('after-sales.work-orders.index') }}">Work Orders</a>
                <a href="{{ route('maintenance.dues.index') }}">Maintenance Dues</a>
                <a href="{{ route('after-sales.tickets.index') }}">Reset filters</a>
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

        <section class="blade-dashboard-kpis" aria-label="Ticket KPIs">
            <article class="blade-dashboard-kpi">
                <span>Total Tickets</span>
                <strong>{{ number_format($ticketCount) }}</strong>
                <small>Ticket register</small>
            </article>
            <article class="blade-dashboard-kpi">
                <span>Active</span>
                <strong>{{ number_format($openCount) }}</strong>
                <small>Current page</small>
            </article>
            <article class="blade-dashboard-kpi">
                <span>Critical</span>
                <strong>{{ number_format($criticalCount) }}</strong>
                <small>Current page</small>
            </article>
            <article class="blade-dashboard-kpi">
                <span>SLA Overdue</span>
                <strong>{{ number_format($overdueCount) }}</strong>
                <small>Current page</small>
            </article>
        </section>

        <section class="blade-workspace-grid">
            <article class="blade-dashboard-card">
                <div class="blade-dashboard-section-title">
                    <div>
                        <span class="blade-dashboard-label">Create</span>
                        <h2>Raise service ticket</h2>
                    </div>
                    <small>{{ $canCreateTicket ? 'Authorized' : 'Read only' }}</small>
                </div>

                @if ($canCreateTicket)
                    <form method="POST" action="{{ route('after-sales.tickets.store') }}" class="blade-form-grid">
                        @csrf
                        <label class="blade-form-wide">
                            Confirmed booking / unit
                            <select name="booking_id" required>
                                <option value="">Select confirmed booking</option>
                                @foreach ($bookings as $booking)
                                    <option value="{{ $booking->id }}" @selected((int) old('booking_id') === (int) $booking->id)>
                                        {{ $booking->booking_code }}
                                        @if ($booking->unit)
                                            · {{ $booking->unit->unit_code }}
                                        @endif
                                        @if ($booking->customer)
                                            · {{ $booking->customer->name }}
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label>
                            Category
                            <select name="category" required>
                                @foreach ($categories as $value => $label)
                                    <option value="{{ $value }}" @selected(old('category', 'maintenance') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label>
                            Priority
                            <select name="priority" required>
                                @foreach ($priorities as $value => $label)
                                    <option value="{{ $value }}" @selected(old('priority', 'medium') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>

                        @unless ($isBuyerPortalUser)
                            <label>
                                Source
                                <select name="source">
                                    @foreach ($sources as $value => $label)
                                        <option value="{{ $value }}" @selected(old('source', 'phone') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                        @endunless

                        <label class="blade-form-wide">
                            Subject
                            <input type="text" name="subject" value="{{ old('subject') }}" maxlength="255" required>
                        </label>

                        <label class="blade-form-wide">
                            Description
                            <textarea name="description" rows="4" required>{{ old('description') }}</textarea>
                        </label>

                        <button type="submit" class="blade-primary-action">Raise ticket</button>
                    </form>
                @else
                    <p class="blade-muted">You can view tickets but cannot create new after-sales complaints from this role.</p>
                @endif
            </article>

            <article class="blade-dashboard-card">
                <div class="blade-dashboard-section-title">
                    <div>
                        <span class="blade-dashboard-label">Controls</span>
                        <h2>Ticket filters</h2>
                    </div>
                    <small>{{ number_format($ticketCount) }} record(s)</small>
                </div>

                <form method="GET" action="{{ route('after-sales.tickets.index') }}" class="blade-filter-grid blade-filter-grid-compact">
                    @unless ($isBuyerPortalUser)
                        <label>
                            Project
                            <select name="project_id">
                                <option value="">All projects</option>
                                @foreach ($projects as $project)
                                    <option value="{{ $project->id }}" @selected(($filters['project_id'] ?? null) == $project->id)>
                                        {{ $project->code }} · {{ $project->name }}
                                    </option>
                                @endforeach
                            </select>
                        </label>
                        <label>
                            Customer
                            <select name="customer_id">
                                <option value="">All customers</option>
                                @foreach ($customers as $customer)
                                    <option value="{{ $customer->id }}" @selected(($filters['customer_id'] ?? null) == $customer->id)>
                                        {{ $customer->code }} · {{ $customer->name }}
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
                    @endunless
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
                        Priority
                        <select name="priority">
                            <option value="">All priorities</option>
                            @foreach ($priorities as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['priority'] ?? null) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        Category
                        <select name="category">
                            <option value="">All categories</option>
                            @foreach ($categories as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['category'] ?? null) === $value)>{{ $label }}</option>
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
                    <h2>Ticket SLA register</h2>
                </div>
                <small>{{ $tickets->firstItem() ?? 0 }}-{{ $tickets->lastItem() ?? 0 }} of {{ $tickets->total() }}</small>
            </div>

            <div class="blade-dashboard-table-wrap">
                <table class="blade-dashboard-table">
                    <thead>
                        <tr>
                            <th scope="col">Ticket</th>
                            <th scope="col">Customer / unit</th>
                            <th scope="col">Category</th>
                            <th scope="col">SLA</th>
                            <th scope="col">Workflow</th>
                            <th scope="col">Status</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($tickets as $ticket)
                            <tr>
                                <td>
                                    <strong>{{ $ticket->ticket_number }}</strong>
                                    <span>{{ $ticket->subject }}</span>
                                    <small>{{ $ticket->source }} · raised by {{ $ticket->raisedBy?->name ?? '—' }}</small>
                                </td>
                                <td>
                                    <strong>{{ $ticket->customer?->name ?? '—' }}</strong>
                                    <span>{{ $ticket->booking?->booking_code ?? '—' }}</span>
                                    <small>{{ $ticket->unit?->unit_code ?? 'No unit' }}</small>
                                </td>
                                <td>
                                    <strong>{{ $categories[$ticket->category] ?? ucfirst($ticket->category) }}</strong>
                                    <span>{{ $priorities[$ticket->priority] ?? ucfirst($ticket->priority) }}</span>
                                </td>
                                <td>
                                    <strong>{{ $ticket->sla_due_at?->format('d M Y H:i') ?? '—' }}</strong>
                                    <span>
                                        @if (in_array($ticket->status, ['open', 'assigned', 'in_progress'], true) && $ticket->sla_due_at?->isPast())
                                            Overdue
                                        @else
                                            {{ $ticket->first_response_due_at?->format('d M Y H:i') ?? '—' }}
                                        @endif
                                    </span>
                                </td>
                                <td>
                                    <strong>{{ $ticket->assignedTo?->name ?? 'Unassigned' }}</strong>
                                    <span>{{ count($ticket->workOrders ?? []) }} work order(s)</span>
                                    <small>{{ count($ticket->workflow_history ?? []) }} event(s)</small>
                                </td>
                                <td><span class="blade-status-pill">{{ $statuses[$ticket->status] ?? ucfirst($ticket->status) }}</span></td>
                                <td>
                                    <div class="blade-row-actions">
                                        @can('assign', $ticket)
                                            <form method="POST" action="{{ route('after-sales.tickets.assign', $ticket) }}" class="blade-inline-form">
                                                @csrf
                                                @method('PATCH')
                                                <select name="assigned_to_user_id" required>
                                                    <option value="">Assignee</option>
                                                    @foreach ($assignees as $assignee)
                                                        <option value="{{ $assignee->id }}">{{ $assignee->name }}</option>
                                                    @endforeach
                                                </select>
                                                <input type="text" name="note" placeholder="Assignment note">
                                                <button type="submit">Assign</button>
                                            </form>
                                        @endcan

                                        @can('resolve', $ticket)
                                            <form method="POST" action="{{ route('after-sales.tickets.resolve', $ticket) }}" class="blade-inline-form">
                                                @csrf
                                                @method('PATCH')
                                                <input type="text" name="resolution_summary" placeholder="Resolution summary" required>
                                                <button type="submit">Resolve</button>
                                            </form>
                                        @endcan

                                        @can('close', $ticket)
                                            <form method="POST" action="{{ route('after-sales.tickets.close', $ticket) }}" class="blade-inline-form">
                                                @csrf
                                                @method('PATCH')
                                                <input type="number" name="customer_rating" min="1" max="5" placeholder="Rating">
                                                <input type="text" name="note" placeholder="Closure note">
                                                <button type="submit">Close</button>
                                            </form>
                                        @endcan

                                        @if (! auth()->user()->can('assign', $ticket) && ! auth()->user()->can('resolve', $ticket) && ! auth()->user()->can('close', $ticket))
                                            <span class="blade-muted">No action</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">No after-sales tickets match the selected filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="blade-pagination">{{ $tickets->links() }}</div>
        </section>
    </div>
@endsection
