<?php

namespace App\Application\Collaboration\Actions;

use App\Models\User;
use App\Models\WorkTask;
use App\Models\WorkTaskAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class StoreWorkTaskAttachment
{
    public function execute(WorkTask $task, UploadedFile $file, User $actor): WorkTaskAttachment
    {
        return DB::transaction(function () use ($task, $file, $actor): WorkTaskAttachment {
            $path = 'tasks/'.$task->company_id.'/'.$task->id;
            $storedName = Str::uuid()->toString().'.'.($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin');
            $storedPath = $file->storeAs($path, $storedName, 'local');

            return WorkTaskAttachment::create([
                'work_task_id' => $task->id,
                'company_id' => $task->company_id,
                'uploaded_by_user_id' => $actor->id,
                'disk' => 'local',
                'path' => $storedPath,
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'size_bytes' => $file->getSize() ?: 0,
                'checksum_sha256' => hash_file('sha256', $file->getRealPath()),
                'scan_status' => config('builder360.task_attachments.require_scan', false) ? 'pending' : 'clean',
                'metadata' => ['storage_visibility' => 'private', 'scan_adapter' => config('builder360.task_attachments.require_scan', false) ? 'pending' : 'local-validation'],
            ]);
        });
    }
}
