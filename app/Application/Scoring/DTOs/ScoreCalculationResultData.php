<?php

namespace App\Application\Scoring\DTOs;

final readonly class ScoreCalculationResultData
{
    /** @param array<string, mixed> $componentScores @param array<string, float> $appliedWeights @param list<string> $mandatoryFailures */
    public function __construct(
        public int $ruleId, public string $ruleKey, public int $ruleVersion, public float $totalScore,
        public ?string $scoreBand, public array $componentScores, public array $appliedWeights,
        public string $inputHash, public array $mandatoryFailures,
    ) {}
}
