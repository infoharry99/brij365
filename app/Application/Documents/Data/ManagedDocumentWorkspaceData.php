<?php

namespace App\Application\Documents\Data;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final readonly class ManagedDocumentWorkspaceData
{
    public function __construct(
        public LengthAwarePaginator $documents,
        public array $filters,
        public Collection $categories,
        public Collection $projects,
        public Collection $bookings,
        public Collection $customers,
        public Collection $employees,
        public array $ownerTypes,
        public array $statuses,
        public array $abilities,
    ) {}

    public function toView(): array
    {
        return array_merge(get_object_vars($this), [
            'canCreateDocument' => $this->abilities['canCreateDocument'] ?? false,
        ]);
    }
}
