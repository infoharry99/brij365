<?php

namespace App\Application\Collaboration\Actions;

use App\Models\ChatMessage;
use App\Models\User;
use App\Services\Collaboration\ChatConnectService;
use Illuminate\Http\Request;

final class DeleteChatMessage
{
    public function __construct(private readonly ChatConnectService $chat) {}

    public function execute(ChatMessage $message, User $actor, ?Request $request = null): bool
    {
        return $this->chat->deleteMessage($message, $actor, $request);
    }
}
