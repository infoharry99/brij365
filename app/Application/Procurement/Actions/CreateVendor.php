<?php

namespace App\Application\Procurement\Actions;

use App\Application\Procurement\Data\ProcurementCommandData;
use App\Models\Vendor;
use App\Services\Procurement\ProcurementService;

final class CreateVendor
{
    public function __construct(private readonly ProcurementService $service) {}

    public function execute(ProcurementCommandData $command): Vendor
    {
        return $this->service->createVendor($command->attributes, $command->actor, $command->request);
    }
}
