<?php

namespace App\Events\Chat;

class ChatPollClosed extends ChatMessageSent
{
    public function broadcastAs(): string
    {
        return 'poll.closed';
    }
}
