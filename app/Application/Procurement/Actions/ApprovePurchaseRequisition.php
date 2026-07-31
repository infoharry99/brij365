<?php

namespace App\Application\Procurement\Actions;

use App\Application\Procurement\Data\ProcurementCommandData;
use App\Models\PurchaseRequisition;
use App\Services\Procurement\ProcurementService;

final class ApprovePurchaseRequisition
{
    public function __construct(private readonly ProcurementService $service) {}

    public function execute(PurchaseRequisition $requisition, ProcurementCommandData $command): PurchaseRequisition
    {
        return $this->service->approveRequisition($requisition, $command->actor, $command->attributes, $command->request);
    }
}
