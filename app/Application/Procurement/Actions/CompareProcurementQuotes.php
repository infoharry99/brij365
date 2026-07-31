<?php

namespace App\Application\Procurement\Actions;

use App\Models\PurchaseRequisition;
use App\Models\User;
use App\Services\Procurement\ProcurementService;

final class CompareProcurementQuotes
{
    public function __construct(private readonly ProcurementService $service) {}

    public function execute(PurchaseRequisition $requisition, User $actor): array
    {
        return $this->service->quoteComparison($requisition, $actor);
    }
}
