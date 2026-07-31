<?php

namespace App\Listeners\Calendar;

use App\Domain\Collaboration\Services\CalendarInvitationManager;
use App\Events\Calendar\CalendarEventChanged;
use Illuminate\Contracts\Queue\ShouldQueue;

final class SendCalendarInvitations implements ShouldQueue
{
    public function __construct(private readonly CalendarInvitationManager $invitations) {}
    public function handle(CalendarEventChanged $event): void
    {
        if (in_array($event->change, ['created','updated','cancelled'], true)) {
            $this->invitations->sendExternal($event->event, $event->change === 'cancelled' ? 'cancel' : ($event->change === 'updated' ? 'update' : 'request'));
        }
    }
}
