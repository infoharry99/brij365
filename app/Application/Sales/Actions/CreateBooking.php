<?php

namespace App\Application\Sales\Actions;

use App\Application\Sales\Data\SalesCommandData;
use App\Models\Booking;
use App\Services\Sales\BookingService;

final class CreateBooking
{
    public function __construct(private readonly BookingService $bookings) {}

    public function execute(SalesCommandData $command): Booking
    {
        return $this->bookings->create($command->attributes, $command->actor, $command->request);
    }
}
