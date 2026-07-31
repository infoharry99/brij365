<?php

namespace App\Application\Scoring\Actions;

use App\Application\Scoring\DTOs\RosterImpactSimulationInputData;
use App\Application\Scoring\DTOs\RosterImpactSimulationResultData;
use App\Domain\Hr\Services\AttendanceRosterImpactSimulator;
use App\Models\AttendanceRotationRule;

final readonly class SimulateRosterImpact
{
    public function __construct(private AttendanceRosterImpactSimulator $simulator)
    {
    }

    public function execute(
        AttendanceRotationRule $rotation,
        RosterImpactSimulationInputData $input,
    ): RosterImpactSimulationResultData {
        return $this->simulator->simulate($rotation, $input);
    }
}
