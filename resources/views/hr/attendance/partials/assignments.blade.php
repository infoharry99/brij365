<section class="people-alert" role="note">
    <strong>Effective assignment history.</strong> Create overlap-protected assignments and manage dated rosters, rotations, and swaps in
    <a href="{{ route('hr.attendance-rosters.index') }}">Shifts &amp; Rosters</a>.
</section>

<section class="people-ops-panel has-mobile-cards">
    <header class="people-ops-panel-head"><div><h2>Effective shift assignments</h2><p>Active employee-to-shift relationships in your permitted scope.</p></div><span class="people-count">{{ $assignments->total() }} assignments</span></header>

    <div class="people-ops-table-wrap">
        <table class="people-ops-table">
            <caption>Current effective employee shift assignments</caption>
            <thead><tr><th scope="col">Employee</th><th scope="col">Department / branch</th><th scope="col">Shift</th><th scope="col">Effective from</th><th scope="col">Effective to</th><th scope="col">Status</th></tr></thead>
            <tbody>
                @forelse ($assignments as $assignment)
                    <tr>
                        <td><div class="people-ops-identity"><span class="people-avatar">{{ $assignment->employeeInitial }}</span><div><strong>{{ $assignment->employeeName }}</strong><small>{{ $assignment->employeeCode }}</small></div></div></td>
                        <td><strong>{{ $assignment->department }}</strong><small>{{ $assignment->branch }}</small></td>
                        <td><strong>{{ $assignment->shiftCode }} / {{ $assignment->shiftName }}</strong><small>{{ $assignment->shiftTiming }}</small></td>
                        <td>{{ $assignment->effectiveFrom }}</td>
                        <td>{{ $assignment->effectiveTo }}</td>
                        <td><span class="people-status is-success">{{ $assignment->statusLabel }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="6"><div class="people-ops-empty"><i class="fa-solid fa-user-clock" aria-hidden="true"></i><strong>No active shift assignments</strong><span>No stored active assignments are visible in your authorized employee scope.</span></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="people-ops-mobile-list">
        @foreach ($assignments as $assignment)
            <article class="people-ops-mobile-card">
                <header class="people-ops-mobile-card-head"><div class="people-ops-identity"><span class="people-avatar">{{ $assignment->employeeInitial }}</span><div><strong>{{ $assignment->employeeName }}</strong><small>{{ $assignment->employeeCode }}</small></div></div><span class="people-status is-success">{{ $assignment->statusLabel }}</span></header>
                <dl class="people-ops-mobile-facts"><div><dt>Team</dt><dd>{{ $assignment->department }} / {{ $assignment->branch }}</dd></div><div><dt>Shift</dt><dd>{{ $assignment->shiftCode }} / {{ $assignment->shiftName }}</dd></div><div><dt>Timing</dt><dd>{{ $assignment->shiftTiming }}</dd></div><div><dt>Effective</dt><dd>{{ $assignment->effectiveFrom }} to {{ $assignment->effectiveTo }}</dd></div></dl>
            </article>
        @endforeach
    </div>

    <div class="people-pagination"><span>Showing {{ $assignments->firstItem() ?? 0 }} to {{ $assignments->lastItem() ?? 0 }} of {{ $assignments->total() }}</span>{{ $assignments->withQueryString()->links() }}</div>
</section>
