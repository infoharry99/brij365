<section class="people-ops-grid is-wide-left" aria-label="Leave balance processing">
    <article class="people-ops-panel">
        <header class="people-ops-panel-head"><div><h2>Leave processing run</h2><p>Generate a governed preview before any ledger posting.</p></div></header>
        <div class="people-ops-panel-body">
            @if ($abilities['canCreateProcessingRun'])
                <form method="POST" action="{{ route('hr.leave-processing-runs.store') }}" class="people-form-grid">
                    @csrf
                    <x-forms.company-context :companies="$companies" placeholder="Use my company" />
                    <label class="people-field"><span>Period year</span><input class="people-control" type="number" name="period_year" min="2000" max="2100" value="{{ old('period_year', now()->year) }}" required>@error('period_year')<small class="people-field-error">{{ $message }}</small>@enderror</label>
                    <label class="people-field"><span>Processing type</span><select class="people-control" name="processing_type" required>@foreach($processingTypes as $type)<option value="{{ $type['value'] }}" @selected(old('processing_type', 'monthly_accrual') === $type['value'])>{{ $type['label'] }}</option>@endforeach</select>@error('processing_type')<small class="people-field-error">{{ $message }}</small>@enderror</label>
                    <input type="hidden" name="is_dry_run" value="1">
                    <label class="people-field is-wide"><span>Preview note</span><textarea class="people-control" name="note" rows="3" maxlength="1000" placeholder="Reason or context for this preview">{{ old('note') }}</textarea>@error('note')<small class="people-field-error">{{ $message }}</small>@enderror</label>
                    <div class="people-modal-actions is-wide"><button type="submit" class="people-button is-primary">Generate processing preview</button></div>
                </form>
            @else
                <div class="people-ops-empty"><i class="fa-solid fa-lock" aria-hidden="true"></i><strong>Processing unavailable</strong><span>Your role cannot create leave processing previews.</span></div>
            @endif
        </div>
    </article>
    <article class="people-ops-panel">
        <header class="people-ops-panel-head"><div><h2>Processing safeguards</h2><p>Existing server-authoritative controls applied to every run.</p></div></header>
        <ul class="people-ops-checklist">
            <li><i class="fa-solid fa-check" aria-hidden="true"></i><span>Active policy scope captured in the preview</span><strong>Server validated</strong></li>
            <li><i class="fa-solid fa-check" aria-hidden="true"></i><span>Eligible employees and leave balances evaluated</span><strong>Preview first</strong></li>
            <li><i class="fa-solid fa-check" aria-hidden="true"></i><span>Duplicate period and processing type blocked</span><strong>Idempotent</strong></li>
            <li><i class="fa-solid fa-check" aria-hidden="true"></i><span>Posting requires separate authorized action</span><strong>Audited</strong></li>
        </ul>
    </article>
</section>

