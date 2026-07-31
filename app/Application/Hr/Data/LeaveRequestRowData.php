<?php

namespace App\Application\Hr\Data;

final readonly class LeaveRequestRowData
{
    public function __construct(
        public int $id,
        public string $requestNumber,
        public string $employeeCode,
        public string $employeeName,
        public string $leaveTypeCode,
        public string $leaveTypeName,
        public string $dateRange,
        public string $requestedDays,
        public string $duration,
        public string $status,
        public string $statusLabel,
        public string $reason,
        public ?string $decisionNote,
        public bool $canApprove,
        public bool $canReject,
    ) {}
}
