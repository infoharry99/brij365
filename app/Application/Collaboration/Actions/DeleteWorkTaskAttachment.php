<?php

namespace App\Application\Collaboration\Actions;

use App\Models\WorkTaskAttachment;
use Illuminate\Support\Facades\Storage;

final class DeleteWorkTaskAttachment
{
    public function execute(WorkTaskAttachment $attachment): void
    {
        Storage::disk($attachment->disk)->delete($attachment->path);
        $attachment->delete();
    }
}
