<?php

namespace App\Events\Chat;

class ChatPollVoted extends ChatMessageSent
{
    public function broadcastAs(): string
    {
        return 'poll.voted';
    }
}
