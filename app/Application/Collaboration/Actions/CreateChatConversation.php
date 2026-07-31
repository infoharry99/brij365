<?php
namespace App\Application\Collaboration\Actions;

use App\Application\Collaboration\Data\ChatCommandData;
use App\Application\Collaboration\Data\CreatedChatConversationData;
use App\Services\Collaboration\ChatConnectService;

final class CreateChatConversation
{
    public function __construct(private readonly ChatConnectService $chat) {}
    public function execute(ChatCommandData $command): CreatedChatConversationData
    {
        $conversation = $this->chat->createConversation($command->attributes, $command->actor, $command->request);

        return new CreatedChatConversationData(
            conversation: $conversation,
            messages: $this->chat->activeMessages($conversation, $command->actor),
        );
    }
}
