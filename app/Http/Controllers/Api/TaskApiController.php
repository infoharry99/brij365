<?php

namespace App\Http\Controllers\Api;

use App\Application\Collaboration\Actions\AddWorkTaskComment;
use App\Application\Collaboration\Data\CollaborationCommandData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Collaboration\StoreWorkTaskCommentRequest;
use App\Http\Resources\WorkTaskResource;
use App\Models\WorkTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskApiController extends Controller
{
    /**
     * GET /api/tasks/{workTask}
     * Fetch complete task details with comments, assignees, createdBy & attachments for mobile popup.
     */
    public function show(Request $request, WorkTask $workTask): JsonResponse
    {
        $this->authorize('view', $workTask);

        $workTask->load([
            'createdBy:id,name,email,profile_photo_path',
            'assignedTo:id,name,email,profile_photo_path',
            'assignees:id,name,email,profile_photo_path',
            'comments.author:id,name,email,profile_photo_path',
            'attachments',
            'subtasks',
        ]);

        return response()->json([
            'message' => 'Task details fetched successfully.',
            'data' => (new WorkTaskResource($workTask))->resolve($request),
        ]);
    }

    /**
     * POST /api/tasks/{workTask}/comments
     * Add a comment directly to a task from mobile popup.
     */
    public function storeComment(StoreWorkTaskCommentRequest $request, WorkTask $workTask, AddWorkTaskComment $action): JsonResponse
    {
        $command = new CollaborationCommandData($request->validated(), $request->user(), $request);
        $task = $action->execute($workTask, $command);

        $task->load([
            'createdBy:id,name,email,profile_photo_path',
            'assignedTo:id,name,email,profile_photo_path',
            'assignees:id,name,email,profile_photo_path',
            'comments.author:id,name,email,profile_photo_path',
            'attachments',
        ]);

        return response()->json([
            'message' => "Comment added to task {$task->task_number}.",
            'data' => (new WorkTaskResource($task))->resolve($request),
        ], 201);
    }
}
