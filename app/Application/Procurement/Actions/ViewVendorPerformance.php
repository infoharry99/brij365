<?php

namespace App\Application\Procurement\Actions;

use App\Models\User;
use App\Models\Vendor;
use App\Services\Procurement\ProcurementService;

final class ViewVendorPerformance
{
    public function __construct(private readonly ProcurementService $service) {}

    public function execute(Vendor $vendor, User $actor): array
    {
        return $this->service->vendorPerformance($vendor, $actor);
    }
}
