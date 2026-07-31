<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\HrCommandData;
use App\Models\Employee;
use App\Services\Hr\EmployeeProfileService;

final class CreateEmployee
{
    public function __construct(private readonly EmployeeProfileService $service) {}

    public function execute(HrCommandData $c): Employee
    {
        return $this->service->create($c->attributes, $c->actor, $c->request);
    }
}
