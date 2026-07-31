<section class="people-ops-panel" aria-labelledby="leave-policy-title">
    <header class="people-ops-panel-head"><div><h2 id="leave-policy-title">Leave policy controls</h2><p>Active persisted leave policies. No leave rule is hardcoded in this screen.</p></div>@if(!$abilities['canManageLeave'])<span class="people-status">Read only</span>@endif</header>
    <div class="people-ops-controls-grid">
        @forelse($types as $policy)
            <article class="people-ops-control">
                <strong>{{ $policy->code }} - {{ $policy->name }}</strong>
                <span><small>Annual entitlement</small>{{ $policy->annualEntitlement }}</span>
                <span><small>Payment</small>{{ $policy->paidLabel }}</span>
                <span><small>Carry forward</small>{{ $policy->carryForwardLabel }}</span>
                <span><small>Encashment</small>{{ $policy->encashmentLabel }}</span>
                <span><small>Partial day</small>{{ $policy->halfDayLabel }}</span>
                <span><small>Supporting evidence</small>{{ $policy->documentLabel }}</span>
                <span><small>Balance control</small>{{ $policy->negativeBalanceLabel }}</span>
                <span><small>Approval chain</small>{{ $policy->approvalChain }}</span>
            </article>
        @empty
            <div class="people-ops-empty"><strong>No active leave policies</strong><span>An authorized administrator must configure and activate leave types before requests can be submitted.</span></div>
        @endforelse
    </div>
    <div class="people-pagination">{{ $types->withQueryString()->links() }}</div>
</section>
