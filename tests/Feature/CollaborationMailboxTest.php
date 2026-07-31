<?php

namespace Tests\Feature;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatMessageRead;
use App\Models\CollaborationMessage;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Project;
use App\Models\User;
use App\Services\Builder360\Builder360Bootstrap;
use App\Services\Collaboration\CollaborationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollaborationMailboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_bootstrap_exposes_company_email_mailbox_contract_without_internal_messaging(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();

        $bootstrap = app(Builder360Bootstrap::class)->forUser($sales);
        $partnerBootstrap = app(Builder360Bootstrap::class)->forUser($partner);

        $this->assertNull($bootstrap['collaboration_mailbox_options']);
        $this->assertIsArray($bootstrap['company_mailbox_options']);
        $this->assertSame('company-email', $bootstrap['company_mailbox_options']['source']);
        $this->assertSame('/mailbox', $bootstrap['company_mailbox_options']['index_url']);
        $this->assertSame('/mailbox/accounts', $bootstrap['company_mailbox_options']['accounts_url']);
        $this->assertArrayHasKey('accounts', $bootstrap['company_mailbox_options']);
        $this->assertArrayNotHasKey('recipients', $bootstrap['company_mailbox_options']);
        $this->assertArrayNotHasKey('projects', $bootstrap['company_mailbox_options']);
        $this->assertArrayNotHasKey('priorities', $bootstrap['company_mailbox_options']);
        $this->assertNull($partnerBootstrap['collaboration_mailbox_options']);
        $this->assertSame('company-email', $partnerBootstrap['company_mailbox_options']['source']);
    }

    public function test_internal_users_can_list_seeded_inbox_and_sent_mailbox_messages(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();

        $this->actingAs($finance)
            ->getJson(route('collaboration.messages.index', ['folder' => 'inbox']))
            ->assertOk()
            ->assertJsonPath('data.0.message_number', 'MSG-10001')
            ->assertJsonPath('data.0.sender.email', 'priya.nair@builder360.test')
            ->assertJsonPath('data.0.status', 'unread');

        $this->actingAs($sales)
            ->getJson(route('collaboration.messages.index', ['folder' => 'sent']))
            ->assertOk()
            ->assertJsonPath('data.0.message_number', 'MSG-10001')
            ->assertJsonPath('data.0.recipient.email', 'suresh.iyer@builder360.test');
    }

    public function test_mailbox_send_read_archive_notification_and_audit_workflow(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();

        $response = $this->actingAs($sales)
            ->postJson(route('collaboration.messages.store'), [
                'project_id' => $project->id,
                'recipient_user_ids' => [$finance->id, $construction->id],
                'subject' => 'Weekend site visit and payment coordination',
                'body' => 'Please align on payment follow-up and site-readiness before the scheduled customer visit.',
                'priority' => 'critical',
                'metadata' => ['source' => 'feature_test'],
            ])
            ->assertCreated()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.status', 'unread')
            ->assertJsonPath('data.0.priority', 'critical');

        $messageNumber = $response->json('data.0.message_number');
        $threadKey = $response->json('data.0.thread_key');
        $message = CollaborationMessage::where('thread_key', $threadKey)
            ->where('recipient_user_id', $finance->id)
            ->firstOrFail();
        $messageNumber = $message->message_number;

        $this->assertDatabaseHas('user_notifications', [
            'recipient_user_id' => $finance->id,
            'triggered_by_user_id' => $sales->id,
            'category' => 'mailbox',
            'severity' => 'critical',
            'notifiable_type' => CollaborationMessage::class,
            'notifiable_id' => $message->id,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'collaboration.message.sent',
            'auditable_type' => CollaborationMessage::class,
            'auditable_id' => $message->id,
        ]);

        $this->actingAs($sales)
            ->patchJson(route('collaboration.messages.read', $message))
            ->assertForbidden();

        $this->actingAs($finance)
            ->patchJson(route('collaboration.messages.read', $message))
            ->assertOk()
            ->assertJsonPath('data.status', 'read');

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'collaboration.message.read',
            'auditable_type' => CollaborationMessage::class,
            'auditable_id' => $message->id,
        ]);

        $this->actingAs($finance)
            ->patchJson(route('collaboration.messages.archive', $message))
            ->assertOk()
            ->assertJsonPath('data.status', 'archived');

        $this->actingAs($finance)
            ->getJson(route('collaboration.messages.index', ['folder' => 'inbox']))
            ->assertOk()
            ->assertJsonMissing(['message_number' => $messageNumber]);

        $this->actingAs($finance)
            ->getJson(route('collaboration.messages.index', [
                'folder' => 'inbox',
                'status' => 'archived',
                'thread_key' => $threadKey,
            ]))
            ->assertOk()
            ->assertJsonPath('data.0.message_number', $messageNumber);
    }

    public function test_internal_users_can_export_scoped_mailbox_messages(): void
    {
        $this->seed();

        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();
        $message = CollaborationMessage::where('message_number', 'MSG-10001')->firstOrFail();

        $response = $this->actingAs($finance)
            ->get(route('collaboration.messages.export', [
                'folder' => 'all',
                'format' => 'csv',
            ]));

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertSee('message_number')
            ->assertSee('MSG-10001')
            ->assertSee('Skyline payment follow-up coordination');

        $this->assertStringContainsString('builder360-collaboration-messages.csv', $response->headers->get('Content-Disposition', ''));
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control', ''));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control', ''));

        $this->assertDatabaseHas('audit_events', [
            'user_id' => $finance->id,
            'event_type' => 'collaboration.message.exported',
            'action' => 'Exported collaboration message register',
        ]);

        $this->actingAs($finance)
            ->get(route('collaboration.messages.export', [
                'folder' => 'all',
                'format' => 'pdf',
            ]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $singleMessageResponse = $this->actingAs($finance)
            ->get(route('collaboration.messages.export', [
                'folder' => 'all',
                'message_id' => $message->id,
                'format' => 'pdf',
            ]));

        $singleMessageResponse
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringContainsString(
            'builder360-mailbox-message-MSG-10001.pdf',
            $singleMessageResponse->headers->get('Content-Disposition', ''),
        );

        $this->actingAs($construction)
            ->getJson(route('collaboration.messages.export', [
                'folder' => 'all',
                'message_id' => $message->id,
                'format' => 'pdf',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('message_id');

        $this->actingAs($partner)
            ->get(route('collaboration.messages.export', ['folder' => 'all']))
            ->assertForbidden();
    }

    public function test_mailbox_scheduled_send_is_hidden_until_release_then_notifies_recipient(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $scheduledFor = now()->addDay()->setSeconds(0);

        $response = $this->actingAs($sales)
            ->postJson(route('collaboration.messages.store'), [
                'recipient_user_ids' => [$finance->id],
                'subject' => 'Scheduled collection follow-up',
                'body' => 'Send this only at the scheduled time.',
                'priority' => 'high',
                'scheduled_for' => $scheduledFor->toISOString(),
                'metadata' => ['source' => 'scheduled_send_test'],
            ])
            ->assertCreated()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'scheduled')
            ->assertJsonPath('data.0.sent_at', null);

        $message = CollaborationMessage::where('message_number', $response->json('data.0.message_number'))->firstOrFail();

        $this->assertNotNull($message->scheduled_for);
        $this->assertNull($message->sent_at);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'collaboration.message.scheduled',
            'auditable_type' => CollaborationMessage::class,
            'auditable_id' => $message->id,
        ]);

        $this->assertDatabaseMissing('user_notifications', [
            'recipient_user_id' => $finance->id,
            'notifiable_type' => CollaborationMessage::class,
            'notifiable_id' => $message->id,
        ]);

        $this->actingAs($finance)
            ->getJson(route('collaboration.messages.index', ['folder' => 'inbox']))
            ->assertOk()
            ->assertJsonMissing(['message_number' => $message->message_number]);

        $this->actingAs($sales)
            ->getJson(route('collaboration.messages.index', ['folder' => 'sent', 'status' => 'scheduled']))
            ->assertOk()
            ->assertJsonPath('data.0.message_number', $message->message_number);

        $this->assertSame(1, app(CollaborationService::class)->releaseDueScheduledMessages(now()->addDays(2)));

        $message->refresh();

        $this->assertSame('unread', $message->status);
        $this->assertNotNull($message->sent_at);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'collaboration.message.scheduled_released',
            'auditable_type' => CollaborationMessage::class,
            'auditable_id' => $message->id,
        ]);

        $this->assertDatabaseHas('user_notifications', [
            'recipient_user_id' => $finance->id,
            'triggered_by_user_id' => $sales->id,
            'category' => 'mailbox',
            'severity' => 'info',
            'notifiable_type' => CollaborationMessage::class,
            'notifiable_id' => $message->id,
        ]);

        $this->actingAs($finance)
            ->getJson(route('collaboration.messages.index', ['folder' => 'inbox']))
            ->assertOk()
            ->assertJsonFragment(['message_number' => $message->message_number]);
    }

    public function test_sender_can_cancel_scheduled_mailbox_message_before_release(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();

        $response = $this->actingAs($sales)
            ->postJson(route('collaboration.messages.store'), [
                'recipient_user_ids' => [$finance->id],
                'subject' => 'Scheduled cancellation check',
                'body' => 'This scheduled message will be cancelled.',
                'scheduled_for' => now()->addDay()->toISOString(),
            ])
            ->assertCreated();

        $message = CollaborationMessage::where('message_number', $response->json('data.0.message_number'))->firstOrFail();

        $this->actingAs($finance)
            ->patchJson(route('collaboration.messages.cancel-scheduled', $message), ['reason' => 'Recipient cannot cancel'])
            ->assertForbidden();

        $this->actingAs($sales)
            ->patchJson(route('collaboration.messages.cancel-scheduled', $message), ['reason' => 'No longer required'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.metadata.scheduled_cancel.reason', 'No longer required');

        $this->assertSame(0, app(CollaborationService::class)->releaseDueScheduledMessages(now()->addDays(2)));

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'collaboration.message.scheduled_cancelled',
            'auditable_type' => CollaborationMessage::class,
            'auditable_id' => $message->id,
        ]);
    }

    public function test_chat_connect_new_conversation_persists_as_governed_collaboration_message(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();

        $response = $this->actingAs($sales)
            ->postJson(route('collaboration.messages.store'), [
                'recipient_user_ids' => [$finance->id],
                'subject' => 'Chat: Finance handoff for Skyline booking',
                'body' => 'Please validate the buyer collection schedule and confirm next payment milestone.',
                'priority' => 'high',
                'metadata' => [
                    'source' => 'chat_connect_new_conversation',
                    'conversation_name' => 'Finance handoff for Skyline booking',
                ],
            ])
            ->assertCreated()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.subject', 'Chat: Finance handoff for Skyline booking')
            ->assertJsonPath('data.0.priority', 'high')
            ->assertJsonPath('data.0.status', 'unread')
            ->assertJsonPath('data.0.metadata.source', 'chat_connect_new_conversation')
            ->assertJsonPath('data.0.recipient.email', 'suresh.iyer@builder360.test');

        $message = CollaborationMessage::where('message_number', $response->json('data.0.message_number'))->firstOrFail();

        $this->assertSame('chat_connect_new_conversation', $message->metadata['source']);
        $this->assertSame('Finance handoff for Skyline booking', $message->metadata['conversation_name']);
        $this->assertNotNull($message->thread_key);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'collaboration.message.sent',
            'auditable_type' => CollaborationMessage::class,
            'auditable_id' => $message->id,
        ]);

        $this->assertDatabaseHas('user_notifications', [
            'recipient_user_id' => $finance->id,
            'triggered_by_user_id' => $sales->id,
            'category' => 'mailbox',
            'severity' => 'info',
            'notifiable_type' => CollaborationMessage::class,
            'notifiable_id' => $message->id,
        ]);
    }

    public function test_chat_connect_internal_conversation_endpoints_are_role_safe_and_persistent(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $buyer = User::where('email', 'rohan.shah@example.test')->firstOrFail();
        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();

        $response = $this->actingAs($sales)
            ->postJson(route('collaboration.chat.conversations.store'), [
                'type' => 'direct_message',
                'title' => 'Finance collection check',
                'member_user_ids' => [$finance->id],
                'body' => 'Please verify the payment follow-up for the Skyline booking.',
                'priority' => 'high',
            ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'direct_message')
            ->assertJsonPath('data.title', 'Suresh Iyer')
            ->assertJsonPath('data.can_post', true)
            ->assertJsonCount(1, 'messages');

        $conversation = ChatConversation::where('conversation_key', $response->json('data.conversation_key'))->firstOrFail();

        $this->assertDatabaseHas('chat_conversation_members', [
            'chat_conversation_id' => $conversation->id,
            'user_id' => $sales->id,
            'member_role' => 'owner',
        ]);
        $this->assertDatabaseHas('chat_conversation_members', [
            'chat_conversation_id' => $conversation->id,
            'user_id' => $finance->id,
            'member_role' => 'member',
        ]);
        $this->assertDatabaseHas('chat_messages', [
            'chat_conversation_id' => $conversation->id,
            'sender_user_id' => $sales->id,
            'priority' => 'high',
            'type' => 'text',
            'body' => 'Please verify the payment follow-up for the Skyline booking.',
        ]);
        $firstMessage = ChatMessage::where('chat_conversation_id', $conversation->id)
            ->where('sender_user_id', $sales->id)
            ->firstOrFail();
        $this->assertDatabaseHas('chat_message_reads', [
            'chat_message_id' => $firstMessage->id,
            'user_id' => $finance->id,
        ]);

        $this->actingAs($finance)
            ->getJson(route('collaboration.chat.conversations.index'))
            ->assertOk()
            ->assertJsonFragment(['conversation_key' => $conversation->conversation_key])
            ->assertJsonPath('data.0.unread_count', 1);

        $this->actingAs($finance)
            ->postJson(route('collaboration.chat.conversations.messages.store', $conversation), [
                'body' => 'Payment follow-up is confirmed.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.body', 'Payment follow-up is confirmed.')
            ->assertJsonPath('data.conversation_id', $conversation->id);

        $this->actingAs($sales)
            ->getJson(route('collaboration.chat.conversations.messages.index', $conversation))
            ->assertOk()
            ->assertJsonFragment(['body' => 'Payment follow-up is confirmed.']);

        $this->actingAs($finance)
            ->patchJson(route('collaboration.chat.conversations.read', $conversation))
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->assertSame(0, ChatMessageRead::query()
            ->where('user_id', $finance->id)
            ->whereHas('message', fn ($query) => $query->where('chat_conversation_id', $conversation->id))
            ->whereNull('read_at')
            ->count());

        $this->actingAs($finance)
            ->patchJson(route('collaboration.chat.conversations.archive', $conversation))
            ->assertOk()
            ->assertJsonPath('archived', true);

        $this->actingAs($sales)
            ->postJson(route('collaboration.chat.conversations.store'), [
                'type' => 'direct_message',
                'title' => 'Blocked external chat',
                'member_user_ids' => [$buyer->id],
                'body' => 'This should be rejected.',
            ])
            ->assertUnprocessable();

        $this->actingAs($partner)
            ->getJson(route('collaboration.chat.conversations.index'))
            ->assertForbidden();
    }

    public function test_mailbox_replies_keep_thread_key_and_reject_non_participant_parent(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $seeded = CollaborationMessage::where('message_number', 'MSG-10001')->firstOrFail();

        $replyThread = $this->actingAs($finance)
            ->postJson(route('collaboration.messages.store'), [
                'parent_message_id' => $seeded->id,
                'recipient_user_ids' => [$sales->id],
                'subject' => 'Re: Skyline payment follow-up coordination',
                'body' => 'Payment link reviewed. Finance will monitor the receipt status.',
                'priority' => 'normal',
            ])
            ->assertCreated()
            ->assertJsonPath('data.0.parent_message_id', $seeded->id)
            ->json('data.0.thread_key');

        $this->assertSame($seeded->thread_key, $replyThread);

        $this->actingAs($hr)
            ->postJson(route('collaboration.messages.store'), [
                'parent_message_id' => $seeded->id,
                'recipient_user_ids' => [$sales->id],
                'subject' => 'Invalid non-participant reply',
                'body' => 'This must be rejected.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['parent_message_id']);
    }

    public function test_mailbox_crm_link_is_persisted_in_message_metadata_with_audit_scope_checks(): void
    {
        $this->seed();

        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();
        $message = CollaborationMessage::where('message_number', 'MSG-10001')->firstOrFail();
        $project = Project::where('company_id', $message->company_id)->firstOrFail();

        $this->actingAs($finance)
            ->patchJson(route('collaboration.messages.crm-link.update', $message), [
                'action' => 'link',
                'record_type' => 'project',
                'record_id' => $project->id,
                'note' => 'Payment follow-up linked from mailbox.',
            ])
            ->assertOk()
            ->assertJsonPath('data.metadata.crm_link.record_type', 'project')
            ->assertJsonPath('data.metadata.crm_link.record_id', $project->id)
            ->assertJsonPath('data.metadata.crm_link.linked_by_user_id', $finance->id)
            ->assertJsonPath('data.metadata.crm_link.note', 'Payment follow-up linked from mailbox.');

        $message->refresh();
        $this->assertSame('project', $message->metadata['crm_link']['record_type']);
        $this->assertSame($project->id, $message->metadata['crm_link']['record_id']);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'collaboration.message.crm_linked',
            'auditable_type' => CollaborationMessage::class,
            'auditable_id' => $message->id,
        ]);

        $this->actingAs($hr)
            ->patchJson(route('collaboration.messages.crm-link.update', $message), [
                'action' => 'unlink',
            ])
            ->assertForbidden();

        $this->actingAs($partner)
            ->patchJson(route('collaboration.messages.crm-link.update', $message), [
                'action' => 'unlink',
            ])
            ->assertForbidden();

        $this->actingAs($finance)
            ->patchJson(route('collaboration.messages.crm-link.update', $message), [
                'action' => 'unlink',
            ])
            ->assertOk()
            ->assertJsonMissingPath('data.metadata.crm_link');

        $message->refresh();
        $this->assertArrayNotHasKey('crm_link', $message->metadata ?? []);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'collaboration.message.crm_unlinked',
            'auditable_type' => CollaborationMessage::class,
            'auditable_id' => $message->id,
        ]);
    }

    public function test_mailbox_lead_quick_actions_can_log_existing_crm_activity(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $message = CollaborationMessage::where('message_number', 'MSG-10001')->firstOrFail();
        $lead = Lead::where('company_id', $message->company_id)->firstOrFail();

        $this->actingAs($sales)
            ->patchJson(route('collaboration.messages.crm-link.update', $message), [
                'action' => 'link',
                'record_type' => 'lead',
                'record_id' => $lead->id,
                'note' => 'Lead linked for mailbox timeline logging.',
            ])
            ->assertOk()
            ->assertJsonPath('data.metadata.crm_link.record_type', 'lead')
            ->assertJsonPath('data.metadata.crm_link.record_id', $lead->id);

        $activityNumber = $this->actingAs($sales)
            ->postJson(route('crm.lead-activities.store'), [
                'lead_id' => $lead->id,
                'activity_type' => 'email',
                'subject' => 'Mailbox email: '.$message->subject,
                'description' => 'Direction: Inbound'.PHP_EOL.PHP_EOL.'Mailbox message: '.$message->message_number,
                'outcome' => 'mailbox_logged',
                'metadata' => [
                    'source' => 'mailbox_quick_action',
                    'mailbox_action' => 'log_email',
                    'mailbox_message_number' => $message->message_number,
                    'mailbox_message_id' => $message->id,
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.activity_type', 'email')
            ->assertJsonPath('data.metadata.source', 'mailbox_quick_action')
            ->json('data.activity_number');

        $activity = LeadActivity::where('activity_number', $activityNumber)->firstOrFail();

        $this->assertSame('mailbox_quick_action', $activity->metadata['source']);
        $this->assertSame($message->id, $activity->metadata['mailbox_message_id']);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'crm.lead_activity.created',
            'auditable_type' => LeadActivity::class,
            'auditable_id' => $activity->id,
        ]);
    }

    public function test_mailbox_user_state_is_persisted_in_metadata_and_read_state_is_permission_controlled(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();
        $message = CollaborationMessage::where('message_number', 'MSG-10001')->firstOrFail();

        $flagResponse = $this->actingAs($finance)
            ->patchJson(route('collaboration.messages.state.update', $message), [
                'action' => 'set_flags',
                'starred' => true,
                'important' => true,
            ])
            ->assertOk();

        $flagMap = $flagResponse->json('data.metadata.mailbox_user_state') ?? [];
        $stateKey = 'user_'.$finance->id;
        $flagState = $flagMap[$stateKey] ?? [];
        $this->assertTrue($flagState['starred'] ?? false);
        $this->assertTrue($flagState['important'] ?? false);

        $labelResponse = $this->actingAs($finance)
            ->patchJson(route('collaboration.messages.state.update', $message), [
                'action' => 'set_labels',
                'labels' => ['finance', 'customer_followup'],
            ])
            ->assertOk();

        $labelMap = $labelResponse->json('data.metadata.mailbox_user_state') ?? [];
        $labelState = $labelMap[$stateKey] ?? [];
        $this->assertSame(['finance', 'customer_followup'], $labelState['labels'] ?? []);

        $folderResponse = $this->actingAs($finance)
            ->patchJson(route('collaboration.messages.state.update', $message), [
                'action' => 'move',
                'folder' => 'spam',
            ])
            ->assertOk();

        $folderMap = $folderResponse->json('data.metadata.mailbox_user_state') ?? [];
        $folderState = $folderMap[$stateKey] ?? [];
        $this->assertSame('spam', $folderState['folder'] ?? null);

        $snoozedUntil = now()->addDay()->setTime(9, 0)->toISOString();
        $snoozeResponse = $this->actingAs($finance)
            ->patchJson(route('collaboration.messages.state.update', $message), [
                'action' => 'snooze',
                'snoozed_until' => $snoozedUntil,
                'note' => 'Follow up tomorrow from mailbox.',
            ])
            ->assertOk();

        $snoozeMap = $snoozeResponse->json('data.metadata.mailbox_user_state') ?? [];
        $snoozeState = $snoozeMap[$stateKey] ?? [];
        $this->assertSame('snoozed', $snoozeState['folder'] ?? null);
        $this->assertSame($snoozedUntil, $snoozeState['snoozed_until'] ?? null);
        $this->assertSame('Follow up tomorrow from mailbox.', $snoozeState['snooze_note'] ?? null);

        $this->actingAs($finance)
            ->patchJson(route('collaboration.messages.state.update', $message), [
                'action' => 'mark_read',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'read');

        $this->actingAs($finance)
            ->patchJson(route('collaboration.messages.state.update', $message), [
                'action' => 'mark_unread',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'unread')
            ->assertJsonPath('data.read_at', null);

        $message->refresh();
        $this->assertSame('snoozed', $message->metadata['mailbox_user_state'][$stateKey]['folder']);
        $this->assertSame($snoozedUntil, $message->metadata['mailbox_user_state'][$stateKey]['snoozed_until']);
        $this->assertSame(['finance', 'customer_followup'], $message->metadata['mailbox_user_state'][$stateKey]['labels']);

        $this->actingAs($finance)
            ->patchJson(route('collaboration.messages.state.update', $message), [
                'action' => 'snooze',
                'snoozed_until' => now()->subMinute()->toISOString(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['snoozed_until']);

        $this->actingAs($finance)
            ->patchJson(route('collaboration.messages.state.update', $message), [
                'action' => 'snooze',
                'snoozed_until' => now()->addDays(91)->toISOString(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['snoozed_until']);

        $this->actingAs($sales)
            ->patchJson(route('collaboration.messages.state.update', $message), [
                'action' => 'mark_unread',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['action']);

        $this->actingAs($partner)
            ->patchJson(route('collaboration.messages.state.update', $message), [
                'action' => 'set_flags',
                'starred' => true,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'collaboration.message.mailbox_state_updated',
            'auditable_type' => CollaborationMessage::class,
            'auditable_id' => $message->id,
        ]);
    }

    public function test_mailbox_reactions_are_persisted_in_metadata_and_permission_controlled(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();
        $message = CollaborationMessage::where('message_number', 'MSG-10001')->firstOrFail();

        $this->actingAs($finance)
            ->patchJson(route('collaboration.messages.reactions.update', $message), [
                'emoji' => '👍',
                'action' => 'toggle',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Mailbox message reaction updated.');

        $message->refresh();
        $this->assertCount(1, $message->metadata['reactions']['👍'] ?? []);
        $this->assertSame($finance->id, $message->metadata['reactions']['👍'][0]['user_id'] ?? null);

        $this->actingAs($sales)
            ->patchJson(route('collaboration.messages.reactions.update', $message), [
                'emoji' => '👍',
                'action' => 'add',
            ])
            ->assertOk();

        $message->refresh();
        $this->assertCount(2, $message->metadata['reactions']['👍'] ?? []);

        $this->actingAs($finance)
            ->patchJson(route('collaboration.messages.reactions.update', $message), [
                'emoji' => '👍',
                'action' => 'toggle',
            ])
            ->assertOk();

        $message->refresh();
        $this->assertCount(1, $message->metadata['reactions']['👍'] ?? []);
        $this->assertSame($sales->id, $message->metadata['reactions']['👍'][0]['user_id'] ?? null);

        $this->actingAs($partner)
            ->patchJson(route('collaboration.messages.reactions.update', $message), [
                'emoji' => '🔥',
                'action' => 'toggle',
            ])
            ->assertForbidden();

        $this->actingAs($finance)
            ->patchJson(route('collaboration.messages.reactions.update', $message), [
                'emoji' => '<script>',
                'action' => 'toggle',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['emoji']);

        $scheduled = CollaborationMessage::create([
            'company_id' => $message->company_id,
            'sender_user_id' => $sales->id,
            'recipient_user_id' => $finance->id,
            'message_number' => 'MSG-REACTION-SCHEDULED',
            'thread_key' => 'THR-REACTION-SCHEDULED',
            'subject' => 'Scheduled reaction validation',
            'body' => 'This scheduled message should not accept reactions.',
            'priority' => 'normal',
            'status' => 'scheduled',
            'scheduled_for' => now()->addDay(),
            'metadata' => ['source' => 'reaction_test'],
        ]);

        $this->actingAs($finance)
            ->patchJson(route('collaboration.messages.reactions.update', $scheduled), [
                'emoji' => '✅',
                'action' => 'toggle',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'collaboration.message.reaction_updated',
            'auditable_type' => CollaborationMessage::class,
            'auditable_id' => $message->id,
        ]);
    }

    public function test_mailbox_validates_filters_recipients_and_company_scope(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $buyer = User::where('email', 'rohan.shah@example.test')->firstOrFail();
        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();
        $otherCompany = Company::where('code', 'B360P')->firstOrFail();
        $otherProject = Project::where('company_id', $otherCompany->id)->firstOrFail();
        $externalUser = User::factory()->create([
            'company_id' => $otherCompany->id,
            'name' => 'External Mailbox User',
            'email' => 'external.mailbox@example.test',
            'status' => 'active',
        ]);

        $this->actingAs($sales)
            ->getJson(route('collaboration.messages.index', ['unexpected_filter' => 'blocked']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['unexpected_filter'])
            ->assertJsonPath('errors.unexpected_filter.0', 'The selected filter is not available for this endpoint.');

        $this->actingAs($sales)
            ->getJson(route('collaboration.messages.index', ['project_id' => $otherProject->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id']);

        $invalidRecipientPayload = [
            'recipient_user_ids' => [$sales->id],
            'subject' => 'Invalid mailbox recipient',
            'body' => 'This message must be rejected.',
        ];

        $this->actingAs($sales)
            ->postJson(route('collaboration.messages.store'), $invalidRecipientPayload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['recipient_user_ids']);

        foreach ([$buyer, $partner, $externalUser] as $recipient) {
            $this->actingAs($sales)
                ->postJson(route('collaboration.messages.store'), [
                    'recipient_user_ids' => [$recipient->id],
                    'subject' => 'Invalid mailbox recipient',
                    'body' => 'This message must be rejected.',
                ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['recipient_user_ids']);
        }

        $this->actingAs($sales)
            ->postJson(route('collaboration.messages.store'), [
                'project_id' => $otherProject->id,
                'recipient_user_ids' => [$finance->id],
                'subject' => 'Invalid cross-company project',
                'body' => 'This message must be rejected.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id']);
    }

    public function test_mailbox_fails_closed_without_company_assignment_and_blocks_partner_access(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();
        $message = CollaborationMessage::where('message_number', 'MSG-10001')->firstOrFail();

        $finance->forceFill(['company_id' => null])->save();

        $this->actingAs($finance)
            ->getJson(route('collaboration.messages.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($finance)
            ->patchJson(route('collaboration.messages.read', $message))
            ->assertForbidden();

        $this->actingAs($finance)
            ->postJson(route('collaboration.messages.store'), [
                'recipient_user_ids' => [$sales->id],
                'subject' => 'Scope failure',
                'body' => 'This must fail closed.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['recipient_user_ids']);

        $this->actingAs($partner)
            ->getJson(route('collaboration.messages.index'))
            ->assertForbidden();

        $this->actingAs($partner)
            ->postJson(route('collaboration.messages.store'), [
                'recipient_user_ids' => [$sales->id],
                'subject' => 'Partner denied',
                'body' => 'Partner must not access internal mailbox.',
            ])
            ->assertForbidden();
    }
}
