<?php
namespace App\Application\Collaboration\Actions;
use App\Domain\Collaboration\Services\CalendarInvitationManager;
use App\Models\CalendarEvent;
use App\Models\User;
final class RespondToCalendarInvitation { public function __construct(private readonly CalendarInvitationManager $manager) {} public function execute(CalendarEvent $event, User $actor, string $response): CalendarEvent { return $this->manager->respondInternal($event,$actor,$response); } }
