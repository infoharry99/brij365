<?php

namespace App\Application\Collaboration\Actions;

use App\Application\Collaboration\Data\ChatConversationIndexData;
use App\Http\Resources\ChatMessageResource;
use App\Models\User;
use App\Services\Collaboration\ChatConnectService;
use Illuminate\Http\Request;

final class ListChatConversations
{
    public function __construct(private readonly ChatConnectService $chat) {}

    /** @param array<string,mixed> $filters */
    public function execute(User $user, array $filters, Request $request): ChatConversationIndexData
    {
        $conversations = $this->chat->conversationsFor($user, $filters);
        if (isset($filters['conversation_id']) && ! $conversations->contains('id', (int) $filters['conversation_id'])) {
            $conversations->prepend($this->chat->viewableConversation($user, (int) $filters['conversation_id']));
        }
        $messages = [];

        $selectedConversationId = (int) ($filters['conversation_id'] ?? 0);

        foreach ($conversations->where('id', $selectedConversationId) as $conversation) {
            $messages[$conversation->id] = ChatMessageResource::collection(
                $this->chat->activeMessages($conversation, $user),
            )->resolve($request);
        }

        return new ChatConversationIndexData($conversations, $messages);
    }
}
