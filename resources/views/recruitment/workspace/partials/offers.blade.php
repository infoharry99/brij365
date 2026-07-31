<section class="people-ops-stack" data-recruitment-surface="offers">
    @if($abilities['canCreateOffer'])
        <details id="recruitment-create" class="people-ops-panel" @if($errors->any()) open @endif><summary class="people-ops-panel-head"><div><h2>Create offer draft</h2><p>Offer creation and release remain separated by policy.</p></div><span class="people-button"><i class="fa-solid fa-plus" aria-hidden="true"></i> Offer</span></summary>
            <form method="POST" action="{{ route('recruitment.offers.store') }}" class="people-form-grid people-ops-panel-body">@csrf
                <label class="people-field">Candidate<select name="candidate_id" required><option value="">Select candidate</option>@foreach($offerCandidateOptions as $candidate)<option value="{{ $candidate->id }}" @selected((string)old('candidate_id') === (string)$candidate->id)>{{ $candidate->candidate_code }} · {{ $candidate->name }}</option>@endforeach</select></label>
                <label class="people-field">Template code<input name="template_code" value="{{ old('template_code', 'APPOINTMENT_STANDARD') }}" maxlength="80" required></label>
                <label class="people-field">Offered CTC<input type="number" name="offered_ctc" value="{{ old('offered_ctc') }}" min="1" step="0.01" required></label>
                <label class="people-field">Joining date<input type="date" name="joining_date" value="{{ old('joining_date') }}" min="{{ now()->addDay()->toDateString() }}" required></label>
                <label class="people-field">Candidate name placeholder<input name="placeholders[candidate_name]" value="{{ old('placeholders.candidate_name') }}" maxlength="255" required></label>
                <label class="people-field">Designation placeholder<input name="placeholders[designation]" value="{{ old('placeholders.designation') }}" maxlength="255" required></label>
                <label class="people-field">Department placeholder<input name="placeholders[department]" value="{{ old('placeholders.department') }}" maxlength="255" required></label>
                <label class="people-field">Joining date placeholder<input type="date" name="placeholders[joining_date]" value="{{ old('placeholders.joining_date') }}" required></label>
                <label class="people-field">Offered CTC placeholder<input type="number" name="placeholders[offered_ctc]" value="{{ old('placeholders.offered_ctc') }}" min="1" step="0.01" required></label>
                <div class="people-field is-wide"><button class="people-button is-primary" type="submit">Create offer draft</button></div>
            </form>
        </details>
    @endif

    <form method="GET" action="{{ route('recruitment.offers.index') }}" class="people-ops-filterbar"><label class="people-field">Status<select name="status"><option value="">All statuses</option>@foreach($offerStatuses as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></label><button class="people-button" type="submit"><i class="fa-solid fa-filter" aria-hidden="true"></i> Apply</button>@if(array_filter($filters))<a class="people-button" href="{{ route('recruitment.offers.index') }}">Clear</a>@endif</form>

    <article class="people-ops-panel has-mobile-cards"><header class="people-ops-panel-head"><div><h2>Offer lifecycle</h2><p>Drafts can be released only by an independent authorized reviewer.</p></div><small>{{ $offers->firstItem() ?? 0 }}–{{ $offers->lastItem() ?? 0 }} of {{ $offers->total() }}</small></header>
        <div class="people-ops-table-wrap"><table class="people-ops-table"><caption>Recruitment offers</caption><thead><tr><th scope="col">Offer</th><th scope="col">Candidate</th><th scope="col">Template</th><th scope="col">CTC / Joining</th><th scope="col">Status</th><th scope="col">Owner</th><th scope="col">Actions</th></tr></thead><tbody>
        @forelse($offers as $offer)<tr><td><strong>{{ $offer->number }}</strong><small>{{ $offer->openingTitle }} · {{ $offer->department }}</small></td><td>{{ $offer->candidateName }}<small>{{ $offer->candidateCode }}</small></td><td>{{ $offer->template }}</td><td>{{ $offer->offeredCtc ?? 'Restricted' }}<small>{{ $offer->joiningDate }}</small></td><td><span class="people-status {{ $offer->statusTone }}">{{ $offer->statusLabel }}</span></td><td>{{ $offer->createdBy }}<small>{{ $offer->releasedBy }} · {{ $offer->releasedAt }}</small></td><td class="is-actions">@if($offer->canRelease)<form method="POST" action="{{ route('recruitment.offers.release', $offer->id) }}" class="people-ops-list-actions">@csrf @method('PATCH')<input name="release_note" maxlength="2000" placeholder="Release note"><button class="people-button is-primary" type="submit">Release</button></form>@else<span class="people-status is-muted">{{ $offer->status === 'draft' ? 'Release unavailable' : 'No action' }}</span>@endif</td></tr>@empty<tr><td colspan="7"><div class="people-ops-empty"><i class="fa-solid fa-file-signature" aria-hidden="true"></i><strong>No offers found</strong><span>Change the filters or create an authorized offer draft.</span></div></td></tr>@endforelse
        </tbody></table></div>
        <div class="people-ops-mobile-list">
            @forelse($offers as $offer)
                <article class="people-ops-mobile-card">
                    <header class="people-ops-mobile-card-head"><div><strong>{{ $offer->candidateName }}</strong><small>{{ $offer->number }} · {{ $offer->candidateCode }}</small></div><span class="people-status {{ $offer->statusTone }}">{{ $offer->statusLabel }}</span></header>
                    <dl class="people-ops-mobile-facts"><div><dt>Opening</dt><dd>{{ $offer->openingTitle }}</dd></div><div><dt>Joining</dt><dd>{{ $offer->joiningDate }}</dd></div><div><dt>Template</dt><dd>{{ $offer->template }}</dd></div><div><dt>Created by</dt><dd>{{ $offer->createdBy }}</dd></div></dl>
                    @if($offer->canRelease)
                        <div class="people-ops-mobile-actions"><form method="POST" action="{{ route('recruitment.offers.release', $offer->id) }}" class="people-ops-list-actions">@csrf @method('PATCH')<label class="people-field"><span class="sr-only">Release note for {{ $offer->number }}</span><input name="release_note" maxlength="2000" placeholder="Release note"></label><button class="people-button is-primary" type="submit">Release offer</button></form></div>
                    @elseif($offer->status === 'draft')
                        <p class="people-subtext">Release unavailable for your role.</p>
                    @endif
                </article>
            @empty
                <div class="people-ops-empty"><strong>No offers found</strong><span>Change the filters or create an authorized offer draft.</span></div>
            @endforelse
        </div>
        <div class="people-pagination">{{ $offers->links() }}</div>
    </article>
</section>
