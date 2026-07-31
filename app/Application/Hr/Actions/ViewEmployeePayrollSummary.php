<?php

namespace App\Application\Hr\Actions;

use App\Domain\Hr\Services\EmployeePayrollSummary;
use App\Models\Employee;
use App\Models\User;

final class ViewEmployeePayrollSummary
{
    public function __construct(private readonly EmployeePayrollSummary $summary) {}

    public function execute(Employee $employee, User $actor): array
    {
        return $this->summary->forEmployee($employee, $actor);
    }
}
