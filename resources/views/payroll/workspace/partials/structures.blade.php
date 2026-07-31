<section class="people-ops-kpis is-four" aria-label="Salary master summary">
    <article class="people-ops-kpi is-info"><span class="people-ops-kpi-icon"><i class="fa-solid fa-layer-group" aria-hidden="true"></i></span><span>Active structures</span><strong>{{ number_format($summary->activeStructures) }}</strong><small>Only currently active structures are listed.</small></article>
    <article class="people-ops-kpi is-purple"><span class="people-ops-kpi-icon"><i class="fa-solid fa-list-check" aria-hidden="true"></i></span><span>Active components</span><strong>{{ number_format($summary->activeComponents) }}</strong><small>Configured earnings and deductions.</small></article>
</section>

<section class="people-ops-panel" aria-labelledby="salary-structures-title">
    <header class="people-ops-panel-head"><div><h2 id="salary-structures-title">Salary structures</h2><p>{{ $structures->total() }} active structure{{ $structures->total() === 1 ? '' : 's' }} in this company scope.</p></div></header>
    <div class="people-ops-table-wrap">
        <table class="people-ops-table">
            <caption>Active salary structure register</caption>
            <thead><tr><th scope="col">Code</th><th scope="col">Name</th><th scope="col" class="is-number">Version</th><th scope="col">Effective</th><th scope="col" class="is-number">Monthly CTC</th><th scope="col">Components</th></tr></thead>
            <tbody>
                @forelse($structures as $structure)
                    <tr><td><strong>{{ $structure->code }}</strong></td><td>{{ $structure->name }}</td><td class="is-number">{{ $structure->version }}</td><td>{{ $structure->effectiveRange }}</td><td class="is-number"><strong>{{ $structure->monthlyCtc }}</strong></td><td>@forelse($structure->components as $component)<span class="people-subtext">{{ $component }}</span>@empty<span class="people-subtext">No components configured</span>@endforelse</td></tr>
                @empty
                    <tr><td colspan="6"><div class="people-ops-empty"><i class="fa-solid fa-layer-group" aria-hidden="true"></i><strong>No active salary structures</strong><span>No active salary structures are available in your company scope.</span></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="people-pagination">{{ $structures->withQueryString()->links() }}</div>
</section>
