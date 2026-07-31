<?php
namespace App\Application\Collaboration\Actions;

use App\Application\Collaboration\Data\ChatCommandData;
use App\Models\ChatMessage;
use App\Models\ChatPoll;
use App\Services\Collaboration\ChatConnectService;

final class CloseChatPoll
{
    public function __construct(private readonly ChatConnectService $chat) {}
    public function execute(ChatPoll $poll, ChatCommandData $command): ChatMessage
    {
        return $this->chat->closePoll($poll->load('message.conversation'), $command->actor, $command->request);
    }
}
