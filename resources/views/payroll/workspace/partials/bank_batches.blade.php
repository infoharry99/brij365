<section class="people-ops-grid is-wide-left" aria-label="Bank transfer controls">
    <article class="people-ops-panel">
        <header class="people-ops-panel-head"><div><h2>Bank transfer controls</h2><p>Bank batches can be prepared only from approved payroll runs.</p></div></header>
        <div class="people-ops-panel-body">
            <ul class="people-ops-checklist">
                <li><i class="fa-solid fa-shield-halved" aria-hidden="true"></i><span>Preparation validates employee bank details, duplicate employees, control totals, and checksums.</span><strong>{{ $summary->preparedBatches }} prepared</strong></li>
                <li><i class="fa-solid fa-user-lock" aria-hidden="true"></i><span>Release is a separate authorized action and enforces segregation of duties.</span><strong>{{ $summary->releasedBatches }} released</strong></li>
            </ul>
        </div>
    </article>
    <article class="people-ops-panel">
        <header class="people-ops-panel-head"><div><h2>Batch filters</h2><p>Filter without exposing restricted payment instructions.</p></div></header>
        <div class="people-ops-panel-body">
            <form method="GET" action="{{ route('payroll.bank-transfer-batches.index') }}" class="people-form-grid">
                <label class="people-field"><span>Status</span><select class="people-control" name="status"><option value="">All supported statuses</option>@foreach($batchStatuses as $status)<option value="{{ $status['value'] }}" @selected(request('status') === $status['value'])>{{ $status['label'] }}</option>@endforeach</select></label>
                <label class="people-field"><span>Bank name</span><input class="people-control" name="bank_name" value="{{ request('bank_name') }}" maxlength="120"></label>
                <label class="people-field"><span>From</span><input class="people-control" type="date" name="from" value="{{ request('from') }}"></label>
                <label class="people-field"><span>To</span><input class="people-control" type="date" name="to" value="{{ request('to') }}"></label>
                @if($abilities['canViewBankPayload'])
                    <label class="people-field is-wide"><span>Restricted payment instructions</span><select class="people-control" name="include_payload"><option value="">Keep hidden</option><option value="1" @selected(request('include_payload') === '1')>Show in this authorized session</option></select><small>Contains bank-transfer instructions. It remains hidden unless explicitly disclosed by an authorized payroll approver.</small></label>
                @endif
                <div class="people-modal-actions is-wide"><button type="submit" class="people-button">Apply filters</button><a class="people-button" href="{{ route('payroll.bank-transfer-batches.index') }}">Clear</a></div>
            </form>
        </div>
    </article>
</section>

@if(request('include_payload') === '1' && $abilities['canViewBankPayload'])
    <div class="people-alert" role="status"><strong>Restricted disclosure enabled.</strong> Payment instructions below are visible only for this filtered response. Do not share or copy them outside the approved bank-transfer workflow.</div>
@endif

<section class="people-ops-panel" aria-labelledby="bank-batches-title">
    <header class="people-ops-panel-head"><div><h2 id="bank-batches-title">Bank transfer batches</h2><p>{{ $batches->total() }} batch{{ $batches->total() === 1 ? '' : 'es' }} in this authorized register.</p></div></header>
    <div class="people-ops-table-wrap">
        <table class="people-ops-table">
            <caption>Payroll bank transfer batch register</caption>
            <thead><tr><th scope="col">Batch</th><th scope="col">Payroll run</th><th scope="col">Bank / date</th><th scope="col">Status</th><th scope="col" class="is-number">Items</th><th scope="col" class="is-number">Control total</th><th scope="col">Control</th><th scope="col">Prepared / released</th><th scope="col" class="is-actions">Action</th></tr></thead>
            <tbody>
                @forelse($batches as $batch)
                    <tr>
                        <td><strong>{{ $batch->batchNumber }}</strong></td>
                        <td>{{ $batch->runNumber }}<small>{{ $batch->period }}</small></td>
                        <td>{{ $batch->bankName }}<small>{{ $batch->paymentDate }}</small></td>
                        <td><span class="people-status is-{{ $batch->status === 'released' ? 'success' : 'warning' }}">{{ $batch->statusLabel }}</span></td>
                        <td class="is-number">{{ number_format($batch->itemCount) }}</td>
                        <td class="is-number"><strong>{{ $batch->controlTotal }}</strong></td>
                        <td>{{ $batch->checksum }}</td>
                        <td>{{ $batch->preparedBy }}@if($batch->releasedBy)<small>Released by {{ $batch->releasedBy }}</small>@endif</td>
                        <td class="is-actions">
                            @if($batch->canRelease)
                                <form method="POST" action="{{ route('payroll.bank-transfer-batches.release', $batch->id) }}">@csrf @method('PATCH')<label class="people-field"><span class="sr-only">Release note for {{ $batch->batchNumber }}</span><input class="people-control" name="release_note" maxlength="500" placeholder="Release note"></label><button type="submit" class="people-ops-action-link">Release batch</button></form>
                            @else<span class="people-subtext">No permitted action</span>@endif
                        </td>
                    </tr>
                    @if($batch->payload !== null)
                        <tr><td colspan="9"><details><summary>Restricted payment instructions for {{ $batch->batchNumber }}</summary><pre>{{ $batch->payload }}</pre></details></td></tr>
                    @endif
                @empty
                    <tr><td colspan="9"><div class="people-ops-empty"><i class="fa-solid fa-building-columns" aria-hidden="true"></i><strong>No bank batches found</strong><span>Clear filters or prepare a batch from an approved payroll run.</span></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="people-pagination">{{ $batches->withQueryString()->links() }}</div>
</section>
