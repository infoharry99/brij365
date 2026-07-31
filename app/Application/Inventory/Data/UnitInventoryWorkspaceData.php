<?php

namespace App\Application\Inventory\Data;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final readonly class UnitInventoryWorkspaceData
{
    /** @param array<string, mixed> $filters */
    public function __construct(
        public LengthAwarePaginator $units,
        public array $filters,
        public Collection $projects,
        public array $unitTypes,
        public array $statuses,
        public array $summary,
    ) {}
}
