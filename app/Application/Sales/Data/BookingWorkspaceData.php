<?php

namespace App\Application\Sales\Data;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final readonly class BookingWorkspaceData
{
    /** @param array<string, mixed> $filters */
    public function __construct(
        public LengthAwarePaginator $bookings,
        public array $filters,
        public Collection $bookableUnits,
        public Collection $leads,
        public Collection $projects,
        public Collection $customers,
        public Collection $partners,
        public array $statuses,
        public bool $canCreate,
    ) {}
}
