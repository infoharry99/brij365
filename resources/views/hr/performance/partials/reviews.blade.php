@if($abilities['canCreateReview'])
    <details class="people-ops-panel" id="create-performance-review" @if($errors->any()) open @endif>
        <summary class="people-ops-panel-head"><div><h2>Open employee review</h2><p>Create a review inside an active authorized performance cycle.</p></div></summary>
        <div class="people-ops-panel-body">
            <form method="POST" action="{{ route('hr.performance-reviews.store') }}" class="people-form-grid">
                @csrf
                <label class="people-field"><span>Cycle</span><select class="people-control" name="performance_cycle_id" required><option value="">Select cycle</option>@foreach($activeCycles as $cycle)<option value="{{ $cycle->id }}" @selected((string)old('performance_cycle_id')===(string)$cycle->id)>{{ $cycle->cycle_code }} - {{ $cycle->name }}</option>@endforeach</select></label>
                <label class="people-field"><span>Employee</span><select class="people-control" name="employee_id" required><option value="">Select employee</option>@foreach($employees as $employee)<option value="{{ $employee->id }}" @selected((string)old('employee_id')===(string)$employee->id)>{{ $employee->employee_code }} - {{ $employee->name }}</option>@endforeach</select></label>
                <label class="people-field"><span>Manager</span><select class="people-control" name="manager_employee_id"><option value="">Use reporting manager</option>@foreach($managers as $manager)<option value="{{ $manager->id }}" @selected((string)old('manager_employee_id')===(string)$manager->id)>{{ $manager->employee_code }} - {{ $manager->name }}</option>@endforeach</select></label>
                <label class="people-field"><span>KPI</span><input class="people-control" name="kpis[0][name]" maxlength="160" value="{{ old('kpis.0.name') }}" required></label>
                <label class="people-field"><span>Target</span><input class="people-control" name="kpis[0][target]" maxlength="160" value="{{ old('kpis.0.target') }}" required></label>
                <label class="people-field"><span>Metric</span><input class="people-control" name="kpis[0][metric]" maxlength="100" value="{{ old('kpis.0.metric') }}" required></label>
                <label class="people-field"><span>Weight percent</span><input class="people-control" type="number" name="kpis[0][weight]" min="0" max="100" step="0.01" value="{{ old('kpis.0.weight',100) }}" required></label>
                <button class="people-button is-primary" type="submit">Open review</button>
            </form>
        </div>
    </details>
@endif

