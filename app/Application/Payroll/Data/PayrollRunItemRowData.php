<?php

namespace App\Application\Payroll\Data;

final readonly class PayrollRunItemRowData
{
    public function __construct(
        public int $id,
        public string $employeeCode,
        public string $employeeName,
        public string $designation,
        public string $department,
        public int $payableDays,
        public string $grossEarnings,
        public string $deductions,
        public string $netPayable,
        public string $status,
        public string $statusLabel,
    ) {}
}
