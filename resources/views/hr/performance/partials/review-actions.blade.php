@php
    $mobile = $mobile ?? false;
    $managerComponents = [
        'kpi_achievement' => 'KPI achievement',
        'kra_achievement' => 'KRA achievement',
        'competencies' => 'Competencies',
        'behaviour' => 'Behaviour',
    ];
    $hasAction = $review->canSubmitSelf
        || $review->canSubmitManager
        || $review->canCalibrate
        || $review->canRequestOverride
        || $review->canDecideOverride
        || $review->canClose;
@endphp

@if($hasAction)
    <div class="{{ $mobile ? 'people-ops-mobile-actions' : 'people-ops-record-actions' }}">
        @if($review->canSubmitSelf)
            <details>
                <summary class="{{ $mobile ? 'people-button' : 'people-ops-action-link' }}">Self review</summary>
                <form method="POST" action="{{ route('hr.performance-reviews.self-submit', $review->id) }}" class="people-form-grid" x-data="serverFormState" x-on:submit="beginSubmit" x-bind:aria-busy="busyAria" data-idle-label="Submit self review" data-busy-label="Submitting...">
                    @csrf
                    @method('PATCH')
                    <label class="people-field">
                        <span>Self score ({{ $review->ratingScaleMin }} to {{ $review->ratingScaleMax }})</span>
                        <input class="people-control" type="number" min="{{ $review->ratingScaleMin }}" max="{{ $review->ratingScaleMax }}" step="0.01" name="self_score" required>
                    </label>
                    <label class="people-field is-wide"><span>Key strengths</span><textarea class="people-control" name="strengths"></textarea></label>
                    <label class="people-field is-wide"><span>Improvement areas</span><textarea class="people-control" name="improvement_areas"></textarea></label>
                    <button class="people-button is-primary" type="submit" x-bind:disabled="busy"><span x-text="submitLabel">Submit self review</span></button>
                </form>
            </details>
        @endif

        @if($review->canSubmitManager)
            <details>
                <summary class="{{ $mobile ? 'people-button' : 'people-ops-action-link' }}">Manager review</summary>
                <form method="POST" action="{{ route('hr.performance-reviews.manager-submit', $review->id) }}" class="people-form-grid" x-data="serverFormState" x-on:submit="beginSubmit" x-bind:aria-busy="busyAria" data-idle-label="Submit manager review" data-busy-label="Submitting...">
                    @csrf
                    @method('PATCH')
                    <label class="people-field">
                        <span>Overall manager score ({{ $review->ratingScaleMin }} to {{ $review->ratingScaleMax }})</span>
                        <input class="people-control" type="number" min="{{ $review->ratingScaleMin }}" max="{{ $review->ratingScaleMax }}" step="0.01" name="manager_score" required>
                    </label>
                    @foreach($managerComponents as $input => $label)
                        <label class="people-field">
                            <span>{{ $label }} ({{ $review->ratingScaleMin }} to {{ $review->ratingScaleMax }})</span>
                            <input class="people-control" type="number" min="{{ $review->ratingScaleMin }}" max="{{ $review->ratingScaleMax }}" step="0.01" name="scoring_inputs[{{ $input }}]" required>
                        </label>
                    @endforeach
                    <p class="people-subtext is-wide">Attendance is resolved from finalized attendance-period snapshots for the complete review period. Managers cannot enter or override it.</p>
                    <label class="people-field is-wide"><span>Manager comments</span><textarea class="people-control" name="manager_comments" required></textarea></label>
                    <button class="people-button is-primary" type="submit" x-bind:disabled="busy"><span x-text="submitLabel">Submit manager review</span></button>
                </form>
            </details>
        @endif

        @if($review->canCalibrate)
            <details>
                <summary class="{{ $mobile ? 'people-button' : 'people-ops-action-link' }}">Calculate score</summary>
                <form method="POST" action="{{ route('hr.performance-reviews.calibrate', $review->id) }}" class="people-form-grid" x-data="serverFormState" x-on:submit="beginSubmit" x-bind:aria-busy="busyAria" data-idle-label="Calculate governed score" data-busy-label="Calculating...">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="lock_version" value="{{ $review->lockVersion }}">
                    <label class="people-field">
                        <span>HR calibration ({{ $review->ratingScaleMin }} to {{ $review->ratingScaleMax }})</span>
                        <input class="people-control" type="number" min="{{ $review->ratingScaleMin }}" max="{{ $review->ratingScaleMax }}" step="0.01" name="hr_calibration" required>
                    </label>
                    <label class="people-field is-wide"><span>Calibration evidence and comments</span><textarea class="people-control" name="hr_comments" minlength="12" maxlength="3000" required></textarea></label>
                    <p class="people-subtext is-wide">The active Employee Performance rule is applied on the server and its version, checksum, inputs and calculation trace are pinned to this review.</p>
                    <button class="people-button is-primary" type="submit" x-bind:disabled="busy"><span x-text="submitLabel">Calculate governed score</span></button>
                </form>
            </details>
        @endif

        @if($review->canRequestOverride)
            <details>
                <summary class="{{ $mobile ? 'people-button' : 'people-ops-action-link' }}">Request override</summary>
                <form method="POST" action="{{ route('hr.performance-reviews.score-overrides.store', $review->id) }}" class="people-form-grid" x-data="serverFormState" x-on:submit="beginSubmit" x-bind:aria-busy="busyAria" data-idle-label="Request separate approval" data-busy-label="Submitting...">
                    @csrf
                    <input type="hidden" name="lock_version" value="{{ $review->lockVersion }}">
                    <div class="people-alert is-info is-wide">Calculated result: <strong>{{ $review->formulaScore }}</strong>{{ $review->formulaRating ? ' / '.$review->formulaRating : '' }}. An override does not become final until a different authorized user approves it.</div>
                    <label class="people-field"><span>Requested normalized score (0 to 100)</span><input class="people-control" type="number" min="0" max="100" step="0.01" name="requested_score" required></label>
                    <label class="people-field is-wide"><span>Business reason</span><textarea class="people-control" name="reason" minlength="12" maxlength="2000" required></textarea></label>
                    <label class="people-field is-wide"><span>Evidence reference</span><textarea class="people-control" name="evidence" minlength="12" maxlength="3000" required></textarea></label>
                    <button class="people-button is-primary" type="submit" x-bind:disabled="busy"><span x-text="submitLabel">Request separate approval</span></button>
                </form>
            </details>
        @endif

        @if($review->canDecideOverride && $review->overrideRequestId)
            <details>
                <summary class="{{ $mobile ? 'people-button' : 'people-ops-action-link' }}">Decide override</summary>
                <div class="people-form-grid">
                    <div class="people-alert is-warning is-wide">
                        {{ $review->overrideRequester ?? 'An authorized reviewer' }} requested {{ $review->overrideRequestedScore }} / 100. Maker-checker separation is enforced.
                    </div>
                    <form method="POST" action="{{ route('hr.performance-score-overrides.approve', $review->overrideRequestId) }}" class="people-form-grid is-wide" x-data="serverFormState" x-on:submit="beginSubmit" x-bind:aria-busy="busyAria" data-idle-label="Approve override" data-busy-label="Approving...">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="lock_version" value="{{ $review->lockVersion }}">
                        <label class="people-field is-wide"><span>Approval decision reason</span><textarea class="people-control" name="decision_reason" minlength="12" maxlength="2000" required></textarea></label>
                        <button class="people-button is-primary" type="submit" x-bind:disabled="busy"><span x-text="submitLabel">Approve override</span></button>
                    </form>
                    <form method="POST" action="{{ route('hr.performance-score-overrides.reject', $review->overrideRequestId) }}" class="people-form-grid is-wide" x-data="serverFormState" x-on:submit="beginSubmit" x-bind:aria-busy="busyAria" data-idle-label="Reject override" data-busy-label="Rejecting...">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="lock_version" value="{{ $review->lockVersion }}">
                        <label class="people-field is-wide"><span>Rejection reason</span><textarea class="people-control" name="decision_reason" minlength="12" maxlength="2000" required></textarea></label>
                        <button class="people-button is-danger" type="submit" x-bind:disabled="busy"><span x-text="submitLabel">Reject override</span></button>
                    </form>
                </div>
            </details>
        @endif

        @if($review->canClose)
            <details>
                <summary class="{{ $mobile ? 'people-button' : 'people-ops-action-link' }}">Close review</summary>
                <form method="POST" action="{{ route('hr.performance-reviews.close', $review->id) }}" class="people-form-grid" x-data="serverFormState" x-on:submit="beginSubmit" x-bind:aria-busy="busyAria" data-idle-label="Close review" data-busy-label="Closing...">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="lock_version" value="{{ $review->lockVersion }}">
                    <div class="people-alert is-info is-wide">
                        Governed result: <strong>{{ $review->formulaScore }}</strong>{{ $review->formulaRating ? ' / '.$review->formulaRating : '' }} · Rule version {{ $review->scoringRuleVersion }}{{ $review->scoreIsOverride ? ' · approved override' : '' }}.
                    </div>
                    <label class="people-field is-wide"><span>HR closure comments</span><textarea class="people-control" name="hr_comments" required></textarea></label>
                    <label class="people-field is-wide"><input type="hidden" name="pip_required" value="0"><input type="checkbox" name="pip_required" value="1"> Add a governed performance-improvement plan</label>
                    <label class="people-field is-wide"><span>PIP objective (required when PIP applies)</span><textarea class="people-control" name="pip_plan[objectives][0]" maxlength="500"></textarea></label>
                    <label class="people-field"><span>PIP start</span><input class="people-control" type="date" name="pip_plan[starts_on]"></label>
                    <label class="people-field"><span>PIP end</span><input class="people-control" type="date" name="pip_plan[ends_on]"></label>
                    <label class="people-field"><span>Review frequency</span><input class="people-control" name="pip_plan[review_frequency]" maxlength="80"></label>
                    <label class="people-field"><span>PIP owner</span><input class="people-control" name="pip_plan[owner]" maxlength="120"></label>
                    <button class="people-button is-primary" type="submit" x-bind:disabled="busy"><span x-text="submitLabel">Close review from governed result</span></button>
                </form>
            </details>
        @endif
    </div>
@elseif(!$mobile)
    <span class="people-subtext">No action</span>
@endif
