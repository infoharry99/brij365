<?php

namespace App\Application\Hr\Data;

final readonly class LeaveProcessingLineItemData
{
    public function __construct(
        public string $employeeCode,
        public string $employeeName,
        public string $leaveTypeCode,
        public string $openingBalanceDays,
        public string $availableBeforeDays,
        public string $accrualDays,
        public string $carryForwardDays,
        public string $lapseDays,
    ) {}
}
