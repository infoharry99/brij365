<?php

namespace App\Application\Hr\Data;

use App\Models\Employee;

final readonly class EmployeePayrollSummaryPageData
{
    public function __construct(public Employee $employee, public array $summary, public array $profileNavigation) {}

    public function toView(): array { return get_object_vars($this); }
}
