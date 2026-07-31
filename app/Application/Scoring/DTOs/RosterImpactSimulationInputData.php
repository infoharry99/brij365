<?php

namespace App\Application\Scoring\DTOs;

final readonly class RosterImpactSimulationInputData
{
    public function __construct(
        public string $startDate,
        public string $endDate,
    ) {
    }
}
