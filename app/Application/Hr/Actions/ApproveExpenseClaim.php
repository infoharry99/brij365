<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\HrCommandData;
use App\Models\ExpenseClaim;
use App\Services\Hr\EmployeeOperationsService;

final class ApproveExpenseClaim
{
    public function __construct(private readonly EmployeeOperationsService $service) {}

    public function execute(ExpenseClaim $claim, HrCommandData $c): ExpenseClaim
    {
        return $this->service->approveClaim($claim, $c->attributes, $c->actor, $c->request);
    }
}
