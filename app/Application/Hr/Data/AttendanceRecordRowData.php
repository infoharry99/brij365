<?php

namespace App\Application\Hr\Data;

final readonly class AttendanceRecordRowData
{
    public function __construct(
        public int $id,
        public string $workDate,
        public string $employeeCode,
        public string $employeeName,
        public string $employeeInitial,
        public string $branch,
        public string $shiftCode,
        public string $shiftName,
        public string $shiftTiming,
        public string $checkIn,
        public string $checkOut,
        public string $status,
        public string $statusLabel,
        public int $lateMinutes,
        public int $earlyLeaveMinutes,
        public int $workedMinutes,
        public string $sourceLabel,
        public string $calculationBasis,
    ) {}
}
