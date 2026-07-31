<?php

namespace Tests\Feature;

use App\Application\Collaboration\Data\CollaborationCommandData;
use App\Application\Collaboration\Data\ChatCommandData;
use ReflectionClass;
use Tests\TestCase;

class CollaborationApplicationLayerTest extends TestCase
{
    public function test_collaboration_write_command_is_immutable(): void
    {
        $reflection = new ReflectionClass(CollaborationCommandData::class);

        $this->assertTrue($reflection->isReadOnly());
        $this->assertTrue((new ReflectionClass(ChatCommandData::class))->isReadOnly());
    }

    public function test_task_and_calendar_mutations_enter_through_use_case_actions(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Collaboration/CollaborationController.php'));

        $this->assertIsString($controller);
        $this->assertStringContainsString('CreateWorkTask $action', $controller);
        $this->assertStringContainsString('UpdateWorkTask $action', $controller);
        $this->assertStringContainsString('CreateCalendarEvent $action', $controller);
        $this->assertStringContainsString('ArchiveCalendarEvent $action', $controller);

        foreach ([
            '$service->createTask(',
            '$service->updateTaskDetails(',
            '$service->archiveTask(',
            '$service->createCalendarEvent(',
            '$service->updateCalendarEvent(',
            '$service->deleteCalendarEvent(',
        ] as $forbiddenCall) {
            $this->assertStringNotContainsString($forbiddenCall, $controller);
        }
    }

    public function test_chat_and_mailbox_mutations_enter_through_use_case_actions(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Collaboration/CollaborationController.php'));

        $this->assertIsString($controller);
        $this->assertStringContainsString('CreateChatConversation $action', $controller);
        $this->assertStringContainsString('SendChatMessage $action', $controller);
        $this->assertStringContainsString('CloseChatPollRequest $request', $controller);
        $this->assertStringContainsString('SendMailboxMessage $action', $controller);
        $this->assertStringContainsString('ArchiveMailboxMessage $action', $controller);
        $this->assertStringContainsString('ExportWorkTaskRegister $action', $controller);
        $this->assertStringContainsString('ExportMailboxRegister $action', $controller);
        $this->assertStringNotContainsString('Builder360Bootstrap', $controller);
        $this->assertStringNotContainsString('CollaborationService $service', $controller);
        $this->assertStringNotContainsString('ChatConnectService $service', $controller);

        foreach ([
            '$service->createConversation(',
            '$service->sendMessage($chatConversation',
            '$service->createPoll(',
            '$service->closePoll(',
            '$service->archiveForUser(',
            '$service->markMessageRead(',
            '$service->archiveMessage(',
            '$service->updateMessageState(',
        ] as $forbiddenCall) {
            $this->assertStringNotContainsString($forbiddenCall, $controller);
        }
    }

    public function test_reverb_is_the_only_configured_live_broadcast_transport(): void
    {
        $connections = config('broadcasting.connections');

        $this->assertArrayHasKey('reverb', $connections);
        $this->assertSame('reverb', config('broadcasting.connections.reverb.driver'));
        $this->assertStringContainsString('BROADCAST_CONNECTION=reverb', file_get_contents(base_path('.env.example')));

        $client = file_get_contents(resource_path('js/app.js'));
        $this->assertIsString($client);
        $this->assertStringContainsString("broadcaster: 'reverb'", $client);
        $this->assertStringContainsString(".listen('.message.sent'", $client);
        $this->assertStringContainsString(".listen('.poll.voted'", $client);
        $this->assertStringContainsString('startPolling()', $client);
    }
}
