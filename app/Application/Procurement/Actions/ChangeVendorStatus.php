<?php

namespace App\Application\Procurement\Actions;

use App\Application\Procurement\Data\ProcurementCommandData;
use App\Models\Vendor;
use App\Services\Procurement\ProcurementService;

final class ChangeVendorStatus
{
    public function __construct(private readonly ProcurementService $service) {}

    public function execute(Vendor $vendor, ProcurementCommandData $command): Vendor
    {
        return $this->service->updateVendorStatus($vendor, $command->attributes, $command->actor, $command->request);
    }
}
