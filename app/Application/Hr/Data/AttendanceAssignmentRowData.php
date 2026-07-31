<?php

namespace App\Application\Hr\Data;

final readonly class AttendanceAssignmentRowData
{
    public function __construct(
        public int $id,
        public string $employeeCode,
        public string $employeeName,
        public string $employeeInitial,
        public string $department,
        public string $branch,
        public string $shiftCode,
        public string $shiftName,
        public string $shiftTiming,
        public string $effectiveFrom,
        public string $effectiveTo,
        public string $statusLabel,
    ) {}
}
