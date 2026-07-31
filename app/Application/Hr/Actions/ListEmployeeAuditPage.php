<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\EmployeeAuditPageData;
use App\Domain\Hr\Services\EmployeeAuditRegister;
use App\Domain\Hr\Services\EmployeeProfileNavigation;
use App\Models\Employee;
use App\Models\User;

final class ListEmployeeAuditPage
{
    public function __construct(
        private readonly EmployeeAuditRegister $register,
        private readonly EmployeeProfileNavigation $navigation,
    ) {}

    public function execute(Employee $employee, User $actor, array $filters): EmployeeAuditPageData
    {
        return new EmployeeAuditPageData(
            employee: $employee,
            events: $this->register->events($employee, $filters),
            profileNavigation: $this->navigation->links($employee, $actor, false),
        );
    }
}
