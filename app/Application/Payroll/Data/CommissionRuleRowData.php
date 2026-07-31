<?php

namespace App\Application\Payroll\Data;

final readonly class CommissionRuleRowData
{
    public function __construct(
        public int $id,
        public string $code,
        public string $name,
        public string $type,
        public string $typeLabel,
        public string $basisLabel,
        public string $valueLabel,
        public string $effectiveRange,
        public string $status,
        public string $statusLabel,
        public string $projectLabel,
        public string $createdBy,
    ) {}
}
