<?php

namespace App\Application\Collaboration\Data;

use App\Models\ChatConversation;
use Illuminate\Support\Collection;

final readonly class CreatedChatConversationData
{
    public function __construct(
        public ChatConversation $conversation,
        public Collection $messages,
    ) {}
}
