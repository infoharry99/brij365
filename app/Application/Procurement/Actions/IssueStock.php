<?php

namespace App\Application\Procurement\Actions;

use App\Application\Procurement\Data\ProcurementCommandData;
use App\Models\StockMovement;
use App\Services\Procurement\ProcurementService;

final class IssueStock
{
    public function __construct(private readonly ProcurementService $service) {}

    public function execute(ProcurementCommandData $command): StockMovement
    {
        return $this->service->issueStock($command->attributes, $command->actor, $command->request);
    }
}
