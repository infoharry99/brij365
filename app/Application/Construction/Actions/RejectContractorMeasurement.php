<?php

namespace App\Application\Construction\Actions;

use App\Application\Construction\Data\ConstructionCommandData;
use App\Models\ContractorMeasurement;
use App\Services\Construction\ConstructionService;

final class RejectContractorMeasurement
{
    public function __construct(private readonly ConstructionService $service) {}

    public function execute(ContractorMeasurement $measurement, ConstructionCommandData $command): ContractorMeasurement
    {
        return $this->service->rejectContractorMeasurement($measurement, (string) $command->attributes['reason'], $command->actor, $command->request);
    }
}
