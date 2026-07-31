<?php

namespace App\Application\Construction\Actions;

use App\Application\Construction\Data\ConstructionCommandData;
use App\Models\ContractorBill;
use App\Services\Construction\ConstructionService;

final class MarkContractorBillPaid
{
    public function __construct(private readonly ConstructionService $service) {}

    public function execute(ContractorBill $bill, ConstructionCommandData $command): ContractorBill
    {
        return $this->service->markContractorBillPaid($bill, $command->actor, $command->attributes, $command->request);
    }
}
