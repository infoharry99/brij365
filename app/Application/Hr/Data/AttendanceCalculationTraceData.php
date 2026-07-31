<?php

namespace App\Application\Hr\Data;

final readonly class AttendanceCalculationTraceData
{
    public function __construct(
        public int $recordId,
        public string $employeeCode,
        public string $employeeName,
        public string $employeeInitial,
        public string $branch,
        public string $workDate,
        public string $sourceLabel,
        public string $checkIn,
        public string $checkOut,
        public ?string $regularizationRequestNumber,
        public bool $hasLinkedShift,
        public string $shiftCode,
        public string $shiftName,
        public string $shiftTiming,
        public bool $overnight,
        public int $lateGraceMinutes,
        public int $earlyLeaveGraceMinutes,
        public int $halfDayThresholdMinutes,
        public int $fullDayThresholdMinutes,
        public string $status,
        public string $statusLabel,
        public int $lateMinutes,
        public int $earlyLeaveMinutes,
        public int $workedMinutes,
        public string $provenanceNote,
    ) {}
}
