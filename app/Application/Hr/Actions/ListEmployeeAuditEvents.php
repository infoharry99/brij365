<?php

namespace App\Application\Hr\Actions;

use App\Domain\Hr\Services\EmployeeAuditRegister;
use App\Models\Employee;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListEmployeeAuditEvents
{
    public function __construct(private readonly EmployeeAuditRegister $register) {}

    public function execute(Employee $employee, array $filters): LengthAwarePaginator
    {
        return $this->register->events($employee, $filters);
    }
}
