@php($actionContext = $actionContext ?? 'desktop')

@if ($loan->canApprove)
    <details id="loan-approve-{{ $loan->id }}-{{ $actionContext }}">
        <summary class="people-ops-action-link" aria-label="Approve loan {{ $loan->loanNumber }} for {{ $loan->employeeName }}">Approve</summary>
        <form
            method="POST"
            action="{{ route('hr.loans.approve', $loan->id) }}"
            x-data="serverFormState"
            x-on:submit="beginSubmit"
            x-bind:aria-busy="busyAria"
            data-idle-label="Approve"
            data-busy-label="Approving…"
        >
            @csrf
            @method('PATCH')
            <input class="people-control" type="number" name="approved_amount" value="{{ $loan->approvalAmountInput }}" min="1000" step="0.01" required aria-label="Approved amount for loan {{ $loan->loanNumber }}">
            <label class="people-field"><span>Repayment starts</span><input class="people-control" type="date" name="repayment_starts_on" value="{{ $loan->repaymentStartsOnInput }}" required></label>
            <input class="people-control" name="decision_note" maxlength="1000" placeholder="Decision note" aria-label="Approval note for loan {{ $loan->loanNumber }}">
            <button class="people-button is-primary" type="submit" x-bind:disabled="busy"><span x-text="submitLabel">Approve</span></button>
        </form>
    </details>
@endif

@if ($loan->canReject)
    <details id="loan-reject-{{ $loan->id }}-{{ $actionContext }}">
        <summary class="people-ops-action-link is-danger" aria-label="Reject loan {{ $loan->loanNumber }} for {{ $loan->employeeName }}">Reject</summary>
        <form
            method="POST"
            action="{{ route('hr.loans.reject', $loan->id) }}"
            x-data="serverFormState"
            x-on:submit="beginSubmit"
            x-bind:aria-busy="busyAria"
            data-idle-label="Reject"
            data-busy-label="Rejecting…"
        >
            @csrf
            @method('PATCH')
            <textarea class="people-control" name="decision_note" maxlength="1000" required placeholder="Rejection reason" aria-label="Rejection reason for loan {{ $loan->loanNumber }}"></textarea>
            <button class="people-button" type="submit" x-bind:disabled="busy"><span x-text="submitLabel">Reject</span></button>
        </form>
    </details>
@endif

@if ($loan->canDisburse)
    <details id="loan-disburse-{{ $loan->id }}-{{ $actionContext }}">
        <summary class="people-ops-action-link" aria-label="Disburse loan {{ $loan->loanNumber }} for {{ $loan->employeeName }}">Disburse</summary>
        <form
            method="POST"
            action="{{ route('hr.loans.disburse', $loan->id) }}"
            x-data="serverFormState"
            x-on:submit="beginSubmit"
            x-bind:aria-busy="busyAria"
            data-idle-label="Disburse"
            data-busy-label="Disbursing…"
        >
            @csrf
            @method('PATCH')
            <label class="people-field"><span>Audit reference (optional)</span><input class="people-control" name="payment_reference" maxlength="120"></label>
            <textarea class="people-control" name="note" maxlength="1000" placeholder="Disbursement note" aria-label="Disbursement note for loan {{ $loan->loanNumber }}"></textarea>
            <button class="people-button is-primary" type="submit" x-bind:disabled="busy"><span x-text="submitLabel">Disburse</span></button>
        </form>
    </details>
@endif

@if (! $loan->canApprove && ! $loan->canReject && ! $loan->canDisburse)
    <span class="people-subtext">No action</span>
@endif
