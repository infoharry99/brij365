<?php

namespace App\Application\Collaboration\Actions;

use App\Application\Collaboration\Data\CollaborationCommandData;
use App\Domain\Collaboration\Services\CalendarInvitationManager;
use App\Events\Calendar\CalendarEventChanged;
use App\Models\CalendarEvent;
use App\Services\Collaboration\CollaborationService;

final class CreateCalendarEvent
{
    public function __construct(
        private readonly CollaborationService $collaboration,
        private readonly CalendarInvitationManager $invitations,
    ) {}

    public function execute(CollaborationCommandData $command): CalendarEvent
    {
        $event = $this->collaboration->createCalendarEvent(
            $command->attributes,
            $command->actor,
            $command->request,
        );

        CalendarEventChanged::dispatch($event, 'created');

        // Send invitation emails to external guests (attendee_type = 'guest')
        $event->loadMissing(['attendeeRecords', 'organizer', 'project']);
        if ($event->attendeeRecords->where('attendee_type', 'guest')->isNotEmpty()) {
            $this->invitations->sendExternal($event, 'request');
        }

        return $event;
    }
}