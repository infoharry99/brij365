<section class="people-ops-stack" data-recruitment-surface="candidates">
    @if($abilities['canCreateCandidate'])
        <details id="recruitment-create" class="people-ops-panel" @if($errors->any()) open @endif><summary class="people-ops-panel-head"><div><h2>Create candidate</h2><p>Add a candidate only to an open company-scoped requisition.</p></div><span class="people-button"><i class="fa-solid fa-plus" aria-hidden="true"></i> Candidate</span></summary>
            <form method="POST" action="{{ route('recruitment.candidates.store') }}" class="people-form-grid people-ops-panel-body">@csrf
                <label class="people-field">Open requisition<select name="job_opening_id" required><option value="">Select open job</option>@foreach($openOpeningOptions as $opening)<option value="{{ $opening->id }}" @selected((string)old('job_opening_id') === (string)$opening->id)>{{ $opening->opening_code }} · {{ $opening->title }}</option>@endforeach</select></label>
                <label class="people-field">Candidate name<input name="name" value="{{ old('name') }}" maxlength="255" required></label>
                <label class="people-field">Email<input type="email" name="email" value="{{ old('email') }}" maxlength="255" required></label>
                <label class="people-field">Phone<input name="phone" value="{{ old('phone') }}" maxlength="30" required></label>
                <label class="people-field">Source<select name="source" required>@foreach($sources as $source)<option value="{{ $source }}" @selected(old('source', 'LinkedIn') === $source)>{{ $source }}</option>@endforeach</select></label>
                <label class="people-field">Current company<input name="current_company" value="{{ old('current_company') }}" maxlength="255"></label>
                <label class="people-field">Experience years<input type="number" name="experience_years" value="{{ old('experience_years', 0) }}" min="0" max="60" step="0.01" required></label>
                <label class="people-field">Current CTC<input type="number" name="current_ctc" value="{{ old('current_ctc') }}" min="0" step="0.01"></label>
                <label class="people-field">Expected CTC<input type="number" name="expected_ctc" value="{{ old('expected_ctc') }}" min="0" step="0.01"></label>
                <label class="people-field">Notice period days<input type="number" name="notice_period_days" value="{{ old('notice_period_days') }}" min="0" max="365"></label>
                <label class="people-field">Skill<input name="skills[]" value="{{ old('skills.0') }}" maxlength="80"></label>
                <label class="people-field is-wide">Notes<textarea name="notes" maxlength="5000">{{ old('notes') }}</textarea></label>
                <div class="people-field is-wide"><button class="people-button is-primary" type="submit">Create candidate</button></div>
            </form>
        </details>
    @endif

    <form method="GET" action="{{ route('recruitment.candidates.index') }}" class="people-ops-filterbar">
        <label class="people-field">Search<input type="search" name="search" value="{{ $filters['search'] ?? '' }}" maxlength="120" placeholder="Name, email, phone, or code"></label>
        <label class="people-field">Stage<select name="stage"><option value="">All stages</option>@foreach($candidateStages as $value => $label)<option value="{{ $value }}" @selected(($filters['stage'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></label>
        <label class="people-field">Source<select name="source"><option value="">All sources</option>@foreach($sources as $source)<option value="{{ $source }}" @selected(($filters['source'] ?? '') === $source)>{{ $source }}</option>@endforeach</select></label>
        <button class="people-button" type="submit"><i class="fa-solid fa-filter" aria-hidden="true"></i> Apply</button>@if(array_filter($filters))<a class="people-button" href="{{ route('recruitment.candidates.index') }}">Clear</a>@endif
    </form>

    <article class="people-ops-panel has-mobile-cards"><header class="people-ops-panel-head"><div><h2>Candidate pipeline</h2><p>Stage changes remain controlled by recruitment, interview, offer, and conversion workflows.</p></div><small>{{ $candidates->firstItem() ?? 0 }}–{{ $candidates->lastItem() ?? 0 }} of {{ $candidates->total() }}</small></header>
        <div class="people-ops-table-wrap"><table class="people-ops-table"><caption>Recruitment candidates</caption><thead><tr><th scope="col">Candidate</th><th scope="col">Opening</th><th scope="col">Source</th><th scope="col">Experience</th><th scope="col">Stage</th><th scope="col">Owner</th><th scope="col">Actions</th></tr></thead><tbody>
        @forelse($candidates as $candidate)<tr><td><div class="people-ops-identity"><span class="people-avatar">{{ $candidate->initials }}</span><div><strong>{{ $candidate->name }}</strong><small>{{ $candidate->code }} · {{ $candidate->email }}</small></div></div></td><td>{{ $candidate->openingTitle }}<small>{{ $candidate->openingCode }} · {{ $candidate->department }}</small></td><td>{{ $candidate->source }}<small>{{ $candidate->currentCompany }}</small></td><td>{{ $candidate->experience }}@if($candidate->ctcSummary)<small>{{ $candidate->ctcSummary }}</small>@endif</td><td><span class="people-status {{ $candidate->stageTone }}">{{ $candidate->stageLabel }}</span><small>{{ $candidate->interviewCount }} interviews · {{ $candidate->offerStatus }}</small></td><td>{{ $candidate->owner }}</td><td class="is-actions">
            @if($candidate->allowedStages)<form method="POST" action="{{ route('recruitment.candidates.stage', $candidate->id) }}" class="people-ops-list-actions">@csrf @method('PATCH')<label class="people-field"><span class="sr-only">New stage for {{ $candidate->name }}</span><select name="stage" required>@foreach($candidate->allowedStages as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label><input name="transition_note" maxlength="2000" placeholder="Transition note"><button class="people-button" type="submit">Update</button></form>@elseif($candidate->canConvert)<span class="people-status is-warning">Ready for conversion</span>@else<span class="people-status is-muted">Workflow controlled</span>@endif
        </td></tr>@empty<tr><td colspan="7"><div class="people-ops-empty"><i class="fa-solid fa-user-group" aria-hidden="true"></i><strong>No candidates found</strong><span>Change the filters or add a candidate to an open requisition.</span></div></td></tr>@endforelse
        </tbody></table></div>
        <div class="people-ops-mobile-list">
            @forelse($candidates as $candidate)
                <article class="people-ops-mobile-card">
                    <header class="people-ops-mobile-card-head"><div class="people-ops-identity"><span class="people-avatar">{{ $candidate->initials }}</span><div><strong>{{ $candidate->name }}</strong><small>{{ $candidate->code }} · {{ $candidate->email }}</small></div></div><span class="people-status {{ $candidate->stageTone }}">{{ $candidate->stageLabel }}</span></header>
                    <dl class="people-ops-mobile-facts"><div><dt>Opening</dt><dd>{{ $candidate->openingTitle }}</dd></div><div><dt>Source</dt><dd>{{ $candidate->source }}</dd></div><div><dt>Experience</dt><dd>{{ $candidate->experience }}</dd></div><div><dt>Owner</dt><dd>{{ $candidate->owner }}</dd></div></dl>
                    <div class="people-ops-mobile-actions">
                        @if($candidate->allowedStages)
                            <form method="POST" action="{{ route('recruitment.candidates.stage', $candidate->id) }}" class="people-ops-list-actions">
                                @csrf @method('PATCH')
                                <label class="people-field"><span class="sr-only">New stage for {{ $candidate->name }}</span><select name="stage" required>@foreach($candidate->allowedStages as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
                                <label class="people-field"><span class="sr-only">Transition note for {{ $candidate->name }}</span><input name="transition_note" maxlength="2000" placeholder="Transition note"></label>
                                <button class="people-button is-primary" type="submit">Update stage</button>
                            </form>
                        @elseif($candidate->canConvert)
                            <span class="people-status is-warning">Ready for conversion</span>
                        @else
                            <span class="people-status is-muted">Workflow controlled</span>
                        @endif
                    </div>
                </article>
            @empty
                <div class="people-ops-empty"><strong>No candidates found</strong><span>Change the filters or add a candidate to an open requisition.</span></div>
            @endforelse
        </div>
        <div class="people-pagination">{{ $candidates->links() }}</div>
    </article>
</section>
