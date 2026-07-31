<?php

namespace App\Application\Construction\Actions;

use App\Application\Construction\Data\ConstructionCommandData;
use App\Models\ContractorBill;
use App\Services\Construction\ConstructionService;

final class CreateContractorBill
{
    public function __construct(private readonly ConstructionService $service) {}

    public function execute(ConstructionCommandData $command): ContractorBill
    {
        return $this->service->createContractorBill($command->attributes, $command->actor, $command->request);
    }
}
