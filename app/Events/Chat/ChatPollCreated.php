<?php

namespace App\Events\Chat;

class ChatPollCreated extends ChatMessageSent
{
    public function broadcastAs(): string
    {
        return 'poll.created';
    }
}
