<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

class BookingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('booking.view')
            || $user->hasPermission('booking.manage')
            || $user->hasPermission('buyer.view');
    }

    public function view(User $user, Booking $booking): bool
    {
        if ($user->hasPermission('booking.view') || $user->hasPermission('booking.manage')) {
            return app(CompanyScopeService::class)->allows($user, $booking->company_id);
        }

        if ($user->hasPermission('buyer.view')) {
            return $booking->customer()->where('portal_user_id', $user->id)->exists();
        }

        return $user->hasPermission('partner.portal')
            && $booking->partner?->email === $user->email;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('booking.manage');
    }
}
