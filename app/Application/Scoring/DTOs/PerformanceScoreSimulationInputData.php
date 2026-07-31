<?php

namespace App\Application\Scoring\DTOs;

final readonly class PerformanceScoreSimulationInputData
{
    /** @param array<string, float> $criterionScores */
    public function __construct(public array $criterionScores)
    {
    }
}
