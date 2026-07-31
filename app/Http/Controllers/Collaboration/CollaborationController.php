<?php

namespace App\Http\Controllers\Collaboration;

use App\Application\Collaboration\Actions\AddWorkTaskComment;
use App\Application\Collaboration\Actions\ArchiveChatConversation;
use App\Application\Collaboration\Actions\ArchiveCalendarEvent;
use App\Application\Collaboration\Actions\ArchiveMailboxMessage;
use App\Application\Collaboration\Actions\ArchiveWorkTask;
use App\Application\Collaboration\Actions\AssignWorkTask;
use App\Application\Collaboration\Actions\BulkArchiveWorkTasks;
use App\Application\Collaboration\Actions\BulkUpdateWorkTasks;
use App\Application\Collaboration\Actions\CancelCalendarEvent;
use App\Application\Collaboration\Actions\CancelScheduledMailboxMessage;
use App\Application\Collaboration\Actions\ChangeChatMessageReaction;
use App\Application\Collaboration\Actions\ChangeMailboxMessageCrmLink;
use App\Application\Collaboration\Actions\ChangeMailboxMessageReaction;
use App\Application\Collaboration\Actions\ChangeMailboxMessageState;
use App\Application\Collaboration\Actions\ChangeWorkTaskDependencies;
use App\Application\Collaboration\Actions\ChangeWorkTaskStatus;
use App\Application\Collaboration\Actions\ChangeWorkTaskSubtaskStatus;
use App\Application\Collaboration\Actions\ChangeWorkTaskWatcher;
use App\Application\Collaboration\Actions\CompleteCalendarEvent;
use App\Application\Collaboration\Actions\CloseChatPoll;
use App\Application\Collaboration\Actions\CreateCalendarEvent;
use App\Application\Collaboration\Actions\StoreCalendarEventAttachment;
use App\Application\Collaboration\Actions\CreateChatConversation;
use App\Application\Collaboration\Actions\CreateChatPoll;
use App\Application\Collaboration\Actions\CreateWorkTask;
use App\Application\Collaboration\Actions\CreateWorkTaskSubtask;
use App\Application\Collaboration\Actions\ExportMailboxRegister;
use App\Application\Collaboration\Actions\ExportWorkTaskRegister;
use App\Application\Collaboration\Actions\ListCalendarWorkspace;
use App\Application\Collaboration\Actions\ListChatWorkspace;
use App\Application\Collaboration\Actions\ListChatConversations;
use App\Application\Collaboration\Actions\ListChatMessages;
use App\Application\Collaboration\Actions\ListMailboxWorkspace;
use App\Application\Collaboration\Actions\ListTaskWorkspace;
use App\Application\Collaboration\Actions\LogWorkTaskTime;
use App\Application\Collaboration\Actions\MarkChatConversationRead;
use App\Application\Collaboration\Actions\MarkMailboxMessageRead;
use App\Application\Collaboration\Actions\RequestWorkTaskTransfer;
use App\Application\Collaboration\Actions\ResolveWorkTaskTransfer;
use App\Application\Collaboration\Actions\SendChatMessage;
use App\Application\Collaboration\Actions\DeleteChatMessage;
use App\Application\Collaboration\Actions\SendMailboxMessage;
use App\Application\Collaboration\Actions\UpdateCalendarEvent;
use App\Application\Collaboration\Actions\UpdateWorkTask;
use App\Application\Collaboration\Actions\UpdateWorkTaskChecklist;
use App\Application\Collaboration\Actions\VoteChatPoll;
use App\Application\Collaboration\Data\ChatCommandData;
use App\Application\Collaboration\Data\CollaborationCommandData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Collaboration\ArchiveWorkTaskRequest;
use App\Http\Requests\Collaboration\ArchiveChatConversationRequest;
use App\Http\Requests\Collaboration\AssignWorkTaskRequest;
use App\Http\Requests\Collaboration\ArchiveCollaborationMessageRequest;
use App\Http\Requests\Collaboration\BulkArchiveWorkTasksRequest;
use App\Http\Requests\Collaboration\BulkUpdateWorkTasksRequest;
use App\Http\Requests\Collaboration\CalendarEventIndexRequest;
use App\Http\Requests\Collaboration\CancelCalendarEventRequest;
use App\Http\Requests\Collaboration\CloseChatPollRequest;
use App\Http\Requests\Collaboration\CancelScheduledCollaborationMessageRequest;
use App\Http\Requests\Collaboration\ChatConversationIndexRequest;
use App\Http\Requests\Collaboration\CollaborationMessageIndexRequest;
use App\Http\Requests\Collaboration\CompleteCalendarEventRequest;
use App\Http\Requests\Collaboration\DeleteCalendarEventRequest;
use App\Http\Requests\Collaboration\MarkCollaborationMessageReadRequest;
use App\Http\Requests\Collaboration\MarkChatConversationReadRequest;
use App\Http\Requests\Collaboration\RequestWorkTaskTransferApprovalRequest;
use App\Http\Requests\Collaboration\ResolveWorkTaskTransferRequest;
use App\Http\Requests\Collaboration\StoreCalendarEventRequest;
use App\Http\Requests\Collaboration\StoreChatConversationRequest;
use App\Http\Requests\Collaboration\StoreChatMessageRequest;
use App\Http\Requests\Collaboration\StoreChatPollRequest;
use App\Http\Requests\Collaboration\StoreCollaborationMessageRequest;
use App\Http\Requests\Collaboration\UpdateChatConversationRequest;
use App\Http\Requests\Collaboration\AddChatConversationMembersRequest;
use App\Http\Requests\Collaboration\RemoveChatConversationMemberRequest;
use App\Models\MailboxAccount;
use App\Models\InternalMailboxDispatch;
use App\Models\MailboxEmail;
use App\Models\MailboxOutboxMessage;
use App\Http\Requests\Collaboration\StoreWorkTaskCommentRequest;
use App\Http\Requests\Collaboration\StoreWorkTaskRequest;
use App\Http\Requests\Collaboration\StoreWorkTaskSubtaskRequest;
use App\Http\Requests\Collaboration\StoreWorkTaskTimeLogRequest;
use App\Http\Requests\Collaboration\UpdateCollaborationMessageCrmLinkRequest;
use App\Http\Requests\Collaboration\UpdateCollaborationMessageReactionRequest;
use App\Http\Requests\Collaboration\UpdateCollaborationMessageStateRequest;
use Illuminate\Http\Request;
use App\Http\Requests\Collaboration\UpdateCalendarEventRequest;
use App\Http\Requests\Collaboration\UpdateChatMessageReactionRequest;
use App\Http\Requests\Collaboration\UpdateWorkTaskChecklistRequest;
use App\Http\Requests\Collaboration\UpdateWorkTaskDependenciesRequest;
use App\Http\Requests\Collaboration\UpdateWorkTaskRequest;
use App\Http\Requests\Collaboration\UpdateWorkTaskStatusRequest;
use App\Http\Requests\Collaboration\UpdateWorkTaskSubtaskRequest;
use App\Http\Requests\Collaboration\UpdateWorkTaskWatcherRequest;
use App\Http\Requests\Collaboration\VoteChatPollRequest;
use App\Http\Requests\Collaboration\WorkTaskIndexRequest;
use App\Http\Resources\CalendarEventResource;
use App\Http\Resources\ChatConversationResource;
use App\Http\Resources\ChatMessageResource;
use App\Http\Resources\CollaborationMessageResource;
use App\Http\Resources\WorkTaskResource;
use App\Models\CalendarEvent;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatMessageAttachment;
use App\Models\ChatPoll;
use App\Models\CollaborationMessage;
use App\Models\WorkTask;
use App\Models\WorkTaskSubtask;
use App\Models\WorkTaskTransferRequest;
use App\Services\Collaboration\CollaborationService;
use App\Services\Collaboration\ChatConnectService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\View\View;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use App\Domain\Collaboration\Services\CalendarInvitationManager;

