<?php

namespace App\Application\Procurement\Actions;

use App\Application\Procurement\Data\ProcurementCommandData;
use App\Models\GoodsReceipt;
use App\Services\Procurement\ProcurementService;

final class ReceiveGoods
{
    public function __construct(private readonly ProcurementService $service) {}

    public function execute(ProcurementCommandData $command): GoodsReceipt
    {
        return $this->service->receiveGoods($command->attributes, $command->actor, $command->request);
    }
}
