<section class="people-ops-panel" aria-labelledby="leave-balances-title">
    <header class="people-ops-panel-head"><div><h2 id="leave-balances-title">Leave balances</h2><p>Posted and pending ledger positions for the selected year.</p></div></header>
    <div class="people-ops-panel-body">
        <form method="GET" action="{{ route('hr.leave-balances.index') }}" class="people-ops-filterbar">
            <label class="people-field"><span>Employee</span><select class="people-control" name="employee_id"><option value="">All employees</option>@foreach($employees as $employee)<option value="{{ $employee->id }}" @selected((string) request('employee_id') === (string) $employee->id)>{{ $employee->employee_code }} - {{ $employee->name }}</option>@endforeach</select></label>
            <label class="people-field"><span>Period year</span><input class="people-control" type="number" name="period_year" min="2000" max="2100" value="{{ request('period_year') }}" placeholder="Any year"></label>
            <button class="people-button" type="submit">Apply filters</button><a class="people-button" href="{{ route('hr.leave-balances.index') }}">Clear</a>
        </form>
    </div>
    <div class="people-ops-table-wrap">
        <table class="people-ops-table">
            <caption>Employee leave balance register</caption>
            <thead><tr><th scope="col">Employee</th><th scope="col">Leave type</th><th scope="col">Year</th><th scope="col" class="is-number">Opening</th><th scope="col" class="is-number">Accrued</th><th scope="col" class="is-number">Used</th><th scope="col" class="is-number">Pending</th><th scope="col" class="is-number">Adjusted</th><th scope="col" class="is-number">Available</th></tr></thead>
            <tbody>@forelse($balances as $balance)<tr><td><div class="people-ops-identity"><div><strong>{{ $balance->employeeName }}</strong><small>{{ $balance->employeeCode }}</small></div></div></td><td>{{ $balance->leaveTypeName }}<small>{{ $balance->leaveTypeCode }}</small></td><td>{{ $balance->periodYear }}</td><td class="is-number">{{ $balance->opening }}</td><td class="is-number">{{ $balance->accrued }}</td><td class="is-number">{{ $balance->used }}</td><td class="is-number">{{ $balance->pending }}</td><td class="is-number">{{ $balance->adjusted }}</td><td class="is-number"><strong>{{ $balance->available }}</strong></td></tr>@empty<tr><td colspan="9"><div class="people-ops-empty"><strong>No leave balances found</strong><span>No ledger rows match the selected filters.</span></div></td></tr>@endforelse</tbody>
        </table>
    </div>
    <div class="people-pagination">{{ $balances->withQueryString()->links() }}</div>
</section>
