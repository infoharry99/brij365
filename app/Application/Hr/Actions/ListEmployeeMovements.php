<?php

namespace App\Application\Hr\Actions;

use App\Domain\Hr\Services\EmployeeMovementRegister;
use App\Models\Employee;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListEmployeeMovements
{
    public function __construct(private readonly EmployeeMovementRegister $register) {}

    public function execute(Employee $employee, array $filters): LengthAwarePaginator
    {
        return $this->register->all($employee, $filters);
    }
}
