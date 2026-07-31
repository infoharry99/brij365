<?php

namespace App\Application\Procurement\Actions;

use App\Application\Procurement\Data\ProcurementCommandData;
use App\Models\PurchaseOrder;
use App\Services\Procurement\ProcurementService;

final class CreatePurchaseOrder
{
    public function __construct(private readonly ProcurementService $service) {}

    public function execute(ProcurementCommandData $command): PurchaseOrder
    {
        return $this->service->createPurchaseOrder($command->attributes, $command->actor, $command->request);
    }
}
