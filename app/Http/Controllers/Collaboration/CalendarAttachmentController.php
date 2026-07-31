<?php
namespace App\Http\Controllers\Collaboration;
use App\Application\Collaboration\Actions\RemoveCalendarEventAttachment;
use App\Application\Collaboration\Actions\StoreCalendarEventAttachment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Collaboration\StoreCalendarEventAttachmentRequest;
use App\Models\CalendarEvent;
use App\Models\CalendarEventAttachment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
final class CalendarAttachmentController extends Controller {
    public function store(StoreCalendarEventAttachmentRequest $request,CalendarEvent $calendarEvent,StoreCalendarEventAttachment $action): RedirectResponse { $action->execute($calendarEvent,$request->user(),$request->file('attachment')); return back()->with('status','Attachment uploaded.'); }
    public function preview(CalendarEvent $calendarEvent,CalendarEventAttachment $calendarEventAttachment): StreamedResponse { $this->authorize('view',$calendarEvent); $this->guard($calendarEvent,$calendarEventAttachment); abort_if($calendarEventAttachment->scan_status==='blocked',403); abort_unless(str_starts_with($calendarEventAttachment->mime_type,'image/')||$calendarEventAttachment->mime_type==='application/pdf',415); return Storage::disk($calendarEventAttachment->disk)->response($calendarEventAttachment->path,$calendarEventAttachment->original_name,['Content-Type'=>$calendarEventAttachment->mime_type,'Content-Disposition'=>'inline']); }
    public function download(CalendarEvent $calendarEvent,CalendarEventAttachment $calendarEventAttachment): StreamedResponse { $this->authorize('view',$calendarEvent); $this->guard($calendarEvent,$calendarEventAttachment); abort_if($calendarEventAttachment->scan_status==='blocked',403); return Storage::disk($calendarEventAttachment->disk)->download($calendarEventAttachment->path,$calendarEventAttachment->original_name); }
    public function destroy(CalendarEvent $calendarEvent,CalendarEventAttachment $calendarEventAttachment,RemoveCalendarEventAttachment $action): RedirectResponse { $this->authorize('update',$calendarEvent); $this->guard($calendarEvent,$calendarEventAttachment); $action->execute($calendarEventAttachment); return back()->with('status','Attachment removed.'); }
    private function guard(CalendarEvent $event,CalendarEventAttachment $attachment): void { abort_unless((int)$attachment->calendar_event_id===(int)$event->id,404); }
}
