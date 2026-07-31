@if($abilities['canFinalize'])
    <section class="people-ops-panel people-roster-create">
        <header class="people-ops-panel-head"><div><h2>Finalize attendance period</h2><p>Freezes immutable per-employee payroll attendance snapshots. Pending regularizations block finalization.</p></div><span class="people-status is-warning">Payroll boundary</span></header>
        <form class="people-ops-panel-body people-form-grid" method="POST" action="{{ route('hr.attendance-periods.finalize') }}" data-disable-on-submit>
            @csrf
            <label class="people-field">Period start<input class="people-control" type="date" name="period_start" value="{{ old('period_start', now()->subMonthNoOverflow()->startOfMonth()->toDateString()) }}" required></label>
            <label class="people-field">Period end<input class="people-control" type="date" name="period_end" value="{{ old('period_end', now()->subMonthNoOverflow()->endOfMonth()->toDateString()) }}" max="{{ now()->toDateString() }}" required></label>
            <div class="people-form-actions is-wide"><button class="people-button is-primary" type="submit"><i class="fa-solid fa-lock" aria-hidden="true"></i> Finalize and snapshot</button></div>
        </form>
    </section>
@endif

<section class="people-ops-panel has-mobile-cards">
    <header class="people-ops-panel-head"><div><h2>Attendance period locks</h2><p>Reopening preserves prior snapshots and creates a governed new period version on the next finalization.</p></div><span class="people-count">{{ $periodLocks->total() }} periods</span></header>
    <div class="people-ops-table-wrap"><table class="people-ops-table"><caption>Finalized and reopened attendance periods</caption><thead><tr><th scope="col">Period</th><th scope="col">Version</th><th scope="col">Snapshots</th><th scope="col">Finalized by</th><th scope="col">Status</th><th scope="col">Action</th></tr></thead><tbody>
    @forelse($periodLocks as $period)
        <tr><td><strong>{{ $period->period_start->format('d M Y') }} – {{ $period->period_end->format('d M Y') }}</strong><small>Hash {{ str($period->source_hash)->limit(18) }}</small></td><td>v{{ $period->version }}</td><td>{{ $period->snapshots_count }}</td><td>{{ $period->finalizedBy?->name ?: 'System' }}<small>{{ $period->finalized_at?->format('d M Y, H:i') }}</small></td><td><span @class(['people-status', 'is-success' => $period->status === 'finalized', 'is-warning' => $period->status === 'reopened'])>{{ str($period->status)->headline() }}</span>@if($period->reopen_reason)<small>{{ $period->reopen_reason }}</small>@endif</td><td>
            @can('reopen', $period)
                <form class="people-inline-form" method="POST" action="{{ route('hr.attendance-periods.reopen', $period) }}">@csrf @method('PATCH')<input type="hidden" name="lock_version" value="{{ $period->lock_version }}"><input class="people-control" name="reopen_reason" placeholder="Required reopen reason" required maxlength="2000"><button class="people-button is-danger" type="submit">Reopen</button></form>
            @else<span class="people-muted">Immutable</span>@endcan
        </td></tr>
    @empty
        <tr><td colspan="6"><div class="people-ops-empty"><i class="fa-solid fa-lock-open" aria-hidden="true"></i><strong>No finalized periods</strong><span>Finalize attendance only after exceptions and regularizations are resolved.</span></div></td></tr>
    @endforelse
    </tbody></table></div>
    <div class="people-ops-mobile-list">@foreach($periodLocks as $period)<article class="people-ops-mobile-card"><header class="people-ops-mobile-card-head"><div><strong>{{ $period->period_start->format('d M') }} – {{ $period->period_end->format('d M Y') }}</strong><small>Version {{ $period->version }}</small></div><span class="people-status is-success">{{ str($period->status)->headline() }}</span></header><dl class="people-ops-mobile-facts"><div><dt>Snapshots</dt><dd>{{ $period->snapshots_count }}</dd></div><div><dt>Finalized by</dt><dd>{{ $period->finalizedBy?->name ?: 'System' }}</dd></div></dl></article>@endforeach</div>
    <div class="people-pagination"><span>Showing {{ $periodLocks->firstItem() ?? 0 }} to {{ $periodLocks->lastItem() ?? 0 }} of {{ $periodLocks->total() }}</span>{{ $periodLocks->links() }}</div>
</section>
