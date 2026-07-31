<?php

namespace App\Application\Inventory\Data;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final readonly class UnitPricingWorkspaceData
{
    /** @param array<string, mixed> $filters */
    public function __construct(
        public LengthAwarePaginator $versions,
        public array $filters,
        public Collection $projects,
        public Collection $units,
        public array $statuses,
        public bool $canCreate,
    ) {}
}
