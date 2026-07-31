<?php

namespace App\Application\Collaboration\Actions;

use App\Application\Collaboration\Data\CollaborationCommandData;
use App\Models\CalendarEvent;
use App\Services\Collaboration\CollaborationService;
use App\Events\Calendar\CalendarEventChanged;

final class CompleteCalendarEvent
{
    public function __construct(private readonly CollaborationService $collaboration) {}

    public function execute(CalendarEvent $event, CollaborationCommandData $command): CalendarEvent
    {
        $event = $this->collaboration->completeCalendarEvent($event, $command->attributes, $command->actor, $command->request);
        CalendarEventChanged::dispatch($event, 'completed');
        return $event;
    }
}
