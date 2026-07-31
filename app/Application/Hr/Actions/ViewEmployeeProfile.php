<?php

namespace App\Application\Hr\Actions;

use App\Domain\Hr\Services\EmployeeRegister;
use App\Models\Employee;

final class ViewEmployeeProfile
{
    public function __construct(private readonly EmployeeRegister $register) {}

    public function execute(Employee $employee): Employee
    {
        return $this->register->detail($employee);
    }
}
