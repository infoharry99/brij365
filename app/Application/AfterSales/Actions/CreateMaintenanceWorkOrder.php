<?php

namespace App\Application\AfterSales\Actions;

use App\Application\AfterSales\Data\AfterSalesCommandData;
use App\Models\MaintenanceWorkOrder;
use App\Services\AfterSales\AfterSalesService;

final class CreateMaintenanceWorkOrder
{
    public function __construct(private readonly AfterSalesService $afterSales) {}
    public function execute(AfterSalesCommandData $command): MaintenanceWorkOrder
    {
        return $this->afterSales->createWorkOrder($command->attributes, $command->actor, $command->request);
    }
}
