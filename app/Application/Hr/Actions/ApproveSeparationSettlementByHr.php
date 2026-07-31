<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\HrCommandData;
use App\Models\EmployeeSeparationSettlement;
use App\Services\Hr\EmployeeSeparationSettlementService;

final class ApproveSeparationSettlementByHr
{
    public function __construct(private readonly EmployeeSeparationSettlementService $service) {}

    public function execute(EmployeeSeparationSettlement $settlement, HrCommandData $command): EmployeeSeparationSettlement
    {
        return $this->service->hrApprove($settlement, $command->actor, $command->request, $command->attributes['note'] ?? null);
    }
}
