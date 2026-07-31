<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\HrCommandData;
use App\Models\ExpenseClaim;
use App\Services\Hr\EmployeeOperationsService;

final class SubmitExpenseClaim
{
    public function __construct(private readonly EmployeeOperationsService $service) {}

    public function execute(HrCommandData $c): ExpenseClaim
    {
        return $this->service->submitClaim($c->attributes, $c->actor, $c->request);
    }
}
