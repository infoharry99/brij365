<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\HrCommandData;
use App\Models\EmployeeLoan;
use App\Services\Hr\EmployeeOperationsService;

final class DisburseEmployeeLoan
{
    public function __construct(private readonly EmployeeOperationsService $service) {}

    public function execute(EmployeeLoan $loan, HrCommandData $c): EmployeeLoan
    {
        return $this->service->disburseLoan($loan, $c->attributes, $c->actor, $c->request);
    }
}
