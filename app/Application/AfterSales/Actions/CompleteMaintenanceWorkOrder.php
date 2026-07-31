<?php

namespace App\Application\AfterSales\Actions;

use App\Application\AfterSales\Data\AfterSalesCommandData;
use App\Models\MaintenanceWorkOrder;
use App\Services\AfterSales\AfterSalesService;

final class CompleteMaintenanceWorkOrder
{
    public function __construct(private readonly AfterSalesService $afterSales) {}
    public function execute(MaintenanceWorkOrder $workOrder, AfterSalesCommandData $command): MaintenanceWorkOrder
    {
        return $this->afterSales->completeWorkOrder($workOrder, $command->attributes, $command->actor, $command->request);
    }
}
