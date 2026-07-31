<?php

namespace App\Application\Hr\Data;

final readonly class EmployeeLoanRowData
{
    public function __construct(
        public int $id,
        public string $loanNumber,
        public string $employeeCode,
        public string $employeeName,
        public string $employeeInitial,
        public string $employeeContext,
        public string $loanType,
        public string $loanTypeLabel,
        public string $requestedOn,
        public string $purpose,
        public string $principalAmount,
        public string $approvedAmount,
        public string $approvalAmountInput,
        public int $installmentMonths,
        public string $monthlyInstallment,
        public string $repaymentStartsOn,
        public string $repaymentStartsOnInput,
        public string $status,
        public string $statusLabel,
        public string $statusTone,
        public string $workflowNote,
        public string $workflowActor,
        public string $workflowAt,
        public bool $canApprove,
        public bool $canReject,
        public bool $canDisburse,
    ) {}
}
