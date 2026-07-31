<?php

namespace App\Application\Scoring\DTOs;

final readonly class CreateScoringRuleData
{
    public function __construct(
        public string $ruleKey,
        public string $name,
        public string $changeReason,
        public ?string $effectiveAt,
    ) {
    }
}
