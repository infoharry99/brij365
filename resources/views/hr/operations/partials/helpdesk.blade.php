<section class="people-ops-grid is-wide-left" aria-label="HR helpdesk controls">
    <article class="people-ops-panel" id="helpdesk-form">
        <header class="people-ops-panel-head">
            <div><h2>Raise HR helpdesk ticket</h2><p>Submit a governed employee support request to the authorized HR team.</p></div>
        </header>
        <div class="people-ops-panel-body">
            @if ($abilities['canCreateTicket'])
                <form method="POST" action="{{ route('hr.helpdesk-tickets.store') }}" class="people-form-grid" x-data="serverFormState" x-on:submit="beginSubmit" x-bind:aria-busy="busyAria" data-idle-label="Raise HR ticket" data-busy-label="Submitting…">
                    @csrf
                    <label class="people-field">
                        <span>Employee</span>
                        <select class="people-control" name="employee_id" required>
                            <option value="">Select employee</option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}" @selected((string) old('employee_id') === (string) $employee->id)>{{ $employee->employee_code }} - {{ $employee->name }}{{ $employee->department ? ' / '.$employee->department : '' }}</option>
                            @endforeach
                        </select>
                        @error('employee_id')<small class="people-field-error">{{ $message }}</small>@enderror
                    </label>
                    <label class="people-field">
                        <span>Category</span>
                        <select class="people-control" name="category" required>
                            <option value="">Select category</option>
                            @foreach ($helpdeskCategories as $category)
                                <option value="{{ $category }}" @selected(old('category') === $category)>{{ str($category)->replace('_', ' ')->title() }}</option>
                            @endforeach
                        </select>
                        @error('category')<small class="people-field-error">{{ $message }}</small>@enderror
                    </label>
                    <label class="people-field">
                        <span>Priority</span>
                        <select class="people-control" name="priority" required>
                            @foreach ($helpdeskPriorities as $priority)
                                <option value="{{ $priority }}" @selected(old('priority', 'medium') === $priority)>{{ ucfirst($priority) }}</option>
                            @endforeach
                        </select>
                        @error('priority')<small class="people-field-error">{{ $message }}</small>@enderror
                    </label>
                    <label class="people-field is-wide">
                        <span>Subject</span>
                        <input class="people-control" name="subject" value="{{ old('subject') }}" maxlength="255" required placeholder="Short support request summary">
                        @error('subject')<small class="people-field-error">{{ $message }}</small>@enderror
                    </label>
                    <label class="people-field is-wide">
                        <span>Description</span>
                        <textarea class="people-control" name="description" rows="4" minlength="10" maxlength="5000" required placeholder="Describe the issue and the outcome you need">{{ old('description') }}</textarea>
                        @error('description')<small class="people-field-error">{{ $message }}</small>@enderror
                    </label>
                    <div class="people-modal-actions is-wide"><button type="submit" class="people-button is-primary" x-bind:disabled="busy"><span x-text="submitLabel">Raise HR ticket</span></button></div>
                </form>
            @else
                <div class="people-ops-empty"><i class="fa-solid fa-lock" aria-hidden="true"></i><strong>Ticket creation unavailable</strong><span>Your role can review authorized tickets but cannot raise a support request.</span></div>
            @endif
        </div>
    </article>

    <article class="people-ops-panel">
        <header class="people-ops-panel-head"><div><h2>Ticket filters</h2><p>Filter the authorized register without changing company scope.</p></div></header>
        <div class="people-ops-panel-body">
            <form method="GET" action="{{ route('hr.helpdesk-tickets.index') }}" class="people-form-grid">
                <label class="people-field"><span>Employee</span><select class="people-control" name="employee_id"><option value="">All employees</option>@foreach($employees as $employee)<option value="{{ $employee->id }}" @selected((string) request('employee_id') === (string) $employee->id)>{{ $employee->employee_code }} - {{ $employee->name }}</option>@endforeach</select></label>
                <label class="people-field"><span>Status</span><select class="people-control" name="status"><option value="">All statuses</option>@foreach($helpdeskStatuses as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></label>
                <label class="people-field"><span>Category</span><select class="people-control" name="category"><option value="">All categories</option>@foreach($helpdeskCategories as $category)<option value="{{ $category }}" @selected(request('category') === $category)>{{ str($category)->replace('_', ' ')->title() }}</option>@endforeach</select></label>
                <label class="people-field"><span>Priority</span><select class="people-control" name="priority"><option value="">All priorities</option>@foreach($helpdeskPriorities as $priority)<option value="{{ $priority }}" @selected(request('priority') === $priority)>{{ ucfirst($priority) }}</option>@endforeach</select></label>
                <div class="people-modal-actions is-wide"><button class="people-button" type="submit">Apply filters</button><a class="people-button" href="{{ route('hr.helpdesk-tickets.index') }}">Clear</a></div>
            </form>
        </div>
    </article>
</section>

<section class="people-ops-panel has-mobile-cards" aria-labelledby="hr-helpdesk-title">
    <header class="people-ops-panel-head"><div><h2 id="hr-helpdesk-title">HR helpdesk tickets</h2><p>{{ $tickets->total() }} ticket{{ $tickets->total() === 1 ? '' : 's' }} match the selected filters.</p></div></header>
    <div class="people-ops-table-wrap">
        <table class="people-ops-table">
            <caption>HR helpdesk ticket register</caption>
            <thead><tr><th scope="col">Ticket</th><th scope="col">Employee</th><th scope="col">Category / priority</th><th scope="col">Status / owner</th><th scope="col">Timing</th><th scope="col">Resolution / history</th><th scope="col" class="is-actions">Action</th></tr></thead>
            <tbody>
                @forelse ($tickets as $ticket)
                    <tr>
                        <td><strong>{{ $ticket->ticketNumber }} / {{ $ticket->subject }}</strong><small>{{ $ticket->description }}</small></td>
                        <td><div class="people-ops-identity"><span class="people-avatar">{{ $ticket->employeeInitial }}</span><div><strong>{{ $ticket->employeeName }}</strong><small>{{ $ticket->employeeCode }} / {{ $ticket->employeeContext }}</small></div></div></td>
                        <td>{{ $ticket->categoryLabel }}<small><span class="people-status is-{{ $ticket->priorityTone }}">{{ $ticket->priorityLabel }}</span></small></td>
                        <td><span class="people-status is-{{ $ticket->statusTone }}">{{ $ticket->statusLabel }}</span><small>Raised by: {{ $ticket->raisedBy }}</small><small>Assigned to: {{ $ticket->assignedTo }}</small></td>
                        <td>{{ $ticket->createdAt }}<small>Resolved: {{ $ticket->resolvedAt }}</small><small>Closed: {{ $ticket->closedAt }}</small></td>
                        <td><strong>{{ $ticket->resolutionSummary }}</strong>@if($ticket->attachmentCount)<small>{{ $ticket->attachmentCount }} attachment{{ $ticket->attachmentCount === 1 ? '' : 's' }} recorded: {{ implode(', ', $ticket->attachmentNames) }}</small>@else<small>No attachments recorded</small>@endif<small>{{ $ticket->workflowNote }} / {{ $ticket->workflowActor }} / {{ $ticket->workflowAt }}</small></td>
                        <td class="is-actions">@include('hr.operations.partials.helpdesk-actions', ['ticket' => $ticket])</td>
                    </tr>
                @empty
                    <tr><td colspan="7"><div class="people-ops-empty"><i class="fa-solid fa-headset" aria-hidden="true"></i><strong>No HR helpdesk tickets found</strong><span>Clear the filters or raise a new ticket when permitted.</span></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="people-ops-mobile-list">
        @forelse ($tickets as $ticket)
            <article class="people-ops-mobile-card">
                <div class="people-ops-mobile-card-head"><strong>{{ $ticket->ticketNumber }} / {{ $ticket->subject }}</strong><span class="people-status is-{{ $ticket->statusTone }}">{{ $ticket->statusLabel }}</span></div>
                <dl class="people-ops-mobile-facts"><div><dt>Employee</dt><dd>{{ $ticket->employeeName }} / {{ $ticket->employeeCode }}</dd></div><div><dt>Category / priority</dt><dd>{{ $ticket->categoryLabel }} / {{ $ticket->priorityLabel }}</dd></div><div><dt>Raised / assigned</dt><dd>{{ $ticket->raisedBy }} / {{ $ticket->assignedTo }}</dd></div><div><dt>Created</dt><dd>{{ $ticket->createdAt }}</dd></div><div><dt>Resolved</dt><dd>{{ $ticket->resolvedAt }}</dd></div><div><dt>Closed</dt><dd>{{ $ticket->closedAt }}</dd></div></dl>
                <p>{{ $ticket->description }}</p>
                <p><strong>Resolution:</strong> {{ $ticket->resolutionSummary }}</p>
                <p><strong>Evidence:</strong> {{ $ticket->attachmentCount ? implode(', ', $ticket->attachmentNames) : 'No attachments recorded' }}</p>
                <p class="people-subtext">{{ $ticket->workflowNote }} / {{ $ticket->workflowActor }} / {{ $ticket->workflowAt }}</p>
                <div class="people-ops-mobile-actions">@include('hr.operations.partials.helpdesk-actions', ['ticket' => $ticket])</div>
            </article>
        @empty
            <div class="people-ops-empty"><strong>No HR helpdesk tickets found</strong><span>Clear the filters or raise a new ticket when permitted.</span></div>
        @endforelse
    </div>
    <div class="people-pagination">{{ $tickets->withQueryString()->links() }}</div>
</section>
