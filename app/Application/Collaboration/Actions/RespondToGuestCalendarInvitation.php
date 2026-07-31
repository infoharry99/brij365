<?php
namespace App\Application\Collaboration\Actions;
use App\Domain\Collaboration\Services\CalendarInvitationManager;
use App\Models\CalendarEvent;
use App\Models\CalendarEventAttendee;
final class RespondToGuestCalendarInvitation { public function __construct(private readonly CalendarInvitationManager $manager) {} public function execute(CalendarEventAttendee $attendee,string $response): CalendarEvent { return $this->manager->respondGuest($attendee,$response); } }
