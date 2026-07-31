<section class="people-ops-stack" data-recruitment-surface="pipeline">
    <form method="GET" action="{{ route('recruitment.pipeline.index') }}" class="people-ops-filterbar">
        <label class="people-field">Search<input type="search" name="search" value="{{ $filters['search'] ?? '' }}" maxlength="120" placeholder="Name, email, phone, or candidate code"></label>
        <label class="people-field">Source<select name="source"><option value="">All sources</option>@foreach($sources as $source)<option value="{{ $source }}" @selected(($filters['source'] ?? '') === $source)>{{ $source }}</option>@endforeach</select></label>
        <label class="people-field">Cards per stage<select name="per_page">@foreach([15, 25, 50, 100] as $size)<option value="{{ $size }}" @selected((int)($filters['per_page'] ?? 15) === $size)>{{ $size }}</option>@endforeach</select></label>
        <button class="people-button" type="submit"><i class="fa-solid fa-filter" aria-hidden="true"></i> Apply</button>
        @if(array_filter($filters))<a class="people-button" href="{{ route('recruitment.pipeline.index') }}">Clear</a>@endif
    </form>

    <article class="people-ops-panel">
        <header class="people-ops-panel-head">
            <div><h2>Candidate pipeline</h2><p>Authorized candidates are grouped by their persisted workflow stage. Stage changes use the same server policy as the candidate register.</p></div>
            <small>{{ number_format(collect($pipelineColumns)->sum(fn ($column): int => $column->total)) }} matching candidates</small>
        </header>

        <div class="people-pipeline-viewport" tabindex="0" aria-label="Candidate pipeline columns">
            <div class="people-pipeline-track">
                @foreach($pipelineColumns as $column)
                    <section class="people-pipeline-column" aria-labelledby="pipeline-stage-{{ $column->stage }}">
                        <header class="people-pipeline-column-head">
                            <span class="people-pipeline-stage-dot {{ $column->tone }}" aria-hidden="true"></span>
                            <h3 id="pipeline-stage-{{ $column->stage }}">{{ $column->label }}</h3>
                            <span class="people-pipeline-count" aria-label="{{ $column->total }} candidates">{{ $column->total }}</span>
                        </header>
                        <div class="people-pipeline-column-body">
                            @forelse($column->candidates as $candidate)
                                <article class="people-pipeline-card">
                                    <header>
                                        <span class="people-avatar">{{ $candidate->initials }}</span>
                                        <div><h4>{{ $candidate->name }}</h4><p>{{ $candidate->code }} · {{ $candidate->source }}</p></div>
                                    </header>
                                    <dl>
                                        <div><dt>Opening</dt><dd>{{ $candidate->openingTitle }}</dd></div>
                                        <div><dt>Department</dt><dd>{{ $candidate->department }}</dd></div>
                                        <div><dt>Owner</dt><dd>{{ $candidate->owner }}</dd></div>
                                        <div><dt>Progress</dt><dd>{{ $candidate->interviewCount }} interviews · {{ $candidate->offerStatus }}</dd></div>
                                    </dl>
                                    @if($candidate->allowedStages)
                                        <form method="POST" action="{{ route('recruitment.candidates.stage', $candidate->id) }}" class="people-pipeline-action">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="return_to" value="pipeline">
                                            <label class="people-field"><span class="sr-only">Move {{ $candidate->name }} to</span><select name="stage" required>@foreach($candidate->allowedStages as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
                                            <label class="people-field"><span class="sr-only">Transition note for {{ $candidate->name }}</span><input name="transition_note" maxlength="2000" placeholder="Transition note"></label>
                                            <button class="people-button is-primary" type="submit">Move candidate</button>
                                        </form>
                                    @elseif($candidate->canConvert)
                                        <span class="people-status is-warning">Ready for conversion</span>
                                    @else
                                        <span class="people-status is-muted">Workflow controlled</span>
                                    @endif
                                </article>
                            @empty
                                <div class="people-pipeline-empty"><span>No candidates match this stage and the active filters.</span></div>
                            @endforelse

                            @if($column->candidates->count() < $column->total)
                                <a class="people-button people-pipeline-more" href="{{ route('recruitment.candidates.index', array_filter([
                                    'stage' => $column->stage,
                                    'source' => $filters['source'] ?? null,
                                    'search' => $filters['search'] ?? null,
                                    'per_page' => $column->limit,
                                ], fn ($value): bool => $value !== null && $value !== '')) }}">
                                    View all {{ $column->total }}
                                </a>
                            @endif
                        </div>
                    </section>
                @endforeach
            </div>
        </div>
    </article>
</section>
