<?php

namespace App\Http\Controllers\Collaboration;

use App\Application\Collaboration\Actions\DeleteWorkTaskAttachment;
use App\Application\Collaboration\Actions\DuplicateWorkTask;
use App\Application\Collaboration\Actions\StoreWorkTaskAttachment;
use App\Domain\Collaboration\Services\TaskCompletionApprovalService;
use App\Domain\Collaboration\Services\TaskRecurrenceService;
use App\Application\Collaboration\Data\CollaborationCommandData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Collaboration\DeleteWorkTaskAttachmentRequest;
use App\Http\Requests\Collaboration\DecideWorkTaskCompletionRequest;
use App\Http\Requests\Collaboration\DuplicateWorkTaskRequest;
use App\Http\Requests\Collaboration\StoreWorkTaskAttachmentRequest;
use App\Http\Requests\Collaboration\UpdateWorkTaskRecurrenceRequest;
use App\Http\Requests\Collaboration\ReopenWorkTaskRequest;
use App\Models\WorkTask;
use App\Models\WorkTaskAttachment;
use App\Models\WorkTaskCompletionApproval;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WorkTaskLifecycleController extends Controller
{
    public function duplicate(DuplicateWorkTaskRequest $request, WorkTask $workTask, DuplicateWorkTask $action): RedirectResponse
    {
        $task = $action->execute($workTask, new CollaborationCommandData($request->validated(), $request->user(), $request));

        return redirect()->route('collaboration.tasks.index', ['scope' => 'all', 'task_id' => $task->id])
            ->with('status', 'Task duplicated successfully.');
    }

    public function storeAttachment(StoreWorkTaskAttachmentRequest $request, WorkTask $workTask, StoreWorkTaskAttachment $action): RedirectResponse
    {
        $action->execute($workTask, $request->file('attachment'), $request->user());

        return back()->with('status', 'Task attachment uploaded successfully.');
    }

    public function destroyAttachment(DeleteWorkTaskAttachmentRequest $request, WorkTask $workTask, WorkTaskAttachment $workTaskAttachment, DeleteWorkTaskAttachment $action): RedirectResponse
    {
        $action->execute($workTaskAttachment);

        return back()->with('status', 'Task attachment removed.');
    }

    public function downloadAttachment(Request $request, WorkTask $workTask, WorkTaskAttachment $workTaskAttachment): StreamedResponse
    {
        abort_unless((int) $workTaskAttachment->work_task_id === (int) $workTask->id && $request->user()?->can('view', $workTask), 403);

        return Storage::disk($workTaskAttachment->disk)->download($workTaskAttachment->path, $workTaskAttachment->original_filename);
    }

    public function previewAttachment(Request $request, WorkTask $workTask, WorkTaskAttachment $workTaskAttachment)
    {
        abort_unless((int) $workTaskAttachment->work_task_id === (int) $workTask->id && $request->user()?->can('view', $workTask), 403);
        abort_if(in_array($workTaskAttachment->scan_status, ['blocked', 'failed'], true), 422, 'This attachment is not available.');
        abort_unless(
            str_starts_with($workTaskAttachment->mime_type, 'image/')
            || $workTaskAttachment->mime_type === 'application/pdf',
            415
        );

        return Storage::disk($workTaskAttachment->disk)->response(
            $workTaskAttachment->path,
            $workTaskAttachment->original_filename,
            ['Content-Type' => $workTaskAttachment->mime_type, 'Cache-Control' => 'private, no-store'],
        );
    }

    public function updateRecurrence(UpdateWorkTaskRecurrenceRequest $request, WorkTask $workTask, TaskRecurrenceService $service): RedirectResponse
    {
        $rule = $workTask->recurrenceRule;
        abort_unless($rule, 404);
        $request->string('action')->toString() === 'skip' ? $service->skipNext($rule) : $service->cancel($rule);

        return back()->with('status', $request->string('action')->toString() === 'skip' ? 'The next recurring task was skipped.' : 'Future recurring tasks were cancelled.');
    }

    public function decideCompletion(DecideWorkTaskCompletionRequest $request, WorkTaskCompletionApproval $workTaskCompletionApproval, TaskCompletionApprovalService $service): RedirectResponse
    {
        $task = $service->decide($workTaskCompletionApproval, $request->user(), $request->string('decision')->toString(), $request->string('note')->toString(), $request);
        return redirect()->route('collaboration.tasks.index', ['scope' => 'pending', 'task_id' => $task->id])->with('status', 'Completion request updated successfully.');
    }

    public function reopen(ReopenWorkTaskRequest $request, WorkTask $workTask, TaskCompletionApprovalService $service): RedirectResponse
    {
        $task = $service->reopen($workTask, $request->user(), $request->string('note')->toString(), $request);
        return redirect()->route('collaboration.tasks.index', ['scope' => 'all', 'task_id' => $task->id])->with('status', 'Task reopened successfully.');
    }
}
