<?php
 
namespace App\Policies;
 
use App\Models\CalendarEvent;
use App\Models\User;
use App\Services\Security\CompanyScopeService;
 
class CalendarEventPolicy
{
    // Who can open the calendar page at all
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('collaboration.view')
            || $user->hasPermission('collaboration.manage')
            || $user->hasPermission('employee.self_service');
    }
 
    // Who can see ALL events (admin bypass)
    public function viewAll(User $user): bool
    {
        return $user->hasPermission('collaboration.manage')
            || $user->hasPermission('calendar.viewAll');
    }
 
    // Who can view a specific event — mirrors baseQuery visibility
    public function view(User $user, CalendarEvent $calendarEvent): bool
    {
        // Must be able to open the calendar page at all
        if (! $this->viewAny($user)) {
            return false;
        }
 
        // Must be same company
        if (! $this->sameCompany($user, $calendarEvent)) {
            return false;
        }
 
        // Managers can see everything
        if ($user->hasPermission('collaboration.manage')) {
            return true;
        }
 
        // Everyone else: own events or invited events only
        return (int) $calendarEvent->organizer_user_id === (int) $user->id
            || $this->isAttendee($user, $calendarEvent);
    }
 
    public function create(User $user): bool
    {
        return $user->hasPermission('collaboration.manage')
            || $user->hasPermission('employee.self_service');
    }
 
    public function update(User $user, CalendarEvent $calendarEvent): bool
    {
        return $this->sameCompany($user, $calendarEvent)
            && $calendarEvent->status !== 'cancelled'
            && (
                $user->hasPermission('collaboration.manage')
                || (int) $calendarEvent->organizer_user_id === (int) $user->id
            );
    }
 
    public function cancel(User $user, CalendarEvent $calendarEvent): bool
    {
        return $this->update($user, $calendarEvent);
    }
 
    public function complete(User $user, CalendarEvent $calendarEvent): bool
    {
        return $this->update($user, $calendarEvent);
    }
 
    public function archive(User $user, CalendarEvent $calendarEvent): bool
    {
        return $this->sameCompany($user, $calendarEvent)
            && (
                $user->hasPermission('collaboration.manage')
                || (int) $calendarEvent->organizer_user_id === (int) $user->id
            );
    }
 
    // ── Private helpers ───────────────────────────────────────────────
 
    private function sameCompany(User $user, CalendarEvent $calendarEvent): bool
    {
        return app(CompanyScopeService::class)->allows($user, $calendarEvent->company_id);
    }
 
    // Check live DB attendeeRecords (not JSON cast column)
    private function isAttendee(User $user, CalendarEvent $calendarEvent): bool
    {
        return $calendarEvent->attendeeRecords()
            ->where('user_id', $user->id)
            ->where('response', '!=', 'declined')
            ->exists();
    }
}