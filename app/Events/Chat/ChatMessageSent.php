<?php

namespace App\Events\Chat;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageSent implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public ChatMessage $message)
    {
    }

    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('chat.conversation.'.$this->message->chat_conversation_id)];
        $this->message->loadMissing('conversation.activeMembers');

        foreach ($this->message->conversation?->activeMembers ?? [] as $member) {
            $channels[] = new PrivateChannel('chat.user.'.$member->user_id);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        return [
            'message_id' => $this->message->id,
            'conversation_id' => $this->message->chat_conversation_id,
            'message_number' => $this->message->message_number,
            'updated_at' => $this->message->updated_at?->toISOString(),
        ];
    }
}
