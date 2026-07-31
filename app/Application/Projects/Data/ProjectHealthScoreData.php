<?php

namespace App\Application\Projects\Data;

use Carbon\CarbonImmutable;

final readonly class ProjectHealthScoreData
{
    /** @param array<string, mixed> $components */
    public function __construct(
        public int $projectId,
        public string $score,
        public string $band,
        public int $ruleVersion,
        public CarbonImmutable $calculatedAt,
        public array $components,
    ) {}
}
