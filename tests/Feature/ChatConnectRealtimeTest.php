<?php

namespace Tests\Feature;

use App\Events\Chat\ChatConversationRead;
use App\Events\Chat\ChatMessageSent;
use App\Models\ChatConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ChatConnectRealtimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_message_and_read_workflows_dispatch_private_conversation_events(): void
    {
        $this->seed();
        Event::fake([ChatMessageSent::class, ChatConversationRead::class]);

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();

        $this->actingAs($sales)
            ->postJson(route('collaboration.chat.conversations.store'), [
                'type' => 'direct_message',
                'title' => 'Realtime verification',
                'member_user_ids' => [$finance->id],
                'body' => 'Initial message.',
            ])
            ->assertCreated();

        $conversation = ChatConversation::where('title', 'Realtime verification')->firstOrFail();

        $this->actingAs($finance)
            ->postJson(route('collaboration.chat.conversations.messages.store', $conversation), [
                'body' => 'Realtime response.',
                'message_type' => 'text',
            ])
            ->assertCreated();

        $this->actingAs($sales)
            ->patchJson(route('collaboration.chat.conversations.read', $conversation))
            ->assertOk();

        Event::assertDispatched(ChatMessageSent::class, fn (ChatMessageSent $event): bool =>
            (int) $event->message->chat_conversation_id === (int) $conversation->id
            && (string) $event->broadcastOn()[0] === 'private-chat.conversation.'.$conversation->id
            && $event->broadcastAs() === 'message.sent'
        );
        Event::assertDispatched(ChatConversationRead::class, fn (ChatConversationRead $event): bool =>
            $event->conversationId === $conversation->id
            && $event->broadcastAs() === 'conversation.read'
        );
    }
}
