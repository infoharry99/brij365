@php($mobile = $mobile ?? false)

@if(($actions['canHrApprove'] ?? false) || ($actions['canFinanceApprove'] ?? false) || ($actions['canComplete'] ?? false))
    <div class="{{ $mobile ? 'people-ops-mobile-actions' : 'people-ops-record-actions' }}">
        @if($actions['canHrApprove'] ?? false)
            <details>
                <summary class="{{ $mobile ? 'people-button' : 'people-ops-action-link' }}">HR approval</summary>
                <form method="POST" action="{{ route('hr.separation-settlements.hr-approve', $settlement) }}" class="people-form-grid" x-data="serverFormState" x-on:submit="beginSubmit" x-bind:aria-busy="busyAria" data-idle-label="Approve as HR" data-busy-label="Approving...">
                    @csrf
                    @method('PATCH')
                    <label class="people-field is-wide"><span>HR approval note</span><input class="people-control" name="note" maxlength="1000"></label>
                    <button class="people-button is-primary" type="submit" x-bind:disabled="busy"><span x-text="submitLabel">Approve as HR</span></button>
                </form>
            </details>
        @endif

        @if($actions['canFinanceApprove'] ?? false)
            <details>
                <summary class="{{ $mobile ? 'people-button' : 'people-ops-action-link' }}">Finance approval</summary>
                <form method="POST" action="{{ route('hr.separation-settlements.finance-approve', $settlement) }}" class="people-form-grid" x-data="serverFormState" x-on:submit="beginSubmit" x-bind:aria-busy="busyAria" data-idle-label="Approve as Finance" data-busy-label="Approving...">
                    @csrf
                    @method('PATCH')
                    <label class="people-field is-wide"><span>Finance approval note</span><input class="people-control" name="note" maxlength="1000"></label>
                    <button class="people-button is-primary" type="submit" x-bind:disabled="busy"><span x-text="submitLabel">Approve as Finance</span></button>
                </form>
            </details>
        @endif

        @if($actions['canComplete'] ?? false)
            <details>
                <summary class="{{ $mobile ? 'people-button' : 'people-ops-action-link' }}">Complete settlement</summary>
                <form method="POST" action="{{ route('hr.separation-settlements.complete', $settlement) }}" class="people-form-grid" x-data="serverFormState" x-on:submit="beginSubmit" x-bind:aria-busy="busyAria" data-idle-label="Complete settlement" data-busy-label="Completing...">
                    @csrf
                    @method('PATCH')
                    <label class="people-field"><span>Payment reference</span><input class="people-control" name="payment_reference" maxlength="120" required></label>
                    <label class="people-field is-wide"><span>Completion note</span><input class="people-control" name="note" maxlength="1000"></label>
                    <button class="people-button is-primary" type="submit" x-bind:disabled="busy"><span x-text="submitLabel">Complete settlement</span></button>
                </form>
            </details>
        @endif
    </div>
@elseif(!$mobile)
    <span class="people-subtext">No action</span>
@endif
