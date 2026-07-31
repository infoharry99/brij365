<?php

namespace App\Application\Scoring\DTOs;

final readonly class ScoreSnapshotRowData
{
    public function __construct(
        public int $id,
        public string $subject,
        public string $ruleName,
        public string $score,
        public string $band,
        public int $ruleVersion,
        public string $calculatedAt,
        public bool $override,
        public bool $canOverride,
    ) {
    }
}
