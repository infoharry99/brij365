<?php

namespace Tests\Feature;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatMessageAttachment;
use App\Models\ChatPoll;
use App\Models\Project;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Builder360\Builder360Bootstrap;
use App\Services\Collaboration\ChatAccessService;
use App\Services\Collaboration\ChatConnectService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ChatConnectFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_internal_user_can_use_classic_blade_chat_connect_workspace(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();

        $this->actingAs($sales)
            ->post(route('collaboration.chat.conversations.store'), [
                'type' => 'direct_message',
                'title' => 'Classic chat verification',
                'member_user_ids' => [$finance->id],
                'body' => 'First server-rendered chat message.',
            ])
            ->assertRedirect();

        $conversation = ChatConversation::where('title', 'Classic chat verification')->firstOrFail();

        $this->actingAs($sales)
            ->get(route('collaboration.chat.index', ['conversation_id' => $conversation->id]))
            ->assertOk()
            ->assertSee('Chat Connect')
            ->assertSee('Suresh Iyer')
            ->assertSee('First server-rendered chat message.')
            ->assertSee('Send message')
            ->assertSee('class="b360-shell"', false)
            ->assertDontSee('id="root"', false)
            ->assertDontSee('resources/js/app.jsx', false);
    }

    public function test_role_based_chat_access_controls_bootstrap_and_routes(): void
    {
        $this->seed();

        $employee = User::where('email', 'amit.verma@builder360.test')->firstOrFail();
        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();

        $employeeBootstrap = app(Builder360Bootstrap::class)->forUser($employee);
        $partnerBootstrap = app(Builder360Bootstrap::class)->forUser($partner);

        $this->assertIsArray($employeeBootstrap['chat_connect_options']);
        $this->assertTrue($employeeBootstrap['chat_connect_options']['enabled']);
        $this->assertTrue($employeeBootstrap['chat_connect_options']['capabilities']['poll']);
        $this->assertArrayHasKey('role_access', $employeeBootstrap['chat_connect_options']);
        $this->assertNull($partnerBootstrap['chat_connect_options']);

        $this->actingAs($partner)
            ->getJson(route('collaboration.chat.conversations.index'))
            ->assertForbidden();
    }

    public function test_chat_file_and_voice_note_uploads_are_private_and_membership_checked(): void
    {
        $this->seed();
        Storage::fake('local');

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();

        $conversationResponse = $this->actingAs($sales)
            ->postJson(route('collaboration.chat.conversations.store'), [
                'type' => 'direct_message',
                'title' => 'Attachment test',
                'member_user_ids' => [$finance->id],
                'body' => 'Starting attachment conversation.',
            ])
            ->assertCreated();

        $conversation = ChatConversation::where('conversation_key', $conversationResponse->json('data.conversation_key'))->firstOrFail();

        $fileResponse = $this->actingAs($sales)
            ->post(route('collaboration.chat.conversations.messages.store', $conversation), [
                'body' => 'Sharing project file.',
                'message_type' => 'file',
                'attachments' => [
                    UploadedFile::fake()->create('project-brief.pdf', 18, 'application/pdf'),
                ],
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.attachments.0.type', 'file')
            ->assertJsonPath('data.attachments.0.filename', 'project-brief.pdf');

        $attachment = ChatMessageAttachment::where('original_filename', 'project-brief.pdf')->firstOrFail();
        Storage::disk('local')->assertExists($attachment->path);

        $this->actingAs($finance)
            ->get(route('collaboration.chat.attachments.download', $attachment))
            ->assertOk();

        $this->actingAs($hr)
            ->get(route('collaboration.chat.attachments.download', $attachment))
            ->assertForbidden();

        $this->actingAs($sales)
            ->post(route('collaboration.chat.conversations.messages.store', $conversation), [
                'message_type' => 'voice_note',
                'duration_seconds' => 7,
                'attachments' => [
                    UploadedFile::fake()->create('voice-note.webm', 10, 'audio/webm'),
                ],
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.type', 'voice_note')
            ->assertJsonPath('data.attachments.0.type', 'voice_note');

        $this->assertDatabaseHas('chat_message_attachments', [
            'original_filename' => 'voice-note.webm',
            'type' => 'voice_note',
            'mime_type' => 'audio/webm',
        ]);

        $this->assertNotNull($fileResponse->json('data.attachments.0.download_url'));
    }

    public function test_chat_poll_creation_voting_and_closing_are_permission_checked(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();

        $conversationResponse = $this->actingAs($sales)
            ->postJson(route('collaboration.chat.conversations.store'), [
                'type' => 'direct_message',
                'title' => 'Poll test',
                'member_user_ids' => [$finance->id],
                'body' => 'Starting poll conversation.',
            ])
            ->assertCreated();

        $conversation = ChatConversation::where('conversation_key', $conversationResponse->json('data.conversation_key'))->firstOrFail();

        $pollResponse = $this->actingAs($sales)
            ->postJson(route('collaboration.chat.conversations.polls.store', $conversation), [
                'question' => 'Which follow-up should we prioritize?',
                'options' => ['Payment call', 'Site visit'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'poll')
            ->assertJsonPath('data.poll.question', 'Which follow-up should we prioritize?');

        $poll = ChatPoll::where('question', 'Which follow-up should we prioritize?')->with('options')->firstOrFail();

        $this->actingAs($finance)
            ->postJson(route('collaboration.chat.polls.votes.store', $poll), [
                'option_ids' => [$poll->options->first()->id],
            ])
            ->assertOk()
            ->assertJsonPath('data.poll.total_votes', 1);

        $this->actingAs($partner)
            ->postJson(route('collaboration.chat.polls.votes.store', $poll), [
                'option_ids' => [$poll->options->first()->id],
            ])
            ->assertForbidden();

        $this->actingAs($partner)
            ->patchJson(route('collaboration.chat.polls.close', $poll))
            ->assertForbidden();

        $this->actingAs($sales)
            ->patchJson(route('collaboration.chat.polls.close', $poll))
            ->assertOk()
            ->assertJsonPath('data.poll.status', 'closed');

        $this->actingAs($finance)
            ->postJson(route('collaboration.chat.polls.votes.store', $poll->refresh()), [
                'option_ids' => [$poll->options->first()->id],
            ])
            ->assertUnprocessable();

        $removedConversationResponse = $this->actingAs($sales)
            ->postJson(route('collaboration.chat.conversations.store'), [
                'type' => 'direct_message',
                'title' => 'Removed creator poll test',
                'member_user_ids' => [$finance->id],
                'body' => 'Starting removed creator poll conversation.',
            ])
            ->assertOk();

        $removedConversation = ChatConversation::where('conversation_key', $removedConversationResponse->json('data.conversation_key'))->firstOrFail();

        $removedPollResponse = $this->actingAs($sales)
            ->postJson(route('collaboration.chat.conversations.polls.store', $removedConversation), [
                'question' => 'Can a removed creator close this?',
                'options' => ['Yes', 'No'],
            ])
            ->assertCreated();

        $removedPoll = ChatPoll::where('question', 'Can a removed creator close this?')->firstOrFail();
        $removedConversation->members()->where('user_id', $sales->id)->update(['removed_at' => now()]);

        $this->actingAs($sales)
            ->patchJson(route('collaboration.chat.polls.close', $removedPoll))
            ->assertForbidden();

        $this->actingAs($hr)
            ->patchJson(route('collaboration.chat.polls.close', $removedPoll))
            ->assertForbidden();
    }

    public function test_chat_forward_and_reply_contexts_do_not_create_unintended_conversations(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();

        $sourceResponse = $this->actingAs($sales)
            ->postJson(route('collaboration.chat.conversations.store'), [
                'type' => 'direct_message',
                'title' => 'Source chat',
                'member_user_ids' => [$finance->id],
                'body' => 'Original message for forwarding.',
            ])
            ->assertCreated();

        $targetResponse = $this->actingAs($sales)
            ->postJson(route('collaboration.chat.conversations.store'), [
                'type' => 'direct_message',
                'title' => 'Target chat',
                'member_user_ids' => [$hr->id],
                'body' => 'Existing target conversation.',
            ])
            ->assertCreated();

        $sourceConversation = ChatConversation::where('conversation_key', $sourceResponse->json('data.conversation_key'))->firstOrFail();
        $targetConversation = ChatConversation::where('conversation_key', $targetResponse->json('data.conversation_key'))->firstOrFail();
        $sourceMessage = ChatMessage::where('chat_conversation_id', $sourceConversation->id)
            ->where('body', 'Original message for forwarding.')
            ->firstOrFail();

        $conversationCount = ChatConversation::count();

        $this->actingAs($sales)
            ->postJson(route('collaboration.chat.conversations.messages.store', $targetConversation), [
                'body' => 'Forwarded message '.$sourceMessage->message_number.' from Source chat:',
                'priority' => 'normal',
                'metadata' => [
                    'source' => 'chat_connect_forward',
                    'forwarded_from_message_id' => $sourceMessage->id,
                    'forwarded_from_conversation_id' => $sourceConversation->id,
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.conversation_id', $targetConversation->id)
            ->assertJsonPath('data.parent_message_id', null);

        $this->assertSame($conversationCount, ChatConversation::count());
        $this->assertDatabaseHas('chat_messages', [
            'chat_conversation_id' => $targetConversation->id,
            'sender_user_id' => $sales->id,
            'body' => 'Forwarded message '.$sourceMessage->message_number.' from Source chat:',
        ]);

        $this->actingAs($sales)
            ->postJson(route('collaboration.chat.conversations.messages.store', $sourceConversation), [
                'body' => 'Reply inside source conversation.',
                'parent_message_id' => $sourceMessage->id,
                'priority' => 'high',
            ])
            ->assertCreated()
            ->assertJsonPath('data.conversation_id', $sourceConversation->id)
            ->assertJsonPath('data.parent_message_id', $sourceMessage->id)
            ->assertJsonPath('data.priority', 'high');

        $this->actingAs($sales)
            ->postJson(route('collaboration.chat.conversations.messages.store', $targetConversation), [
                'body' => 'Invalid cross-conversation reply.',
                'parent_message_id' => $sourceMessage->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('parent_message_id');

        $this->assertSame($conversationCount, ChatConversation::count());
    }

    public function test_chat_connect_bootstrap_does_not_require_mailbox_permission(): void
    {
        $this->seed();

        $employee = User::where('email', 'amit.verma@builder360.test')->firstOrFail();
        $role = Role::create([
            'slug' => 'chat_only',
            'name' => 'Chat Only',
            'scope_level' => 'company',
            'permissions' => [],
            'is_active' => true,
        ]);
        $user = User::create([
            'role_id' => $role->id,
            'company_id' => $employee->company_id,
            'name' => 'Chat Only User',
            'email' => 'chat.only@builder360.test',
            'password' => 'password',
            'status' => 'active',
        ]);

        SystemSetting::create([
            'company_id' => null,
            'created_by_user_id' => $employee->id,
            'approved_by_user_id' => $employee->id,
            'scope_key' => 'global',
            'setting_group' => 'collaboration',
            'setting_key' => ChatAccessService::SETTING_KEY,
            'label' => 'Chat Connect Access',
            'description' => 'Chat-only test access.',
            'value_type' => 'json',
            'value' => [
                'roles' => [
                    'chat_only' => [
                        'can_view' => true,
                        'can_create_dm' => true,
                        'can_create_group' => false,
                        'can_create_channel' => false,
                        'can_post' => true,
                        'can_upload' => false,
                        'can_send_voice' => false,
                        'can_create_poll' => false,
                        'can_vote_poll' => true,
                        'can_manage_members' => false,
                        'can_archive' => false,
                        'can_export' => false,
                        'read_only' => false,
                    ],
                ],
            ],
            'status' => 'active',
            'version' => 99,
            'approved_at' => now(),
        ]);

        $this->assertFalse($user->can('viewAny', \App\Models\CollaborationMessage::class));

        $bootstrap = app(Builder360Bootstrap::class)->forUser($user);

        $this->assertIsArray($bootstrap['chat_connect_options']);
        $this->assertTrue($bootstrap['chat_connect_options']['enabled']);
        $this->assertSame($user->id, $bootstrap['chat_connect_options']['current_user_id']);
        $this->assertArrayHasKey('conversation_index_url', $bootstrap['chat_connect_options']);
        $this->assertArrayHasKey('conversation_store_url', $bootstrap['chat_connect_options']);
        $this->assertArrayHasKey('recipients', $bootstrap['chat_connect_options']);
    }

    public function test_chat_message_loading_is_enforced_inside_service(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();

        $conversationResponse = $this->actingAs($sales)
            ->postJson(route('collaboration.chat.conversations.store'), [
                'type' => 'direct_message',
                'title' => 'Message loading service test',
                'member_user_ids' => [$finance->id],
                'body' => 'This should be visible only to members.',
            ])
            ->assertCreated();

        $conversation = ChatConversation::where('conversation_key', $conversationResponse->json('data.conversation_key'))->firstOrFail();

        $this->expectException(AuthorizationException::class);

        app(ChatConnectService::class)->activeMessages($conversation, $hr);
    }

    public function test_direct_messages_reuse_the_existing_pair_and_use_participant_titles(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();

        $first = $this->actingAs($sales)->postJson(route('collaboration.chat.conversations.store'), [
            'type' => 'direct_message',
            'title' => 'First title must not create a second chat',
            'member_user_ids' => [$finance->id],
            'body' => 'First direct message.',
        ])->assertCreated();

        $second = $this->actingAs($sales)->postJson(route('collaboration.chat.conversations.store'), [
            'type' => 'direct_message',
            'title' => 'A different title for the same pair',
            'member_user_ids' => [$finance->id],
            'body' => 'Second direct message.',
        ])->assertOk();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, ChatConversation::where('type', 'direct_message')->count());

        $conversation = ChatConversation::findOrFail($first->json('data.id'));
        $this->assertSame('Suresh Iyer', $conversation->displayTitleFor($sales));
        $this->assertSame('Priya Nair', $conversation->displayTitleFor($finance));
        $this->assertDatabaseHas('chat_messages', ['chat_conversation_id' => $conversation->id, 'body' => 'Second direct message.']);
    }

    public function test_chat_filters_search_members_and_mentions_and_deep_links_outside_the_first_page(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();

        $response = $this->actingAs($sales)->postJson(route('collaboration.chat.conversations.store'), [
            'type' => 'direct_message',
            'member_user_ids' => [$finance->id],
            'body' => 'Mentioning Priya in a searchable finance conversation.',
        ])->assertCreated();

        $conversation = ChatConversation::findOrFail($response->json('data.id'));

        $this->actingAs($sales)
            ->getJson(route('collaboration.chat.conversations.index', ['q' => 'suresh.iyer']))
            ->assertOk()
            ->assertJsonPath('data.0.id', $conversation->id);

        $neutralGroupResponse = $this->actingAs($sales)->postJson(route('collaboration.chat.conversations.store'), [
            'type' => 'group_chat',
            'title' => 'Neutral operations room',
            'member_user_ids' => [$finance->id],
            'body' => 'Budget follow-up.',
        ])->assertCreated();

        $ownNameSearch = $this->actingAs($sales)
            ->getJson(route('collaboration.chat.conversations.index', ['q' => $sales->email]))
            ->assertOk();

        $this->assertNotContains(
            $neutralGroupResponse->json('data.id'),
            collect($ownNameSearch->json('data'))->pluck('id')->all(),
            'Searching the current user must not match every conversation through their own membership.',
        );

        $this->actingAs($finance)->postJson(route('collaboration.chat.conversations.messages.store', $conversation), [
            'body' => 'Please review this update, Priya.',
            'metadata' => ['mentions' => [$sales->id]],
        ])->assertCreated();

        $this->assertTrue(UserNotification::query()
            ->where('recipient_user_id', $sales->id)
            ->where('category', 'chat')
            ->where('title', 'like', 'You were mentioned in%')
            ->exists());

        $this->actingAs($sales)
            ->getJson(route('collaboration.chat.conversations.index', ['view' => 'mentions']))
            ->assertOk()
            ->assertJsonPath('data.0.id', $conversation->id);

        $conversation->forceFill(['last_message_at' => now()->subYear()])->save();
        foreach (range(1, 51) as $index) {
            $other = ChatConversation::create([
                'company_id' => $sales->company_id,
                'owner_user_id' => $sales->id,
                'conversation_key' => 'CHAT-DEEP-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                'type' => 'group_chat',
                'title' => 'Newer conversation '.$index,
                'visibility' => 'private',
                'status' => 'active',
                'last_message_at' => now()->subSeconds($index),
            ]);
            $other->members()->create([
                'user_id' => $sales->id,
                'member_role' => 'owner',
                'can_post' => true,
                'can_upload' => true,
                'can_manage_members' => true,
            ]);
        }

        $this->actingAs($sales)
            ->get(route('collaboration.chat.index', ['conversation_id' => $conversation->id]))
            ->assertOk()
            ->assertSee('Please review this update, Priya.');

        $this->actingAs($sales)
            ->get(route('collaboration.chat.index', ['list_only' => 1]))
            ->assertOk()
            ->assertSee('b360-chat-screen no-conversation', false);
    }

    public function test_muted_members_are_not_notified_and_blocked_attachments_cannot_be_downloaded(): void
    {
        $this->seed();
        Storage::fake('local');

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();

        $response = $this->actingAs($sales)->postJson(route('collaboration.chat.conversations.store'), [
            'type' => 'direct_message',
            'member_user_ids' => [$finance->id],
            'body' => 'Initial message.',
        ])->assertCreated();

        $conversation = ChatConversation::findOrFail($response->json('data.id'));
        $conversation->members()->where('user_id', $sales->id)->update(['muted' => true]);
        UserNotification::query()->where('recipient_user_id', $sales->id)->delete();

        $fileResponse = $this->actingAs($finance)->post(route('collaboration.chat.conversations.messages.store', $conversation), [
            'body' => 'Muted update with file.',
            'attachments' => [UploadedFile::fake()->create('private-proof.png', 10, 'image/png')],
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->assertDatabaseMissing('user_notifications', [
            'recipient_user_id' => $sales->id,
            'notifiable_type' => ChatMessage::class,
            'notifiable_id' => $fileResponse->json('data.id'),
        ]);

        $attachment = ChatMessageAttachment::findOrFail($fileResponse->json('data.attachments.0.id'));
        $attachment->forceFill(['scan_status' => 'blocked'])->save();

        $this->actingAs($sales)
            ->get(route('collaboration.chat.attachments.download', $attachment))
            ->assertStatus(423);
    }

    public function test_mentions_must_reference_active_conversation_members(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();

        $response = $this->actingAs($sales)->postJson(route('collaboration.chat.conversations.store'), [
            'type' => 'direct_message',
            'member_user_ids' => [$finance->id],
            'body' => 'Mention validation conversation.',
        ])->assertCreated();

        $conversation = ChatConversation::findOrFail($response->json('data.id'));

        $this->actingAs($sales)->postJson(route('collaboration.chat.conversations.messages.store', $conversation), [
            'body' => 'This invalid mention must be rejected.',
            'metadata' => ['mentions' => [$hr->id]],
        ])->assertUnprocessable()->assertJsonValidationErrors('metadata.mentions');
    }

    public function test_groups_and_channels_are_created_filtered_and_authorized_end_to_end(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $employee = User::where('email', 'amit.verma@builder360.test')->firstOrFail();
        $project = Project::query()->where('company_id', $sales->company_id)->firstOrFail();

        $groupResponse = $this->actingAs($sales)->postJson(route('collaboration.chat.conversations.store'), [
            'type' => 'group_chat',
            'title' => 'Sales and finance coordination',
            'member_user_ids' => [$finance->id, $hr->id],
            'body' => 'Group conversation started.',
        ])->assertCreated();

        $group = ChatConversation::findOrFail($groupResponse->json('data.id'));
        $this->assertSame(3, $group->activeMembers()->count());

        $this->actingAs($finance)->postJson(route('collaboration.chat.conversations.messages.store', $group), [
            'body' => 'Finance can post in the assigned group.',
        ])->assertCreated()->assertJsonPath('data.conversation_id', $group->id);

        $this->actingAs($hr)
            ->get(route('collaboration.chat.index', ['conversation_id' => $group->id]))
            ->assertOk()
            ->assertSee('Finance can post in the assigned group.');

        $channelResponse = $this->actingAs($sales)->postJson(route('collaboration.chat.conversations.store'), [
            'type' => 'project_channel',
            'title' => 'Skyline delivery channel',
            'project_id' => $project->id,
            'member_user_ids' => [$finance->id, $hr->id],
            'body' => 'Project channel started.',
        ])->assertCreated();

        $channel = ChatConversation::findOrFail($channelResponse->json('data.id'));
        $this->assertSame($project->id, $channel->project_id);

        $this->actingAs($sales)
            ->getJson(route('collaboration.chat.conversations.index', ['type' => 'group_chat']))
            ->assertOk()
            ->assertJsonPath('data.0.id', $group->id);

        $this->actingAs($sales)
            ->getJson(route('collaboration.chat.conversations.index', ['view' => 'channels']))
            ->assertOk()
            ->assertJsonPath('data.0.id', $channel->id);

        $this->actingAs($sales)
            ->get(route('collaboration.chat.index'))
            ->assertOk()
            ->assertSee('Groups')
            ->assertSee('Channels');

        $this->actingAs($sales)->postJson(route('collaboration.chat.conversations.store'), [
            'type' => 'project_channel',
            'title' => 'Missing project channel',
            'member_user_ids' => [$finance->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('project_id');

        $this->actingAs($employee)->postJson(route('collaboration.chat.conversations.store'), [
            'type' => 'project_channel',
            'title' => 'Unauthorized employee channel',
            'project_id' => $project->id,
            'member_user_ids' => [$finance->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('type');
    }
}
