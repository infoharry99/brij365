<?php

namespace App\Application\Hr\Data;

final readonly class LeaveProcessingRunRowData
{
    /**
     * @param array<int, LeaveProcessingLineItemData> $lineItems
     */
    public function __construct(
        public int $id,
        public string $runNumber,
        public int $periodYear,
        public string $processingType,
        public string $processingTypeLabel,
        public string $status,
        public string $statusLabel,
        public int $employeeCount,
        public int $lineCount,
        public string $accrualDays,
        public string $carryForwardDays,
        public string $lapseDays,
        public string $createdBy,
        public ?string $postedBy,
        public string $createdAt,
        public ?string $postedAt,
        public bool $canPost,
        public array $lineItems,
        public ?LeaveProcessingRuleSnapshotData $rulesSnapshot,
    ) {}
}
