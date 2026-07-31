<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\HrCommandData;
use App\Models\EmployeeLoan;
use App\Services\Hr\EmployeeOperationsService;

final class RejectEmployeeLoan
{
    public function __construct(private readonly EmployeeOperationsService $service) {}

    public function execute(EmployeeLoan $loan, HrCommandData $c): EmployeeLoan
    {
        return $this->service->rejectLoan($loan, $c->attributes, $c->actor, $c->request);
    }
}
