<?php

namespace App\Application\Hr\Data;

final readonly class AttendanceRegularizationRowData
{
    public function __construct(
        public int $id,
        public string $requestNumber,
        public string $employeeCode,
        public string $employeeName,
        public string $workDate,
        public string $requestedCheckIn,
        public string $requestedCheckOut,
        public string $status,
        public string $statusLabel,
        public string $reason,
        public ?string $decisionNote,
        public bool $canApprove,
        public bool $canReject,
    ) {}
}