class CollaborationController extends Controller
{
    public function __construct(
        private readonly CollaborationService $collaborationService,
        private readonly ChatConnectService $chatConnectService,
        private readonly CalendarInvitationManager $invitations,
    ) {}

    public function chat(ChatConversationIndexRequest $request, ListChatWorkspace $action): View
    {
        $filters = $request->validated();
        $page = $action->execute($request->user(), $filters);

        return view('collaboration.chat.index', [
            'conversations' => $page->conversations,
            'selectedConversation' => $page->selectedConversation,
            'chatMessages' => $page->messages,
            'filters' => $page->filters,
            'projects' => $page->projects,
            'users' => $page->users,
            'chatOptions' => $page->options,
            'conversationTypes' => $page->conversationTypes,
            'canCreateChat' => $page->canCreate,
        ]);
    }

    public function chatConversations(ChatConversationIndexRequest $request, ListChatConversations $action): JsonResponse
    {
        $filters = $request->validated();
        $page = $action->execute($request->user(), $filters, $request);

        return response()->json([
            'data' => ChatConversationResource::collection($page->conversations)->resolve($request),
            'messages' => $page->messages,
            'filters' => [
                'types' => [
                    ['value' => 'direct_message', 'label' => 'DMs'],
                    ['value' => 'group_chat', 'label' => 'Groups'],
                    ['value' => 'department_channel', 'label' => 'Departments'],
                    ['value' => 'project_channel', 'label' => 'Projects'],
                    ['value' => 'unit_conversation', 'label' => 'Units'],
                    ['value' => 'lead_conversation', 'label' => 'Leads'],
                    ['value' => 'approval_thread', 'label' => 'Approvals'],
                    ['value' => 'voucher_thread', 'label' => 'Vouchers'],
                    ['value' => 'task_thread', 'label' => 'Tasks'],
                    ['value' => 'announcement_channel', 'label' => 'Announcements'],
                ],
            ],
        ]);
    }

    public function chatConversationSidebar(ChatConversationIndexRequest $request, ListChatConversations $action): View
    {
        $filters = $request->validated();
        $page = $action->execute($request->user(), $filters, $request);
        $selectedConversation = isset($filters['conversation_id'])
            ? $page->conversations->firstWhere('id', (int) $filters['conversation_id'])
            : $page->conversations->first();

        return view('collaboration.chat.partials.conversation-list', [
            'conversations' => $page->conversations,
            'selectedConversation' => $selectedConversation,
            'filterQuery' => array_filter($filters, fn ($value) => $value !== null && $value !== ''),
        ]);
    }

    public function storeChatConversation(StoreChatConversationRequest $request, CreateChatConversation $action): JsonResponse|RedirectResponse
    {
        $result = $action->execute($this->chatCommand($request));
        $conversation = $result->conversation;
        $created = $conversation->wasRecentlyCreated;

        if (! $request->wantsJson()) {
            return redirect()
                ->route('collaboration.chat.index', ['conversation_id' => $conversation->id])
                ->with('status', $created ? 'Chat conversation created successfully.' : 'Existing direct conversation opened.');
        }

        return response()->json([
            'message' => $created ? 'Chat conversation created.' : 'Existing direct conversation opened.',
            'data' => (new ChatConversationResource($conversation))->resolve($request),
            'messages' => ChatMessageResource::collection($result->messages)->resolve($request),
        ], $created ? Response::HTTP_CREATED : Response::HTTP_OK);
    }

