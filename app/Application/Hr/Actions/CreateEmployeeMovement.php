<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\HrCommandData;
use App\Models\Employee;
use App\Models\EmployeeMovement;
use App\Services\Hr\EmployeeMovementService;

final class CreateEmployeeMovement
{
    public function __construct(private readonly EmployeeMovementService $service) {}

    public function execute(Employee $employee, HrCommandData $c): EmployeeMovement
    {
        return $this->service->create($employee, $c->attributes, $c->actor, $c->request);
    }
}
