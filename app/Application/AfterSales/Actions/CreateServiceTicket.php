<?php

namespace App\Application\AfterSales\Actions;

use App\Application\AfterSales\Data\AfterSalesCommandData;
use App\Models\ServiceTicket;
use App\Services\AfterSales\AfterSalesService;

final class CreateServiceTicket
{
    public function __construct(private readonly AfterSalesService $afterSales) {}
    public function execute(AfterSalesCommandData $command): ServiceTicket
    {
        return $this->afterSales->createTicket($command->attributes, $command->actor, $command->request);
    }
}
