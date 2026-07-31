<?php

namespace App\Application\Hr\Actions;

use App\Domain\Hr\Services\EmployeeDocumentRegister;
use App\Models\Employee;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListEmployeeDocuments
{
    public function __construct(private readonly EmployeeDocumentRegister $register) {}

    public function execute(Employee $employee, array $filters): LengthAwarePaginator
    {
        return $this->register->employeeDocuments($employee, $filters);
    }
}
