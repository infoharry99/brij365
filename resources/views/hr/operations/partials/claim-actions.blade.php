@php($actionContext = $actionContext ?? 'desktop')

@if ($claim->canApprove)
    <details id="claim-approve-{{ $claim->id }}-{{ $actionContext }}">
        <summary class="people-ops-action-link" aria-label="Approve claim {{ $claim->claimNumber }} for {{ $claim->employeeName }}">Approve</summary>
        <form
            method="POST"
            action="{{ route('hr.expense-claims.approve', $claim->id) }}"
            x-data="serverFormState"
            x-on:submit="beginSubmit"
            x-bind:aria-busy="busyAria"
            data-idle-label="Approve"
            data-busy-label="Approving…"
        >
            @csrf
            @method('PATCH')
            <input class="people-control" type="number" name="approved_amount" value="{{ $claim->approvalAmountInput }}" min="1" step="0.01" required aria-label="Approved amount for claim {{ $claim->claimNumber }}">
            <input class="people-control" name="decision_note" maxlength="1000" placeholder="Decision note" aria-label="Approval note for claim {{ $claim->claimNumber }}">
            <button class="people-button is-primary" type="submit" x-bind:disabled="busy"><span x-text="submitLabel">Approve</span></button>
        </form>
    </details>
@endif

@if ($claim->canReject)
    <details id="claim-reject-{{ $claim->id }}-{{ $actionContext }}">
        <summary class="people-ops-action-link is-danger" aria-label="Reject claim {{ $claim->claimNumber }} for {{ $claim->employeeName }}">Reject</summary>
        <form
            method="POST"
            action="{{ route('hr.expense-claims.reject', $claim->id) }}"
            x-data="serverFormState"
            x-on:submit="beginSubmit"
            x-bind:aria-busy="busyAria"
            data-idle-label="Reject"
            data-busy-label="Rejecting…"
        >
            @csrf
            @method('PATCH')
            <textarea class="people-control" name="decision_note" maxlength="1000" required placeholder="Rejection reason" aria-label="Rejection reason for claim {{ $claim->claimNumber }}"></textarea>
            <button class="people-button" type="submit" x-bind:disabled="busy"><span x-text="submitLabel">Reject</span></button>
        </form>
    </details>
@endif

@if ($claim->canPay)
    <details id="claim-pay-{{ $claim->id }}-{{ $actionContext }}">
        <summary class="people-ops-action-link" aria-label="Mark claim {{ $claim->claimNumber }} as paid">Mark paid</summary>
        <form
            method="POST"
            action="{{ route('hr.expense-claims.pay', $claim->id) }}"
            x-data="serverFormState"
            x-on:submit="beginSubmit"
            x-bind:aria-busy="busyAria"
            data-idle-label="Mark paid"
            data-busy-label="Recording payment…"
        >
            @csrf
            @method('PATCH')
            <label class="people-field"><span>Audit reference (optional)</span><input class="people-control" name="payment_reference" maxlength="120"></label>
            <textarea class="people-control" name="note" maxlength="1000" placeholder="Payment note" aria-label="Payment note for claim {{ $claim->claimNumber }}"></textarea>
            <button class="people-button is-primary" type="submit" x-bind:disabled="busy"><span x-text="submitLabel">Mark paid</span></button>
        </form>
    </details>
@endif

@if (! $claim->canApprove && ! $claim->canReject && ! $claim->canPay)
    <span class="people-subtext">No action</span>
@endif
