@php($actionContext = $actionContext ?? 'desktop')

@if ($document->canDownload)
    <a
        class="{{ $actionContext === 'mobile' ? 'people-button' : 'people-ops-action-link' }}"
        href="{{ route('documents.download', $document->id) }}"
        aria-label="Download {{ $document->title }} for {{ $document->employeeName }}"
    >Download</a>
@endif

@if ($document->canApprove)
    <details id="document-approve-{{ $document->id }}-{{ $actionContext }}">
        <summary class="people-ops-action-link" aria-label="Approve document {{ $document->documentNumber }} for {{ $document->employeeName }}">Approve</summary>
        <form
            method="POST"
            action="{{ route('hr.employees.documents.approve', [$document->employeeId, $document->id]) }}"
            x-data="serverFormState"
            x-on:submit="beginSubmit"
            x-bind:aria-busy="busyAria"
            data-idle-label="Approve"
            data-busy-label="Approving…"
        >
            @csrf
            @method('PATCH')
            <input class="people-control" name="approval_note" maxlength="1000" placeholder="Approval note" aria-label="Approval note for document {{ $document->documentNumber }}">
            <button class="people-button is-primary" type="submit" x-bind:disabled="busy"><span x-text="submitLabel">Approve</span></button>
        </form>
    </details>
@endif

@if (! $document->canDownload && ! $document->canApprove)
    <span class="people-subtext">No action</span>
@endif
