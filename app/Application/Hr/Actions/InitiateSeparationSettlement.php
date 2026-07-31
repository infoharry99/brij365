<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\HrCommandData;
use App\Models\EmployeeSeparationSettlement;
use App\Services\Hr\EmployeeSeparationSettlementService;

final class InitiateSeparationSettlement
{
    public function __construct(private readonly EmployeeSeparationSettlementService $service) {}

    public function execute(HrCommandData $command): EmployeeSeparationSettlement
    {
        return $this->service->initiate($command->attributes, $command->actor, $command->request);
    }
}
