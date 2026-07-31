<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\HrCommandData;
use App\Models\EmployeeSeparationSettlement;
use App\Services\Hr\EmployeeSeparationSettlementService;

final class CompleteSeparationSettlement
{
    public function __construct(private readonly EmployeeSeparationSettlementService $service) {}

    public function execute(EmployeeSeparationSettlement $settlement, HrCommandData $command): EmployeeSeparationSettlement
    {
        return $this->service->complete($settlement, $command->attributes, $command->actor, $command->request);
    }
}
