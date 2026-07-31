<?php

namespace App\Application\Payroll\Data;

final readonly class CommissionRunRowData
{
    public function __construct(
        public int $id,
        public string $runNumber,
        public string $ruleLabel,
        public string $period,
        public string $dateRange,
        public string $status,
        public string $statusLabel,
        public int $itemCount,
        public string $sourceTotal,
        public string $eligibleTotal,
        public string $commissionTotal,
        public string $generatedBy,
        public ?string $approvedBy,
        public bool $canApprove,
        public bool $canReject,
    ) {}
}
