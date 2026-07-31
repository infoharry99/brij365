<?php
namespace App\Application\Collaboration\Actions;

use App\Application\Collaboration\Data\ChatCommandData;
use App\Models\ChatMessage;
use App\Services\Collaboration\ChatConnectService;

final class ChangeChatMessageReaction
{
    public function __construct(private readonly ChatConnectService $chat) {}
    public function execute(ChatMessage $message, ChatCommandData $command): ChatMessage
    {
        return $this->chat->updateReaction($message, $command->actor, $command->attributes['emoji'], $command->attributes['action'] ?? 'toggle', $command->request);
    }
}
