<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\HrCommandData;
use App\Models\EmployeeLoan;
use App\Services\Hr\EmployeeOperationsService;

final class SubmitEmployeeLoan
{
    public function __construct(private readonly EmployeeOperationsService $service) {}

    public function execute(HrCommandData $c): EmployeeLoan
    {
        return $this->service->submitLoan($c->attributes, $c->actor, $c->request);
    }
}
