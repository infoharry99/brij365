<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\HrCommandData;
use App\Models\Employee;
use App\Services\Hr\EmployeeProfileService;

final class UpdateEmployee
{
    public function __construct(private readonly EmployeeProfileService $service) {}

    public function execute(Employee $employee, HrCommandData $c): Employee
    {
        return $this->service->update($employee, $c->attributes, $c->actor, $c->request);
    }
}
