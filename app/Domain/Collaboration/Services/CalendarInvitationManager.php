<?php

namespace App\Domain\Collaboration\Services;

use App\Events\Calendar\CalendarEventChanged;
use App\Mail\CalendarInvitationMail;
use App\Mail\CalendarResponseNotificationMail;
use App\Models\CalendarEvent;
use App\Models\CalendarEventAttendee;
use App\Models\User;
use App\Services\Notifications\NotificationCenterService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

final class CalendarInvitationManager
{
    public function __construct(private readonly NotificationCenterService $notifications) {}

    public function respondInternal(CalendarEvent $event, User $actor, string $response): CalendarEvent
    {
        return DB::transaction(function () use ($event, $actor, $response): CalendarEvent {
            $locked = CalendarEvent::query()->lockForUpdate()->findOrFail($event->id);
            $attendee = $locked->attendeeRecords()->where('user_id', $actor->id)->first();
            if (! $attendee) throw ValidationException::withMessages(['response' => 'You are not an attendee of this event.']);
            $this->recordResponse($attendee, $response);
            $this->refreshLegacyAttendees($locked);
            $locked->increment('lock_version');
            $this->notifyOrganizer($locked, $attendee, $actor);
            CalendarEventChanged::dispatch($locked->refresh(), 'attendee_response_changed');
            return $locked->load(['attendeeRecords.user', 'organizer']);
        });
    }

    public function respondGuest(CalendarEventAttendee $attendee, string $response): CalendarEvent
    {
        return DB::transaction(function () use ($attendee, $response): CalendarEvent {
            $locked = CalendarEventAttendee::query()->lockForUpdate()->findOrFail($attendee->id);
            abort_unless($locked->attendee_type === 'guest', 404);
            $this->recordResponse($locked, $response);
            $event = CalendarEvent::query()->lockForUpdate()->findOrFail($locked->calendar_event_id);
            $this->refreshLegacyAttendees($event);
            $event->increment('lock_version');
            $this->notifyOrganizer($event, $locked, null);
            CalendarEventChanged::dispatch($event->refresh(), 'guest_response_changed');
            return $event;
        });
    }

    public function sendExternal(CalendarEvent $event, string $change = 'request'): int
    {
        $sent = 0;
        $event->loadMissing(['attendeeRecords', 'organizer', 'project']);
    
        foreach ($event->attendeeRecords->where('attendee_type', 'guest') as $attendee) {
            try {
                Mail::to($attendee->email)->send(new CalendarInvitationMail($event, $attendee, $change));
                $attendee->forceFill(['last_notified_at' => now()])->save();
                $sent++;
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Failed sending calendar guest invitation to '.$attendee->email.': '.$e->getMessage());
            }
        }
        return $sent;
    }
    
    private function recordResponse(CalendarEventAttendee $attendee, string $response): void
    {
        if (! in_array($response, ['accepted','tentative','declined'], true)) {
            throw ValidationException::withMessages(['response' => 'Choose Accept, Tentative, or Decline.']);
        }
        $attendee->forceFill(['response' => $response, 'responded_at' => now()])->save();
    }

    private function refreshLegacyAttendees(CalendarEvent $event): void
    {
        $event->forceFill(['attendees' => $event->attendeeRecords()->get()->map(fn (CalendarEventAttendee $a) => [
            'user_id'=>$a->user_id,'name'=>$a->name,'email'=>$a->email,'response'=>$a->response,'attendee_type'=>$a->attendee_type,
        ])->all()])->saveQuietly();
    }

    private function notifyOrganizer(CalendarEvent $event, CalendarEventAttendee $attendee, ?User $actor): void
    {
        $event->loadMissing('organizer');

        if ($event->organizer) {
            $this->notifications->sendToUser($event->organizer, [
                'category' => 'calendar', 'severity' => 'info', 'title' => $attendee->name.' '.$attendee->response.' '.$event->title,
                'body' => 'Invitation response updated.', 'action_url' => route('collaboration.calendar-events.index', ['event_id' => $event->id], false),
                'payload' => ['calendar_event_id' => $event->id, 'response' => $attendee->response],
            ], $actor, $event);

            if (filter_var($event->organizer->email, FILTER_VALIDATE_EMAIL)) {
                try {
                    Mail::to($event->organizer->email)->send(new CalendarResponseNotificationMail($event, $attendee, 'organizer'));
                } catch (\Throwable $e) {
                    Log::error('Failed sending RSVP update email to organizer: '.$e->getMessage());
                }
            }
        }

        if (filter_var($attendee->email, FILTER_VALIDATE_EMAIL)) {
            try {
                Mail::to($attendee->email)->send(new CalendarResponseNotificationMail($event, $attendee, 'attendee'));
            } catch (\Throwable $e) {
                Log::error('Failed sending RSVP confirmation email to attendee: '.$e->getMessage());
            }
        }
    }
}
