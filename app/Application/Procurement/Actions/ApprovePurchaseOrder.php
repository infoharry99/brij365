<?php

namespace App\Application\Procurement\Actions;

use App\Application\Procurement\Data\ProcurementCommandData;
use App\Models\PurchaseOrder;
use App\Services\Procurement\ProcurementService;

final class ApprovePurchaseOrder
{
    public function __construct(private readonly ProcurementService $service) {}

    public function execute(PurchaseOrder $order, ProcurementCommandData $command): PurchaseOrder
    {
        return $this->service->approvePurchaseOrder($order, $command->actor, $command->attributes, $command->request);
    }
}
