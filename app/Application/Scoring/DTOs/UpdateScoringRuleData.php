<?php

namespace App\Application\Scoring\DTOs;

final readonly class UpdateScoringRuleData
{
    /** @param array<string, mixed> $configuration */
    public function __construct(
        public string $name,
        public string $changeReason,
        public ?string $effectiveAt,
        public array $configuration,
    ) {
    }
}
