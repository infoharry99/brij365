<?php

namespace App\Application\Hr\Data;

use App\Models\Employee;

final readonly class EmployeeSelfServiceDashboardData
{
    public function __construct(
        public Employee $employee,
        public array $summary,
        public array $recentAttendance,
        public array $myActions,
        public array $quickActions,
        public array $leaveBalances,
        public array $abilities,
    ) {}

    public function toView(): array
    {
        return get_object_vars($this);
    }
}
