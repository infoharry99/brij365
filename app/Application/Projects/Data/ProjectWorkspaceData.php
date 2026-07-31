<?php

namespace App\Application\Projects\Data;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final readonly class ProjectWorkspaceData
{
    /** @param array<string, mixed> $filters @param array<int, ProjectHealthScoreData> $healthScores */
    public function __construct(
        public LengthAwarePaginator $projects,
        public array $filters,
        public Collection $companies,
        public Collection $branches,
        public Collection $assignableUsers,
        public Collection $employees,
        public array $statuses,
        public array $projectTypes,
        public array $accessLevels,
        public bool $canCreate,
        public bool $canManageTeam,
        public array $healthScores,
    ) {}
}
