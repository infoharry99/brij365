<?php

namespace App\Application\AfterSales\Actions;

use App\Application\AfterSales\Data\AfterSalesCommandData;
use App\Models\ServiceTicket;
use App\Services\AfterSales\AfterSalesService;

final class AssignServiceTicket
{
    public function __construct(private readonly AfterSalesService $afterSales) {}
    public function execute(ServiceTicket $ticket, AfterSalesCommandData $command): ServiceTicket
    {
        return $this->afterSales->assignTicket($ticket, $command->attributes, $command->actor, $command->request);
    }
}
