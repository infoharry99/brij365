<?php
namespace App\Application\Collaboration\Actions;

use App\Application\Collaboration\Data\ChatCommandData;
use App\Models\ChatMessage;
use App\Models\ChatPoll;
use App\Services\Collaboration\ChatConnectService;

final class VoteChatPoll
{
    public function __construct(private readonly ChatConnectService $chat) {}
    public function execute(ChatPoll $poll, ChatCommandData $command): ChatMessage
    {
        return $this->chat->votePoll($poll->load('message.conversation'), $command->attributes['option_ids'], $command->actor, $command->request);
    }
}
