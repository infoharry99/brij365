<?php

namespace App\Application\Crm\Data;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final readonly class LeadQualificationWorkspaceData
{
    /** @param array<string,mixed> $filters @param array<int,mixed> $leadScores */
    public function __construct(
        public LengthAwarePaginator $qualifications,
        public array $filters,
        public Collection $leads,
        public array $rules,
        public array $statuses,
        public bool $canQualify,
        public bool $canManageScoring,
        public string $scoringUrl,
        public array $leadScores,
    ) {}
}
