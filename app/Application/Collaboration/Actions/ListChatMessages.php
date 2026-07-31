<?php

namespace App\Application\Collaboration\Actions;

use App\Models\ChatConversation;
use App\Models\User;
use App\Services\Collaboration\ChatConnectService;
use Illuminate\Support\Collection;

final class ListChatMessages
{
    public function __construct(private readonly ChatConnectService $chat) {}

    public function execute(ChatConversation $conversation, User $user): Collection
    {
        return $this->chat->activeMessages($conversation, $user);
    }
}
