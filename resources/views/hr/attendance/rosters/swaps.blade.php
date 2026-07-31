@php($sourceEntries = $abilities['canManage'] ? $availableEntries : $availableEntries->filter(fn($entry) => (int) $entry->employee?->user_id === (int) auth()->id()))

@if($abilities['canRequestSwap'])
    <section class="people-ops-panel people-roster-create">
        <header class="people-ops-panel-head"><div><h2>Request shift swap</h2><p>Only published, unlocked working entries are eligible. Approval exchanges both employees atomically.</p></div><span class="people-status is-warning">Approval required</span></header>
        <form class="people-ops-panel-body people-form-grid" method="POST" action="{{ route('hr.attendance-shift-swaps.store') }}" data-disable-on-submit>
            @csrf
            <label class="people-field">Your/source entry<select class="people-control" name="source_roster_entry_id" required><option value="">Select source entry</option>@foreach($sourceEntries as $entry)<option value="{{ $entry->id }}:{{ $entry->lock_version }}">{{ $entry->work_date->format('d M Y') }} · {{ $entry->employee->name }} · {{ $entry->shift?->code }}</option>@endforeach</select></label>
            <label class="people-field">Target entry<select class="people-control" name="target_roster_entry_id" required><option value="">Select target entry</option>@foreach($availableEntries as $entry)<option value="{{ $entry->id }}:{{ $entry->lock_version }}">{{ $entry->work_date->format('d M Y') }} · {{ $entry->employee->name }} · {{ $entry->shift?->code }}</option>@endforeach</select></label>
            <label class="people-field is-wide">Reason<textarea class="people-control people-textarea" name="reason" required maxlength="2000" placeholder="Explain why this swap is needed.">{{ old('reason') }}</textarea></label>
            <div class="people-form-actions is-wide"><button class="people-button is-primary" type="submit"><i class="fa-solid fa-right-left" aria-hidden="true"></i> Submit swap request</button></div>
        </form>
    </section>
@endif

<section class="people-ops-panel has-mobile-cards">
    <header class="people-ops-panel-head"><div><h2>Shift swap requests</h2><p>Maker-checker decisions preserve both roster entries and their audit trace.</p></div><span class="people-count">{{ $swaps->total() }} requests</span></header>
    <div class="people-ops-table-wrap"><table class="people-ops-table"><caption>Attendance shift swap requests</caption><thead><tr><th scope="col">Request</th><th scope="col">Source</th><th scope="col">Target</th><th scope="col">Reason</th><th scope="col">Status</th><th scope="col">Actions</th></tr></thead><tbody>
    @forelse($swaps as $swap)
        <tr>
            <td><strong>{{ $swap->request_number }}</strong><small>{{ $swap->created_at->format('d M Y, H:i') }} · {{ $swap->requestedBy?->name }}</small></td>
            <td><strong>{{ $swap->sourceEntry?->employee?->name }}</strong><small>{{ $swap->sourceEntry?->work_date?->format('d M Y') }} · {{ $swap->sourceEntry?->shift?->code }}</small></td>
            <td><strong>{{ $swap->targetEntry?->employee?->name }}</strong><small>{{ $swap->targetEntry?->work_date?->format('d M Y') }} · {{ $swap->targetEntry?->shift?->code }}</small></td>
            <td>{{ str($swap->reason)->limit(70) }}</td>
            <td><span @class(['people-status', 'is-warning' => $swap->status === 'submitted', 'is-success' => $swap->status === 'approved', 'is-danger' => in_array($swap->status, ['rejected', 'cancelled'], true)])>{{ str($swap->status)->headline() }}</span>@if($swap->decision_note)<small>{{ $swap->decision_note }}</small>@endif</td>
            <td class="is-actions"><div class="people-roster-actions">
                @can('approve', $swap)<form method="POST" action="{{ route('hr.attendance-shift-swaps.approve', $swap) }}">@csrf @method('PATCH')<input type="hidden" name="lock_version" value="{{ $swap->lock_version }}"><button class="people-button is-primary" type="submit">Approve</button></form>@endcan
                @can('reject', $swap)<form class="people-inline-form" method="POST" action="{{ route('hr.attendance-shift-swaps.reject', $swap) }}">@csrf @method('PATCH')<input type="hidden" name="lock_version" value="{{ $swap->lock_version }}"><input class="people-control" name="decision_note" placeholder="Required rejection reason" required maxlength="2000"><button class="people-button is-danger" type="submit">Reject</button></form>@endcan
                @can('cancel', $swap)<form method="POST" action="{{ route('hr.attendance-shift-swaps.cancel', $swap) }}">@csrf @method('PATCH')<input type="hidden" name="lock_version" value="{{ $swap->lock_version }}"><button class="people-button" type="submit">Cancel</button></form>@endcan
            </div></td>
        </tr>
    @empty
        <tr><td colspan="6"><div class="people-ops-empty"><i class="fa-solid fa-right-left" aria-hidden="true"></i><strong>No shift swap requests</strong><span>Eligible employees can request a swap from published roster entries.</span></div></td></tr>
    @endforelse
    </tbody></table></div>
    <div class="people-ops-mobile-list">@foreach($swaps as $swap)<article class="people-ops-mobile-card"><header class="people-ops-mobile-card-head"><div><strong>{{ $swap->request_number }}</strong><small>{{ $swap->created_at->format('d M Y') }}</small></div><span class="people-status is-warning">{{ str($swap->status)->headline() }}</span></header><dl class="people-ops-mobile-facts"><div><dt>Source</dt><dd>{{ $swap->sourceEntry?->employee?->name }} · {{ $swap->sourceEntry?->shift?->code }}</dd></div><div><dt>Target</dt><dd>{{ $swap->targetEntry?->employee?->name }} · {{ $swap->targetEntry?->shift?->code }}</dd></div><div><dt>Reason</dt><dd>{{ $swap->reason }}</dd></div></dl></article>@endforeach</div>
    <div class="people-pagination"><span>Showing {{ $swaps->firstItem() ?? 0 }} to {{ $swaps->lastItem() ?? 0 }} of {{ $swaps->total() }}</span>{{ $swaps->links() }}</div>
</section>
