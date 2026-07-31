<?php

namespace App\Application\Hr\Data;

final readonly class EmployeeLoanSummaryData
{
    public function __construct(
        public int $total,
        public int $submitted,
        public int $approved,
        public int $rejected,
        public int $disbursed,
        public int $closed,
        public string $requestedAmount,
        public string $approvedAmount,
    ) {}
}