<section class="people-ops-panel has-mobile-cards" aria-labelledby="performance-reviews-title">
    <header class="people-ops-panel-head"><div><h2 id="performance-reviews-title">Employee reviews</h2><p>{{ $reviews->total() }} authorized review{{ $reviews->total() === 1 ? '' : 's' }}.</p></div></header>
    <div class="people-ops-panel-body">
        <form method="GET" action="{{ route('hr.performance-reviews.index') }}" class="people-ops-filterbar">
            <label class="people-field"><span>Cycle</span><select class="people-control" name="cycle_id"><option value="">All cycles</option>@foreach($activeCycles as $cycle)<option value="{{ $cycle->id }}" @selected((string)request('cycle_id')===(string)$cycle->id)>{{ $cycle->cycle_code }} - {{ $cycle->name }}</option>@endforeach</select></label>
            <label class="people-field"><span>Employee</span><select class="people-control" name="employee_id"><option value="">All visible employees</option>@foreach($employees as $employee)<option value="{{ $employee->id }}" @selected((string)request('employee_id')===(string)$employee->id)>{{ $employee->employee_code }} - {{ $employee->name }}</option>@endforeach</select></label>
            <label class="people-field"><span>Status</span><select class="people-control" name="status"><option value="">All statuses</option>@foreach(['draft'=>'Draft','self_submitted'=>'Self submitted','manager_submitted'=>'Manager submitted','closed'=>'Closed'] as $value=>$label)<option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>@endforeach</select></label>
            <label class="people-field"><span>PIP</span><select class="people-control" name="pip_required"><option value="">All reviews</option><option value="1" @selected(request('pip_required')==='1')>PIP required</option><option value="0" @selected(request('pip_required')==='0')>Not required</option></select></label>
            <button class="people-button is-primary">Apply filters</button><a class="people-button" href="{{ route('hr.performance-reviews.index') }}">Clear</a>
        </form>
    </div>
    <div class="people-ops-table-wrap">
        <table class="people-ops-table">
            <caption>Employee performance reviews</caption>
            <thead><tr><th scope="col">Review</th><th scope="col">Employee</th><th scope="col">Cycle / period</th><th scope="col">Scores</th><th scope="col">Status</th><th scope="col" class="is-actions">Action</th></tr></thead>
            <tbody>
            @forelse($reviews as $review)
                <tr>
                    <td><strong>{{ $review->number }}</strong><small>Manager: {{ $review->managerName }}</small></td>
                    <td><div class="people-ops-identity"><div><strong>{{ $review->employeeName }}</strong><small>{{ $review->employeeCode }} / {{ $review->department }}</small></div></div></td>
                    <td>{{ $review->cycleName }}<small>{{ $review->period }}</small></td>
                    <td>
                        Self {{ $review->selfScore ?? '—' }} / Manager {{ $review->managerScore ?? '—' }}
                        @if($review->formulaScore)
                            <small>Formula {{ $review->formulaScore }} / {{ $review->formulaRating }}{{ $review->scoreIsOverride ? ' (approved override)' : '' }}</small>
                            <details class="people-score-trace">
                                <summary>Calculation trace</summary>
                                <span>Rule v{{ $review->scoringRuleVersion }} · {{ $review->scoringCalculatedAt }}</span>
                                @foreach(($review->calculationTrace['components'] ?? []) as $componentKey => $component)
                                    <span>{{ $component['label'] ?? str($componentKey)->headline() }}: {{ number_format((float)($component['normalized_score'] ?? 0), 2) }} × {{ number_format((float)data_get($review->calculationTrace, 'weights.'.$componentKey, 0), 2) }}%</span>
                                @endforeach
                                <span>Input hash: {{ str((string)($review->calculationTrace['input_hash'] ?? ''))->limit(20) }}</span>
                            </details>
                        @endif
                        <small>Final {{ $review->finalScore ?? '—' }}{{ $review->finalRating ? ' / '.$review->finalRating : '' }}</small>
                    </td>
                    <td>
                        <span class="people-status is-{{ $review->status === 'closed' ? 'success' : 'info' }}">{{ $review->statusLabel }}</span>
                        @if($review->overrideStatus === 'pending')<small>Override awaiting separate approval</small>@endif
                        @if($review->pipRequired)<small>PIP required</small>@endif
                    </td>
                    <td class="is-actions">@include('hr.performance.partials.review-actions', ['review' => $review, 'mobile' => false])</td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="people-ops-empty"><strong>No employee reviews found</strong><span>Clear the filters or open an authorized review.</span></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="people-ops-mobile-list">
        @forelse($reviews as $review)
            <article class="people-ops-mobile-card">
                <header class="people-ops-mobile-card-head"><div><strong>{{ $review->employeeName }}</strong><small>{{ $review->number }}</small></div><span class="people-status is-info">{{ $review->statusLabel }}</span></header>
                <dl class="people-ops-mobile-facts">
                    <div><dt>Cycle</dt><dd>{{ $review->cycleName }}</dd></div><div><dt>Period</dt><dd>{{ $review->period }}</dd></div>
                    <div><dt>Formula score</dt><dd>{{ $review->formulaScore ?? '—' }}</dd></div><div><dt>Final score</dt><dd>{{ $review->finalScore ?? '—' }}</dd></div>
                    <div><dt>Manager</dt><dd>{{ $review->managerName }}</dd></div><div><dt>Governance</dt><dd>{{ $review->overrideStatus === 'pending' ? 'Override pending' : ($review->scoreIsOverride ? 'Approved override' : 'Formula') }}</dd></div>
                </dl>
                @include('hr.performance.partials.review-actions', ['review' => $review, 'mobile' => true])
            </article>
        @empty
            <div class="people-ops-empty"><strong>No employee reviews found</strong></div>
        @endforelse
    </div>
    {{ $reviews->withQueryString()->links() }}
</section>
