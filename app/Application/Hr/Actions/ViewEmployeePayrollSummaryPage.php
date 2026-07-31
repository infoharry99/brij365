<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\EmployeePayrollSummaryPageData;
use App\Domain\Hr\Services\EmployeeProfileNavigation;
use App\Domain\Hr\Services\EmployeePayrollSummary;
use App\Models\Employee;
use App\Models\User;

final class ViewEmployeePayrollSummaryPage
{
    public function __construct(
        private readonly EmployeePayrollSummary $summary,
        private readonly EmployeeProfileNavigation $navigation,
    ) {}

    public function execute(Employee $employee, User $actor): EmployeePayrollSummaryPageData
    {
        return new EmployeePayrollSummaryPageData(
            employee: $employee,
            summary: $this->summary->forEmployee($employee, $actor),
            profileNavigation: $this->navigation->links($employee, $actor, $this->navigation->isSelfServiceOnly($employee, $actor)),
        );
    }
}