<section class="people-ops-panel" aria-labelledby="leave-processing-title">
    <header class="people-ops-panel-head"><div><h2 id="leave-processing-title">Leave processing runs</h2><p>Persisted previews and posted runs; no values are calculated in the browser.</p></div></header>
    <div class="people-ops-panel-body"><form method="GET" action="{{ route('hr.leave-processing-runs.index') }}" class="people-ops-filterbar"><label class="people-field"><span>Year</span><input class="people-control" type="number" name="period_year" min="2000" max="2100" value="{{ request('period_year') }}"></label><label class="people-field"><span>Type</span><select class="people-control" name="processing_type"><option value="">All types</option>@foreach($processingTypes as $type)<option value="{{ $type['value'] }}" @selected(request('processing_type') === $type['value'])>{{ $type['label'] }}</option>@endforeach</select></label><label class="people-field"><span>Status</span><select class="people-control" name="status"><option value="">All statuses</option>@foreach($processingStatuses as $status)<option value="{{ $status['value'] }}" @selected(request('status') === $status['value'])>{{ $status['label'] }}</option>@endforeach</select></label><button class="people-button" type="submit">Apply filters</button><a class="people-button" href="{{ route('hr.leave-processing-runs.index') }}">Clear</a></form></div>
    <div class="people-ops-table-wrap">
        <table class="people-ops-table">
            <caption>Leave processing run history</caption>
            <thead><tr><th scope="col">Run</th><th scope="col">Year / type</th><th scope="col">Status</th><th scope="col">Persisted summary</th><th scope="col">Created / posted</th><th scope="col" class="is-actions">Action</th></tr></thead>
            <tbody>
                @forelse($processingRuns as $run)
                    <tr>
                        <td><strong>{{ $run->runNumber }}</strong></td>
                        <td>{{ $run->periodYear }}<small>{{ $run->processingTypeLabel }}</small></td>
                        <td><span class="people-status is-{{ $run->status }}">{{ $run->statusLabel }}</span></td>
                        <td>{{ $run->employeeCount }} employees / {{ $run->lineCount }} lines<small>Accrual {{ $run->accrualDays }} / Carry {{ $run->carryForwardDays }} / Lapse {{ $run->lapseDays }}</small></td>
                        <td>{{ $run->createdBy }}<small>{{ $run->createdAt }}</small>@if($run->postedBy)<small>Posted by {{ $run->postedBy }}{{ $run->postedAt ? ' / '.$run->postedAt : '' }}</small>@endif</td>
                        <td class="is-actions">@if($run->canPost)<form method="POST" action="{{ route('hr.leave-processing-runs.post', $run->id) }}">@csrf @method('PATCH')<input class="people-control" name="note" maxlength="1000" placeholder="Posting note"><button class="people-ops-action-link" type="submit">Post run</button></form>@else<span class="people-subtext">No action</span>@endif</td>
                    </tr>
                    <tr class="people-processing-detail-row">
                        <td colspan="6">
                            <details class="people-processing-details">
                                <summary>Persisted preview details</summary>
                                <div class="people-processing-details-body">
                                    <section aria-label="Rules captured for this run">
                                        <h3>Rules captured for this run</h3>
                                        @if ($run->rulesSnapshot)
                                            <dl class="people-processing-rules">
                                                <div><dt>Setting</dt><dd>{{ $run->rulesSnapshot->settingKey }}</dd></div>
                                                <div><dt>Monthly accrual</dt><dd>{{ $run->rulesSnapshot->monthlyAccrualLabel }}</dd></div>
                                                <div><dt>Year-end processing</dt><dd>{{ $run->rulesSnapshot->yearEndLabel }}</dd></div>
                                                <div><dt>Encashment tax</dt><dd>{{ $run->rulesSnapshot->encashmentTaxRate }}</dd></div>
                                                <div class="is-wide"><dt>Encashment formula</dt><dd>{{ $run->rulesSnapshot->encashmentFormula }}</dd></div>
                                            </dl>
                                            @if ($run->rulesSnapshot->leaveTypes !== [])
                                                <div class="people-processing-rule-list" aria-label="Leave type rules">
                                                    @foreach ($run->rulesSnapshot->leaveTypes as $rule)
                                                        <article>
                                                            <strong>{{ $rule->code }}</strong>
                                                            <span>{{ $rule->annualEntitlementDays }} days annual entitlement</span>
                                                            <small>Carry forward {{ $rule->carryForwardLabel }} / maximum {{ $rule->maxCarryForwardDays }} days / Encashment {{ $rule->encashmentLabel }}</small>
                                                        </article>
                                                    @endforeach
                                                </div>
                                            @endif
                                        @else
                                            <p class="people-subtext">No persisted rule snapshot is available for this historical run.</p>
                                        @endif
                                    </section>
                                    <section aria-label="Persisted employee processing lines">
                                        <h3>Persisted employee processing lines</h3>
                                        @if ($run->lineItems !== [])
                                            <div class="people-processing-lines" role="list">
                                                @foreach ($run->lineItems as $line)
                                                    <article role="listitem">
                                                        <strong>{{ $line->employeeName }}</strong>
                                                        <span>{{ $line->employeeCode }} / {{ $line->leaveTypeCode }}</span>
                                                        <small>Opening {{ $line->openingBalanceDays }} / Available before {{ $line->availableBeforeDays }} / Accrual {{ $line->accrualDays }} / Carry {{ $line->carryForwardDays }} / Lapse {{ $line->lapseDays }}</small>
                                                    </article>
                                                @endforeach
                                            </div>
                                        @else
                                            <p class="people-subtext">No persisted line items are available for this historical run.</p>
                                        @endif
                                    </section>
                                </div>
                            </details>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6"><div class="people-ops-empty"><strong>No leave processing runs found</strong><span>Create a preview or clear the selected filters.</span></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="people-pagination">{{ $processingRuns->withQueryString()->links() }}</div>
</section>
