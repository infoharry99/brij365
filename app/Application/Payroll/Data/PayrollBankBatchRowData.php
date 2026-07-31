<?php

namespace App\Application\Payroll\Data;

final readonly class PayrollBankBatchRowData
{
    public function __construct(
        public int $id,
        public string $batchNumber,
        public string $runNumber,
        public string $period,
        public string $bankName,
        public string $paymentDate,
        public string $status,
        public string $statusLabel,
        public int $itemCount,
        public string $controlTotal,
        public string $checksum,
        public string $preparedBy,
        public ?string $releasedBy,
        public bool $canRelease,
        public ?string $payload,
    ) {}
}
