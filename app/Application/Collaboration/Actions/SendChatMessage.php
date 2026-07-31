<?php
namespace App\Application\Collaboration\Actions;

use App\Application\Collaboration\Data\ChatCommandData;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Services\Collaboration\ChatConnectService;

final class SendChatMessage
{
    public function __construct(private readonly ChatConnectService $chat) {}
    public function execute(ChatConversation $conversation, ChatCommandData $command): ChatMessage
    {
        return $this->chat->sendMessage($conversation, $command->attributes, $command->actor, $command->request);
    }
}
