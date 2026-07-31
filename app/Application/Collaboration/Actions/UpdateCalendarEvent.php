<?php

namespace App\Application\Collaboration\Actions;

use App\Application\Collaboration\Data\CollaborationCommandData;
use App\Domain\Collaboration\Services\CalendarInvitationManager;
use App\Events\Calendar\CalendarEventChanged;
use App\Models\CalendarEvent;
use App\Services\Collaboration\CollaborationService;

final class UpdateCalendarEvent
{
    public function __construct(
        private readonly CollaborationService $collaboration,
        private readonly CalendarInvitationManager $invitations,
    ) {}

    public function execute(CalendarEvent $event, CollaborationCommandData $command): CalendarEvent
    {
        // Snapshot guest list before update to detect new guests added
        $guestsBefore = $event->attendeeRecords()
            ->where('attendee_type', 'guest')
            ->pluck('email')
            ->map(fn ($e) => strtolower($e))
            ->sort()
            ->values();

        $event = $this->collaboration->updateCalendarEvent(
            $event,
            $command->attributes,
            $command->actor,
            $command->request,
        );

        CalendarEventChanged::dispatch($event, 'updated');

        // Notify guests after update
        $event->loadMissing(['attendeeRecords', 'organizer', 'project']);
        $guests = $event->attendeeRecords->where('attendee_type', 'guest');

        if ($guests->isNotEmpty()) {
            $guestsAfter = $guests
                ->pluck('email')
                ->map(fn ($e) => strtolower($e))
                ->sort()
                ->values();

            // New guests added → send 'request' (invitation)
            // Existing guests whose event changed → send 'update'
            $newGuests = $guestsAfter->diff($guestsBefore);
            $existingGuests = $guestsAfter->intersect($guestsBefore);

            // Send invitation to newly added guests
            if ($newGuests->isNotEmpty()) {
                foreach ($guests->whereIn('email', $newGuests->all()) as $attendee) {
                    \Illuminate\Support\Facades\Mail::to($attendee->email)
                        ->queue(new \App\Mail\CalendarInvitationMail($event, $attendee, 'request'));
                    $attendee->forceFill(['last_notified_at' => now()])->save();
                }
            }

            // Send update notification to existing guests
            if ($existingGuests->isNotEmpty()) {
                foreach ($guests->whereIn('email', $existingGuests->all()) as $attendee) {
                    \Illuminate\Support\Facades\Mail::to($attendee->email)
                        ->queue(new \App\Mail\CalendarInvitationMail($event, $attendee, 'update'));
                    $attendee->forceFill(['last_notified_at' => now()])->save();
                }
            }
        }

        return $event;
    }
}