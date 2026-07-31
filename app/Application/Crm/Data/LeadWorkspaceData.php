<?php

namespace App\Application\Crm\Data;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final readonly class LeadWorkspaceData
{
    /** @param array<string,mixed> $filters */
    public function __construct(
        public LengthAwarePaginator $leads,
        public array $filters,
        public Collection $companies,
        public Collection $projects,
        public Collection $campaigns,
        public Collection $partners,
        public Collection $sources,
        public array $stages,
        public array $statuses,
        public bool $canCreate,
    ) {}
}
