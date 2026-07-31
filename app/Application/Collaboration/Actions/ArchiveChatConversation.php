<?php
namespace App\Application\Collaboration\Actions;

use App\Application\Collaboration\Data\ChatCommandData;
use App\Models\ChatConversation;
use App\Models\ChatConversationMember;
use App\Services\Collaboration\ChatConnectService;

final class ArchiveChatConversation
{
    public function __construct(private readonly ChatConnectService $chat) {}
    public function execute(ChatConversation $conversation, ChatCommandData $command): ChatConversationMember
    {
        return $this->chat->archiveForUser($conversation, $command->actor, $command->request);
    }
}
