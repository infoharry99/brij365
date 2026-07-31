<section class="people-ops-stack" data-recruitment-surface="openings">
    @if ($abilities['canCreateOpening'])
        <details id="recruitment-create" class="people-ops-panel" @if($errors->any()) open @endif>
            <summary class="people-ops-panel-head">
                <div><h2>Create job opening</h2><p>Submit a company-scoped requisition for independent approval.</p></div>
                <span class="people-button"><i class="fa-solid fa-plus" aria-hidden="true"></i> Requisition</span>
            </summary>
            <form method="POST" action="{{ route('recruitment.job-openings.store') }}" class="people-form-grid people-ops-panel-body">
                @csrf
                <x-forms.company-context :companies="$companies" :selected="old('company_id', $companies->first()?->id)" required />
                <label class="people-field">Branch<select name="branch_id"><option value="">No branch</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected((string) old('branch_id') === (string) $branch->id)>{{ $branch->code }} · {{ $branch->name }}</option>@endforeach</select></label>
                <label class="people-field">Project<select name="project_id"><option value="">No project</option>@foreach($projects as $project)<option value="{{ $project->id }}" @selected((string) old('project_id') === (string) $project->id)>{{ $project->code }} · {{ $project->name }}</option>@endforeach</select></label>
                <label class="people-field">Title<input name="title" value="{{ old('title') }}" maxlength="255" required></label>
                <label class="people-field">Department<input name="department" value="{{ old('department') }}" maxlength="120" required></label>
                <label class="people-field">Designation<input name="designation" value="{{ old('designation') }}" maxlength="120" required></label>
                <label class="people-field">Positions<input type="number" name="positions" value="{{ old('positions', 1) }}" min="1" max="200" required></label>
                <label class="people-field">Employment type<select name="employment_type" required>@foreach($employmentTypes as $value => $label)<option value="{{ $value }}" @selected(old('employment_type', 'full_time') === $value)>{{ $label }}</option>@endforeach</select></label>
                <label class="people-field">Work location<input name="work_location" value="{{ old('work_location') }}" maxlength="255"></label>
                <label class="people-field">Minimum CTC<input type="number" name="budget_min_ctc" value="{{ old('budget_min_ctc') }}" min="0" step="0.01"></label>
                <label class="people-field">Maximum CTC<input type="number" name="budget_max_ctc" value="{{ old('budget_max_ctc') }}" min="0" step="0.01"></label>
                <label class="people-field">Target hiring date<input type="date" name="target_hiring_date" value="{{ old('target_hiring_date') }}" min="{{ now()->toDateString() }}"></label>
                <label class="people-field">Required skill<input name="required_skills[]" value="{{ old('required_skills.0') }}" maxlength="120"></label>
                <label class="people-field is-wide">Business justification<textarea name="business_justification" maxlength="2000">{{ old('business_justification') }}</textarea></label>
                <div class="people-field is-wide"><button class="people-button is-primary" type="submit">Submit requisition</button></div>
            </form>
        </details>
    @endif

    <form method="GET" action="{{ route('recruitment.job-openings.index') }}" class="people-ops-filterbar">
        <label class="people-field">Status<select name="status"><option value="">All statuses</option>@foreach($openingStatuses as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></label>
        <label class="people-field">Department<select name="department"><option value="">All departments</option>@foreach($departments as $department)<option value="{{ $department }}" @selected(($filters['department'] ?? '') === $department)>{{ $department }}</option>@endforeach</select></label>
        <label class="people-field">Rows<select name="per_page">@foreach([15,25,50,100] as $size)<option value="{{ $size }}" @selected((int)($filters['per_page'] ?? 15) === $size)>{{ $size }}</option>@endforeach</select></label>
        <button class="people-button" type="submit"><i class="fa-solid fa-filter" aria-hidden="true"></i> Apply</button>
        @if(array_filter($filters))<a class="people-button" href="{{ route('recruitment.job-openings.index') }}">Clear</a>@endif
    </form>

    <article class="people-ops-panel has-mobile-cards">
        <header class="people-ops-panel-head"><div><h2>Job openings</h2><p>Approved and pending requisitions in your authorized scope.</p></div><small>{{ $openings->firstItem() ?? 0 }}–{{ $openings->lastItem() ?? 0 }} of {{ $openings->total() }}</small></header>
        <div class="people-ops-table-wrap"><table class="people-ops-table"><caption>Recruitment job openings</caption><thead><tr><th scope="col">Opening</th><th scope="col">Department</th><th scope="col">Positions</th><th scope="col">Target</th><th scope="col">Status</th><th scope="col">Owner</th><th scope="col">Actions</th></tr></thead><tbody>
        @forelse($openings as $opening)
            <tr><td><strong>{{ $opening->code }}</strong><small>{{ $opening->title }} · {{ $opening->designation }}</small>@if($opening->budgetRange)<small>{{ $opening->budgetRange }}</small>@endif</td><td>{{ $opening->department }}<small>{{ $opening->employmentType }} · {{ $opening->location }}</small></td><td>{{ $opening->positions }}</td><td>{{ $opening->targetDate }}</td><td><span class="people-status {{ $opening->statusTone }}">{{ $opening->statusLabel }}</span></td><td>{{ $opening->createdBy }}<small>{{ $opening->reviewedBy }}</small></td><td class="is-actions">
                @if($opening->canApprove || $opening->canReject)<div class="people-ops-list-actions">
                    @if($opening->canApprove)<form method="POST" action="{{ route('recruitment.job-openings.approve', $opening->id) }}">@csrf @method('PATCH')<button class="people-ops-action-link" type="submit">Approve</button></form>@endif
                    @if($opening->canReject)<form method="POST" action="{{ route('recruitment.job-openings.reject', $opening->id) }}">@csrf @method('PATCH')<input type="hidden" name="review_note" value="Rejected from requisition register"><button class="people-ops-action-link is-danger" type="submit">Reject</button></form>@endif
                </div>@else<span class="people-status is-muted">{{ $opening->status === 'pending_approval' ? 'Review unavailable' : 'No action' }}</span>@endif
            </td></tr>
        @empty<tr><td colspan="7"><div class="people-ops-empty"><i class="fa-solid fa-briefcase" aria-hidden="true"></i><strong>No job openings found</strong><span>Change the filters or create an authorized requisition.</span></div></td></tr>@endforelse
        </tbody></table></div>
        <div class="people-ops-mobile-list">
            @forelse($openings as $opening)
                <article class="people-ops-mobile-card">
                    <header class="people-ops-mobile-card-head"><div class="people-ops-identity"><span class="people-avatar">{{ mb_substr($opening->code, -2) }}</span><div><strong>{{ $opening->title }}</strong><small>{{ $opening->code }} · {{ $opening->department }}</small></div></div><span class="people-status {{ $opening->statusTone }}">{{ $opening->statusLabel }}</span></header>
                    <dl class="people-ops-mobile-facts"><div><dt>Designation</dt><dd>{{ $opening->designation }}</dd></div><div><dt>Positions</dt><dd>{{ $opening->positions }}</dd></div><div><dt>Target</dt><dd>{{ $opening->targetDate }}</dd></div><div><dt>Owner</dt><dd>{{ $opening->createdBy }}</dd></div></dl>
                    @if($opening->canApprove || $opening->canReject)
                        <div class="people-ops-mobile-actions">
                            @if($opening->canApprove)<form method="POST" action="{{ route('recruitment.job-openings.approve', $opening->id) }}">@csrf @method('PATCH')<button class="people-button is-primary" type="submit">Approve</button></form>@endif
                            @if($opening->canReject)<form method="POST" action="{{ route('recruitment.job-openings.reject', $opening->id) }}">@csrf @method('PATCH')<input type="hidden" name="review_note" value="Rejected from requisition register"><button class="people-button is-danger" type="submit">Reject</button></form>@endif
                        </div>
                    @elseif($opening->status === 'pending_approval')
                        <p class="people-subtext">Review unavailable for your role.</p>
                    @endif
                </article>
            @empty
                <div class="people-ops-empty"><strong>No job openings found</strong><span>Change the filters or create an authorized requisition.</span></div>
            @endforelse
        </div>
        <div class="people-pagination">{{ $openings->links() }}</div>
    </article>
</section>
