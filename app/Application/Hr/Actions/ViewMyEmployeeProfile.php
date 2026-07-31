<?php

namespace App\Application\Hr\Actions;

use App\Domain\Hr\Services\EmployeeRegister;
use App\Models\Employee;
use App\Models\User;

final class ViewMyEmployeeProfile
{
    public function __construct(private readonly EmployeeRegister $register) {}

    public function execute(User $user): ?Employee
    {
        $employee = $this->register->self($user);

        return $employee?->loadCount($this->register->detailCounts());
    }
}
