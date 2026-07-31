<?php

namespace App\Application\Payroll\Data;

final readonly class PayrollRunRowData
{
    /**
     * @param  list<PayrollRunItemRowData>  $items
     */
    public function __construct(
        public int $id,
        public string $runNumber,
        public string $period,
        public string $dateRange,
        public string $status,
        public string $statusLabel,
        public int $employeeCount,
        public string $grossEarnings,
        public string $deductions,
        public string $netPayable,
        public string $generatedBy,
        public ?string $approvedBy,
        public bool $canApprove,
        public bool $canPrepareBatch,
        public bool $canViewCompensation,
        public array $items,
    ) {}
}
