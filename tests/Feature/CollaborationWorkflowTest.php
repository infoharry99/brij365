<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\CalendarEvent;
use App\Models\CollaborationMessage;
use App\Models\Company;
use App\Models\Project;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\WorkTask;
use App\Models\WorkTaskComment;
use App\Models\WorkTaskSubtask;
use App\Models\WorkTaskTimeLog;
use App\Models\WorkTaskTransferRequest;
use App\Services\Builder360\Builder360Bootstrap;
use App\Services\Collaboration\CollaborationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CollaborationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_internal_users_can_list_seeded_tasks_and_calendar_events(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();

        $this->actingAs($sales)
            ->getJson(route('collaboration.tasks.index'))
            ->assertOk()
            ->assertJsonPath('data.0.task_number', 'TSK-10001')
            ->assertJsonPath('data.0.assigned_to.email', 'rajesh.kulkarni@builder360.test');

        $this->actingAs($sales)
            ->getJson(route('collaboration.calendar-events.index', [
                'event_type' => 'meeting',
            ]))
            ->assertOk()
            ->assertJsonPath('data.0.event_number', 'CAL-10001')
            ->assertJsonPath('data.0.organizer.email', 'priya.nair@builder360.test');
    }

    public function test_internal_user_can_use_native_blade_collaboration_task_workspace(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();

        $this->actingAs($sales)
            ->get(route('collaboration.tasks.index'))
            ->assertOk()
            ->assertSee('Task Dashboard')
            ->assertSee('Create task')
            ->assertSee('Hide workspace')
            ->assertSee('Full Screen')
            ->assertSee('people-search-picker', false)
            ->assertSee('tm-people-select', false)
            ->assertSee('Repeat & reminders', false)
            ->assertSee('tm-advanced', false)
            ->assertSee('name="assigned_to_user_id"', false)
            ->assertSee('TSK-10001')
            ->assertDontSee('window.Builder360Server', false)
            ->assertDontSee('id="root"', false);

        $this->actingAs($sales)
            ->get(route('collaboration.tasks.index', ['scope' => 'mine', 'view' => 'board']))
            ->assertOk()
            ->assertSee('Board')
            ->assertSee('List')
            ->assertSee('Calendar')
            ->assertSee('tm-kanban', false);

        $this->actingAs($sales)
            ->post(route('collaboration.tasks.store'), [
                'title' => 'Blade follow-up task',
                'description' => 'Created through the native Blade collaboration task workspace.',
                'assigned_to_user_id' => $construction->id,
                'project_id' => $project->id,
                'priority' => 'high',
                'due_at' => now()->addDays(3)->format('Y-m-d H:i:s'),
                'module_context' => 'crm',
            ])
            ->assertRedirect(route('collaboration.tasks.index'))
            ->assertSessionHas('status');

        $task = WorkTask::where('title', 'Blade follow-up task')->firstOrFail();

        $this->assertSame('open', $task->status);
        $this->assertSame($construction->id, $task->assigned_to_user_id);

        $this->actingAs($construction)
            ->patch(route('collaboration.tasks.status.update', $task), [
                'status' => 'in_progress',
                'note' => 'Updated through the native Blade workspace.',
            ])
            ->assertRedirect(route('collaboration.tasks.index', ['scope' => 'mine', 'view' => 'board']))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('work_tasks', [
            'id' => $task->id,
            'status' => 'in_progress',
        ]);
    }

    public function test_task_activity_center_and_filters_render_normalized_activity_without_view_time_parsing(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();

        foreach (['all', 'comments', 'status', 'transfers', 'approvals', 'attachments', 'time'] as $filter) {
            $this->actingAs($sales)
                ->get(route('collaboration.tasks.index', [
                    'scope' => 'activity',
                    'activity_filter' => $filter,
                ]))
                ->assertOk()
                ->assertSee('Activity Center')
                ->assertDontSee('IlluminateSupportCarbon', false);
        }
    }

    public function test_internal_user_can_use_native_blade_calendar_workspace(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();
        $startsAt = now()->addDays(11)->setTime(10, 0);

        $this->actingAs($sales)
            ->get(route('collaboration.calendar-events.index'))
            ->assertOk()
            ->assertSee('Calendar Management')
            ->assertSee('New event')
            ->assertSee('Show options')
            ->assertSee('Full Screen')
            ->assertSee('Month')
            ->assertSee('Week')
            ->assertSee('Employee')
            ->assertSee('Team')
            ->assertSee('people-search-picker', false)
            ->assertSee('name="event_type"', false)
            ->assertDontSee('window.Builder360Server', false)
            ->assertDontSee('id="root"', false);

        $this->actingAs($sales)
            ->get(route('collaboration.calendar-events.index', ['view' => 'list']))
            ->assertOk()
            ->assertSee('Skyline sales and construction coordination')
            ->assertSee('cal-list', false);

        $this->actingAs($sales)
            ->post(route('collaboration.calendar-events.store'), [
                'title' => 'Blade sales coordination meeting',
                'description' => 'Created through the native Blade calendar workspace.',
                'event_type' => 'meeting',
                'starts_at' => $startsAt->format('Y-m-d H:i:s'),
                'ends_at' => $startsAt->copy()->addHour()->format('Y-m-d H:i:s'),
                'project_id' => $project->id,
                'location' => 'Sales office',
                'visibility' => 'internal',
            ])
            ->assertRedirect(route('collaboration.calendar-events.index'))
            ->assertSessionHas('status');

        $event = CalendarEvent::where('title', 'Blade sales coordination meeting')->firstOrFail();

        $this->assertSame('scheduled', $event->status);

        $this->actingAs($sales)
            ->patch(route('collaboration.calendar-events.complete', $event), [
                'note' => 'Meeting completed from Blade.',
            ])
            ->assertRedirect(route('collaboration.calendar-events.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('calendar_events', [
            'id' => $event->id,
            'status' => 'completed',
        ]);
    }

    public function test_legacy_internal_message_routes_remain_compatible_but_mailbox_ui_is_external_only(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();

        $this->actingAs($sales)
            ->get(route('collaboration.messages.index', ['folder' => 'all']))
            ->assertOk()
            ->assertSee('Mailbox')
            ->assertSee('All accounts')
            ->assertDontSee('Send message')
            ->assertDontSee('name="recipient_user_ids[]"', false)
            ->assertDontSee('window.Builder360Server', false)
            ->assertDontSee('id="root"', false);

        $this->actingAs($sales)
            ->post(route('collaboration.messages.store'), [
                'recipient_user_ids' => [$construction->id],
                'project_id' => $project->id,
                'subject' => 'Blade mailbox test message',
                'body' => 'Created through the native Blade mailbox workspace.',
                'priority' => 'high',
            ])
            ->assertRedirect(route('collaboration.messages.index'))
            ->assertSessionHas('status');

        $message = CollaborationMessage::where('subject', 'Blade mailbox test message')->firstOrFail();

        $this->assertSame($sales->id, $message->sender_user_id);
        $this->assertSame($construction->id, $message->recipient_user_id);

        $this->actingAs($construction)
            ->patch(route('collaboration.messages.read', $message))
            ->assertRedirect(route('collaboration.messages.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('collaboration_messages', [
            'id' => $message->id,
            'status' => 'read',
        ]);
    }

    public function test_task_creation_assignment_status_update_notification_and_audit(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();

        $taskNumber = $this->actingAs($sales)
            ->postJson(route('collaboration.tasks.store'), [
                'title' => 'Prepare buyer walk-through list',
                'description' => 'Prepare open points before the buyer weekend walk-through.',
                'assigned_to_user_id' => $construction->id,
                'project_id' => $project->id,
                'priority' => 'critical',
                'due_at' => now()->addDay()->setTime(15, 0)->toISOString(),
                'module_context' => 'possession',
                'checklist' => [
                    ['label' => 'Review customer open points', 'done' => false],
                    ['label' => 'Confirm site access', 'done' => false],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.priority', 'critical')
            ->json('data.task_number');

        $task = WorkTask::where('task_number', $taskNumber)->firstOrFail();

        $this->assertDatabaseHas('user_notifications', [
            'recipient_user_id' => $construction->id,
            'triggered_by_user_id' => $sales->id,
            'category' => 'collaboration',
            'severity' => 'critical',
            'notifiable_type' => WorkTask::class,
            'notifiable_id' => $task->id,
        ]);

        $this->actingAs($construction)
            ->patchJson(route('collaboration.tasks.status.update', $task), [
                'status' => 'in_progress',
                'note' => 'Started preparation with site team.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress');

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'collaboration.task.status_updated',
            'auditable_type' => WorkTask::class,
            'auditable_id' => $task->id,
        ]);
    }

    public function test_mailbox_quick_action_can_create_related_collaboration_task(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $message = CollaborationMessage::where('message_number', 'MSG-10001')->firstOrFail();

        $taskNumber = $this->actingAs($sales)
            ->postJson(route('collaboration.tasks.store'), [
                'title' => 'Follow up: '.$message->subject,
                'description' => 'Created from mailbox quick action for message '.$message->message_number,
                'assigned_to_user_id' => $finance->id,
                'project_id' => $message->project_id,
                'priority' => 'high',
                'module_context' => 'mailbox',
                'related_type' => CollaborationMessage::class,
                'related_id' => $message->id,
                'checklist' => [
                    ['label' => 'Review mailbox conversation', 'done' => false],
                    ['label' => 'Update linked CRM/customer record if required', 'done' => false],
                ],
                'metadata' => [
                    'source' => 'mailbox_quick_action',
                    'mailbox_message_number' => $message->message_number,
                    'mailbox_message_id' => $message->id,
                    'mailbox_subject' => $message->subject,
                    'mailbox_direction' => 'in',
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.module_context', 'mailbox')
            ->assertJsonPath('data.related_type', CollaborationMessage::class)
            ->assertJsonPath('data.related_id', $message->id)
            ->assertJsonPath('data.metadata.source', 'mailbox_quick_action')
            ->json('data.task_number');

        $task = WorkTask::where('task_number', $taskNumber)->firstOrFail();

        $this->assertSame($message->id, $task->related_id);
        $this->assertSame(CollaborationMessage::class, $task->related_type);
        $this->assertSame('mailbox_quick_action', $task->metadata['source']);
        $this->assertSame($message->message_number, $task->metadata['mailbox_message_number']);
        $this->assertCount(2, $task->checklist);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'collaboration.task.created',
            'auditable_type' => WorkTask::class,
            'auditable_id' => $task->id,
        ]);

        $this->assertDatabaseHas('user_notifications', [
            'recipient_user_id' => $finance->id,
            'triggered_by_user_id' => $sales->id,
            'category' => 'collaboration',
            'notifiable_type' => WorkTask::class,
            'notifiable_id' => $task->id,
        ]);
    }

    public function test_chat_quick_action_can_create_related_collaboration_task(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $message = CollaborationMessage::where('message_number', 'MSG-10001')->firstOrFail();

        $taskNumber = $this->actingAs($sales)
            ->postJson(route('collaboration.tasks.store'), [
                'title' => 'Task: Skyline payment follow-up coordination',
                'description' => "Chat source: Skyline payment follow-up coordination\n\nThread key: ".$message->thread_key,
                'assigned_to_user_id' => $construction->id,
                'priority' => 'medium',
                'module_context' => 'chat',
                'related_type' => CollaborationMessage::class,
                'related_id' => $message->id,
                'checklist' => [
                    ['label' => 'Review chat context', 'done' => false],
                    ['label' => 'Update responsible stakeholder after action', 'done' => false],
                ],
                'metadata' => [
                    'source' => 'chat_connect_task',
                    'conversation_id' => 'server_thread_'.$message->thread_key,
                    'conversation_name' => 'Skyline payment follow-up coordination',
                    'thread_key' => $message->thread_key,
                    'latest_message_id' => $message->id,
                    'latest_message_number' => $message->message_number,
                    'draft_snapshot' => 'Please assign this follow-up.',
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.module_context', 'chat')
            ->assertJsonPath('data.related_type', CollaborationMessage::class)
            ->assertJsonPath('data.related_id', $message->id)
            ->assertJsonPath('data.metadata.source', 'chat_connect_task')
            ->json('data.task_number');

        $task = WorkTask::where('task_number', $taskNumber)->firstOrFail();

        $this->assertSame('chat', $task->module_context);
        $this->assertSame(CollaborationMessage::class, $task->related_type);
        $this->assertSame($message->id, $task->related_id);
        $this->assertSame('chat_connect_task', $task->metadata['source']);
        $this->assertSame($message->thread_key, $task->metadata['thread_key']);
        $this->assertCount(2, $task->checklist);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'collaboration.task.created',
            'auditable_type' => WorkTask::class,
            'auditable_id' => $task->id,
        ]);

        $this->assertDatabaseHas('user_notifications', [
            'recipient_user_id' => $construction->id,
            'triggered_by_user_id' => $sales->id,
            'category' => 'collaboration',
            'notifiable_type' => WorkTask::class,
            'notifiable_id' => $task->id,
        ]);
    }

    public function test_task_assignment_endpoint_updates_assignee_filters_notifications_and_audit(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();

        $taskNumber = $this->actingAs($sales)
            ->postJson(route('collaboration.tasks.store'), [
                'title' => 'Verify overdue collection escalation list',
                'description' => 'Prepare customer-wise collection escalation action list.',
                'project_id' => $project->id,
                'priority' => 'high',
                'due_at' => now()->addDays(2)->setTime(17, 0)->toISOString(),
                'module_context' => 'collections',
            ])
            ->assertCreated()
            ->assertJsonPath('data.assigned_to.email', 'priya.nair@builder360.test')
            ->json('data.task_number');

        $task = WorkTask::where('task_number', $taskNumber)->firstOrFail();
        $task->forceFill(['assigned_to_user_id' => null])->save();
        $task->refresh();

        $this->actingAs($sales)
            ->patchJson(route('collaboration.tasks.assign', $task), [
                'assigned_to_user_id' => $finance->id,
                'note' => 'Finance must own collection escalation verification.',
            ])
            ->assertOk()
            ->assertJsonPath('data.task_number', $taskNumber)
            ->assertJsonPath('data.assigned_to.email', 'suresh.iyer@builder360.test');

        $this->assertDatabaseHas('work_tasks', [
            'id' => $task->id,
            'assigned_to_user_id' => $finance->id,
        ]);

        $this->assertDatabaseHas('user_notifications', [
            'recipient_user_id' => $finance->id,
            'triggered_by_user_id' => $sales->id,
            'category' => 'collaboration',
            'severity' => 'info',
            'notifiable_type' => WorkTask::class,
            'notifiable_id' => $task->id,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'collaboration.task.assigned',
            'auditable_type' => WorkTask::class,
            'auditable_id' => $task->id,
        ]);

        $this->actingAs($sales)
            ->getJson(route('collaboration.tasks.index', [
                'assigned_to_user_id' => $finance->id,
                'q' => 'collection escalation',
                'module_context' => 'collections',
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.task_number', $taskNumber)
            ->assertJsonPath('data.0.assigned_to.email', 'suresh.iyer@builder360.test');
    }

    public function test_dashboard_bootstrap_exposes_collaboration_task_api_contract_for_authorized_users(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $bootstrap = app(Builder360Bootstrap::class)->forUser($sales);

        $this->assertIsArray($bootstrap['collaboration_task_options']);
        $this->assertSame('/collaboration/tasks', $bootstrap['collaboration_task_options']['index_url']);
        $this->assertSame('/collaboration/tasks/export', $bootstrap['collaboration_task_options']['export_url']);
        $this->assertSame('/collaboration/tasks', $bootstrap['collaboration_task_options']['store_url']);
        $this->assertSame('/collaboration/tasks/bulk/update', $bootstrap['collaboration_task_options']['bulk_update_url']);
        $this->assertSame('/collaboration/tasks/bulk/archive', $bootstrap['collaboration_task_options']['bulk_archive_url']);
        $this->assertSame('/collaboration/tasks/__TASK__', $bootstrap['collaboration_task_options']['update_url_template']);
        $this->assertSame('/collaboration/tasks/__TASK__/assign', $bootstrap['collaboration_task_options']['assign_url_template']);
        $this->assertSame('/collaboration/tasks/__TASK__/transfer-requests', $bootstrap['collaboration_task_options']['transfer_request_url_template']);
        $this->assertSame('/collaboration/tasks/transfer-requests/__TRANSFER__/resolve', $bootstrap['collaboration_task_options']['transfer_resolve_url_template']);
        $this->assertSame('/collaboration/tasks/__TASK__/status', $bootstrap['collaboration_task_options']['status_url_template']);
        $this->assertSame('/collaboration/tasks/__TASK__/watcher', $bootstrap['collaboration_task_options']['watcher_url_template']);
        $this->assertSame('/collaboration/tasks/__TASK__/dependencies', $bootstrap['collaboration_task_options']['dependencies_url_template']);
        $this->assertSame('/collaboration/tasks/__TASK__/comments', $bootstrap['collaboration_task_options']['comment_url_template']);
        $this->assertSame('/collaboration/tasks/__TASK__/checklist', $bootstrap['collaboration_task_options']['checklist_url_template']);
        $this->assertSame('/collaboration/tasks/__TASK__/subtasks', $bootstrap['collaboration_task_options']['subtask_url_template']);
        $this->assertSame('/collaboration/tasks/__TASK__/subtasks/__SUBTASK__', $bootstrap['collaboration_task_options']['subtask_status_url_template']);
        $this->assertSame('/collaboration/tasks/__TASK__/time-logs', $bootstrap['collaboration_task_options']['time_log_url_template']);
        $this->assertSame('/collaboration/tasks/__TASK__/archive', $bootstrap['collaboration_task_options']['archive_url_template']);
        $this->assertTrue($bootstrap['collaboration_task_options']['can_create']);
        $this->assertTrue($bootstrap['collaboration_task_options']['can_manage']);
        $this->assertFalse($bootstrap['collaboration_task_options']['can_manage_settings']);
        $this->assertSame('/settings/system-settings', $bootstrap['collaboration_task_options']['system_settings_store_url']);
        $this->assertSame('collaboration.task_settings', $bootstrap['collaboration_task_options']['task_settings_key']);
        $this->assertSame('collaboration.task_settings', $bootstrap['collaboration_task_options']['task_settings']['setting_key']);
        $this->assertTrue($bootstrap['collaboration_task_options']['task_settings']['value']['auto_progress']);
        $this->assertArrayHasKey('notifications', $bootstrap['collaboration_task_options']['task_settings']['value']);
        $this->assertNotEmpty($bootstrap['collaboration_task_options']['assignees']);
        $this->assertNotEmpty($bootstrap['collaboration_task_options']['projects']);
        $this->assertContains('in_progress', $bootstrap['collaboration_task_options']['statuses']);
        $this->assertContains('critical', $bootstrap['collaboration_task_options']['priorities']);

        $this->assertIsArray($bootstrap['collaboration_calendar_options']);
        $this->assertSame('laravel-sqlite', $bootstrap['collaboration_calendar_options']['source']);
        $this->assertSame('/collaboration/calendar-events', $bootstrap['collaboration_calendar_options']['index_url']);
        $this->assertSame('/collaboration/calendar-events', $bootstrap['collaboration_calendar_options']['store_url']);
        $this->assertSame('/collaboration/calendar-events/__EVENT__', $bootstrap['collaboration_calendar_options']['update_url_template']);
        $this->assertSame('/collaboration/calendar-events/__EVENT__/complete', $bootstrap['collaboration_calendar_options']['complete_url_template']);
        $this->assertSame('/collaboration/calendar-events/__EVENT__/cancel', $bootstrap['collaboration_calendar_options']['cancel_url_template']);
        $this->assertSame('/collaboration/calendar-events/__EVENT__', $bootstrap['collaboration_calendar_options']['delete_url_template']);
        $this->assertTrue($bootstrap['collaboration_calendar_options']['can_create']);
        $this->assertTrue($bootstrap['collaboration_calendar_options']['can_manage']);
        $this->assertTrue($bootstrap['collaboration_calendar_options']['can_delete']);
        $this->assertNotEmpty($bootstrap['collaboration_calendar_options']['assignees']);
        $this->assertNotEmpty($bootstrap['collaboration_calendar_options']['projects']);
        $this->assertContains('site_visit', $bootstrap['collaboration_calendar_options']['event_types']);
        $this->assertContains('cancelled', $bootstrap['collaboration_calendar_options']['statuses']);
    }

    public function test_task_transfer_approval_request_and_manager_resolution_workflow(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();

        $taskNumber = $this->actingAs($sales)
            ->postJson(route('collaboration.tasks.store'), [
                'title' => 'Transfer approval workflow task',
                'assigned_to_user_id' => $construction->id,
                'priority' => 'high',
                'module_context' => 'collections',
            ])
            ->assertCreated()
            ->json('data.task_number');

        $task = WorkTask::where('task_number', $taskNumber)->firstOrFail();

        $transferId = $this->actingAs($construction)
            ->postJson(route('collaboration.tasks.transfer-requests.store', $task), [
                'assigned_to_user_id' => $finance->id,
                'reason' => 'Finance must own the collection verification.',
                'metadata' => ['source' => 'feature_test'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.task.task_number', $taskNumber)
            ->assertJsonPath('data.requested_by.email', 'rajesh.kulkarni@builder360.test')
            ->assertJsonPath('data.to_user.email', 'suresh.iyer@builder360.test')
            ->json('data.id');

        $transfer = WorkTaskTransferRequest::findOrFail($transferId);

        $this->assertDatabaseHas('work_task_transfer_requests', [
            'id' => $transfer->id,
            'work_task_id' => $task->id,
            'requested_by_user_id' => $construction->id,
            'from_user_id' => $construction->id,
            'to_user_id' => $finance->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('work_tasks', [
            'id' => $task->id,
            'assigned_to_user_id' => $construction->id,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'collaboration.task.transfer_requested',
            'auditable_type' => WorkTask::class,
            'auditable_id' => $task->id,
            'user_id' => $construction->id,
        ]);

        $this->assertDatabaseHas('user_notifications', [
            'recipient_user_id' => $sales->id,
            'triggered_by_user_id' => $construction->id,
            'category' => 'collaboration',
            'notifiable_type' => WorkTask::class,
            'notifiable_id' => $task->id,
        ]);

        $this->actingAs($construction)
            ->patchJson(route('collaboration.tasks.transfer-requests.resolve', $transfer), [
                'action' => 'approved',
                'note' => 'Self approval should be blocked.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('transfer_request');

        $this->actingAs($sales)
            ->patchJson(route('collaboration.tasks.transfer-requests.resolve', $transfer), [
                'action' => 'approved',
                'note' => 'Approved after collection ownership review.',
            ])
            ->assertOk()
            ->assertJsonPath('data.task_number', $taskNumber)
            ->assertJsonPath('data.assigned_to.email', 'suresh.iyer@builder360.test')
            ->assertJsonPath('data.transfer_requests.0.status', 'approved');

        $this->assertDatabaseHas('work_tasks', [
            'id' => $task->id,
            'assigned_to_user_id' => $finance->id,
        ]);

        $this->assertDatabaseHas('work_task_transfer_requests', [
            'id' => $transfer->id,
            'status' => 'approved',
            'approved_by_user_id' => $sales->id,
            'approval_note' => 'Approved after collection ownership review.',
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'collaboration.task.transfer_approved',
            'auditable_type' => WorkTask::class,
            'auditable_id' => $task->id,
            'user_id' => $sales->id,
        ]);
    }

    public function test_task_transfer_approval_validates_scope_duplicate_pending_and_partner_access(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();
        $otherCompany = Company::where('code', 'B360P')->firstOrFail();
        $externalUser = User::factory()->create([
            'company_id' => $otherCompany->id,
            'name' => 'External Transfer User',
            'email' => 'external.transfer@example.test',
            'status' => 'active',
        ]);

        $taskNumber = $this->actingAs($sales)
            ->postJson(route('collaboration.tasks.store'), [
                'title' => 'Transfer validation workflow task',
                'assigned_to_user_id' => $construction->id,
                'priority' => 'medium',
            ])
            ->assertCreated()
            ->json('data.task_number');

        $task = WorkTask::where('task_number', $taskNumber)->firstOrFail();

        $this->actingAs($partner)
            ->postJson(route('collaboration.tasks.transfer-requests.store', $task), [
                'assigned_to_user_id' => $finance->id,
                'reason' => 'Unauthorized partner transfer request.',
            ])
            ->assertForbidden();

        $this->actingAs($construction)
            ->postJson(route('collaboration.tasks.transfer-requests.store', $task), [
                'assigned_to_user_id' => $externalUser->id,
                'reason' => 'Cross-company transfer must fail.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('assigned_to_user_id');

        $transferId = $this->actingAs($construction)
            ->postJson(route('collaboration.tasks.transfer-requests.store', $task), [
                'assigned_to_user_id' => $finance->id,
                'reason' => 'Valid pending transfer.',
            ])
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($construction)
            ->postJson(route('collaboration.tasks.transfer-requests.store', $task), [
                'assigned_to_user_id' => $sales->id,
                'reason' => 'Duplicate pending request should fail.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('task');

        $transfer = WorkTaskTransferRequest::findOrFail($transferId);

        $this->actingAs($partner)
            ->patchJson(route('collaboration.tasks.transfer-requests.resolve', $transfer), [
                'action' => 'rejected',
                'note' => 'Unauthorized partner resolution.',
            ])
            ->assertForbidden();

        $this->actingAs($sales)
            ->patchJson(route('collaboration.tasks.transfer-requests.resolve', $transfer), [
                'action' => 'rejected',
                'note' => 'Rejected by manager.',
            ])
            ->assertOk()
            ->assertJsonPath('data.assigned_to.email', 'rajesh.kulkarni@builder360.test')
            ->assertJsonPath('data.transfer_requests.0.status', 'rejected');
    }

    public function test_task_transfer_reassigns_immediately_when_active_workflow_disables_approval(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();

        $setting = SystemSetting::query()
            ->where('company_id', $sales->company_id)
            ->where('setting_key', 'collaboration.task_settings')
            ->where('status', 'active')
            ->firstOrFail();
        $value = $setting->value;
        $value['transfer_requires_approval'] = false;
        $setting->forceFill(['value' => $value])->save();

        $taskNumber = $this->actingAs($sales)
            ->postJson(route('collaboration.tasks.store'), [
                'title' => 'Immediate transfer workflow task',
                'assigned_to_user_id' => $construction->id,
                'priority' => 'medium',
            ])
            ->assertCreated()
            ->json('data.task_number');

        $task = WorkTask::where('task_number', $taskNumber)->firstOrFail();

        $this->actingAs($construction)
            ->postJson(route('collaboration.tasks.transfer-requests.store', $task), [
                'assigned_to_user_id' => $finance->id,
                'reason' => 'The active workflow permits immediate ownership transfer.',
                'lock_version' => $task->lock_version,
            ])
            ->assertCreated()
            ->assertJsonPath('result', 'transferred')
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.to_user.email', 'suresh.iyer@builder360.test');

        $this->assertDatabaseHas('work_tasks', [
            'id' => $task->id,
            'assigned_to_user_id' => $finance->id,
        ]);
        $this->assertDatabaseMissing('work_task_transfer_requests', [
            'work_task_id' => $task->id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'collaboration.task.transferred',
            'auditable_type' => WorkTask::class,
            'auditable_id' => $task->id,
        ]);
    }

    public function test_task_detail_update_persists_title_priority_due_date_and_audit(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();

        $taskNumber = $this->actingAs($sales)
            ->postJson(route('collaboration.tasks.store'), [
                'title' => 'Initial customer handover checklist',
                'assigned_to_user_id' => $construction->id,
                'priority' => 'medium',
                'module_context' => 'possession',
            ])
            ->assertCreated()
            ->json('data.task_number');

        $task = WorkTask::where('task_number', $taskNumber)->firstOrFail();
        $dueAt = now()->addDays(3)->setTime(16, 30)->toISOString();

        $this->actingAs($construction)
            ->patchJson(route('collaboration.tasks.update', $task), [
                'title' => 'Updated customer handover checklist',
                'priority' => 'critical',
                'due_at' => $dueAt,
                'note' => 'Updated title, urgency and due date from task drawer.',
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated customer handover checklist')
            ->assertJsonPath('data.priority', 'critical')
            ->assertJsonPath('data.assigned_to.email', 'rajesh.kulkarni@builder360.test');

        $this->assertDatabaseHas('work_tasks', [
            'id' => $task->id,
            'title' => 'Updated customer handover checklist',
            'priority' => 'critical',
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'collaboration.task.details_updated',
            'auditable_type' => WorkTask::class,
            'auditable_id' => $task->id,
        ]);

        $event = AuditEvent::where('event_type', 'collaboration.task.details_updated')->latest('id')->firstOrFail();

        $this->assertSame($construction->id, $event->user_id);
        $this->assertSame($taskNumber, $event->metadata['task_number']);
        $this->assertContains('title', $event->metadata['changed_fields']);
        $this->assertContains('priority', $event->metadata['changed_fields']);
        $this->assertSame('medium', $event->metadata['before']['priority']);
        $this->assertSame('critical', $event->metadata['after']['priority']);
    }

    public function test_task_watcher_preference_persists_in_metadata_with_audit(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();

        $taskNumber = $this->actingAs($sales)
            ->postJson(route('collaboration.tasks.store'), [
                'title' => 'Watcher persistence target',
                'assigned_to_user_id' => $construction->id,
                'priority' => 'medium',
                'module_context' => 'collaboration',
            ])
            ->assertCreated()
            ->json('data.task_number');

        $task = WorkTask::where('task_number', $taskNumber)->firstOrFail();

        $this->actingAs($construction)
            ->patchJson(route('collaboration.tasks.watcher.update', $task), [
                'action' => 'watch',
                'note' => 'Watching this task from the drawer.',
            ])
            ->assertOk()
            ->assertJsonPath('data.metadata.watcher_user_ids.0', $construction->id)
            ->assertJsonPath('data.metadata.watcher_count', 1);

        $task->refresh();

        $this->assertSame([$construction->id], $task->metadata['watcher_user_ids']);
        $this->assertSame(1, $task->metadata['watcher_count']);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'collaboration.task.watch_started',
            'auditable_type' => WorkTask::class,
            'auditable_id' => $task->id,
        ]);

        $this->actingAs($construction)
            ->patchJson(route('collaboration.tasks.watcher.update', $task), [
                'action' => 'toggle',
                'note' => 'Stopped watching from the drawer.',
            ])
            ->assertOk()
            ->assertJsonPath('data.metadata.watcher_user_ids', [])
            ->assertJsonPath('data.metadata.watcher_count', 0);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'collaboration.task.watch_stopped',
            'auditable_type' => WorkTask::class,
            'auditable_id' => $task->id,
        ]);

        $this->actingAs($partner)
            ->patchJson(route('collaboration.tasks.watcher.update', $task), [
                'action' => 'watch',
            ])
            ->assertForbidden();
    }

    public function test_task_dependencies_persist_in_metadata_with_audit_and_cycle_validation(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();

        $dependencyNumber = $this->actingAs($sales)
            ->postJson(route('collaboration.tasks.store'), [
                'title' => 'Dependency prerequisite task',
                'assigned_to_user_id' => $construction->id,
                'priority' => 'medium',
                'module_context' => 'construction',
            ])
            ->assertCreated()
            ->json('data.task_number');

        $taskNumber = $this->actingAs($sales)
            ->postJson(route('collaboration.tasks.store'), [
                'title' => 'Dependent execution task',
                'assigned_to_user_id' => $construction->id,
                'priority' => 'high',
                'module_context' => 'construction',
            ])
            ->assertCreated()
            ->json('data.task_number');

        $dependency = WorkTask::where('task_number', $dependencyNumber)->firstOrFail();
        $task = WorkTask::where('task_number', $taskNumber)->firstOrFail();

        $this->actingAs($construction)
            ->patchJson(route('collaboration.tasks.dependencies.update', $task), [
                'dependency_task_ids' => [$dependency->id],
                'note' => 'Dependency added from the task drawer.',
            ])
            ->assertOk()
            ->assertJsonPath('data.metadata.dependency_task_ids.0', $dependency->id)
            ->assertJsonPath('data.metadata.task_dependencies.0.task_number', $dependencyNumber)
            ->assertJsonPath('data.metadata.dependency_count', 1);

        $task->refresh();

        $this->assertSame([$dependency->id], $task->metadata['dependency_task_ids']);
        $this->assertSame($dependencyNumber, $task->metadata['task_dependencies'][0]['task_number']);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'collaboration.task.dependencies_updated',
            'auditable_type' => WorkTask::class,
            'auditable_id' => $task->id,
        ]);

        $this->actingAs($construction)
            ->patchJson(route('collaboration.tasks.dependencies.update', $dependency), [
                'dependency_task_ids' => [$task->id],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('dependency_task_ids');

        $this->actingAs($construction)
            ->patchJson(route('collaboration.tasks.dependencies.update', $task), [
                'dependency_task_ids' => [$task->id],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('dependency_task_ids');

        $this->actingAs($construction)
            ->patchJson(route('collaboration.tasks.dependencies.update', $task), [
                'dependency_task_ids' => [],
                'note' => 'Dependencies removed from the task drawer.',
            ])
            ->assertOk()
            ->assertJsonPath('data.metadata.dependency_task_ids', [])
            ->assertJsonPath('data.metadata.task_dependencies', [])
            ->assertJsonPath('data.metadata.dependency_count', 0);

        $this->actingAs($partner)
            ->patchJson(route('collaboration.tasks.dependencies.update', $task), [
                'dependency_task_ids' => [$dependency->id],
            ])
            ->assertForbidden();
    }

    public function test_task_detail_update_validates_payload_and_blocks_completed_tasks(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();

        $taskNumber = $this->actingAs($sales)
            ->postJson(route('collaboration.tasks.store'), [
                'title' => 'Task details validation target',
                'priority' => 'medium',
            ])
            ->assertCreated()
            ->json('data.task_number');

        $task = WorkTask::where('task_number', $taskNumber)->firstOrFail();

        $this->actingAs($sales)
            ->patchJson(route('collaboration.tasks.update', $task), [
                'priority' => 'urgent',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('priority');

        $this->actingAs($sales)
            ->patchJson(route('collaboration.tasks.update', $task), [
                'note' => 'No editable fields.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('task');

        $this->actingAs($sales)
            ->patchJson(route('collaboration.tasks.status.update', $task), [
                'status' => 'completed',
                'note' => 'Complete before attempted edit.',
            ])
            ->assertOk();

        $task->refresh();

        $this->actingAs($sales)
            ->patchJson(route('collaboration.tasks.update', $task), [
                'title' => 'Should not update completed task',
            ])
            ->assertForbidden();
    }

    public function test_task_archive_soft_deletes_hides_from_index_and_audits(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();

        $taskNumber = $this->actingAs($sales)
            ->postJson(route('collaboration.tasks.store'), [
                'title' => 'Archive completed walkthrough preparation',
                'assigned_to_user_id' => $construction->id,
                'priority' => 'medium',
                'module_context' => 'possession',
            ])
            ->assertCreated()
            ->json('data.task_number');

        $task = WorkTask::where('task_number', $taskNumber)->firstOrFail();

        $this->actingAs($construction)
            ->patchJson(route('collaboration.tasks.archive', $task), [
                'note' => 'No longer required after revised possession plan.',
            ])
            ->assertOk()
            ->assertJsonPath('data.id', $task->id)
            ->assertJsonPath('data.task_number', $taskNumber)
            ->assertJsonPath('data.archived', true)
            ->assertJsonPath('data.deleted_at', fn (?string $value): bool => $value !== null);

        $this->assertSoftDeleted('work_tasks', ['id' => $task->id]);

        $archivedTask = WorkTask::withTrashed()->findOrFail($task->id);

        $this->assertTrue($archivedTask->trashed());
        $this->assertTrue(collect($archivedTask->workflow_history)->contains(
            fn (array $event): bool => $event['status'] === 'archived'
                && $event['actor_user_id'] === $construction->id
                && $event['note'] === 'No longer required after revised possession plan.',
        ));

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'collaboration.task.archived',
            'auditable_type' => WorkTask::class,
            'auditable_id' => $task->id,
            'user_id' => $construction->id,
        ]);

        $this->actingAs($sales)
            ->getJson(route('collaboration.tasks.index', ['q' => $taskNumber]))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_task_archive_denies_partner_access(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();

        $taskNumber = $this->actingAs($sales)
            ->postJson(route('collaboration.tasks.store'), [
                'title' => 'Partner cannot archive internal task',
                'priority' => 'medium',
            ])
            ->assertCreated()
            ->json('data.task_number');

        $task = WorkTask::where('task_number', $taskNumber)->firstOrFail();

        $this->actingAs($partner)
            ->patchJson(route('collaboration.tasks.archive', $task), [
                'note' => 'Unauthorized archive attempt.',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('work_tasks', [
            'id' => $task->id,
            'deleted_at' => null,
        ]);
    }

    public function test_readonly_auditor_can_view_task_but_cannot_mutate_it(): void
    {
        $this->seed();

        $auditor = User::where('email', 'ishaan.trivedi@builder360.test')->firstOrFail();
        $manager = User::where('email', 'priya.nair@builder360.test')->firstOrFail();

        $task = WorkTask::create([
            'company_id' => $auditor->company_id,
            'created_by_user_id' => $auditor->id,
            'assigned_to_user_id' => $auditor->id,
            'task_number' => 'TSK-AUD-001',
            'title' => 'Readonly auditor task boundary',
            'priority' => 'medium',
            'status' => 'open',
            'workflow_history' => [],
            'metadata' => [],
        ]);

        $this->actingAs($auditor)
            ->getJson(route('collaboration.tasks.index', ['q' => $task->task_number]))
            ->assertOk()
            ->assertJsonPath('data.0.task_number', $task->task_number)
            ->assertJsonPath('data.0.permissions.can_view', true)
            ->assertJsonPath('data.0.permissions.can_update_status', false)
            ->assertJsonPath('data.0.permissions.can_update_details', false)
            ->assertJsonPath('data.0.permissions.can_assign', false)
            ->assertJsonPath('data.0.permissions.can_archive', false)
            ->assertJsonPath('data.0.permissions.can_comment', false)
            ->assertJsonPath('data.0.permissions.can_log_time', false)
            ->assertJsonPath('data.0.permissions.can_request_transfer', false)
            ->assertJsonPath('data.0.permissions.can_manage_checklist', false)
            ->assertJsonPath('data.0.permissions.can_manage_subtasks', false);

        $this->actingAs($auditor)
            ->postJson(route('collaboration.tasks.store'), [
                'title' => 'Auditor cannot create task',
                'priority' => 'medium',
            ])
            ->assertForbidden();

        $this->actingAs($auditor)
            ->patchJson(route('collaboration.tasks.status.update', $task), [
                'status' => 'in_progress',
                'note' => 'Readonly users cannot update status.',
            ])
            ->assertForbidden();

        $this->actingAs($auditor)
            ->patchJson(route('collaboration.tasks.update', $task), [
                'title' => 'Auditor cannot update title',
            ])
            ->assertForbidden();

        $this->actingAs($auditor)
            ->patchJson(route('collaboration.tasks.assign', $task), [
                'assigned_to_user_id' => $manager->id,
            ])
            ->assertForbidden();

        $this->actingAs($auditor)
            ->postJson(route('collaboration.tasks.comments.store', $task), [
                'body' => 'Readonly users cannot comment.',
            ])
            ->assertForbidden();

        $this->actingAs($auditor)
            ->postJson(route('collaboration.tasks.time-logs.store', $task), [
                'minutes' => 30,
            ])
            ->assertForbidden();

        $this->actingAs($auditor)
            ->patchJson(route('collaboration.tasks.archive', $task), [
                'note' => 'Readonly users cannot archive.',
            ])
            ->assertForbidden();

        $this->actingAs($auditor)
            ->postJson(route('collaboration.tasks.transfer-requests.store', $task), [
                'assigned_to_user_id' => $manager->id,
                'reason' => 'Readonly users cannot request transfer.',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('work_tasks', [
            'id' => $task->id,
            'title' => 'Readonly auditor task boundary',
            'assigned_to_user_id' => $auditor->id,
            'status' => 'open',
            'deleted_at' => null,
        ]);
    }

    public function test_task_bulk_archive_soft_deletes_selected_tasks_and_audits_each_record(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();

        $firstTaskNumber = $this->actingAs($sales)
            ->postJson(route('collaboration.tasks.store'), [
                'title' => 'Bulk archive first task',
                'assigned_to_user_id' => $construction->id,
                'priority' => 'medium',
            ])
            ->assertCreated()
            ->json('data.task_number');

        $secondTaskNumber = $this->actingAs($sales)
            ->postJson(route('collaboration.tasks.store'), [
                'title' => 'Bulk archive second task',
                'assigned_to_user_id' => $construction->id,
                'priority' => 'high',
            ])
            ->assertCreated()
            ->json('data.task_number');

        $firstTask = WorkTask::where('task_number', $firstTaskNumber)->firstOrFail();
        $secondTask = WorkTask::where('task_number', $secondTaskNumber)->firstOrFail();

        $this->actingAs($sales)
            ->patchJson(route('collaboration.tasks.bulk-archive'), [
                'task_ids' => [$firstTask->id, $secondTask->id],
                'note' => 'Bulk archived after task list cleanup.',
            ])
            ->assertOk()
            ->assertJsonPath('data.count', 2)
            ->assertJsonPath('data.tasks.0.archived', true)
            ->assertJsonPath('data.tasks.1.archived', true);

        $this->assertSoftDeleted('work_tasks', ['id' => $firstTask->id]);
        $this->assertSoftDeleted('work_tasks', ['id' => $secondTask->id]);

        $this->assertSame(2, AuditEvent::where('event_type', 'collaboration.task.archived')
            ->whereIn('auditable_id', [$firstTask->id, $secondTask->id])
            ->where('user_id', $sales->id)
            ->count());

        $this->actingAs($construction)
            ->getJson(route('collaboration.tasks.index', ['q' => 'Bulk archive']))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_task_bulk_archive_denies_partner_access_and_validates_task_scope(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();
        $otherCompany = Company::where('code', 'B360P')->firstOrFail();
        $externalUser = User::factory()->create([
            'company_id' => $otherCompany->id,
            'name' => 'External Bulk Archive User',
            'email' => 'external.bulk.archive@example.test',
            'status' => 'active',
        ]);

        $taskNumber = $this->actingAs($sales)
            ->postJson(route('collaboration.tasks.store'), [
                'title' => 'Bulk archive scope task',
                'priority' => 'medium',
            ])
            ->assertCreated()
            ->json('data.task_number');

        $task = WorkTask::where('task_number', $taskNumber)->firstOrFail();

        $externalTask = WorkTask::create([
            'company_id' => $otherCompany->id,
            'created_by_user_id' => $externalUser->id,
            'assigned_to_user_id' => $externalUser->id,
            'task_number' => 'TSK-EXT-BULK-ARCHIVE',
            'title' => 'External task must not bulk archive',
            'priority' => 'medium',
            'status' => 'open',
            'workflow_history' => [],
            'metadata' => [],
        ]);

        $this->actingAs($partner)
            ->patchJson(route('collaboration.tasks.bulk-archive'), [
                'task_ids' => [$task->id],
            ])
            ->assertForbidden();

        $this->actingAs($sales)
            ->patchJson(route('collaboration.tasks.bulk-archive'), [
                'task_ids' => [$task->id, $externalTask->id],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('task_ids');

        $this->assertDatabaseHas('work_tasks', [
            'id' => $task->id,
            'deleted_at' => null,
        ]);

        $this->assertDatabaseHas('work_tasks', [
            'id' => $externalTask->id,
            'deleted_at' => null,
        ]);
    }

    public function test_task_bulk_update_completes_and_prioritizes_selected_tasks_with_audit(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();

        $firstTaskNumber = $this->actingAs($sales)
            ->postJson(route('collaboration.tasks.store'), [
                'title' => 'Bulk update first task',
                'assigned_to_user_id' => $construction->id,
                'priority' => 'medium',
            ])
            ->assertCreated()
            ->json('data.task_number');

        $secondTaskNumber = $this->actingAs($sales)
            ->postJson(route('collaboration.tasks.store'), [
                'title' => 'Bulk update second task',
                'assigned_to_user_id' => $construction->id,
                'priority' => 'low',
            ])
            ->assertCreated()
            ->json('data.task_number');

        $firstTask = WorkTask::where('task_number', $firstTaskNumber)->firstOrFail();
        $secondTask = WorkTask::where('task_number', $secondTaskNumber)->firstOrFail();

        $this->actingAs($sales)
            ->patchJson(route('collaboration.tasks.bulk-update'), [
                'task_ids' => [$firstTask->id, $secondTask->id],
                'priority' => 'high',
                'note' => 'Escalated selected work items.',
            ])
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.priority', 'high')
            ->assertJsonPath('data.1.priority', 'high');

        $this->assertDatabaseHas('work_tasks', [
            'id' => $firstTask->id,
            'priority' => 'high',
            'status' => 'open',
        ]);

        $this->assertDatabaseHas('work_tasks', [
            'id' => $secondTask->id,
            'priority' => 'high',
            'status' => 'open',
        ]);

        $this->assertSame(2, AuditEvent::where('event_type', 'collaboration.task.details_updated')
            ->whereIn('auditable_id', [$firstTask->id, $secondTask->id])
            ->where('user_id', $sales->id)
            ->count());

        $this->actingAs($construction)
            ->patchJson(route('collaboration.tasks.bulk-update'), [
                'task_ids' => [$firstTask->id, $secondTask->id],
                'status' => 'completed',
                'note' => 'Completed selected tasks after verification.',
            ])
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.status', 'completed')
            ->assertJsonPath('data.1.status', 'completed');

        $this->assertDatabaseHas('work_tasks', [
            'id' => $firstTask->id,
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('work_tasks', [
            'id' => $secondTask->id,
            'status' => 'completed',
        ]);

        $this->assertNotNull(WorkTask::findOrFail($firstTask->id)->completed_at);
        $this->assertNotNull(WorkTask::findOrFail($secondTask->id)->completed_at);

        $this->assertSame(2, AuditEvent::where('event_type', 'collaboration.task.status_updated')
            ->whereIn('auditable_id', [$firstTask->id, $secondTask->id])
            ->where('user_id', $construction->id)
            ->count());
    }

    public function test_task_bulk_update_denies_partner_access_and_validates_task_scope(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();
        $otherCompany = Company::where('code', 'B360P')->firstOrFail();
        $externalUser = User::factory()->create([
            'company_id' => $otherCompany->id,
            'name' => 'External Bulk Update User',
            'email' => 'external.bulk.update@example.test',
            'status' => 'active',
        ]);

        $taskNumber = $this->actingAs($sales)
            ->postJson(route('collaboration.tasks.store'), [
                'title' => 'Bulk update scope task',
                'priority' => 'medium',
            ])
            ->assertCreated()
            ->json('data.task_number');

        $task = WorkTask::where('task_number', $taskNumber)->firstOrFail();

        $externalTask = WorkTask::create([
            'company_id' => $otherCompany->id,
            'created_by_user_id' => $externalUser->id,
            'assigned_to_user_id' => $externalUser->id,
            'task_number' => 'TSK-EXT-BULK-UPDATE',
            'title' => 'External task must not bulk update',
            'priority' => 'medium',
            'status' => 'open',
            'workflow_history' => [],
            'metadata' => [],
        ]);

        $this->actingAs($partner)
            ->patchJson(route('collaboration.tasks.bulk-update'), [
                'task_ids' => [$task->id],
                'priority' => 'high',
            ])
            ->assertForbidden();

        $this->actingAs($sales)
            ->patchJson(route('collaboration.tasks.bulk-update'), [
                'task_ids' => [$task->id, $externalTask->id],
                'priority' => 'critical',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('task_ids');

        $this->actingAs($sales)
            ->patchJson(route('collaboration.tasks.bulk-update'), [
                'task_ids' => [$task->id],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('task');

        $this->assertDatabaseHas('work_tasks', [
            'id' => $task->id,
            'priority' => 'medium',
            'status' => 'open',
        ]);

        $this->assertDatabaseHas('work_tasks', [
            'id' => $externalTask->id,
            'priority' => 'medium',
            'status' => 'open',
        ]);
    }

    public function test_task_export_returns_scoped_hardened_csv_and_audit_event(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $otherCompany = Company::where('code', 'B360P')->firstOrFail();
        $externalUser = User::factory()->create([
            'company_id' => $otherCompany->id,
            'name' => 'External Task Export User',
            'email' => 'external.task.export@example.test',
            'status' => 'active',
        ]);

        $taskNumber = $this->actingAs($sales)
            ->postJson(route('collaboration.tasks.store'), [
                'title' => '=2+2',
                'assigned_to_user_id' => $construction->id,
                'priority' => 'high',
                'module_context' => 'reports',
                'checklist' => [
                    ['label' => 'Export scope check', 'done' => true],
                ],
            ])
            ->assertCreated()
            ->json('data.task_number');

        WorkTask::create([
            'company_id' => $otherCompany->id,
            'created_by_user_id' => $externalUser->id,
            'assigned_to_user_id' => $externalUser->id,
            'task_number' => 'TSK-EXT-EXPORT',
            'title' => 'External task must not export',
            'priority' => 'medium',
            'status' => 'open',
            'workflow_history' => [],
            'metadata' => [],
        ]);

        $response = $this->actingAs($sales)
            ->get(route('collaboration.tasks.export', [
                'format' => 'csv',
                'q' => '=2+2',
            ]))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $csv = $response->getContent();
        $cacheControl = $response->headers->get('Cache-Control', '');

        $this->assertStringContainsString('task_number,title,status,priority,module_context', $csv);
        $this->assertStringContainsString($taskNumber, $csv);
        $this->assertStringContainsString("'=2+2", $csv);
        $this->assertStringNotContainsString('TSK-EXT-EXPORT', $csv);
        $this->assertStringContainsString('builder360-collaboration-tasks.csv', $response->headers->get('Content-Disposition', ''));
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);

        $event = AuditEvent::where('event_type', 'collaboration.task.exported')->latest('id')->firstOrFail();

        $this->assertSame($sales->id, $event->user_id);
        $this->assertSame('Exported collaboration task register', $event->action);
        $this->assertSame('csv', $event->metadata['format']);
        $this->assertSame(1, $event->metadata['row_count']);
        $this->assertSame('=2+2', $event->metadata['filters']['q']);

        $pdfResponse = $this->actingAs($sales)
            ->get(route('collaboration.tasks.export', [
                'format' => 'pdf',
                'q' => '=2+2',
            ]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringStartsWith('%PDF-1.4', $pdfResponse->getContent());
        $this->assertStringContainsString('builder360-collaboration-tasks.pdf', $pdfResponse->headers->get('Content-Disposition', ''));

        $pdfEvent = AuditEvent::where('event_type', 'collaboration.task.exported')->latest('id')->firstOrFail();

        $this->assertSame('pdf', $pdfEvent->metadata['format']);
        $this->assertSame(1, $pdfEvent->metadata['row_count']);
        $this->assertSame('=2+2', $pdfEvent->metadata['filters']['q']);
    }

    public function test_task_export_validates_filters_and_denies_partner_access(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();

        $this->actingAs($sales)
            ->getJson(route('collaboration.tasks.export', ['format' => 'xlsx']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('format');

        $this->actingAs($partner)
            ->get(route('collaboration.tasks.export', ['format' => 'csv']))
            ->assertForbidden();
    }

    public function test_task_comments_and_checklist_are_persisted_audited_and_notified(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();

        $taskNumber = $this->actingAs($sales)
            ->postJson(route('collaboration.tasks.store'), [
                'title' => 'Coordinate customer walkthrough checklist',
                'assigned_to_user_id' => $construction->id,
                'priority' => 'medium',
                'checklist' => [
                    ['label' => 'Confirm access cards', 'done' => false],
                    ['label' => 'Prepare snag sheet', 'done' => false],
                ],
            ])
            ->assertCreated()
            ->json('data.task_number');

        $task = WorkTask::where('task_number', $taskNumber)->firstOrFail();

        $checklistResponse = $this->actingAs($construction)
            ->patchJson(route('collaboration.tasks.checklist.update', $task), [
                'checklist' => [
                    ['label' => 'Confirm access cards', 'done' => true],
                    ['text' => 'Prepare snag sheet', 'done' => false],
                    ['label' => 'Call facility manager', 'done' => false],
                ],
                'note' => 'Walkthrough checklist updated.',
            ])
            ->assertOk()
            ->assertJsonPath('data.checklist.0.done', true)
            ->assertJsonCount(3, 'data.checklist');

        $this->assertTrue(collect($checklistResponse->json('data.checklist'))->contains(
            fn (array $item): bool => $item['label'] === 'Call facility manager' && $item['done'] === false,
        ));

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'collaboration.task.checklist_updated',
            'auditable_type' => WorkTask::class,
            'auditable_id' => $task->id,
        ]);

        $this->actingAs($construction)
            ->postJson(route('collaboration.tasks.comments.store', $task), [
                'body' => 'Access cards are confirmed. Please review the added facility manager step.',
                'mentions' => [$sales->id],
            ])
            ->assertOk()
            ->assertJsonPath('data.comments.0.author.email', 'rajesh.kulkarni@builder360.test')
            ->assertJsonPath('data.comments.0.mentions.0', $sales->id);

        $comment = WorkTaskComment::where('work_task_id', $task->id)->firstOrFail();

        $this->assertDatabaseHas('work_task_comments', [
            'id' => $comment->id,
            'company_id' => $task->company_id,
            'work_task_id' => $task->id,
            'author_user_id' => $construction->id,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'collaboration.task.comment_added',
            'auditable_type' => WorkTask::class,
            'auditable_id' => $task->id,
        ]);

        $this->assertDatabaseHas('user_notifications', [
            'recipient_user_id' => $sales->id,
            'triggered_by_user_id' => $construction->id,
            'category' => 'collaboration',
            'notifiable_type' => WorkTask::class,
            'notifiable_id' => $task->id,
        ]);
    }

    public function test_task_comment_mentions_reject_cross_company_users(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $otherCompany = Company::where('code', 'B360P')->firstOrFail();
        $externalUser = User::factory()->create([
            'company_id' => $otherCompany->id,
            'name' => 'External Mention User',
            'email' => 'external.mention@example.test',
            'status' => 'active',
        ]);

        $taskNumber = $this->actingAs($sales)
            ->postJson(route('collaboration.tasks.store'), [
                'title' => 'Task mention scope check',
                'priority' => 'medium',
            ])
            ->assertCreated()
            ->json('data.task_number');

        $task = WorkTask::where('task_number', $taskNumber)->firstOrFail();

        $this->actingAs($sales)
            ->postJson(route('collaboration.tasks.comments.store', $task), [
                'body' => 'Trying to mention an external user.',
                'mentions' => [$externalUser->id],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('mentions');
    }

    public function test_task_subtasks_and_time_logs_are_persisted_audited_notified_and_serialized(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();

        $taskNumber = $this->actingAs($sales)
            ->postJson(route('collaboration.tasks.store'), [
                'title' => 'Execute slab inspection follow-up',
                'assigned_to_user_id' => $construction->id,
                'priority' => 'high',
                'module_context' => 'construction',
            ])
            ->assertCreated()
            ->json('data.task_number');

        $task = WorkTask::where('task_number', $taskNumber)->firstOrFail();

        $subtaskResponse = $this->actingAs($sales)
            ->postJson(route('collaboration.tasks.subtasks.store', $task), [
                'title' => 'Upload cube test photos',
                'assigned_to_user_id' => $construction->id,
                'priority' => 'critical',
                'due_at' => now()->addDay()->setTime(17, 0)->toISOString(),
            ])
            ->assertOk()
            ->assertJsonPath('data.subtasks.0.title', 'Upload cube test photos')
            ->assertJsonPath('data.subtasks.0.assigned_to.email', 'rajesh.kulkarni@builder360.test');

        $subtaskId = $subtaskResponse->json('data.subtasks.0.id');
        $subtask = WorkTaskSubtask::findOrFail($subtaskId);

        $this->assertDatabaseHas('work_task_subtasks', [
            'id' => $subtask->id,
            'company_id' => $task->company_id,
            'work_task_id' => $task->id,
            'assigned_to_user_id' => $construction->id,
            'status' => 'open',
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'collaboration.task.subtask_created',
            'auditable_type' => WorkTask::class,
            'auditable_id' => $task->id,
        ]);

        $this->assertDatabaseHas('user_notifications', [
            'recipient_user_id' => $construction->id,
            'triggered_by_user_id' => $sales->id,
            'category' => 'collaboration',
            'severity' => 'critical',
            'notifiable_type' => WorkTask::class,
            'notifiable_id' => $task->id,
        ]);

        $this->actingAs($construction)
            ->patchJson(route('collaboration.tasks.subtasks.update', [$task, $subtask]), [
                'status' => 'completed',
                'note' => 'Photos uploaded and tagged.',
            ])
            ->assertOk()
            ->assertJsonPath('data.subtasks.0.status', 'completed')
            ->assertJsonPath('data.subtasks.0.completed_at', fn (?string $value): bool => $value !== null);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'collaboration.task.subtask_status_updated',
            'auditable_type' => WorkTask::class,
            'auditable_id' => $task->id,
        ]);

        $this->actingAs($construction)
            ->postJson(route('collaboration.tasks.time-logs.store', $task), [
                'minutes' => 95,
                'logged_on' => now()->toDateString(),
                'note' => 'Inspection follow-up and photo tagging.',
                'source' => 'manual',
            ])
            ->assertOk()
            ->assertJsonPath('data.time_logs.0.user.email', 'rajesh.kulkarni@builder360.test')
            ->assertJsonPath('data.time_logs.0.minutes', 95)
            ->assertJsonPath('data.time_logs.0.hours', 1.58);

        $timeLog = WorkTaskTimeLog::where('work_task_id', $task->id)->firstOrFail();

        $this->assertDatabaseHas('work_task_time_logs', [
            'id' => $timeLog->id,
            'company_id' => $task->company_id,
            'work_task_id' => $task->id,
            'user_id' => $construction->id,
            'minutes' => 95,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'collaboration.task.time_logged',
            'auditable_type' => WorkTask::class,
            'auditable_id' => $task->id,
        ]);
    }

    public function test_task_subtasks_reject_cross_company_assignees_and_nested_mismatch(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $otherCompany = Company::where('code', 'B360P')->firstOrFail();
        $externalUser = User::factory()->create([
            'company_id' => $otherCompany->id,
            'name' => 'External Subtask User',
            'email' => 'external.subtask@example.test',
            'status' => 'active',
        ]);

        $firstTaskNumber = $this->actingAs($sales)
            ->postJson(route('collaboration.tasks.store'), [
                'title' => 'First subtask scope task',
                'assigned_to_user_id' => $construction->id,
                'priority' => 'medium',
            ])
            ->assertCreated()
            ->json('data.task_number');

        $secondTaskNumber = $this->actingAs($sales)
            ->postJson(route('collaboration.tasks.store'), [
                'title' => 'Second subtask scope task',
                'assigned_to_user_id' => $construction->id,
                'priority' => 'medium',
            ])
            ->assertCreated()
            ->json('data.task_number');

        $firstTask = WorkTask::where('task_number', $firstTaskNumber)->firstOrFail();
        $secondTask = WorkTask::where('task_number', $secondTaskNumber)->firstOrFail();

        $this->actingAs($sales)
            ->postJson(route('collaboration.tasks.subtasks.store', $firstTask), [
                'title' => 'Invalid external assignment',
                'assigned_to_user_id' => $externalUser->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('assigned_to_user_id');

        $subtaskId = $this->actingAs($sales)
            ->postJson(route('collaboration.tasks.subtasks.store', $firstTask), [
                'title' => 'Valid scoped subtask',
                'assigned_to_user_id' => $construction->id,
            ])
            ->assertOk()
            ->json('data.subtasks.0.id');

        $subtask = WorkTaskSubtask::findOrFail($subtaskId);

        $this->actingAs($sales)
            ->patchJson(route('collaboration.tasks.subtasks.update', [$secondTask, $subtask]), [
                'status' => 'completed',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('subtask');
    }

    public function test_employee_self_service_scope_is_limited_to_own_tasks(): void
    {
        $this->seed();

        $employee = User::where('email', 'amit.verma@builder360.test')->firstOrFail();
        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();

        $this->actingAs($employee)
            ->getJson(route('collaboration.tasks.index'))
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $ownTaskNumber = $this->actingAs($employee)
            ->postJson(route('collaboration.tasks.store'), [
                'title' => 'Upload missing site report photo',
                'priority' => 'medium',
            ])
            ->assertCreated()
            ->assertJsonPath('data.assigned_to.email', 'amit.verma@builder360.test')
            ->json('data.task_number');

        $this->actingAs($employee)
            ->postJson(route('collaboration.tasks.store'), [
                'title' => 'Assign HR task incorrectly',
                'priority' => 'low',
                'assigned_to_user_id' => $hr->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['assigned_to_user_id']);

        $this->actingAs($employee)
            ->getJson(route('collaboration.tasks.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.task_number', $ownTaskNumber);
    }

    public function test_global_user_collaboration_records_are_bound_to_the_active_company(): void
    {
        $this->seed();

        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $company = Company::where('code', 'B360D')->firstOrFail();
        $director->forceFill(['company_id' => null])->save();

        $this->assertSame(
            $company->id,
            app(\App\Services\Security\CompanyScopeService::class)->companyIdFor($director),
        );

        $taskNumber = $this->actingAs($director)
            ->postJson(route('collaboration.tasks.store'), [
                'company_id' => $company->id,
                'title' => 'Global company-scoped private task',
                'priority' => 'medium',
            ])
            ->assertCreated()
            ->assertJsonPath('data.company.id', $company->id)
            ->assertJsonPath('data.assigned_to.email', 'aditya.mehra@builder360.test')
            ->json('data.task_number');

        $this->assertDatabaseHas('work_tasks', [
            'task_number' => $taskNumber,
            'company_id' => $company->id,
            'assigned_to_user_id' => $director->id,
            'created_by_user_id' => $director->id,
        ]);

        $eventNumber = $this->actingAs($director)
            ->postJson(route('collaboration.calendar-events.store'), [
                'company_id' => $company->id,
                'title' => 'Global company-scoped private event',
                'event_type' => 'meeting',
                'starts_at' => now()->addDays(60)->setTime(10, 0)->toISOString(),
                'ends_at' => now()->addDays(60)->setTime(11, 0)->toISOString(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.company.id', $company->id)
            ->assertJsonPath('data.organizer.email', 'aditya.mehra@builder360.test')
            ->json('data.event_number');

        $this->assertDatabaseHas('calendar_events', [
            'event_number' => $eventNumber,
            'company_id' => $company->id,
            'organizer_user_id' => $director->id,
        ]);

        $implicitTaskNumber = $this->actingAs($director)
            ->postJson(route('collaboration.tasks.store'), [
                'title' => 'Active-company private task',
                'priority' => 'medium',
            ])
            ->assertCreated()
            ->assertJsonPath('data.company.id', $company->id)
            ->json('data.task_number');

        $this->assertDatabaseHas('work_tasks', [
            'task_number' => $implicitTaskNumber,
            'company_id' => $company->id,
        ]);

        $implicitEventNumber = $this->actingAs($director)
            ->postJson(route('collaboration.calendar-events.store'), [
                'title' => 'Active-company private event',
                'event_type' => 'meeting',
                'starts_at' => now()->addDays(61)->setTime(10, 0)->toISOString(),
                'ends_at' => now()->addDays(61)->setTime(11, 0)->toISOString(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.company.id', $company->id)
            ->json('data.event_number');

        $this->assertDatabaseHas('calendar_events', [
            'event_number' => $implicitEventNumber,
            'company_id' => $company->id,
        ]);
    }

    public function test_collaboration_indexes_validate_filter_scope(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $employee = User::where('email', 'amit.verma@builder360.test')->firstOrFail();
        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $otherCompany = Company::where('code', 'B360P')->firstOrFail();
        $otherProject = Project::where('company_id', $otherCompany->id)->firstOrFail();
        $externalAssignee = User::factory()->create([
            'company_id' => $otherCompany->id,
            'name' => 'External Collaboration User',
            'email' => 'external.collaboration@example.test',
            'status' => 'active',
        ]);

        $this->actingAs($sales)
            ->getJson(route('collaboration.tasks.index', ['page' => 1]))
            ->assertOk()
            ->assertJsonPath('meta.current_page', 1);

        $this->actingAs($sales)
            ->getJson(route('collaboration.tasks.index', ['event_type' => 'meeting']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['event_type'])
            ->assertJsonPath('errors.event_type.0', 'The selected filter is not available for this endpoint.');

        $this->actingAs($sales)
            ->getJson(route('collaboration.tasks.index', ['status' => 'invalid']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->actingAs($sales)
            ->getJson(route('collaboration.tasks.index', ['assigned_to_user_id' => $externalAssignee->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['assigned_to_user_id']);

        $this->actingAs($sales)
            ->getJson(route('collaboration.tasks.index', ['project_id' => $otherProject->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id']);

        $this->actingAs($sales)
            ->getJson(route('collaboration.calendar-events.index', ['page' => 1]))
            ->assertOk()
            ->assertJsonPath('meta.current_page', 1);

        $this->actingAs($sales)
            ->getJson(route('collaboration.calendar-events.index', ['assigned_to_user_id' => $sales->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['assigned_to_user_id'])
            ->assertJsonPath('errors.assigned_to_user_id.0', 'The selected filter is not available for this endpoint.');

        $this->actingAs($sales)
            ->getJson(route('collaboration.calendar-events.index', ['status' => 'invalid']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->actingAs($sales)
            ->getJson(route('collaboration.calendar-events.index', ['project_id' => $otherProject->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id']);

        $this->actingAs($employee)
            ->getJson(route('collaboration.tasks.index', ['assigned_to_user_id' => $hr->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['assigned_to_user_id']);
    }

    public function test_non_global_collaboration_users_without_company_assignment_fail_closed(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();

        $taskNumber = $this->actingAs($sales)
            ->postJson(route('collaboration.tasks.store'), [
                'title' => 'Scope hardening task',
                'assigned_to_user_id' => $construction->id,
                'project_id' => $project->id,
                'priority' => 'medium',
            ])
            ->assertCreated()
            ->json('data.task_number');

        $eventNumber = $this->actingAs($sales)
            ->postJson(route('collaboration.calendar-events.store'), [
                'title' => 'Scope hardening calendar event',
                'event_type' => 'meeting',
                'starts_at' => now()->addDays(30)->setTime(10, 0)->toISOString(),
                'ends_at' => now()->addDays(30)->setTime(11, 0)->toISOString(),
                'project_id' => $project->id,
                'attendees' => [
                    ['user_id' => $construction->id],
                ],
            ])
            ->assertCreated()
            ->json('data.event_number');

        $task = WorkTask::where('task_number', $taskNumber)->firstOrFail();
        $event = CalendarEvent::where('event_number', $eventNumber)->firstOrFail();

        $sales->forceFill(['company_id' => null])->save();

        $this->actingAs($sales)
            ->getJson(route('collaboration.tasks.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($sales)
            ->getJson(route('collaboration.calendar-events.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($sales)
            ->getJson(route('collaboration.tasks.index', ['assigned_to_user_id' => $construction->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('assigned_to_user_id');

        $this->actingAs($sales)
            ->getJson(route('collaboration.calendar-events.index', ['project_id' => $project->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('project_id');

        $this->actingAs($sales)
            ->postJson(route('collaboration.tasks.store'), [
                'title' => 'Should fail without company scope',
                'priority' => 'medium',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('assigned_to_user_id');

        $this->actingAs($sales)
            ->postJson(route('collaboration.calendar-events.store'), [
                'title' => 'Should fail without company scope',
                'event_type' => 'meeting',
                'starts_at' => now()->addDays(31)->setTime(10, 0)->toISOString(),
                'ends_at' => now()->addDays(31)->setTime(11, 0)->toISOString(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('attendees');

        $this->actingAs($sales)
            ->patchJson(route('collaboration.tasks.status.update', $task), [
                'status' => 'in_progress',
                'note' => 'Should fail closed.',
            ])
            ->assertForbidden();

        $this->actingAs($sales)
            ->patchJson(route('collaboration.calendar-events.cancel', $event), [
                'reason' => 'Should fail closed.',
            ])
            ->assertForbidden();
    }

    public function test_collaboration_service_rejects_cross_company_projects_and_attendees(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $otherCompany = Company::where('code', 'B360P')->firstOrFail();
        $otherProject = Project::where('company_id', $otherCompany->id)->firstOrFail();
        $externalAttendee = User::factory()->create([
            'company_id' => $otherCompany->id,
            'name' => 'External Calendar Attendee',
            'email' => 'external.calendar@example.test',
            'status' => 'active',
        ]);
        $service = app(CollaborationService::class);

        try {
            $service->createTask([
                'title' => 'Invalid external project task',
                'project_id' => $otherProject->id,
                'priority' => 'medium',
            ], $sales);

            $this->fail('Cross-company task project was not rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('project_id', $exception->errors());
        }

        try {
            $service->createCalendarEvent([
                'title' => 'Invalid external project event',
                'event_type' => 'meeting',
                'starts_at' => now()->addDays(20)->setTime(10, 0)->toISOString(),
                'ends_at' => now()->addDays(20)->setTime(11, 0)->toISOString(),
                'project_id' => $otherProject->id,
            ], $sales);

            $this->fail('Cross-company calendar project was not rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('project_id', $exception->errors());
        }

        try {
            $service->createCalendarEvent([
                'title' => 'Invalid external attendee event',
                'event_type' => 'meeting',
                'starts_at' => now()->addDays(21)->setTime(10, 0)->toISOString(),
                'ends_at' => now()->addDays(21)->setTime(11, 0)->toISOString(),
                'attendees' => [
                    ['user_id' => $externalAttendee->id],
                ],
            ], $sales);

            $this->fail('Cross-company calendar attendee was not rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('attendees', $exception->errors());
        }
    }

    public function test_calendar_event_creation_notifies_attendees_and_rejects_conflicts(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();

        $startsAt = now()->addDays(8)->setTime(12, 0);
        $endsAt = now()->addDays(8)->setTime(13, 0);

        $eventNumber = $this->actingAs($sales)
            ->postJson(route('collaboration.calendar-events.store'), [
                'title' => 'Collection follow-up planning',
                'event_type' => 'payment_follow_up',
                'starts_at' => $startsAt->toISOString(),
                'ends_at' => $endsAt->toISOString(),
                'project_id' => $project->id,
                'attendees' => [
                    ['user_id' => $finance->id],
                ],
                'reminders' => [
                    ['minutes_before' => 60],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'scheduled')
            ->json('data.event_number');

        $event = CalendarEvent::where('event_number', $eventNumber)->firstOrFail();

        $this->assertDatabaseHas('user_notifications', [
            'recipient_user_id' => $finance->id,
            'triggered_by_user_id' => $sales->id,
            'category' => 'calendar',
            'notifiable_type' => CalendarEvent::class,
            'notifiable_id' => $event->id,
        ]);

        $this->actingAs($sales)
            ->postJson(route('collaboration.calendar-events.store'), [
                'title' => 'Conflicting finance review',
                'event_type' => 'meeting',
                'starts_at' => $startsAt->copy()->addMinutes(15)->toISOString(),
                'ends_at' => $endsAt->copy()->addMinutes(15)->toISOString(),
                'attendees' => [
                    ['user_id' => $finance->id],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['starts_at']);
    }

    public function test_calendar_event_update_reschedules_with_conflict_validation_audit_and_notifications(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();

        $eventNumber = $this->actingAs($sales)
            ->postJson(route('collaboration.calendar-events.store'), [
                'title' => 'Initial finance coordination',
                'event_type' => 'meeting',
                'starts_at' => now()->addDays(11)->setTime(10, 0)->toISOString(),
                'ends_at' => now()->addDays(11)->setTime(11, 0)->toISOString(),
                'project_id' => $project->id,
                'attendees' => [
                    ['user_id' => $finance->id],
                ],
                'reminders' => [
                    ['minutes_before' => 30],
                ],
            ])
            ->assertCreated()
            ->json('data.event_number');

        $event = CalendarEvent::where('event_number', $eventNumber)->firstOrFail();
        $rescheduledStart = now()->addDays(12)->setTime(14, 0);
        $rescheduledEnd = now()->addDays(12)->setTime(15, 0);

        $this->actingAs($sales)
            ->patchJson(route('collaboration.calendar-events.update', $event), [
                'title' => 'Updated finance coordination',
                'description' => 'Updated agenda from Calendar Management screen.',
                'event_type' => 'payment_follow_up',
                'starts_at' => $rescheduledStart->toISOString(),
                'ends_at' => $rescheduledEnd->toISOString(),
                'project_id' => $project->id,
                'location' => 'Finance desk',
                'visibility' => 'internal',
                'attendees' => [
                    ['user_id' => $finance->id, 'response' => 'pending'],
                ],
                'reminders' => [
                    ['minutes_before' => 45],
                ],
                'note' => 'Customer requested the payment follow-up slot.',
                'metadata' => [
                    'source' => 'calendar_management_screen',
                    'priority' => 'high',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.event_number', $eventNumber)
            ->assertJsonPath('data.title', 'Updated finance coordination')
            ->assertJsonPath('data.event_type', 'payment_follow_up')
            ->assertJsonPath('data.status', 'rescheduled')
            ->assertJsonPath('data.metadata.priority', 'high');

        $event->refresh();

        $this->assertSame('rescheduled', $event->status);
        $this->assertSame('Updated finance coordination', $event->title);
        $this->assertSame('payment_follow_up', $event->event_type);
        $this->assertSame(45, $event->reminders[0]['minutes_before']);
        $this->assertSame('rescheduled', collect($event->workflow_history)->last()['status']);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'collaboration.calendar_event.updated',
            'auditable_type' => CalendarEvent::class,
            'auditable_id' => $event->id,
        ]);

        $this->assertDatabaseHas('user_notifications', [
            'recipient_user_id' => $finance->id,
            'triggered_by_user_id' => $sales->id,
            'category' => 'calendar',
            'notifiable_type' => CalendarEvent::class,
            'notifiable_id' => $event->id,
        ]);

        $conflictingEventNumber = $this->actingAs($sales)
            ->postJson(route('collaboration.calendar-events.store'), [
                'title' => 'Non-conflicting finance review',
                'event_type' => 'meeting',
                'starts_at' => now()->addDays(13)->setTime(10, 0)->toISOString(),
                'ends_at' => now()->addDays(13)->setTime(11, 0)->toISOString(),
                'attendees' => [
                    ['user_id' => $finance->id],
                ],
            ])
            ->assertCreated()
            ->json('data.event_number');

        $conflictingEvent = CalendarEvent::where('event_number', $conflictingEventNumber)->firstOrFail();

        $this->actingAs($sales)
            ->patchJson(route('collaboration.calendar-events.update', $conflictingEvent), [
                'title' => 'Conflicting update',
                'event_type' => 'meeting',
                'starts_at' => $rescheduledStart->copy()->addMinutes(10)->toISOString(),
                'ends_at' => $rescheduledEnd->copy()->addMinutes(10)->toISOString(),
                'attendees' => [
                    ['user_id' => $finance->id],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['starts_at']);
    }

    public function test_calendar_event_completion_is_audited_notified_and_permission_controlled(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();

        $eventNumber = $this->actingAs($sales)
            ->postJson(route('collaboration.calendar-events.store'), [
                'title' => 'Completeable finance review',
                'event_type' => 'meeting',
                'starts_at' => now()->addDays(15)->setTime(10, 0)->toISOString(),
                'ends_at' => now()->addDays(15)->setTime(11, 0)->toISOString(),
                'attendees' => [
                    ['user_id' => $finance->id],
                ],
            ])
            ->assertCreated()
            ->json('data.event_number');

        $event = CalendarEvent::where('event_number', $eventNumber)->firstOrFail();

        $this->actingAs($sales)
            ->patchJson(route('collaboration.calendar-events.complete', $event), [
                'note' => 'Meeting outcomes captured.',
            ])
            ->assertOk()
            ->assertJsonPath('data.event_number', $eventNumber)
            ->assertJsonPath('data.status', 'completed');

        $event->refresh();

        $this->assertSame('completed', $event->status);
        $this->assertSame('completed', collect($event->workflow_history)->last()['status']);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'collaboration.calendar_event.completed',
            'auditable_type' => CalendarEvent::class,
            'auditable_id' => $event->id,
        ]);

        $this->assertDatabaseHas('user_notifications', [
            'recipient_user_id' => $finance->id,
            'triggered_by_user_id' => $sales->id,
            'category' => 'calendar',
            'notifiable_type' => CalendarEvent::class,
            'notifiable_id' => $event->id,
        ]);

        $this->actingAs($sales)
            ->patchJson(route('collaboration.calendar-events.complete', $event), [
                'note' => 'Duplicate completion attempt.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['event']);

        $this->actingAs($partner)
            ->patchJson(route('collaboration.calendar-events.complete', $event), [
                'note' => 'Unauthorized partner attempt.',
            ])
            ->assertForbidden();
    }

    public function test_calendar_cancellation_and_partner_denial(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();
        $event = CalendarEvent::where('event_number', 'CAL-10001')->firstOrFail();

        $this->actingAs($sales)
            ->patchJson(route('collaboration.calendar-events.cancel', $event), [
                'reason' => 'Coordination meeting moved to next week.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'collaboration.calendar_event.cancelled',
            'auditable_type' => CalendarEvent::class,
            'auditable_id' => $event->id,
        ]);

        $this->actingAs($partner)
            ->getJson(route('collaboration.tasks.index'))
            ->assertForbidden();

        $this->actingAs($partner)
            ->getJson(route('collaboration.calendar-events.index'))
            ->assertForbidden();

        $this->assertGreaterThan(0, AuditEvent::where('event_type', 'collaboration.calendar_event.cancelled')->count());
    }

    public function test_calendar_event_delete_soft_archives_with_audit_and_scope_control(): void
    {
        $this->seed();

        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();
        $event = CalendarEvent::where('event_number', 'CAL-10001')->firstOrFail();

        $this->actingAs($partner)
            ->deleteJson(route('collaboration.calendar-events.destroy', $event), [
                'reason' => 'Partner should not archive internal calendar events.',
            ])
            ->assertForbidden();

        $this->actingAs($director)
            ->deleteJson(route('collaboration.calendar-events.destroy', $event), [
                'reason' => 'Archived after schedule consolidation.',
            ])
            ->assertOk()
            ->assertJsonPath('data.event_number', 'CAL-10001')
            ->assertJsonPath('message', 'Calendar event archived.');

        $this->assertSoftDeleted('calendar_events', [
            'id' => $event->id,
            'event_number' => 'CAL-10001',
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'collaboration.calendar_event.archived',
            'auditable_type' => CalendarEvent::class,
            'auditable_id' => $event->id,
        ]);

        $archived = CalendarEvent::withTrashed()->whereKey($event->id)->firstOrFail();

        $this->assertSame('cancelled', $archived->status);
        $this->assertSame($director->id, $archived->metadata['archived_by_user_id']);

        $this->actingAs($director)
            ->getJson(route('collaboration.calendar-events.index'))
            ->assertOk()
            ->assertJsonMissing(['event_number' => 'CAL-10001']);
    }
}
