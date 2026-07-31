<?php

namespace App\Domain\Collaboration\Services;

use App\Events\Calendar\CalendarEventChanged;
use App\Models\CalendarEvent;
use App\Models\CalendarEventAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class CalendarAttachmentManager
{
    public function store(CalendarEvent $event, User $actor, UploadedFile $file): CalendarEventAttachment
    {
        $disk = 'local';
        $name = Str::uuid().'.'.strtolower($file->getClientOriginalExtension() ?: 'bin');
        $path = $file->storeAs('calendar/'.$event->company_id.'/'.$event->id, $name, $disk);
        $attachment = $event->attachments()->create([
            'uploaded_by_user_id'=>$actor->id,'disk'=>$disk,'path'=>$path,'original_name'=>$file->getClientOriginalName(),
            'mime_type'=>$file->getMimeType() ?: 'application/octet-stream','size_bytes'=>$file->getSize(),
            'checksum_sha256'=>hash_file('sha256',$file->getRealPath()),
            'scan_status'=>(bool) config('builder360.calendar.attachments_require_scan', false) ? 'pending' : 'clean',
        ]);
        CalendarEventChanged::dispatch($event->refresh(), 'attachment_added');
        return $attachment;
    }

    public function remove(CalendarEventAttachment $attachment): void
    {
        $event = $attachment->event;
        Storage::disk($attachment->disk)->delete($attachment->path);
        $attachment->delete();
        CalendarEventChanged::dispatch($event->refresh(), 'attachment_removed');
    }
}
