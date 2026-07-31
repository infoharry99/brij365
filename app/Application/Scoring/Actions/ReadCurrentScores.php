<?php

namespace App\Application\Scoring\Actions;

use App\Domain\Scoring\Services\CurrentScoreSnapshotReader;

final readonly class ReadCurrentScores
{
    public function __construct(private CurrentScoreSnapshotReader $reader) {}

    /**
     * @param array<int, int> $subjectIds
     * @return array<int, \App\Application\Scoring\DTOs\CurrentScoreData>
     */
    public function execute(int $companyId, string $ruleKey, string $subjectType, array $subjectIds): array
    {
        return $this->reader->read($companyId, $ruleKey, $subjectType, $subjectIds);
    }
}
