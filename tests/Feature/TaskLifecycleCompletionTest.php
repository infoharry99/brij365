<?php

namespace Tests\Feature;

use App\Domain\Collaboration\Services\TaskReminderDispatcher;
use App\Models\User;
use App\Models\WorkTask;
use App\Models\WorkTaskAttachment;
use App\Models\WorkTaskCompletionApproval;
use App\Models\WorkTaskRecurrenceOccurrence;
use App\Models\WorkTaskReminderDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class TaskLifecycleCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_task_workspace_renders_complete_lifecycle_controls(): void
    {
        $this->seed();
        $actor = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $task = WorkTask::where('task_number', 'TSK-10001')->firstOrFail();

        $this->actingAs($actor)->get(route('collaboration.tasks.index', [
            'scope' => 'mine',
            'view' => 'board',
            'task_id' => $task->id,
        ]))->assertOk()
            ->assertSee('My Tasks')
            ->assertSee('Board')
            ->assertSee('List')
            ->assertSee('Calendar')
            ->assertSee('Subtasks')
            ->assertSee('Checklist')
            ->assertSee('Comments')
            ->assertSee('Activity')
            ->assertSee('Time')
            ->assertSee('Transfer task')
            ->assertSee('Attachments')
            ->assertSee('Recurrence')
            ->assertSee('Reminders');

        $this->actingAs($actor)->get(route('collaboration.tasks.index', [
            'scope' => 'settings',
            'settings_tab' => 'workflow',
        ]))->assertOk()
            ->assertSee('Statuses')
            ->assertSee('Workflow')
            ->assertSee('Permissions')
            ->assertSee('Notifications')
            ->assertSee('Require approval to complete');
    }

    public function test_client_token_prevents_duplicate_task_creation(): void
    {
        $this->seed();
        $actor = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $token = (string) Str::uuid();
        $payload = [
            'title' => 'Idempotent task creation',
            'priority' => 'medium',
            'client_token' => $token,
            'assigned_to_user_id' => $actor->id,
        ];

        $firstId = $this->actingAs($actor)->postJson(route('collaboration.tasks.store'), $payload)
            ->assertCreated()->json('data.id');
        $secondId = $this->actingAs($actor)->postJson(route('collaboration.tasks.store'), $payload)
            ->assertOk()->json('data.id');

        $this->assertSame($firstId, $secondId);
        $this->assertSame(1, WorkTask::where('client_token', $token)->count());
    }

    public function test_authorized_user_can_duplicate_a_task_without_reusing_completed_child_state(): void
    {
        $this->seed();
        $actor = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $source = WorkTask::where('task_number', 'TSK-10001')->firstOrFail();
        $source->subtasks()->create([
            'company_id' => $source->company_id,
            'created_by_user_id' => $actor->id,
            'title' => 'Completed source step',
            'status' => 'completed',
            'priority' => 'high',
        ]);

        $this->actingAs($actor)->post(route('collaboration.tasks.duplicate', $source), [
            'client_token' => (string) Str::uuid(),
        ])->assertRedirect();

        $copy = WorkTask::where('id', '!=', $source->id)
            ->where('metadata->duplicated_from_task_id', $source->id)
            ->latest('id')->firstOrFail();
        $this->assertSame('open', $copy->subtasks()->firstOrFail()->status);
        $this->assertNull($copy->completed_at);
        $this->assertNull($copy->due_at);
    }

    public function test_task_attachments_are_private_and_removable_by_an_authorized_user(): void
    {
        Storage::fake('local');
        $this->seed();
        $actor = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $task = WorkTask::where('task_number', 'TSK-10001')->firstOrFail();

        $this->actingAs($actor)->post(route('collaboration.tasks.attachments.store', $task), [
            'attachment' => UploadedFile::fake()->image('site-photo.png'),
        ])->assertRedirect();

        $attachment = WorkTaskAttachment::where('work_task_id', $task->id)->firstOrFail();
        Storage::disk('local')->assertExists($attachment->path);

        $this->actingAs($actor)->delete(route('collaboration.tasks.attachments.destroy', [$task, $attachment]))
            ->assertRedirect();
        Storage::disk('local')->assertMissing($attachment->path);
        $this->assertDatabaseMissing('work_task_attachments', ['id' => $attachment->id]);
    }

    public function test_employee_completion_respects_the_active_approval_workflow(): void
    {
        $this->seed();
        $employee = User::where('email', 'amit.verma@builder360.test')->firstOrFail();
        $taskId = $this->actingAs($employee)->postJson(route('collaboration.tasks.store'), [
            'title' => 'Completion approval task',
            'priority' => 'medium',
            'assigned_to_user_id' => $employee->id,
        ])->assertCreated()->json('data.id');

        $task = WorkTask::findOrFail($taskId);
        $this->actingAs($employee)->patchJson(route('collaboration.tasks.status.update', $task), [
            'status' => 'completed',
            'note' => 'Work is ready for approval.',
        ])->assertOk()->assertJsonPath('data.status', 'waiting_approval');

        $this->assertDatabaseHas('work_tasks', ['id' => $task->id, 'status' => 'waiting_approval']);
        $this->assertSame($employee->id, data_get($task->fresh()->metadata, 'completion_approval.requested_by_user_id'));
    }

    public function test_recurring_task_and_reminder_generation_are_idempotent(): void
    {
        $this->seed();
        $manager = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $taskId = $this->actingAs($manager)->postJson(route('collaboration.tasks.store'), [
            'title' => 'Weekly sales follow-up',
            'priority' => 'medium',
            'assigned_to_user_id' => $manager->id,
            'due_at' => now()->addHour()->toDateTimeString(),
            'metadata' => [
                'recurrence_frequency' => 'weekly',
                'recurrence_interval' => 1,
                'recurrence_until' => now()->addMonth()->toDateString(),
                'reminder_minutes_before' => [60],
            ],
        ])->assertCreated()->json('data.id');
        $task = WorkTask::findOrFail($taskId);

        $dispatcher = app(TaskReminderDispatcher::class);
        $this->assertSame(1, $dispatcher->dispatchDue(now()));
        $this->assertSame(0, $dispatcher->dispatchDue(now()));

        $this->actingAs($manager)->patchJson(route('collaboration.tasks.status.update', $task), [
            'status' => 'completed',
            'note' => 'Completed this occurrence.',
        ])->assertOk();

        $task->refresh();
        $nextId = data_get($task->metadata, 'recurrence_next_task_id');
        $this->assertNotNull($nextId);
        $this->assertSame($task->due_at->copy()->addWeek()->toDateTimeString(), WorkTask::findOrFail($nextId)->due_at->toDateTimeString());
        $this->assertSame(1, WorkTaskRecurrenceOccurrence::where('source_work_task_id', $task->id)->count());
        $this->assertSame(1, WorkTaskReminderDelivery::where('work_task_id', $task->id)->where('status', 'sent')->count());
    }

    public function test_completion_approval_can_be_decided_and_is_recorded_structurally(): void
    {
        $this->seed();
        $employee = User::where('email', 'amit.verma@builder360.test')->firstOrFail();
        $manager = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $task = WorkTask::create([
            'company_id' => $employee->company_id,
            'created_by_user_id' => $employee->id,
            'assigned_to_user_id' => $employee->id,
            'task_number' => 'TSK-APPROVAL-1',
            'title' => 'Approval lifecycle',
            'priority' => 'high',
            'status' => 'in_progress',
        ]);

        $this->actingAs($employee)->patchJson(route('collaboration.tasks.status.update', $task), [
            'status' => 'completed', 'note' => 'Ready for review.', 'lock_version' => $task->lock_version,
        ])->assertOk()->assertJsonPath('data.status', 'waiting_approval');

        $approval = WorkTaskCompletionApproval::where('work_task_id', $task->id)->firstOrFail();
        $this->actingAs($manager)->patch(route('collaboration.tasks.completion-approvals.decide', $approval), [
            'decision' => 'approve', 'note' => 'Verified and approved.',
        ])->assertRedirect();

        $this->assertDatabaseHas('work_task_completion_approvals', ['id' => $approval->id, 'status' => 'approved', 'decided_by_user_id' => $manager->id]);
        $this->assertDatabaseHas('work_tasks', ['id' => $task->id, 'status' => 'completed']);
    }

    public function test_stale_task_version_is_rejected_without_overwriting_current_state(): void
    {
        $this->seed();
        $manager = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $task = WorkTask::where('task_number', 'TSK-10001')->firstOrFail();
        $staleVersion = $task->lock_version;
        $task->forceFill(['priority' => 'critical'])->save();

        $this->actingAs($manager)->patchJson(route('collaboration.tasks.update', $task), [
            'priority' => 'low', 'lock_version' => $staleVersion,
        ])->assertUnprocessable()->assertJsonValidationErrors('lock_version');

        $this->assertSame('critical', $task->fresh()->priority);
    }
}
