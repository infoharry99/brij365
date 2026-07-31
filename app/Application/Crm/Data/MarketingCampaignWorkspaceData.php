<?php

namespace App\Application\Crm\Data;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final readonly class MarketingCampaignWorkspaceData
{
    /** @param array<string, mixed> $filters @param array<string, int|float> $summary */
    public function __construct(
        public LengthAwarePaginator $campaigns,
        public array $filters,
        public Collection $companies,
        public Collection $projects,
        public array $summary,
        public array $statuses,
        public array $channels,
        public bool $canCreate,
    ) {}
}
