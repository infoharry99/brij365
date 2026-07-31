<?php

namespace App\Application\Hr\Data;

final readonly class LeaveBalanceRowData
{
    public function __construct(
        public string $employeeCode,
        public string $employeeName,
        public string $leaveTypeCode,
        public string $leaveTypeName,
        public int $periodYear,
        public string $opening,
        public string $accrued,
        public string $used,
        public string $pending,
        public string $adjusted,
        public string $available,
    ) {}
}
