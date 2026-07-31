<?php

namespace App\Application\Hr\Data;

final readonly class ExpenseClaimSummaryData
{
    public function __construct(
        public int $total,
        public int $submitted,
        public int $approved,
        public int $rejected,
        public int $paid,
        public string $claimedAmount,
        public string $approvedAmount,
    ) {}
}
