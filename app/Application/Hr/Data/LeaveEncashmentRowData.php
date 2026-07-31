<?php

namespace App\Application\Hr\Data;

final readonly class LeaveEncashmentRowData
{
    public function __construct(
        public int $id,
        public string $encashmentNumber,
        public string $employeeCode,
        public string $employeeName,
        public string $leaveTypeCode,
        public int $periodYear,
        public string $requestedDays,
        public string $approvedDays,
        public string $grossAmount,
        public string $netAmount,
        public string $status,
        public string $statusLabel,
        public string $requestNote,
        public ?string $decisionNote,
        public ?string $payrollReference,
        public bool $canApprove,
        public bool $canReject,
        public bool $canMarkPayroll,
    ) {}
}