    public function chatConversationMessages(ChatConversation $chatConversation, ListChatMessages $action): AnonymousResourceCollection
    {
        $this->authorize('view', $chatConversation);

        return ChatMessageResource::collection(
            $action->execute($chatConversation, request()->user())
        );
    }

    public function chatConversationTimeline(ChatConversation $chatConversation, ListChatMessages $action): View
    {
        $this->authorize('view', $chatConversation);

        return view('collaboration.chat.partials.timeline', [
            'selectedConversation' => $chatConversation,
            'chatMessages' => $action->execute($chatConversation, request()->user()),
        ]);
    }

    public function storeChatConversationMessage(StoreChatMessageRequest $request, ChatConversation $chatConversation, SendChatMessage $action): JsonResponse|RedirectResponse
    {
        $message = $action->execute($chatConversation, $this->chatCommand($request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('collaboration.chat.index', ['conversation_id' => $chatConversation->id])
                ->with('status', 'Message sent successfully.');
        }

        return response()->json([
            'message' => 'Chat message sent.',
            'data' => (new ChatMessageResource($message))->resolve($request),
        ], 201);
    }

    public function storeChatPoll(StoreChatPollRequest $request, ChatConversation $chatConversation, CreateChatPoll $action): JsonResponse|RedirectResponse
    {
        $message = $action->execute($chatConversation, $this->chatCommand($request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('collaboration.chat.index', ['conversation_id' => $chatConversation->id])
                ->with('status', 'Poll created successfully.');
        }

        return response()->json([
            'message' => 'Poll created.',
            'data' => (new ChatMessageResource($message))->resolve($request),
        ], 201);
    }

    public function voteChatPoll(VoteChatPollRequest $request, ChatPoll $poll, VoteChatPoll $action): JsonResponse|RedirectResponse
    {
        $message = $action->execute($poll, $this->chatCommand($request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('collaboration.chat.index', ['conversation_id' => $message->chat_conversation_id])
                ->with('status', 'Poll vote saved successfully.');
        }

        return response()->json([
            'message' => 'Poll vote saved.',
            'data' => (new ChatMessageResource($message))->resolve($request),
        ]);
    }

    public function closeChatPoll(CloseChatPollRequest $request, ChatPoll $poll, CloseChatPoll $action): JsonResponse|RedirectResponse
    {
        $message = $action->execute($poll, $this->chatCommand($request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('collaboration.chat.index', ['conversation_id' => $message->chat_conversation_id])
                ->with('status', 'Poll closed successfully.');
        }

        return response()->json([
            'message' => 'Poll closed.',
            'data' => (new ChatMessageResource($message))->resolve($request),
        ]);
    }

    public function downloadChatAttachment(ChatMessageAttachment $attachment): Response
    {
        $message = $attachment->message()->with('conversation')->firstOrFail();
        $this->authorize('view', $message->conversation);

        abort_if($attachment->scan_status === 'blocked', 423, 'This attachment is unavailable.');
        $disk = $attachment->disk ?: 'local';
        abort_unless(Storage::disk($disk)->exists($attachment->path), 404);

        return Storage::disk($disk)->download(
            $attachment->path,
            $attachment->original_filename,
            [
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    public function previewChatAttachment(ChatMessageAttachment $attachment): Response
    {
        $message = $attachment->message()->with('conversation')->firstOrFail();
        $this->authorize('view', $message->conversation);

        abort_if($attachment->scan_status === 'blocked', 423, 'This attachment is unavailable.');
        abort_unless(str_starts_with((string) $attachment->mime_type, 'image/') || str_starts_with((string) $attachment->mime_type, 'audio/'), 404);
        $disk = $attachment->disk ?: 'local';
        abort_unless(Storage::disk($disk)->exists($attachment->path), 404);

        return response(Storage::disk($disk)->get($attachment->path), 200, [
            'Content-Type' => $attachment->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.addslashes($attachment->original_filename).'"',
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function updateChatMessageReaction(UpdateChatMessageReactionRequest $request, ChatMessage $chatMessage, ChangeChatMessageReaction $action): JsonResponse|RedirectResponse
    {
        $message = $action->execute($chatMessage, $this->chatCommand($request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('collaboration.chat.index', ['conversation_id' => $message->chat_conversation_id])
                ->with('status', 'Reaction updated successfully.');
        }

        return response()->json([
            'message' => 'Reaction updated.',
            'data' => (new ChatMessageResource($message))->resolve($request),
        ]);
    }

    public function destroyChatMessage(Request $request, ChatMessage $chatMessage, DeleteChatMessage $action): JsonResponse|RedirectResponse
    {
        $action->execute($chatMessage, $request->user(), $request);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'message' => 'Message deleted.',
                'message_id' => $chatMessage->id,
            ]);
        }

        return redirect()
            ->route('collaboration.chat.index', ['conversation_id' => $chatMessage->chat_conversation_id])
            ->with('status', 'Message deleted.');
    }

    public function markChatConversationRead(MarkChatConversationReadRequest $request, ChatConversation $chatConversation, MarkChatConversationRead $action): JsonResponse|RedirectResponse
    {
        $updated = $action->execute($chatConversation, $this->chatCommand($request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('collaboration.chat.index', ['conversation_id' => $chatConversation->id])
                ->with('status', 'Conversation marked as read.');
        }

        return response()->json([
            'message' => 'Conversation marked as read.',
            'updated_messages' => $updated,
            'unread_count' => 0,
        ]);
    }

    public function archiveChatConversation(ArchiveChatConversationRequest $request, ChatConversation $chatConversation, ArchiveChatConversation $action): JsonResponse|RedirectResponse
    {
        $membership = $action->execute($chatConversation, $this->chatCommand($request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('collaboration.chat.index')
                ->with('status', 'Conversation archived.');
        }

        return response()->json([
            'message' => 'Conversation archived.',
            'archived' => true,
            'archived_at' => $membership->archived_at?->toISOString(),
        ]);
    }

    public function updateChatConversation(UpdateChatConversationRequest $request, ChatConversation $chatConversation): JsonResponse|RedirectResponse
    {
        $conversation = $this->chatConnectService->updateConversation($chatConversation, $request->validated(), $request->user(), $request);

        if (! $request->wantsJson()) {
            return redirect()
                ->route('collaboration.chat.index', ['conversation_id' => $conversation->id])
                ->with('status', 'Conversation updated successfully.');
        }

        return response()->json([
            'message' => 'Conversation updated.',
            'data' => (new ChatConversationResource($conversation))->resolve($request),
        ]);
    }

    public function addChatConversationMembers(AddChatConversationMembersRequest $request, ChatConversation $chatConversation): JsonResponse|RedirectResponse
    {
        $members = $this->chatConnectService->addMembers($chatConversation, $request->input('member_user_ids', []), $request->user(), $request);

        if (! $request->wantsJson()) {
            return redirect()
                ->route('collaboration.chat.index', ['conversation_id' => $chatConversation->id])
                ->with('status', 'Members added successfully.');
        }

        return response()->json([
            'message' => 'Members added.',
            'added_count' => $members->count(),
        ]);
    }

    public function removeChatConversationMember(RemoveChatConversationMemberRequest $request, ChatConversation $chatConversation, User $user): JsonResponse|RedirectResponse
    {
        $this->chatConnectService->removeMember($chatConversation, $user, $request->user(), $request);

        if (! $request->wantsJson()) {
            return redirect()
                ->route('collaboration.chat.index', ['conversation_id' => $chatConversation->id])
                ->with('status', 'Member removed successfully.');
        }

        return response()->json([
            'message' => 'Member removed.',
            'removed' => true,
        ]);
    }

    public function tasks(WorkTaskIndexRequest $request, ListTaskWorkspace $action): AnonymousResourceCollection|View
    {
        $filters = $request->validated();
        $user = $request->user();
        $page = $action->execute($user, $filters);

        if (! $request->wantsJson()) {
            return view('collaboration.tasks.index', [
                'tasks' => $page->tasks,
                'filters' => $page->filters,
                'companies' => $page->companies,
                'projects' => $page->projects,
                'users' => $page->users,
                'statuses' => $page->statuses,
                'priorities' => $page->priorities,
                'moduleContexts' => $page->moduleContexts,
                'canCreateTask' => $page->canCreate,
                'canManageTasks' => $page->canManage,
                'taskSummary' => $page->summary,
                'taskScopeCounts' => $page->scopeCounts,
                'taskBoard' => $page->board,
                'taskActivity' => $page->activity,
                'taskWorkload' => $page->workload,
                'taskCompletionTrend' => $page->completionTrend,
                'taskStatusDistribution' => $page->statusDistribution,
                'taskApprovalQueue' => $page->approvalQueue,
                'taskPermissionMatrix' => $page->permissionMatrix,
                'selectedTask' => $page->selectedTask,
                'taskSetting' => $page->taskSetting,
                'taskTemplates' => $page->templates,
                'taskTransitionTargets' => $page->transitionTargets,
            ]);
        }

        return WorkTaskResource::collection($page->tasks);
    }

    public function exportTasks(
        WorkTaskIndexRequest $request,
        ExportWorkTaskRegister $action,
    ): Response {
        return $action->execute($request->user(), $request->validated(), $request);
    }

    public function storeTask(StoreWorkTaskRequest $request, CreateWorkTask $action): WorkTaskResource|RedirectResponse
    {
        $task = $action->execute($this->command($request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('collaboration.tasks.index')
                ->with('status', "Task {$task->task_number} created successfully.");
        }

        return (new WorkTaskResource($task))->additional(['message' => 'Task created successfully.']);
    }

    public function assignTask(AssignWorkTaskRequest $request, WorkTask $workTask, AssignWorkTask $action): WorkTaskResource|RedirectResponse
    {
        $task = $action->execute($workTask, $this->command($request));

        if (! $request->wantsJson()) {
            return back()->with('status', "Task {$task->task_number} assigned successfully.");
        }

        return (new WorkTaskResource($task))->additional(['message' => 'Task assigned successfully.']);
    }

    public function requestTaskTransferApproval(
        RequestWorkTaskTransferApprovalRequest $request,
        WorkTask $workTask,
        RequestWorkTaskTransfer $action,
    ): JsonResponse|RedirectResponse {
        $transferRequest = $action->execute($workTask, $this->command($request));
        $transferred = $transferRequest->status === 'approved';

        if (! $request->wantsJson()) {
            return back()->with('status', $transferred
                ? "Task {$workTask->task_number} transferred successfully."
                : "Transfer approval requested for task {$workTask->task_number}.");
        }

        return response()->json([
            'message' => $transferred ? 'Task transferred successfully.' : 'Task transfer approval requested successfully.',
            'result' => $transferred ? 'transferred' : 'pending_approval',
            'data' => $this->transferRequestPayload($transferRequest),
        ], 201);
    }

    public function resolveTaskTransferApproval(
        ResolveWorkTaskTransferRequest $request,
        WorkTaskTransferRequest $workTaskTransferRequest,
        ResolveWorkTaskTransfer $action,
    ): WorkTaskResource|RedirectResponse {
        $task = $action->execute($workTaskTransferRequest, $this->command($request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('collaboration.tasks.index')
                ->with('status', "Task transfer request for {$task->task_number} resolved successfully.");
        }

        return (new WorkTaskResource($task))->additional(['message' => 'Task transfer request resolved successfully.']);
    }

    public function updateTask(UpdateWorkTaskRequest $request, WorkTask $workTask, UpdateWorkTask $action): WorkTaskResource|RedirectResponse
    {
        $task = $action->execute($workTask, $this->command($request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('collaboration.tasks.index')
                ->with('status', "Task {$task->task_number} details updated successfully.");
        }

        return (new WorkTaskResource($task))->additional(['message' => 'Task details updated successfully.']);
    }

    public function updateTaskStatus(UpdateWorkTaskStatusRequest $request, WorkTask $workTask, ChangeWorkTaskStatus $action): WorkTaskResource|RedirectResponse
    {
        $oldStatus = $workTask->status;
        $task = $action->execute($workTask, $this->command($request));

        if (! $request->wantsJson()) {
            if ($task->status === 'waiting_approval') {
                $msg = ($oldStatus === 'waiting_approval')
                    ? "Task {$task->task_number} is already pending Director completion approval."
                    : "Task {$task->task_number} submitted for Director completion approval (Status: Waiting Approval).";
            } else {
                $readableStatus = str($task->status)->replace('_', ' ')->title();
                $msg = "Task {$task->task_number} status updated to {$readableStatus}.";
            }

            return back()->with('status', $msg);
        }

        $jsonMsg = $task->status === 'waiting_approval'
            ? 'Task submitted for Director completion approval.'
            : 'Task status updated successfully.';

        return (new WorkTaskResource($task))->additional(['message' => $jsonMsg]);
    }

    public function updateTaskWatcher(UpdateWorkTaskWatcherRequest $request, WorkTask $workTask, ChangeWorkTaskWatcher $action): WorkTaskResource|RedirectResponse
    {
        $task = $action->execute($workTask, $this->command($request));
        if (! $request->wantsJson()) {
            return back()->with('status', "Task {$task->task_number} watcher preference updated.");
        }

        return (new WorkTaskResource($task))
            ->additional(['message' => 'Task watcher preference updated successfully.']);
    }

    public function updateTaskDependencies(UpdateWorkTaskDependenciesRequest $request, WorkTask $workTask, ChangeWorkTaskDependencies $action): WorkTaskResource|RedirectResponse
    {
        $task = $action->execute($workTask, $this->command($request));
        if (! $request->wantsJson()) {
            return back()->with('status', "Task {$task->task_number} dependencies updated.");
        }

        return (new WorkTaskResource($task))
            ->additional(['message' => 'Task dependencies updated successfully.']);
    }

    public function archiveTask(ArchiveWorkTaskRequest $request, WorkTask $workTask, ArchiveWorkTask $action): JsonResponse|RedirectResponse
    {
        $task = $action->execute($workTask, $this->command($request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('collaboration.tasks.index')
                ->with('status', "Task {$task->task_number} archived successfully.");
        }

        return response()->json([
            'message' => 'Task archived successfully.',
            'data' => [
                'id' => $task->id,
                'task_number' => $task->task_number,
                'archived' => $task->trashed(),
                'deleted_at' => $task->deleted_at?->toISOString(),
            ],
        ]);
    }

    public function bulkArchiveTasks(BulkArchiveWorkTasksRequest $request, BulkArchiveWorkTasks $action): JsonResponse|RedirectResponse
    {
        $tasks = $action->execute($this->command($request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('collaboration.tasks.index')
                ->with('status', count($tasks).' task(s) archived successfully.');
        }

        return response()->json([
            'message' => count($tasks).' task(s) archived successfully.',
            'data' => [
                'count' => count($tasks),
                'tasks' => $tasks,
            ],
        ]);
    }

    public function bulkUpdateTasks(BulkUpdateWorkTasksRequest $request, BulkUpdateWorkTasks $action): AnonymousResourceCollection|RedirectResponse
    {
        $tasks = $action->execute($this->command($request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('collaboration.tasks.index')
                ->with('status', 'Tasks updated successfully.');
        }

        return WorkTaskResource::collection($tasks)->additional(['message' => 'Tasks updated successfully.']);
    }

    public function storeTaskComment(StoreWorkTaskCommentRequest $request, WorkTask $workTask, AddWorkTaskComment $action): WorkTaskResource|RedirectResponse
    {
        $task = $action->execute($workTask, $this->command($request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('collaboration.tasks.index')
                ->with('status', "Comment added to task {$task->task_number}.");
        }

        return (new WorkTaskResource($task))->additional(['message' => 'Task comment added successfully.']);
    }

    public function updateTaskChecklist(UpdateWorkTaskChecklistRequest $request, WorkTask $workTask, UpdateWorkTaskChecklist $action): WorkTaskResource|RedirectResponse
    {
        $task = $action->execute($workTask, $this->command($request));
        if (! $request->wantsJson()) {
            return back()->with('status', "Task {$task->task_number} checklist updated.");
        }

        return (new WorkTaskResource($task))
            ->additional(['message' => 'Task checklist updated successfully.']);
    }

    public function storeTaskSubtask(StoreWorkTaskSubtaskRequest $request, WorkTask $workTask, CreateWorkTaskSubtask $action): WorkTaskResource|RedirectResponse
    {
        $task = $action->execute($workTask, $this->command($request));
        if (! $request->wantsJson()) {
            return back()->with('status', "Subtask added to {$task->task_number}.");
        }

        return (new WorkTaskResource($task))
            ->additional(['message' => 'Task subtask created successfully.']);
    }

    public function updateTaskSubtask(UpdateWorkTaskSubtaskRequest $request, WorkTask $workTask, WorkTaskSubtask $workTaskSubtask, ChangeWorkTaskSubtaskStatus $action): WorkTaskResource|RedirectResponse
    {
        $task = $action->execute($workTask, $workTaskSubtask, $this->command($request));
        if (! $request->wantsJson()) {
            return back()->with('status', "Subtask status updated on {$task->task_number}.");
        }

        return (new WorkTaskResource($task))
            ->additional(['message' => 'Task subtask status updated successfully.']);
    }

    public function storeTaskTimeLog(StoreWorkTaskTimeLogRequest $request, WorkTask $workTask, LogWorkTaskTime $action): WorkTaskResource|RedirectResponse
    {
        $task = $action->execute($workTask, $this->command($request));
        if (! $request->wantsJson()) {
            return back()->with('status', "Time logged on {$task->task_number}.");
        }

        return (new WorkTaskResource($task))
            ->additional(['message' => 'Task time logged successfully.']);
    }

    public function calendarEvents(CalendarEventIndexRequest $request, ListCalendarWorkspace $action): AnonymousResourceCollection|View
    {
        $filters = $request->validated();
        $user = $request->user();
        $page = $action->execute($user, $filters);

        if (! $request->wantsJson()) {
            return view('collaboration.calendar-events.index', [
                'events' => $page->events,
                'filters' => $page->filters,
                'companies' => $page->companies,
                'projects' => $page->projects,
                'eventTypes' => $page->eventTypes,
                'statuses' => $page->statuses,
                'canCreateEvent' => $page->canCreate,
                'canManageEvents' => $page->canManage,
                'calendarUsers' => $page->users,
                'periodEvents' => $page->periodEvents,
                'calendarSummary' => $page->summary,
                'selectedEvent' => $page->selectedEvent,
                'focusDate' => $page->focusDate,
                'periodStart' => $page->periodStart,
                'periodEnd' => $page->periodEnd,
                'periodLabel' => $page->periodLabel,
                'calendarDays' => $page->calendarDays,
                'calendarHours' => $page->hours,
                'employeeLanes' => $page->employeeLanes,
                'teamLanes' => $page->teamLanes,
                'timedDays' => $page->timedDays,
                'calendarTimezone' => $page->timezone,
            ]);
        }

        return CalendarEventResource::collection($page->events);
    }

    public function storeCalendarEvent(
        StoreCalendarEventRequest $request,
        CreateCalendarEvent $action,
        StoreCalendarEventAttachment $attachmentAction,
    ): CalendarEventResource|RedirectResponse {
    
        $event = $action->execute($this->command($request));
    
        // Upload attachments
        foreach ($request->file('attachments', []) as $attachment) {
            $attachmentAction->execute($event, $request->user(), $attachment);
        }
    
        // ── Send guest invitation emails ──────────────────────────────
        // Only fires if the event has external guests (attendee_type = 'guest')
        $event->loadMissing(['attendeeRecords', 'organizer', 'project']);
        if ($event->attendeeRecords->where('attendee_type', 'guest')->isNotEmpty()) {
            $this->invitations->sendExternal($event, 'request');
        }
        // ─────────────────────────────────────────────────────────────
    
        if (! $request->wantsJson()) {
            return redirect()
                ->route('collaboration.calendar-events.index')
                ->with('status', "Calendar event {$event->event_number} created successfully.");
        }
    
        return (new CalendarEventResource($event))
            ->additional(['message' => 'Calendar event created successfully.']);
    }

    public function updateCalendarEvent(UpdateCalendarEventRequest $request, CalendarEvent $calendarEvent, UpdateCalendarEvent $action): CalendarEventResource|RedirectResponse
    {
        $event = $action->execute($calendarEvent, $this->command($request));
        if (! $request->wantsJson()) {
            return back()->with('status', "Calendar event {$event->event_number} updated.");
        }

        return (new CalendarEventResource($event))
            ->additional(['message' => 'Calendar event updated successfully.']);
    }

    public function cancelCalendarEvent(CancelCalendarEventRequest $request, CalendarEvent $calendarEvent, CancelCalendarEvent $action): CalendarEventResource|RedirectResponse
    {
        $event = $action->execute($calendarEvent, $this->command($request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('collaboration.calendar-events.index')
                ->with('status', "Calendar event {$event->event_number} cancelled successfully.");
        }

        return (new CalendarEventResource($event))->additional(['message' => 'Calendar event cancelled successfully.']);
    }

    public function completeCalendarEvent(CompleteCalendarEventRequest $request, CalendarEvent $calendarEvent, CompleteCalendarEvent $action): CalendarEventResource|RedirectResponse
    {
        $event = $action->execute($calendarEvent, $this->command($request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('collaboration.calendar-events.index')
                ->with('status', "Calendar event {$event->event_number} completed successfully.");
        }

        return (new CalendarEventResource($event))->additional(['message' => 'Calendar event completed successfully.']);
    }

    public function deleteCalendarEvent(DeleteCalendarEventRequest $request, CalendarEvent $calendarEvent, ArchiveCalendarEvent $action): JsonResponse|RedirectResponse
    {
        $event = $action->execute($calendarEvent, $this->command($request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('collaboration.calendar-events.index')
                ->with('status', "Calendar event {$event->event_number} archived.");
        }

        return response()->json([
            'data' => [
                'id' => $event->id,
                'event_number' => $event->event_number,
                'deleted_at' => $event->deleted_at?->toISOString(),
            ],
            'message' => 'Calendar event archived.',
        ]);
    }

    public function messages(CollaborationMessageIndexRequest $request, ListMailboxWorkspace $action): AnonymousResourceCollection|View
    {
        $page = $action->execute($request->user(), $request->validated());

        if (! $request->wantsJson()) {
            $externalAccounts = MailboxAccount::query()
                ->where('user_id', $request->user()->id)
                ->where('company_id', $request->user()->company_id)
                ->where('status', 'active')
                ->orderBy('name')
                ->get();
            $externalContacts = MailboxEmail::query()
                ->whereIn('mailbox_account_id', $externalAccounts->pluck('id'))
                ->latest('received_at')
                ->limit(500)
                ->get(['from_addresses', 'to_addresses', 'cc_addresses'])
                ->flatMap(fn (MailboxEmail $email): array => array_merge(
                    $email->from_addresses ?? [],
                    $email->to_addresses ?? [],
                    $email->cc_addresses ?? [],
                ))
                ->filter(fn (mixed $item): bool => is_array($item) && filter_var($item['email'] ?? null, FILTER_VALIDATE_EMAIL))
                ->unique(fn (array $item): string => strtolower($item['email']))
                ->take(200)
                ->values();
            $internalDrafts = InternalMailboxDispatch::query()
                ->with(['recipients.user', 'attachments'])
                ->where('sender_user_id', $request->user()->id)
                ->whereIn('state', ['draft', 'failed', 'scheduled'])
                ->latest('updated_at')
                ->limit(30)
                ->get();
            $composeInternalDraft = $request->filled('internal_draft')
                ? $internalDrafts->firstWhere('id', $request->integer('internal_draft'))
                : null;
            $composeExternalDraft = $request->filled('external_draft')
                ? MailboxOutboxMessage::query()
                    ->with(['attachments', 'account'])
                    ->where('user_id', $request->user()->id)
                    ->whereIn('mailbox_account_id', $externalAccounts->pluck('id'))
                    ->whereIn('state', ['draft', 'failed', 'scheduled'])
                    ->findOrFail($request->integer('external_draft'))
                : null;
            $page->selectedMessage?->loadMissing(['internalDispatch.attachments','internalDispatch.recipients.user']);
            $composeContext = $this->mailboxComposeContext($request, $page->selectedMessage);
            return view('collaboration.messages.index', [
                'messages' => $page->messages,
                'selectedMessage' => $page->selectedMessage,
                'filters' => $page->filters,
                'companies' => $page->companies,
                'projects' => $page->projects,
                'users' => $page->users,
                'folders' => $page->folders,
                'statuses' => $page->statuses,
                'priorities' => $page->priorities,
                'canCreateMessage' => $page->canCreate,
                'externalAccounts'=>$externalAccounts,
                'externalContacts'=>$externalContacts,
                'internalDrafts'=>$internalDrafts,
                'composeInternalDraft'=>$composeInternalDraft,
                'composeExternalDraft'=>$composeExternalDraft,
                'composeContext'=>$composeContext,
            ]);
        }

        return CollaborationMessageResource::collection($page->messages);
    }

    /** @return array<string, mixed> */
    private function mailboxComposeContext(CollaborationMessageIndexRequest $request, ?CollaborationMessage $selectedMessage): array
    {
        $action = $request->string('compose_action')->toString();
        if (! in_array($action, ['reply', 'reply_all', 'forward'], true)) {
            return [];
        }

        $messageId = $request->integer('compose_message_id') ?: $selectedMessage?->id;
        $message = $messageId
            ? $this->collaborationService->messageIndexQuery($request->user(), ['folder' => 'all', 'message_id' => $messageId])
                ->with(['sender.role', 'recipient.role', 'internalDispatch.recipients.user'])
                ->first()
            : null;

        if (! $message) {
            return [];
        }

        $subject = trim((string) $message->subject);
        if ($action === 'forward') {
            return [
                'action' => 'forward',
                'title' => 'Forward message',
                'subject' => str_starts_with(strtolower($subject), 'fwd:') ? $subject : 'Fwd: '.$subject,
                'body' => "\n\n---------- Forwarded message ----------\nFrom: {$message->sender?->name} <{$message->sender?->email}>\nDate: ".($message->sent_at ?? $message->created_at)?->format('d M Y, h:i A')."\nSubject: {$subject}\n\n{$message->body}",
                'recipients' => [],
                'parent_message_id' => null,
            ];
        }

        $recipients = collect();
        $dispatch = $message->internalDispatch;
        if ($action === 'reply_all' && $dispatch) {
            $recipients = $dispatch->recipients
                ->map(fn ($recipient): array => ['user' => $recipient->user, 'type' => $recipient->recipient_type]);
            $recipients->push(['user' => $dispatch->sender, 'type' => 'to']);
        } else {
            $counterpart = (int) $message->sender_user_id === (int) $request->user()->id
                ? $message->recipient
                : $message->sender;
            $recipients->push(['user' => $counterpart, 'type' => 'to']);
        }

        $recipients = $recipients
            ->filter(fn (array $recipient): bool => $recipient['user'] && (int) $recipient['user']->id !== (int) $request->user()->id)
            ->unique(fn (array $recipient): int => (int) $recipient['user']->id)
            ->values();

        return [
            'action' => $action,
            'title' => $action === 'reply_all' ? 'Reply all' : 'Reply',
            'subject' => str_starts_with(strtolower($subject), 're:') ? $subject : 'Re: '.$subject,
            'body' => '',
            'recipients' => $recipients,
            'parent_message_id' => $message->id,
        ];
    }

    public function exportMessages(
        CollaborationMessageIndexRequest $request,
        ExportMailboxRegister $action,
    ): Response {
        return $action->execute($request->user(), $request->validated(), $request);
    }

    public function storeMessage(StoreCollaborationMessageRequest $request, SendMailboxMessage $action): JsonResponse|RedirectResponse
    {
        $messages = $action->execute($this->command($request));
        $scheduled = $messages->every(fn (CollaborationMessage $message): bool => $message->status === 'scheduled');

        if (! $request->wantsJson()) {
            return redirect()
                ->route('collaboration.messages.index')
                ->with('status', $scheduled ? 'Mailbox message scheduled successfully.' : 'Mailbox message sent successfully.');
        }

        return CollaborationMessageResource::collection(
            $messages,
        )
            ->additional(['message' => $scheduled ? 'Mailbox message scheduled successfully.' : 'Mailbox message sent successfully.'])
            ->response()
            ->setStatusCode(201);
    }

    public function markMessageRead(
        MarkCollaborationMessageReadRequest $request,
        CollaborationMessage $collaborationMessage,
        MarkMailboxMessageRead $action,
    ): CollaborationMessageResource|RedirectResponse {
        $message = $action->execute($collaborationMessage, $this->command($request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('collaboration.messages.index')
                ->with('status', "Mailbox message {$message->message_number} marked as read.");
        }

        return (new CollaborationMessageResource($message))->additional(['message' => 'Mailbox message marked as read.']);
    }

    public function archiveMessage(
        ArchiveCollaborationMessageRequest $request,
        CollaborationMessage $collaborationMessage,
        ArchiveMailboxMessage $action,
    ): CollaborationMessageResource|RedirectResponse {
        $message = $action->execute($collaborationMessage, $this->command($request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('collaboration.messages.index')
                ->with('status', "Mailbox message {$message->message_number} archived.");
        }

        return (new CollaborationMessageResource($message))->additional(['message' => 'Mailbox message archived.']);
    }

    public function cancelScheduledMessage(
        CancelScheduledCollaborationMessageRequest $request,
        CollaborationMessage $collaborationMessage,
        CancelScheduledMailboxMessage $action,
    ): CollaborationMessageResource|RedirectResponse {
        $message = $action->execute($collaborationMessage, $this->command($request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('collaboration.messages.index')
                ->with('status', "Scheduled mailbox message {$message->message_number} cancelled.");
        }

        return (new CollaborationMessageResource($message))->additional(['message' => 'Scheduled mailbox message cancelled.']);
    }

    public function updateMessageCrmLink(
        UpdateCollaborationMessageCrmLinkRequest $request,
        CollaborationMessage $collaborationMessage,
        ChangeMailboxMessageCrmLink $action,
    ): CollaborationMessageResource {
        return (new CollaborationMessageResource($action->execute($collaborationMessage, $this->command($request))))
            ->additional(['message' => $request->validated('action') === 'unlink' ? 'Mailbox message unlinked from CRM record.' : 'Mailbox message linked to CRM record.']);
    }

    public function updateMessageState(
        UpdateCollaborationMessageStateRequest $request,
        CollaborationMessage $collaborationMessage,
        ChangeMailboxMessageState $action,
    ): CollaborationMessageResource {
        return (new CollaborationMessageResource($action->execute($collaborationMessage, $this->command($request))))
            ->additional(['message' => 'Mailbox message state updated.']);
    }

    public function updateMessageReaction(
        UpdateCollaborationMessageReactionRequest $request,
        CollaborationMessage $collaborationMessage,
        ChangeMailboxMessageReaction $action,
    ): CollaborationMessageResource {
        return (new CollaborationMessageResource($action->execute($collaborationMessage, $this->command($request))))
            ->additional(['message' => 'Mailbox message reaction updated.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function transferRequestPayload(WorkTaskTransferRequest $transferRequest): array
    {
        return [
            'id' => $transferRequest->id,
            'status' => $transferRequest->status,
            'reason' => $transferRequest->reason,
            'requested_at' => $transferRequest->requested_at?->toISOString(),
            'task' => $transferRequest->relationLoaded('workTask') && $transferRequest->workTask ? [
                'id' => $transferRequest->workTask->id,
                'task_number' => $transferRequest->workTask->task_number,
                'title' => $transferRequest->workTask->title,
            ] : null,
            'requested_by' => $transferRequest->relationLoaded('requestedBy') && $transferRequest->requestedBy ? [
                'id' => $transferRequest->requestedBy->id,
                'name' => $transferRequest->requestedBy->name,
                'email' => $transferRequest->requestedBy->email,
            ] : null,
            'from_user' => $transferRequest->relationLoaded('fromUser') && $transferRequest->fromUser ? [
                'id' => $transferRequest->fromUser->id,
                'name' => $transferRequest->fromUser->name,
                'email' => $transferRequest->fromUser->email,
            ] : null,
            'to_user' => $transferRequest->relationLoaded('toUser') && $transferRequest->toUser ? [
                'id' => $transferRequest->toUser->id,
                'name' => $transferRequest->toUser->name,
                'email' => $transferRequest->toUser->email,
            ] : null,
        ];
    }

    private function command(FormRequest $request): CollaborationCommandData
    {
        return new CollaborationCommandData(
            attributes: $request->validated(),
            actor: $request->user(),
            request: $request,
        );
    }

    private function chatCommand(FormRequest $request): ChatCommandData
    {
        return new ChatCommandData(
            attributes: $request->validated(),
            actor: $request->user(),
            request: $request,
        );
    }

}
