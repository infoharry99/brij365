<?php
namespace App\Http\Controllers\Mailbox;

use App\Application\Mailbox\Actions\ProcessUnifiedMailboxCompose;
use App\Http\Controllers\Controller;
use App\Http\Requests\Mailbox\StoreUnifiedMailboxComposeRequest;
use App\Models\InternalMailboxAttachment;
use App\Models\InternalMailboxDispatch;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UnifiedMailboxComposeController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function store(
        StoreUnifiedMailboxComposeRequest $request,
        ProcessUnifiedMailboxCompose $action,
    ): RedirectResponse|JsonResponse {
        $result = $action->execute($request->toDto());
        $message = match ($result->state) {
            'draft' => 'Draft saved.',
            'scheduled' => 'Message scheduled.',
            'failed' => 'Delivery failed and the message was retained as a draft.',
            default => 'Message sent.',
        };

        $this->audit->record(
            $request->user(),
            match ($result->state) {
                'draft' => 'mailbox.draft.saved',
                'scheduled' => 'mailbox.message.scheduled',
                'failed' => 'mailbox.message.failed',
                default => 'mailbox.message.sent',
            },
            $message,
            $result->record,
            [
                'source' => $result->source,
                'state' => $result->state,
                'attachment_count' => count($request->file('attachments', [])),
            ],
            $request,
        );

        if ($request->expectsJson()) {
            return response()->json([
                'data' => [
                    'id' => $result->record->getKey(),
                    'source' => $result->source,
                    'state' => $result->state,
                    'lock_version' => $result->record->lock_version ?? null,
                    'updated_at' => $result->record->updated_at?->toISOString(),
                ],
                'message' => $message,
            ]);
        }

        return redirect()->route('collaboration.messages.index')->with('status', $message);
    }

    public function discard(Request $request, InternalMailboxDispatch $internalMailboxDispatch): RedirectResponse|JsonResponse
    {
        $this->authorize('delete', $internalMailboxDispatch);

        foreach ($internalMailboxDispatch->attachments as $file) {
            Storage::disk($file->disk)->delete($file->path);
        }

        $this->audit->record(
            $request->user(),
            'mailbox.draft.discarded',
            'Discarded an internal mailbox draft',
            $internalMailboxDispatch,
            ['state' => $internalMailboxDispatch->state],
            $request,
        );
        $internalMailboxDispatch->delete();

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Draft discarded.']);
        }

        return redirect()->route('collaboration.messages.index')->with('status', 'Draft discarded.');
    }

    public function attachment(Request $request, InternalMailboxAttachment $internalMailboxAttachment): StreamedResponse
    {
        $this->authorize('view', $internalMailboxAttachment);
        abort_unless(Storage::disk($internalMailboxAttachment->disk)->exists($internalMailboxAttachment->path), 404);

        $this->audit->record(
            $request->user(),
            'mailbox.attachment.downloaded',
            'Downloaded an internal mailbox attachment',
            $internalMailboxAttachment,
            ['mime_type' => $internalMailboxAttachment->mime_type, 'size_bytes' => $internalMailboxAttachment->size_bytes],
            $request,
        );

        return Storage::disk($internalMailboxAttachment->disk)->download(
            $internalMailboxAttachment->path,
            $internalMailboxAttachment->original_filename,
            ['Content-Type' => $internalMailboxAttachment->mime_type],
        );
    }
}
