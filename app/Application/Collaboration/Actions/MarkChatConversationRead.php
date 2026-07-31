<?php
namespace App\Application\Collaboration\Actions;

use App\Application\Collaboration\Data\ChatCommandData;
use App\Models\ChatConversation;
use App\Services\Collaboration\ChatConnectService;

final class MarkChatConversationRead
{
    public function __construct(private readonly ChatConnectService $chat) {}
    public function execute(ChatConversation $conversation, ChatCommandData $command): int
    {
        return $this->chat->markRead($conversation, $command->actor, $command->request);
    }
}
