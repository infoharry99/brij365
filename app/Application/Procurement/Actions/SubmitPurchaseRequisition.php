<?php

namespace App\Application\Procurement\Actions;

use App\Application\Procurement\Data\ProcurementCommandData;
use App\Models\PurchaseRequisition;
use App\Services\Procurement\ProcurementService;

final class SubmitPurchaseRequisition
{
    public function __construct(private readonly ProcurementService $service) {}

    public function execute(ProcurementCommandData $command): PurchaseRequisition
    {
        return $this->service->submitRequisition($command->attributes, $command->actor, $command->request);
    }
}
