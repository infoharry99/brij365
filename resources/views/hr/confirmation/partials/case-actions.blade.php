@php($mobile = $mobile ?? false)

@if(($actions['canRecommend'] ?? false) || ($actions['canDecide'] ?? false))
    <div class="{{ $mobile ? 'people-ops-mobile-actions' : 'people-ops-record-actions' }}">
        @if($actions['canRecommend'] ?? false)
            <details>
                <summary class="{{ $mobile ? 'people-button' : 'people-ops-action-link' }}">Recommend</summary>
                <form
                    method="POST"
                    action="{{ route('hr.confirmation-cases.recommend', $case) }}"
                    class="people-form-grid"
                    x-data="serverFormState"
                    x-on:submit="beginSubmit"
                    x-bind:aria-busy="busyAria"
                    data-idle-label="Submit recommendation"
                    data-busy-label="Submitting..."
                >
                    @csrf
                    @method('PATCH')
                    <label class="people-field"><span>Recommendation</span><select class="people-control" name="manager_recommendation" required><option value="confirm">Confirm</option><option value="extend">Extend</option><option value="reject">Reject</option></select></label>
                    <label class="people-field is-wide"><span>Comments</span><textarea class="people-control" name="manager_comments" required></textarea></label>
                    <button class="people-button is-primary" type="submit" x-bind:disabled="busy"><span x-text="submitLabel">Submit recommendation</span></button>
                </form>
            </details>
        @endif

        @if($actions['canDecide'] ?? false)
            <details>
                <summary class="{{ $mobile ? 'people-button' : 'people-ops-action-link' }}">HR decision</summary>
                <form
                    method="POST"
                    action="{{ route('hr.confirmation-cases.decide', $case) }}"
                    class="people-form-grid"
                    x-data="serverFormState"
                    x-on:submit="beginSubmit"
                    x-bind:aria-busy="busyAria"
                    data-idle-label="Record HR decision"
                    data-busy-label="Recording..."
                >
                    @csrf
                    @method('PATCH')
                    <label class="people-field"><span>Decision</span><select class="people-control" name="hr_decision" required><option value="confirm">Confirm</option><option value="extend">Extend</option><option value="reject">Reject</option></select></label>
                    <label class="people-field"><span>Effective date</span><input class="people-control" type="date" name="confirmation_effective_on"></label>
                    <label class="people-field"><span>Extended until</span><input class="people-control" type="date" name="extended_until"></label>
                    <label class="people-field"><span>Letter reference</span><input class="people-control" name="confirmation_letter_reference"></label>
                    <label class="people-field is-wide"><span>Comments</span><textarea class="people-control" name="hr_comments" required></textarea></label>
                    <button class="people-button is-primary" type="submit" x-bind:disabled="busy"><span x-text="submitLabel">Record decision</span></button>
                </form>
            </details>
        @endif
    </div>
@elseif(!$mobile)
    <span class="people-subtext">No action</span>
@endif
