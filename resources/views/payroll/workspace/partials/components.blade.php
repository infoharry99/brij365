<section class="people-ops-grid is-wide-left" aria-label="Salary component controls">
    <article class="people-ops-panel">
        <header class="people-ops-panel-head"><div><h2>Component master</h2><p>Review active earnings and deductions used by salary structures.</p></div></header>
        <div class="people-ops-panel-body"><ul class="people-ops-checklist"><li><i class="fa-solid fa-circle-check" aria-hidden="true"></i><span>Only active payroll components are included in this register.</span><strong>{{ $summary->activeComponents }} active</strong></li><li><i class="fa-solid fa-shield-halved" aria-hidden="true"></i><span>Calculation rules remain governed by the persisted payroll configuration.</span><strong>No inline edits</strong></li></ul></div>
    </article>
    <article class="people-ops-panel">
        <header class="people-ops-panel-head"><div><h2>Component filters</h2><p>Limit the register to earnings or deductions.</p></div></header>
        <div class="people-ops-panel-body"><form method="GET" action="{{ route('payroll.components.index') }}" class="people-form-grid"><label class="people-field is-wide"><span>Component type</span><select class="people-control" name="component_type"><option value="">All component types</option>@foreach($componentTypes as $type)<option value="{{ $type['value'] }}" @selected(request('component_type') === $type['value'])>{{ $type['label'] }}</option>@endforeach</select></label><div class="people-modal-actions is-wide"><button class="people-button" type="submit">Apply filter</button><a class="people-button" href="{{ route('payroll.components.index') }}">Clear</a></div></form></div>
    </article>
</section>

<section class="people-ops-panel" aria-labelledby="components-title">
    <header class="people-ops-panel-head"><div><h2 id="components-title">Salary components</h2><p>{{ $components->total() }} active component{{ $components->total() === 1 ? '' : 's' }} in this company scope.</p></div></header>
    <div class="people-ops-table-wrap">
        <table class="people-ops-table">
            <caption>Active salary component register</caption>
            <thead><tr><th scope="col">Code</th><th scope="col">Name</th><th scope="col">Type</th><th scope="col">Calculation</th><th scope="col">Tax</th><th scope="col">Statutory</th><th scope="col">Rules</th></tr></thead>
            <tbody>
                @forelse($components as $component)
                    <tr><td><strong>{{ $component->code }}</strong></td><td>{{ $component->name }}</td><td><span class="people-status is-{{ $component->type === 'earning' ? 'success' : 'warning' }}">{{ $component->typeLabel }}</span></td><td>{{ $component->calculationLabel }}</td><td>{{ $component->taxableLabel }}</td><td>{{ $component->statutoryLabel }}</td><td>@forelse($component->rules as $rule)<span class="people-subtext">{{ $rule }}</span>@empty<span class="people-subtext">No additional rules</span>@endforelse</td></tr>
                @empty
                    <tr><td colspan="7"><div class="people-ops-empty"><i class="fa-solid fa-list-check" aria-hidden="true"></i><strong>No salary components found</strong><span>Clear the filter or activate components in the governed payroll configuration.</span></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="people-pagination">{{ $components->withQueryString()->links() }}</div>
</section>
