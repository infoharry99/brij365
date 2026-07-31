<?php
namespace App\Http\Controllers\Collaboration;
use App\Application\Collaboration\Actions\RespondToCalendarInvitation;
use App\Application\Collaboration\Actions\RespondToGuestCalendarInvitation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Collaboration\RespondCalendarInvitationRequest;
use App\Http\Requests\Collaboration\RespondGuestCalendarInvitationRequest;
use App\Models\CalendarEvent;
use App\Models\CalendarEventAttendee;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
final class CalendarInvitationController extends Controller {
    public function respond(RespondCalendarInvitationRequest $request, CalendarEvent $calendarEvent, RespondToCalendarInvitation $action): RedirectResponse { $action->execute($calendarEvent,$request->user(),$request->validated('response')); return back()->with('status','Your invitation response was saved.'); }
    public function showGuest(CalendarEventAttendee $calendarEventAttendee): View { abort_unless(request()->hasValidSignature() && $calendarEventAttendee->attendee_type==='guest',403); return view('collaboration.calendar-events.guest-invitation',['attendee'=>$calendarEventAttendee->load('event.organizer')]); }
    public function respondGuest(RespondGuestCalendarInvitationRequest $request, CalendarEventAttendee $calendarEventAttendee, RespondToGuestCalendarInvitation $action): RedirectResponse { $action->execute($calendarEventAttendee,$request->validated('response')); return back()->with('status','Your response was saved.'); }
}
