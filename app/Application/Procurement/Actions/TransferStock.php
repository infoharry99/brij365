<?php

namespace App\Application\Procurement\Actions;

use App\Application\Procurement\Data\ProcurementCommandData;
use App\Services\Procurement\ProcurementService;
use Illuminate\Support\Collection;

final class TransferStock
{
    public function __construct(private readonly ProcurementService $service) {}

    public function execute(ProcurementCommandData $command): Collection
    {
        return $this->service->transferStock($command->attributes, $command->actor, $command->request);
    }
}
