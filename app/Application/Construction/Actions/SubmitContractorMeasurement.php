<?php

namespace App\Application\Construction\Actions;

use App\Application\Construction\Data\ConstructionCommandData;
use App\Models\ContractorMeasurement;
use App\Services\Construction\ConstructionService;

final class SubmitContractorMeasurement
{
    public function __construct(private readonly ConstructionService $service) {}

    public function execute(ConstructionCommandData $command): ContractorMeasurement
    {
        return $this->service->submitContractorMeasurement($command->attributes, $command->actor, $command->request);
    }
}
