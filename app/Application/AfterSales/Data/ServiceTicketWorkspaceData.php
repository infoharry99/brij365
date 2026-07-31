<?php

namespace App\Application\AfterSales\Data;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final readonly class ServiceTicketWorkspaceData
{
    public function __construct(
        public LengthAwarePaginator $tickets,
        public array $filters,
        public Collection $projects,
        public Collection $bookings,
        public Collection $customers,
        public Collection $assignees,
        public array $statuses,
        public array $priorities,
        public array $categories,
        public array $sources,
        public array $abilities,
        public bool $isBuyerPortalUser,
    ) {}

    public function toView(): array
    {
        return array_merge(get_object_vars($this), [
            'canCreateTicket' => $this->abilities['canCreateTicket'] ?? false,
        ]);
    }
}
