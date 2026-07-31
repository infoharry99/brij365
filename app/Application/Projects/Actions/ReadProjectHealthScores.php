<?php

namespace App\Application\Projects\Actions;

use App\Domain\Projects\Services\ProjectHealthScoreReader;

final readonly class ReadProjectHealthScores
{
    public function __construct(private ProjectHealthScoreReader $reader) {}

    /**
     * @param array<int, int> $projectIds
     * @return array<int, \App\Application\Projects\Data\ProjectHealthScoreData>
     */
    public function execute(int $companyId, array $projectIds): array
    {
        return $this->reader->forProjects($companyId, $projectIds);
    }
}
