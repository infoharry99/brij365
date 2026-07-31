<?php

namespace App\Application\Crm\Data;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final readonly class LeadActivityWorkspaceData
{
    /** @param array<string, mixed> $filters */
    public function __construct(
        public LengthAwarePaginator $activities,
        public array $filters,
        public Collection $projects,
        public Collection $campaigns,
        public Collection $leads,
        public array $types,
        public bool $canCreate,
    ) {}
}
