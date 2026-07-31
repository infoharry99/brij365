<?php

namespace App\Services\Collaboration;

use App\Events\Chat\ChatConversationRead;
use App\Events\Chat\ChatMessageDeleted;
use App\Events\Chat\ChatMessageSent;
use App\Events\Chat\ChatPollClosed;
use App\Events\Chat\ChatPollCreated;
use App\Events\Chat\ChatPollVoted;
use App\Models\ChatConversation;
use App\Models\ChatConversationMember;
use App\Models\ChatMessage;
use App\Models\ChatMessageAttachment;
use App\Models\ChatMessageRead;
use App\Models\ChatMessageReaction;
use App\Models\ChatPoll;
use App\Models\ChatPollOption;
use App\Models\Project;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\NotificationCenterService;
use App\Services\Security\CompanyScopeService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ChatConnectService
{
    public function __construct(
        private readonly CompanyScopeService $companyScope,
        private readonly AuditLogger $auditLogger,
        private readonly NotificationCenterService $notifications,
        private readonly ChatAccessService $access,
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     * @return Builder<ChatConversation>
     */
    public function conversationIndexQuery(User $user, array $filters = []): Builder
    {
        $this->assertCanView($user);

        $query = ChatConversation::query()
            ->with([
                'company',
                'project',
                'owner',
                'activeMembers.user.role',
                'chatMessages' => fn ($query) => $query
                    ->with(['sender.role', 'parent.sender', 'attachments', 'poll.options.votes', 'poll.votes', 'reactions', 'reads'])
                    ->latest()
                    ->limit(30),
            ])
            ->withCount('activeMembers')
            ->whereHas('activeMembers', fn (Builder $query) => $query->where('user_id', $user->id)->whereNull('removed_at'));

        $this->companyScope->apply($query, $user);

        return $query
            ->when(isset($filters['type']), fn (Builder $query) => $query->where('type', $filters['type']))
            ->when(isset($filters['project_id']), fn (Builder $query) => $query->where('project_id', $filters['project_id']))
            ->when(($filters['view'] ?? null) === 'unread', fn (Builder $query) => $query
                ->whereHas('chatMessages.reads', fn (Builder $readQuery) => $readQuery
                    ->where('user_id', $user->id)
                    ->whereNull('read_at')))
            ->when(($filters['view'] ?? null) === 'mentions', fn (Builder $query) => $query
                ->whereHas('chatMessages', fn (Builder $messageQuery) => $messageQuery
                    ->where(function (Builder $mentionQuery) use ($user): void {
                        $mentionQuery->whereJsonContains('metadata->mentions', $user->id)
                            ->orWhereJsonContains('metadata->mentions', (string) $user->id);
                    })))
            ->when(($filters['view'] ?? null) === 'dms', fn (Builder $query) => $query->where('type', 'direct_message'))
            ->when(($filters['view'] ?? null) === 'channels', fn (Builder $query) => $query->whereNotIn('type', ['direct_message', 'group_chat']))
            ->when(($filters['status'] ?? null) === 'archived', function (Builder $query) use ($user): void {
                $query->whereHas('activeMembers', fn (Builder $memberQuery) => $memberQuery
                    ->where('user_id', $user->id)
                    ->whereNotNull('archived_at'));
            })
            ->when(($filters['status'] ?? null) !== 'archived', function (Builder $query) use ($user): void {
                $query->where('status', 'active')
                    ->whereHas('activeMembers', fn (Builder $memberQuery) => $memberQuery
                        ->where('user_id', $user->id)
                        ->whereNull('archived_at'));
            })
            ->when($filters['q'] ?? null, function (Builder $query, string $search) use ($user): void {
                $needle = '%'.addcslashes(trim($search), '\\%_').'%';
                $query->where(function (Builder $query) use ($needle, $user): void {
                    $query->where('title', 'like', $needle)
                        ->orWhere('description', 'like', $needle)
                        ->orWhere('department', 'like', $needle)
                        ->orWhereHas('activeMembers', fn (Builder $memberQuery) => $memberQuery
                            ->where('user_id', '!=', $user->id)
                            ->whereHas('user', fn (Builder $userQuery) => $userQuery
                                ->where('name', 'like', $needle)
                                ->orWhere('email', 'like', $needle)
                                ->orWhereHas('role', fn (Builder $roleQuery) => $roleQuery->where('name', 'like', $needle))))
                        ->orWhereHas('chatMessages', fn (Builder $messageQuery) => $messageQuery
                            ->where('body', 'like', $needle)
                            ->orWhere('message_number', 'like', $needle)
                            ->orWhereHas('attachments', fn (Builder $attachmentQuery) => $attachmentQuery
                                ->where('original_filename', 'like', $needle)));
                });
            })
            ->latest('last_message_at')
            ->latest();
    }

    /**
     * @return EloquentCollection<int, ChatConversation>
     */
    public function conversationsFor(User $user, array $filters = [], int $limit = 50): EloquentCollection
    {
        $conversations = $this->conversationIndexQuery($user, $filters)
            ->limit($limit)
            ->get();

        $this->attachUnreadCounts($conversations, $user);

        return $conversations;
    }

    public function viewableConversation(User $user, int $conversationId): ChatConversation
    {
        $conversation = ChatConversation::query()
            ->with(['company', 'project', 'owner', 'activeMembers.user.role'])
            ->withCount('activeMembers')
            ->findOrFail($conversationId);

        $this->assertConversationViewable($conversation, $user);
        $conversation->unread_count = ChatMessageRead::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->whereHas('message', fn (Builder $query) => $query->where('chat_conversation_id', $conversation->id))
            ->count();

        return $conversation;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createConversation(array $data, User $actor, ?Request $request = null): ChatConversation
    {
        return DB::transaction(function () use ($data, $actor, $request): ChatConversation {
            $this->assertCanView($actor);
            $this->assertCanCreateType($actor, (string) $data['type']);

            $project = ! empty($data['project_id'])
                ? Project::query()->whereKey($data['project_id'])->firstOrFail()
                : null;

            if ($project && ! $this->companyScope->allows($actor, $project->company_id)) {
                throw ValidationException::withMessages(['project_id' => 'The selected project is not available for your access.']);
            }

            $members = User::query()
                ->with('role')
                ->whereIn('id', $data['member_user_ids'])
                ->get();

            $this->assertMembersAllowed($members, $actor, $project);

            $companyId = $project?->company_id ?? $actor->company_id ?? $members->first()?->company_id;
            if (! $companyId) {
                throw ValidationException::withMessages(['company_id' => 'A company is required to create a chat conversation.']);
            }

            $directPairKey = $data['type'] === 'direct_message'
                ? $this->directPairKey($companyId, $actor->id, (int) $members->firstOrFail()->id)
                : null;

            if ($directPairKey) {
                $existing = ChatConversation::query()
                    ->where('direct_pair_key', $directPairKey)
                    ->where('status', 'active')
                    ->first();

                $existing ??= ChatConversation::query()
                    ->where('company_id', $companyId)
                    ->where('type', 'direct_message')
                    ->where('status', 'active')
                    ->whereHas('activeMembers', fn (Builder $query) => $query->where('user_id', $actor->id))
                    ->whereHas('activeMembers', fn (Builder $query) => $query->where('user_id', $members->firstOrFail()->id))
                    ->has('activeMembers', '=', 2)
                    ->oldest()
                    ->first();

                if ($existing) {
                    if (! $existing->direct_pair_key) {
                        $existing->forceFill(['direct_pair_key' => $directPairKey])->save();
                    }
                    $this->restoreMember($existing, $actor, 'owner', true);
                    $this->restoreMember($existing, $members->firstOrFail(), 'member', false);

                    if (! empty($data['body'])) {
                        $this->sendMessage($existing, [
                            'body' => $data['body'],
                            'priority' => $data['priority'] ?? 'normal',
                            'metadata' => ['source' => 'chat_connect_existing_direct_message'],
                        ], $actor, $request);
                    }

                    $this->auditLogger->record($actor, 'chat.conversation.reopened', 'Opened existing direct conversation', $existing, [
                        'conversation_key' => $existing->conversation_key,
                    ], $request);

                    return $existing->load(['company', 'project', 'owner', 'activeMembers.user.role', 'chatMessages.sender.role', 'chatMessages.attachments', 'chatMessages.poll.options.votes', 'chatMessages.poll.votes', 'chatMessages.reactions', 'chatMessages.reads']);
                }
            }

            $conversation = ChatConversation::create([
                'company_id' => $companyId,
                'project_id' => $project?->id,
                'owner_user_id' => $actor->id,
                'conversation_key' => $this->nextConversationKey(),
                'direct_pair_key' => $directPairKey,
                'type' => $data['type'],
                'title' => $this->conversationTitle($data, $members),
                'description' => $data['description'] ?? null,
                'visibility' => $this->visibilityForType($data['type']),
                'department' => $data['department'] ?? $this->departmentForType($data['type']),
                'related_type' => $data['related_type'] ?? null,
                'related_id' => $data['related_id'] ?? null,
                'status' => 'active',
                'last_message_at' => now(),
                'settings' => ['internal_only' => true, 'realtime_mode' => 'reverb_with_polling_fallback'],
                'metadata' => ['created_from' => 'chat_connect'],
            ]);

            $this->attachMember($conversation, $actor, 'owner', true);
            foreach ($members as $member) {
                $this->attachMember($conversation, $member, 'member', false);
            }

            $this->auditLogger->record($actor, 'chat.conversation.created', 'Created Chat Connect conversation', $conversation, [
                'conversation_key' => $conversation->conversation_key,
                'type' => $conversation->type,
                'member_count' => $members->count() + 1,
            ], $request);

            if (! empty($data['body'])) {
                $this->sendMessage($conversation, [
                    'body' => $data['body'],
                    'priority' => $data['priority'] ?? 'normal',
                    'metadata' => ['source' => 'chat_connect_new_conversation'],
                ], $actor, $request);
            }

            return $conversation->load(['company', 'project', 'owner', 'activeMembers.user.role', 'chatMessages.sender.role', 'chatMessages.attachments', 'chatMessages.poll.options.votes', 'chatMessages.poll.votes', 'chatMessages.reactions', 'chatMessages.reads']);
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function sendMessage(ChatConversation $conversation, array $data, User $actor, ?Request $request = null): ChatMessage
    {
        return DB::transaction(function () use ($conversation, $data, $actor, $request): ChatMessage {
            $membership = $conversation->membershipFor($actor);
            if (! $membership || $membership->archived_at !== null || ! $membership->can_post || ! $this->access->can($actor, 'can_post')) {
                throw ValidationException::withMessages(['conversation' => 'You cannot post in this conversation.']);
            }

            $files = $request?->file('attachments', []) ?? [];
            $files = is_array($files) ? $files : [$files];
            $messageType = (string) ($data['message_type'] ?? 'text');
            if ($messageType === 'voice_note' && ! $this->access->can($actor, 'can_send_voice')) {
                throw ValidationException::withMessages(['attachments' => 'Voice notes are not available for your role.']);
            }
            if ($files && (! $membership->can_upload || ! $this->access->can($actor, 'can_upload'))) {
                throw ValidationException::withMessages(['attachments' => 'File sharing is not available for your role.']);
            }

            $parent = null;
            if (! empty($data['parent_message_id'])) {
                $parent = ChatMessage::query()->whereKey($data['parent_message_id'])->firstOrFail();
                if ((int) $parent->chat_conversation_id !== (int) $conversation->id) {
                    throw ValidationException::withMessages(['parent_message_id' => 'Replies must belong to this conversation.']);
                }
            }

            $message = ChatMessage::create([
                'company_id' => $conversation->company_id,
                'project_id' => $conversation->project_id,
                'chat_conversation_id' => $conversation->id,
                'sender_user_id' => $actor->id,
                'parent_message_id' => $parent?->id,
                'message_number' => $this->nextMessageNumber(),
                'type' => match ($messageType) {
                    'voice_note' => 'voice_note',
                    'poll' => 'poll',
                    default => $files ? 'file' : 'text',
                },
                'body' => $data['body'] ?? null,
                'priority' => $data['priority'] ?? 'normal',
                'status' => 'sent',
                'metadata' => $data['metadata'] ?? [],
                'sent_at' => now(),
            ]);

            foreach ($files as $file) {
                if ($file instanceof UploadedFile) {
                    $this->storeAttachment($message, $file, $actor, $messageType, (int) ($data['duration_seconds'] ?? 0));
                }
            }

            $this->syncReadRowsForMessage($message, $conversation, $actor);
            $conversation->forceFill(['last_message_at' => now()])->save();
            $membership->forceFill(['last_read_at' => now()])->save();

            $this->notifyConversationMembers($conversation, $message, $actor);
            $this->auditLogger->record($actor, 'chat.message.sent', 'Sent Chat Connect message', $message, [
                'conversation_key' => $conversation->conversation_key,
                'type' => $message->type,
                'has_attachments' => $message->attachments()->exists(),
            ], $request);

            $message = $this->loadMessageRelations($message);
            event(new ChatMessageSent($message));

            return $message;
        });
    }

    public function createPoll(ChatConversation $conversation, array $data, User $actor, ?Request $request = null): ChatMessage
    {
        return DB::transaction(function () use ($conversation, $data, $actor, $request): ChatMessage {
            if (! $this->access->can($actor, 'can_create_poll')) {
                throw ValidationException::withMessages(['question' => 'Poll creation is not available for your role.']);
            }

            $message = $this->sendMessage($conversation, [
                'body' => $data['question'],
                'message_type' => 'poll',
                'priority' => 'normal',
                'metadata' => ['source' => 'chat_connect_poll'],
            ], $actor, $request);

            $poll = ChatPoll::create([
                'chat_message_id' => $message->id,
                'created_by_user_id' => $actor->id,
                'question' => $data['question'],
                'allows_multiple' => (bool) ($data['allows_multiple'] ?? false),
                'anonymous' => false,
                'closes_at' => $data['closes_at'] ?? null,
                'status' => 'open',
            ]);

            foreach (array_values($data['options']) as $index => $optionText) {
                ChatPollOption::create([
                    'chat_poll_id' => $poll->id,
                    'option_text' => $optionText,
                    'sort_order' => $index,
                ]);
            }

            $message = $this->loadMessageRelations($message->refresh());
            event(new ChatPollCreated($message));

            return $message;
        });
    }

    public function votePoll(ChatPoll $poll, array $optionIds, User $actor, ?Request $request = null): ChatMessage
    {
        return DB::transaction(function () use ($poll, $optionIds, $actor, $request): ChatMessage {
            if (! $this->access->can($actor, 'can_vote_poll')) {
                throw ValidationException::withMessages(['option_ids' => 'Poll voting is not available for your role.']);
            }
            if ($poll->status !== 'open' || ($poll->closes_at && $poll->closes_at->isPast())) {
                throw ValidationException::withMessages(['option_ids' => 'This poll is closed.']);
            }

            $validOptionIds = $poll->options()->pluck('id')->map(fn ($id) => (int) $id)->all();
            $selectedIds = collect($optionIds)->map(fn ($id) => (int) $id)->intersect($validOptionIds)->values();
            if ($selectedIds->isEmpty()) {
                throw ValidationException::withMessages(['option_ids' => 'Select a valid poll option.']);
            }
            if (! $poll->allows_multiple && $selectedIds->count() > 1) {
                throw ValidationException::withMessages(['option_ids' => 'Select only one option for this poll.']);
            }

            $poll->votes()->where('voter_user_id', $actor->id)->delete();
            foreach ($selectedIds as $optionId) {
                $poll->votes()->create([
                    'chat_poll_option_id' => $optionId,
                    'voter_user_id' => $actor->id,
                ]);
            }

            $poll->message()->touch();

            $message = $this->loadMessageRelations($poll->message()->firstOrFail());
            event(new ChatPollVoted($message));

            $this->auditLogger->record($actor, 'chat.poll.voted', 'Voted on Chat Connect poll', $poll, [
                'message_id' => $message->id,
                'selected_options' => $selectedIds->all(),
            ], $request);

            return $message;
        });
    }

    public function closePoll(ChatPoll $poll, User $actor, ?Request $request = null): ChatMessage
    {
        $message = $poll->message()->with('conversation')->firstOrFail();
        $conversation = $message->conversation;
        $membership = $this->assertConversationWritable($conversation, $actor);

        $canClose = (int) $poll->created_by_user_id === (int) $actor->id
            || (bool) $membership->can_manage_members
            || $this->access->can($actor, 'can_manage_members');

        if (! $canClose || $this->access->isReadOnly($actor)) {
            throw new AuthorizationException('You cannot close this poll.');
        }

        $poll->forceFill(['status' => 'closed', 'closed_at' => now()])->save();
        $message->touch();
        $message = $this->loadMessageRelations($message->refresh());
        event(new ChatPollClosed($message));

        $this->auditLogger->record($actor, 'chat.poll.closed', 'Closed Chat Connect poll', $poll, ['message_id' => $message->id], $request);

        return $message;
    }

    public function updateReaction(ChatMessage $message, User $actor, string $emoji, string $action = 'toggle', ?Request $request = null): ChatMessage
    {
        return DB::transaction(function () use ($message, $actor, $emoji, $action, $request): ChatMessage {
            $conversation = $message->conversation()->firstOrFail();
            $this->assertConversationWritable($conversation, $actor);

            if (! $this->access->can($actor, 'can_post')) {
                throw ValidationException::withMessages(['message' => 'Reactions are not available for your role.']);
            }

            $emoji = trim($emoji);
            if ($emoji === '') {
                throw ValidationException::withMessages(['emoji' => 'Select a reaction.']);
            }

            $existing = ChatMessageReaction::query()
                ->where('chat_message_id', $message->id)
                ->where('user_id', $actor->id)
                ->where('emoji', $emoji)
                ->first();

            if ($action === 'remove' || ($action === 'toggle' && $existing)) {
                $existing?->delete();
            } elseif (! $existing) {
                ChatMessageReaction::create([
                    'chat_message_id' => $message->id,
                    'user_id' => $actor->id,
                    'emoji' => $emoji,
                ]);
            }

            $message->touch();

            $this->auditLogger->record($actor, 'chat.message.reaction.updated', 'Updated Chat Connect message reaction', $message, [
                'conversation_key' => $conversation->conversation_key,
                'emoji' => $emoji,
                'action' => $action,
            ], $request);

            return $this->loadMessageRelations($message->refresh());
        });
    }

    public function canDeleteMessage(User $user, ChatMessage $message): bool
    {
        if ((int) $message->sender_user_id === (int) $user->id) {
            return true;
        }

        $conversation = $message->conversation;
        if (! $conversation) {
            return false;
        }

        $membership = $conversation->membershipFor($user);

        return $user->hasPermission('*')
            || (int) $conversation->owner_user_id === (int) $user->id
            || ($membership !== null && $membership->can_manage_members)
            || $this->access->can($user, 'can_manage_members');
    }

    public function deleteMessage(ChatMessage $message, User $actor, ?Request $request = null): bool
    {
        return DB::transaction(function () use ($message, $actor, $request): bool {
            $conversation = $message->conversation()->firstOrFail();
            $this->assertConversationWritable($conversation, $actor);

            if (! $this->canDeleteMessage($actor, $message)) {
                throw new AuthorizationException('You cannot delete this message.');
            }

            $message->forceFill([
                'deleted_by_user_id' => $actor->id,
            ])->save();

            $deleted = (bool) $message->delete();

            $this->auditLogger->record($actor, 'chat.message.deleted', 'Soft deleted Chat Connect message', $message, [
                'conversation_key' => $conversation->conversation_key,
                'message_number' => $message->message_number,
            ], $request);

            event(new ChatMessageDeleted($message->id, $conversation->id, $actor->id));

            return $deleted;
        });
    }

    public function markRead(ChatConversation $conversation, User $actor, ?Request $request = null): int
    {
        return DB::transaction(function () use ($conversation, $actor, $request): int {
            $this->assertConversationViewable($conversation, $actor);
            $messageIds = $conversation->chatMessages()
                ->where('sender_user_id', '!=', $actor->id)
                ->pluck('id');

            $updated = ChatMessageRead::query()
                ->whereIn('chat_message_id', $messageIds)
                ->where('user_id', $actor->id)
                ->whereNull('read_at')
                ->update(['read_at' => now(), 'updated_at' => now()]);

            $conversation->membershipFor($actor)?->forceFill(['last_read_at' => now()])->save();
            $this->auditLogger->record($actor, 'chat.conversation.read', 'Marked Chat Connect conversation as read', $conversation, [
                'conversation_key' => $conversation->conversation_key,
                'updated_messages' => $updated,
            ], $request);
            event(new ChatConversationRead($conversation->id, $actor->id));

            return $updated;
        });
    }

    public function archiveForUser(ChatConversation $conversation, User $actor, ?Request $request = null): ChatConversationMember
    {
        $this->assertConversationViewable($conversation, $actor);

        if (! $this->access->can($actor, 'can_archive')) {
            throw ValidationException::withMessages(['conversation' => 'Archiving is not available for your role.']);
        }

        $membership = $conversation->membershipFor($actor);
        if (! $membership) {
            throw ValidationException::withMessages(['conversation' => 'You are not a member of this conversation.']);
        }

        $membership->forceFill(['archived_at' => now()])->save();
        $this->auditLogger->record($actor, 'chat.conversation.archived', 'Archived Chat Connect conversation for user', $conversation, [
            'conversation_key' => $conversation->conversation_key,
        ], $request);

        return $membership->refresh();
    }

    public function updateConversation(ChatConversation $conversation, array $data, User $actor, ?Request $request = null): ChatConversation
    {
        return DB::transaction(function () use ($conversation, $data, $actor, $request): ChatConversation {
            $this->assertConversationViewable($conversation, $actor);

            if ($conversation->type === 'direct_message') {
                throw ValidationException::withMessages(['title' => 'Direct messages cannot be edited.']);
            }

            $membership = $conversation->membershipFor($actor);
            $canEdit = (int) $conversation->owner_user_id === (int) $actor->id
                || ($membership !== null && $membership->can_manage_members)
                || $this->access->can($actor, 'can_manage_members');

            if (! $canEdit) {
                throw new AuthorizationException('You cannot edit this conversation.');
            }

            $title = trim((string) ($data['title'] ?? $conversation->title));
            $description = isset($data['description']) ? trim((string) $data['description']) : $conversation->description;

            if ($title === '') {
                throw ValidationException::withMessages(['title' => 'A group or channel title is required.']);
            }

            $conversation->forceFill([
                'title' => $title,
                'description' => $description,
            ])->save();

            $this->auditLogger->record($actor, 'chat.conversation.updated', 'Updated Chat Connect conversation details', $conversation, [
                'title' => $title,
                'description' => $description,
            ], $request);

            return $conversation->refresh();
        });
    }

    public function addMembers(ChatConversation $conversation, array $userIds, User $actor, ?Request $request = null): EloquentCollection
    {
        return DB::transaction(function () use ($conversation, $userIds, $actor, $request): EloquentCollection {
            $this->assertConversationViewable($conversation, $actor);

            if ($conversation->type === 'direct_message') {
                throw ValidationException::withMessages(['member_user_ids' => 'Direct messages cannot have additional members.']);
            }

            $membership = $conversation->membershipFor($actor);
            $canAdd = (int) $conversation->owner_user_id === (int) $actor->id
                || ($membership !== null && $membership->can_manage_members)
                || $this->access->can($actor, 'can_manage_members');

            if (! $canAdd) {
                throw new AuthorizationException('You cannot add members to this conversation.');
            }

            $members = User::query()->with('role')->whereIn('id', $userIds)->get();
            $this->assertMembersAllowed($members, $actor, $conversation->project);

            foreach ($members as $member) {
                $existing = ChatConversationMember::query()
                    ->where('chat_conversation_id', $conversation->id)
                    ->where('user_id', $member->id)
                    ->first();

                if ($existing) {
                    $this->restoreMember($conversation, $member, 'member', false);
                } else {
                    $this->attachMember($conversation, $member, 'member', false);
                }
            }

            $activeCount = $conversation->activeMembers()->count();
            $conversation->touch();

            $this->auditLogger->record($actor, 'chat.members.added', 'Added members to Chat Connect conversation', $conversation, [
                'added_user_ids' => $members->pluck('id')->all(),
                'member_count' => $activeCount,
            ], $request);

            return $members;
        });
    }

    public function removeMember(ChatConversation $conversation, User $targetUser, User $actor, ?Request $request = null): ChatConversationMember
    {
        return DB::transaction(function () use ($conversation, $targetUser, $actor, $request): ChatConversationMember {
            $this->assertConversationViewable($conversation, $actor);

            if ($conversation->type === 'direct_message') {
                throw ValidationException::withMessages(['user' => 'Direct message members cannot be removed.']);
            }

            if ((int) $conversation->owner_user_id === (int) $targetUser->id) {
                throw ValidationException::withMessages(['user' => 'The conversation owner cannot be removed.']);
            }

            $isSelf = (int) $targetUser->id === (int) $actor->id;
            $membership = $conversation->membershipFor($actor);
            $canRemove = $isSelf
                || (int) $conversation->owner_user_id === (int) $actor->id
                || ($membership !== null && $membership->can_manage_members)
                || $this->access->can($actor, 'can_manage_members');

            if (! $canRemove) {
                throw new AuthorizationException('You cannot remove members from this conversation.');
            }

            $targetMembership = $conversation->membershipFor($targetUser);
            if (! $targetMembership) {
                throw ValidationException::withMessages(['user' => 'The selected user is not an active member of this conversation.']);
            }

            $targetMembership->forceFill(['removed_at' => now()])->save();

            $activeCount = $conversation->activeMembers()->count();
            $conversation->touch();

            $this->auditLogger->record($actor, 'chat.member.removed', 'Removed member from Chat Connect conversation', $conversation, [
                'removed_user_id' => $targetUser->id,
                'member_count' => $activeCount,
            ], $request);

            return $targetMembership->refresh();
        });
    }

    /**
     * @return EloquentCollection<int, ChatMessage>
     */
    public function activeMessages(ChatConversation $conversation, User $user, int $limit = 80): EloquentCollection
    {
        $this->assertConversationViewable($conversation, $user);

        return ChatMessage::query()
            ->with(['sender.role', 'parent.sender', 'attachments', 'poll.options.votes', 'poll.votes', 'reactions', 'reads'])
            ->where('chat_conversation_id', $conversation->id)
            ->latest()
            ->limit($limit)
            ->get()
            ->sortBy('created_at')
            ->values();
    }

    public function loadMessageRelations(ChatMessage $message): ChatMessage
    {
        return $message->load(['sender.role', 'parent.sender', 'attachments', 'poll.options.votes', 'poll.votes', 'reactions', 'reads']);
    }

    /**
     * @param EloquentCollection<int, ChatConversation> $conversations
     */
    private function attachUnreadCounts(EloquentCollection $conversations, User $user): void
    {
        if ($conversations->isEmpty()) {
            return;
        }

        $counts = ChatMessageRead::query()
            ->join('chat_messages', 'chat_message_reads.chat_message_id', '=', 'chat_messages.id')
            ->whereIn('chat_messages.chat_conversation_id', $conversations->pluck('id'))
            ->where('chat_message_reads.user_id', $user->id)
            ->whereNull('chat_message_reads.read_at')
            ->selectRaw('chat_messages.chat_conversation_id, count(*) as unread_count')
            ->groupBy('chat_messages.chat_conversation_id')
            ->pluck('unread_count', 'chat_messages.chat_conversation_id');

        $conversations->each(function (ChatConversation $conversation) use ($counts): void {
            $conversation->unread_count = (int) ($counts[$conversation->id] ?? 0);
        });
    }

    private function attachMember(ChatConversation $conversation, User $user, string $role, bool $manager): ChatConversationMember
    {
        $caps = $this->access->capabilitiesFor($user);

        return ChatConversationMember::create([
            'chat_conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'member_role' => $role,
            'can_post' => (bool) ($caps['can_post'] ?? false),
            'can_upload' => (bool) ($caps['can_upload'] ?? false) && ! $conversation->isSensitive(),
            'can_manage_members' => $manager && (bool) ($caps['can_manage_members'] ?? false),
            'muted' => false,
        ]);
    }

    private function restoreMember(ChatConversation $conversation, User $user, string $role, bool $manager): ChatConversationMember
    {
        $caps = $this->access->capabilitiesFor($user);

        return ChatConversationMember::query()->updateOrCreate(
            ['chat_conversation_id' => $conversation->id, 'user_id' => $user->id],
            [
                'member_role' => $role,
                'can_post' => (bool) ($caps['can_post'] ?? false),
                'can_upload' => (bool) ($caps['can_upload'] ?? false) && ! $conversation->isSensitive(),
                'can_manage_members' => $manager && (bool) ($caps['can_manage_members'] ?? false),
                'muted' => false,
                'archived_at' => null,
                'removed_at' => null,
            ],
        );
    }

    private function assertMembersAllowed(EloquentCollection $members, User $actor, ?Project $project = null): void
    {
        foreach ($members as $member) {
            $this->assertCanView($member);

            if (! $this->companyScope->allows($actor, $member->company_id)) {
                throw ValidationException::withMessages(['member_user_ids' => 'All members must be inside your company access.']);
            }

            if ($project && $member->company_id !== null && (int) $member->company_id !== (int) $project->company_id) {
                throw ValidationException::withMessages(['member_user_ids' => 'Project conversations can include only users from the project company.']);
            }
        }
    }

    private function assertCanView(User $user): void
    {
        if (! $this->access->canView($user)) {
            throw ValidationException::withMessages(['chat' => 'Chat Connect is not available for this role.']);
        }
    }

    private function assertConversationViewable(ChatConversation $conversation, User $user): ChatConversationMember
    {
        if (! $this->access->canView($user)) {
            throw new AuthorizationException('Chat Connect is not available for this role.');
        }

        if (! $this->companyScope->allows($user, $conversation->company_id)) {
            throw new AuthorizationException('This conversation is not available for your access.');
        }

        $membership = $conversation->membershipFor($user);
        if (! $membership) {
            throw new AuthorizationException('You are not a member of this conversation.');
        }

        return $membership;
    }

    private function assertConversationWritable(ChatConversation $conversation, User $user): ChatConversationMember
    {
        $membership = $this->assertConversationViewable($conversation, $user);

        if ($conversation->status !== 'active' || $membership->archived_at !== null) {
            throw new AuthorizationException('This conversation is not writable.');
        }

        return $membership;
    }

    private function assertCanCreateType(User $user, string $type): void
    {
        $capability = match ($type) {
            'direct_message' => 'can_create_dm',
            'group_chat' => 'can_create_group',
            default => 'can_create_channel',
        };

        if (! $this->access->can($user, $capability)) {
            throw ValidationException::withMessages(['type' => 'This conversation type is not available for your role.']);
        }
    }

    private function conversationTitle(array $data, EloquentCollection $members): string
    {
        if (! empty($data['title'])) {
            return (string) $data['title'];
        }

        if (($data['type'] ?? '') === 'direct_message') {
            return 'Direct message';
        }

        if (! empty($data['department'])) {
            return '#'.Str::slug((string) $data['department']);
        }

        return Str::headline((string) ($data['type'] ?? 'group_chat'));
    }

    private function visibilityForType(string $type): string
    {
        return in_array($type, ['approval_thread', 'voucher_thread'], true) ? 'restricted_internal' : 'internal';
    }

    private function departmentForType(string $type): ?string
    {
        return match ($type) {
            'approval_thread' => 'approvals',
            'voucher_thread' => 'vouchers',
            default => null,
        };
    }

    private function nextConversationKey(): string
    {
        return sprintf('CHAT-%05d', ChatConversation::query()->withTrashed()->count() + 10001);
    }

    private function directPairKey(int $companyId, int $firstUserId, int $secondUserId): string
    {
        $userIds = [$firstUserId, $secondUserId];
        sort($userIds, SORT_NUMERIC);

        return sprintf('%d:%d:%d', $companyId, $userIds[0], $userIds[1]);
    }

    private function nextMessageNumber(): string
    {
        // take random also
        $chatnumber  = 'CHATMSG' . rand(10000, 99999) .rand(10000, 99999);
        return $chatnumber;
        // return sprintf('CHATMSG-%05d', ChatMessage::query()->count() + 10001);
    }

    private function storeAttachment(ChatMessage $message, UploadedFile $file, User $actor, string $messageType, int $durationSeconds = 0): ChatMessageAttachment
    {
        $mime = $file->getMimeType() ?: 'application/octet-stream';
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: '');
        $audioExtensions = ['mp3', 'wav', 'm4a', 'aac', 'ogg', 'oga', 'webm', '3gp', '3gpp', 'amr', 'flac', 'opus', 'wma'];
        $isAudio = str_starts_with($mime, 'audio/') || in_array($mime, ['application/ogg', 'application/x-ogg'], true) || in_array($extension, $audioExtensions, true);

        $path = 'chat/'.$message->company_id.'/'.$message->chat_conversation_id;
        $storedName = Str::uuid()->toString().'.'.($file->guessExtension() ?: $extension ?: 'bin');
        $storedPath = $file->storeAs($path, $storedName, 'local');

        return ChatMessageAttachment::create([
            'chat_message_id' => $message->id,
            'company_id' => $message->company_id,
            'uploader_user_id' => $actor->id,
            'type' => ($messageType === 'voice_note' || $isAudio) ? 'voice_note' : 'file',
            'disk' => 'local',
            'path' => $storedPath,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $mime,
            'size_bytes' => $file->getSize() ?: 0,
            'checksum_sha256' => hash_file('sha256', $file->getRealPath()),
            'duration_seconds' => ($messageType === 'voice_note' || $isAudio) ? max(0, $durationSeconds) : null,
            'scan_status' => 'pending',
            'metadata' => ['storage_visibility' => 'private'],
        ]);
    }

    private function syncReadRowsForMessage(ChatMessage $message, ChatConversation $conversation, User $actor): void
    {
        $conversation->activeMembers()->pluck('user_id')->each(function ($userId) use ($message, $actor): void {
            ChatMessageRead::create([
                'chat_message_id' => $message->id,
                'user_id' => $userId,
                'delivered_at' => now(),
                'read_at' => (int) $userId === (int) $actor->id ? now() : null,
            ]);
        });
    }

    private function notifyConversationMembers(ChatConversation $conversation, ChatMessage $message, User $actor): void
    {
        $conversation->activeMembers()
            ->with('user')
            ->where('user_id', '!=', $actor->id)
            ->where('muted', false)
            ->get()
            ->pluck('user')
            ->filter()
            ->each(function (User $recipient) use ($conversation, $message, $actor): void {
                $mentions = collect(data_get($message->metadata, 'mentions', []))
                    ->map(fn ($id): int => (int) $id)
                    ->contains((int) $recipient->id);

                $this->notifications->sendToUser($recipient, [
                    'category' => 'chat',
                    'severity' => $message->priority === 'critical' ? 'critical' : ($mentions ? 'warning' : 'info'),
                    'title' => $mentions
                        ? 'You were mentioned in '.$conversation->displayTitleFor($recipient)
                        : 'Chat message: '.$conversation->displayTitleFor($recipient),
                    'body' => Str::limit(strip_tags((string) $message->body ?: 'New chat attachment'), 180),
                    'action_url' => route('collaboration.chat.index', ['conversation_id' => $conversation->id], false),
                    'payload' => [
                        'conversation_id' => $conversation->id,
                        'conversation_key' => $conversation->conversation_key,
                        'message_number' => $message->message_number,
                    ],
                ], $actor, $message);
            });
    }
}
