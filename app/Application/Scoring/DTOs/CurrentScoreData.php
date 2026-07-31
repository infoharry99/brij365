<?php

namespace App\Application\Scoring\DTOs;

use Carbon\CarbonImmutable;

final readonly class CurrentScoreData
{
    /** @param array<string, mixed> $components @param array<string, mixed> $metadata */
    public function __construct(
        public int $subjectId,
        public string $score,
        public string $band,
        public int $ruleVersion,
        public CarbonImmutable $calculatedAt,
        public array $components,
        public array $metadata,
    ) {}
}
