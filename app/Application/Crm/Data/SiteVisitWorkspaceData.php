<?php

namespace App\Application\Crm\Data;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final readonly class SiteVisitWorkspaceData
{
    /** @param array<string,mixed> $filters */
    public function __construct(
        public LengthAwarePaginator $visits,
        public array $filters,
        public Collection $leads,
        public Collection $assignees,
        public array $visitModes,
        public array $statuses,
        public array $outcomes,
        public bool $canSchedule,
    ) {}
}
