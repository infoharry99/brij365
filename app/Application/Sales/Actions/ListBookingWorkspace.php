<?php

namespace App\Application\Sales\Actions;

use App\Application\Sales\Data\BookingWorkspaceData;
use App\Domain\Sales\Services\BookingRegister;
use App\Models\Booking;
use App\Models\User;

final class ListBookingWorkspace
{
    public function __construct(private readonly BookingRegister $register) {}

    /** @param array<string, mixed> $filters */
    public function execute(User $user, array $filters): BookingWorkspaceData
    {
        return new BookingWorkspaceData(
            bookings: $this->register->bookings($user, $filters),
            filters: $filters,
            bookableUnits: $this->register->bookableUnits($user),
            leads: $this->register->leads($user),
            projects: $this->register->projects($user),
            customers: $this->register->customers($user),
            partners: $this->register->partners(),
            statuses: ['draft' => 'Draft', 'confirmed' => 'Confirmed', 'cancelled' => 'Cancelled'],
            canCreate: $user->can('create', Booking::class),
        );
    }
}
