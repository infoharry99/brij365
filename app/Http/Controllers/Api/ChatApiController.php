<?php

namespace App\Http\Controllers\Api;

use App\Application\Collaboration\Data\ChatCommandData;
use App\Application\Collaboration\Actions\CreateChatConversation;
use App\Application\Collaboration\Actions\SendChatMessage;
use App\Application\Collaboration\Actions\ChangeChatMessageReaction;
use App\Application\Collaboration\Actions\DeleteChatMessage;
use App\Http\Controllers\Controller;
use App\Http\Resources\ChatConversationResource;
use App\Http\Resources\ChatMessageResource;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatMessageAttachment;
use App\Models\User;
use App\Services\Collaboration\ChatConnectService;
use App\Services\Security\CompanyScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ChatApiController extends Controller
{
    public function __construct(
        private readonly ChatConnectService $chat,
    ) {
    }

    // -------------------------------------------------------------------------
    // Conversations
    // -------------------------------------------------------------------------

    /**
     * GET /api/chat/conversations
     *
     * List all conversations the authenticated user is a member of.
     *
     * Query params:
     *   - type    : filter by conversation type (direct_message, group_chat, etc.)
     *   - view    : unread | mentions | dms | channels
     *   - status  : archived
     *   - q       : full-text search
     *   - limit   : integer (default 50, max 100)
     */
    public function conversations(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'type'   => ['nullable', 'string'],
            'view'   => ['nullable', 'string', Rule::in(['unread', 'mentions', 'dms', 'channels'])],
            'status' => ['nullable', 'string', Rule::in(['active', 'archived'])],
            'q'      => ['nullable', 'string', 'max:200'],
            'limit'  => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $limit = (int) ($filters['limit'] ?? 50);
        unset($filters['limit']);

        $conversations = $this->chat->conversationsFor($user, $filters, $limit);

        return response()->json([
            'data' => ChatConversationResource::collection($conversations)->resolve($request),
        ]);
    }

    /**
     * GET /api/chat/conversations/{conversation}
     *
     * Show a single conversation with members and latest messages.
     */
    public function showConversation(Request $request, ChatConversation $conversation): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $conversation = $this->chat->viewableConversation($user, $conversation->id);
        $conversation->load(['company', 'project', 'owner', 'activeMembers.user.role']);

        return response()->json([
            'data' => (new ChatConversationResource($conversation))->resolve($request),
        ]);
    }

    public function storeConversation(Request $request, CreateChatConversation $action): JsonResponse
    {
        $data = $request->validate([
            'type'            => ['required', 'string', Rule::in([
                'direct_message', 'group_chat', 'department_channel',
                'project_channel', 'unit_conversation', 'lead_conversation',
                'approval_thread', 'voucher_thread', 'task_thread', 'announcement_channel',
            ])],
            'title'           => ['nullable', 'string', 'max:160'],
            'description'     => ['nullable', 'string', 'max:500'],
            'department'      => ['nullable', 'string', 'max:120'],
            'project_id'      => ['nullable', 'integer', 'exists:projects,id'],
            'member_user_ids' => ['required', 'array', 'min:1', 'max:25'],
            'member_user_ids.*' => ['required', 'integer', 'distinct', Rule::exists('users', 'id')],
            'body'              => ['nullable', 'string', 'max:10000'],
            'priority'          => ['nullable', 'string', Rule::in(['low', 'normal', 'high', 'critical'])],
        ]);

        /** @var User $user */
        $user = $request->user();

        // Basic client-side guard (full authorization is done inside the service)
        if ($data['type'] === 'direct_message' && count($data['member_user_ids']) !== 1) {
            throw ValidationException::withMessages([
                'member_user_ids' => 'Direct messages require exactly one recipient.',
            ]);
        }

        if ($data['type'] !== 'direct_message' && empty(trim((string) ($data['title'] ?? '')))) {
            throw ValidationException::withMessages([
                'title' => 'A title is required for group conversations and channels.',
            ]);
        }

        $command = new ChatCommandData($data, $user, $request);
        $result  = $action->execute($command);

        $conversation = $result->conversation;
        $created      = $conversation->wasRecentlyCreated;

        return response()->json([
            'message'  => $created ? 'Conversation created.' : 'Existing conversation opened.',
            'data'     => (new ChatConversationResource($conversation))->resolve($request),
            'messages' => ChatMessageResource::collection($result->messages)->resolve($request),
        ], $created ? Response::HTTP_CREATED : Response::HTTP_OK);
    }

    // -------------------------------------------------------------------------
    // Messages
    // -------------------------------------------------------------------------

    /**
     * GET /api/chat/conversations/{conversation}/messages
     *
     * Paginated message list for a conversation.
     *
     * Query params:
     *   - limit  : integer 1–200 (default 80)
     *   - before : message ID — load messages older than this (for pagination)
     */
    public function messages(Request $request, ChatConversation $conversation): JsonResponse
    {
        $params = $request->validate([
            'limit'  => ['nullable', 'integer', 'min:1', 'max:200'],
            'before' => ['nullable', 'integer', 'exists:chat_messages,id'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $limit = (int) ($params['limit'] ?? 80);

        // If 'before' is provided, load older messages for infinite scroll
        if (! empty($params['before'])) {
            $beforeId = (int) $params['before'];

            // Authorize viewable conversation (throws if not member)
            $this->chat->viewableConversation($user, $conversation->id);

            $messages = ChatMessage::query()
                ->with(['sender.role', 'parent.sender', 'attachments', 'poll.options.votes', 'poll.votes', 'reactions', 'reads'])
                ->where('chat_conversation_id', $conversation->id)
                ->where('id', '<', $beforeId)
                ->latest()
                ->limit($limit)
                ->get()
                ->sortBy('created_at')
                ->values();
        } else {
            $messages = $this->chat->activeMessages($conversation, $user, $limit);
        }

        $oldest = $messages->first();

        return response()->json([
            'data'            => ChatMessageResource::collection($messages)->resolve($request),
            'meta'            => [
                'count'    => $messages->count(),
                'limit'    => $limit,
                'has_more' => $messages->count() === $limit,
                'oldest_id' => $oldest?->id,
            ],
        ]);
    }

    /**
     * POST /api/chat/conversations/{conversation}/messages
     *
     * Send a message in a conversation.
     *
     * Body params (multipart/form-data for file uploads):
     *   - body              : string (required if no attachments)
     *   - message_type      : text | file | voice_note
     *   - parent_message_id : integer (for replies)
     *   - priority          : low | normal | high | critical
     *   - metadata          : array (mentions, forwarded_from_*)
     *   - attachments[]     : files (max 10, 25 MB each)
     *   - duration_seconds  : integer (for voice notes)
     */
    public function sendMessage(Request $request, ChatConversation $conversation, SendChatMessage $action): JsonResponse
    {
        $data = $request->validate([
            'body'              => ['nullable', 'string', 'max:10000'],
            'message_type'      => ['nullable', 'string', Rule::in(['text', 'file', 'voice_note'])],
            'parent_message_id' => ['nullable', 'integer', Rule::exists('chat_messages', 'id')],
            'priority'          => ['nullable', 'string', Rule::in(['low', 'normal', 'high', 'critical'])],
            'metadata'          => ['nullable', 'array'],
            'metadata.mentions' => ['nullable', 'array', 'max:25'],
            'metadata.mentions.*' => ['integer', 'distinct', Rule::exists('users', 'id')],
            'metadata.forwarded_from_message_id'      => ['nullable', 'integer', Rule::exists('chat_messages', 'id')],
            'metadata.forwarded_from_conversation_id' => ['nullable', 'integer', Rule::exists('chat_conversations', 'id')],
            'attachments'       => ['nullable', 'array', 'max:10'],
            'attachments.*'     => [
                'file', 'max:25600',
                'mimetypes:image/jpeg,image/png,image/webp,image/gif,image/bmp,image/svg+xml,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation,text/plain,text/csv,application/zip,application/x-zip-compressed,application/x-rar-compressed,application/x-7z-compressed,audio/webm,audio/ogg,audio/oga,audio/mpeg,audio/mp3,audio/mp4,audio/m4a,audio/x-m4a,audio/mp4a-latm,audio/x-mp4a,audio/aac,audio/x-aac,audio/wav,audio/x-wav,audio/wave,audio/vnd.wave,audio/3gpp,audio/3gpp2,audio/x-3gpp,video/3gpp,audio/amr,audio/x-amr,audio/flac,audio/x-flac,audio/opus,audio/x-ms-wma,audio/wma,application/ogg,application/x-ogg,application/octet-stream',
            ],
            'duration_seconds'  => ['nullable', 'integer', 'min:0', 'max:600'],
        ]);

        $hasBody        = trim((string) ($data['body'] ?? '')) !== '';
        $hasAttachments = $request->hasFile('attachments');

        if (! $hasBody && ! $hasAttachments) {
            throw ValidationException::withMessages([
                'body' => 'Enter a message or attach a file.',
            ]);
        }

        /** @var User $user */
        $user    = $request->user();
        $command = new ChatCommandData($data, $user, $request);
        $message = $action->execute($conversation, $command);

        return response()->json([
            'message' => 'Message sent.',
            'data'    => (new ChatMessageResource($message))->resolve($request),
        ], 201);
    }

    /**
     * PATCH /api/chat/conversations/{conversation}/read
     *
     * Mark all messages in a conversation as read.
     */
    public function markRead(Request $request, ChatConversation $conversation): JsonResponse
    {
        /** @var User $user */
        $user    = $request->user();
        $updated = $this->chat->markRead($conversation, $user, $request);

        return response()->json([
            'message'          => 'Conversation marked as read.',
            'updated_messages' => $updated,
            'unread_count'     => 0,
        ]);
    }

    /**
     * PATCH /api/chat/messages/{message}/reaction
     *
     * Toggle an emoji reaction on a message.
     *
     * Body:
     *   - emoji  : string (required)
     *   - action : toggle | add | remove (default: toggle)
     */
    public function reaction(Request $request, ChatMessage $message, ChangeChatMessageReaction $action): JsonResponse
    {
        $data = $request->validate([
            'emoji'  => ['required', 'string', 'max:10'],
            'action' => ['nullable', 'string', Rule::in(['toggle', 'add', 'remove'])],
        ]);

        /** @var User $user */
        $user    = $request->user();
        $command = new ChatCommandData($data, $user, $request);
        $updated = $action->execute($message, $command);

        return response()->json([
            'message' => 'Reaction updated.',
            'data'    => (new ChatMessageResource($updated))->resolve($request),
        ]);
    }

    /**
     * DELETE /api/chat/messages/{message}
     *
     * Soft delete a message (allowed by message sender or conversation manager).
     */
    public function deleteMessage(Request $request, ChatMessage $message, DeleteChatMessage $action): JsonResponse
    {
        $action->execute($message, $request->user(), $request);

        return response()->json([
            'message'    => 'Message deleted.',
            'message_id' => $message->id,
        ]);
    }

    /**
     * GET /api/chat/attachments/{attachment}/download
     * GET /api/chat/conversations/{conversation}/attachments/{attachment}/download
     *
     * Download a chat attachment (streams the file).
     */
    public function downloadAttachment(Request $request, ChatMessageAttachment $attachment): Response
    {
        /** @var User $user */
        $user = $request->user();
        $message = $attachment->message()->with('conversation')->first();
        if ($message?->chat_conversation_id) {
            $this->chat->viewableConversation($user, $message->chat_conversation_id);
        }

        abort_if($attachment->scan_status === 'blocked', 423, 'This attachment is unavailable.');
        $disk = $attachment->disk ?: 'local';
        abort_unless(Storage::disk($disk)->exists($attachment->path), 404);

        return Storage::disk($disk)->download(
            $attachment->path,
            $attachment->original_filename,
            [
                'Cache-Control'         => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    /**
     * GET /api/chat/attachments/{attachment}/preview
     *
     * Preview a chat attachment (inline stream).
     */
    public function previewAttachment(Request $request, ChatMessageAttachment $attachment): Response
    {
        /** @var User $user */
        $user = $request->user();
        $message = $attachment->message()->with('conversation')->first();
        if ($message?->chat_conversation_id) {
            $this->chat->viewableConversation($user, $message->chat_conversation_id);
        }

        abort_if($attachment->scan_status === 'blocked', 423, 'This attachment is unavailable.');
        $disk = $attachment->disk ?: 'local';
        abort_unless(Storage::disk($disk)->exists($attachment->path), 404);

        return response(Storage::disk($disk)->get($attachment->path), 200, [
            'Content-Type' => $attachment->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.addslashes($attachment->original_filename).'"',
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    // -------------------------------------------------------------------------
    // Users (for starting new conversations)
    // -------------------------------------------------------------------------

    /**
     * GET /api/chat/users
     *
     * List internal active users available to chat with.
     *
     * Query params:
     *   - q     : search by name or email
     *   - limit : integer 1–100 (default 50)
     */
    public function users(Request $request): JsonResponse
    {
        $params = $request->validate([
            'q'     => ['nullable', 'string', 'max:200'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        /** @var User $currentUser */
        $currentUser = $request->user();
        $limit       = (int) ($params['limit'] ?? 50);
        $search      = trim((string) ($params['q'] ?? ''));

        // External role slugs that are excluded from Chat Connect
        $excludedRoleSlugs = ['buyer', 'channel_partner', 'executive_partner_broker'];

        $query = User::query()
            ->with('role')
            ->where('id', '!=', $currentUser->id)
            ->where('status', 'active')
            ->whereHas('role', fn ($q) => $q->whereNotIn('slug', $excludedRoleSlugs))
            ->when($currentUser->company_id, fn ($q) => $q->where('company_id', $currentUser->company_id))
            ->when($search !== '', function ($q) use ($search) {
                $needle = '%'.addcslashes($search, '\\%_').'%';
                $q->where(fn ($q) => $q->where('name', 'like', $needle)->orWhere('email', 'like', $needle));
            })
            ->orderBy('name')
            ->limit($limit);

        $users = $query->get();

        return response()->json([
            'data' => $users->map(fn (User $u): array => [
                'id'    => $u->id,
                'name'  => $u->name,
                'email' => $u->email,
                'role'  => $u->role?->name,
                'profile_photo_url' => $u->profile_photo_path
                    ? asset('storage/'.$u->profile_photo_path)
                    : null,
            ])->values()->all(),
            'meta' => [
                'count' => $users->count(),
                'limit' => $limit,
            ],
        ]);
    }
}
