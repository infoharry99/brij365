<?php

namespace App\Application\Hr\Data;

final readonly class ExpenseClaimRowData
{
    /**
     * @param  array<int, string>  $attachmentNames
     */
    public function __construct(
        public int $id,
        public string $claimNumber,
        public string $employeeCode,
        public string $employeeName,
        public string $employeeInitial,
        public string $employeeContext,
        public string $claimType,
        public string $claimTypeLabel,
        public string $claimDate,
        public string $description,
        public string $claimedAmount,
        public string $approvedAmount,
        public string $approvalAmountInput,
        public string $status,
        public string $statusLabel,
        public string $statusTone,
        public string $workflowNote,
        public string $workflowActor,
        public string $workflowAt,
        public int $attachmentCount,
        public array $attachmentNames,
        public bool $canApprove,
        public bool $canReject,
        public bool $canPay,
    ) {}
}
