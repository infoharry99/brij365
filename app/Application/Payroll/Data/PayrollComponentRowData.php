<?php

namespace App\Application\Payroll\Data;

final readonly class PayrollComponentRowData
{
    /** @param array<int, string> $rules */
    public function __construct(
        public int $id,
        public string $code,
        public string $name,
        public string $type,
        public string $typeLabel,
        public string $calculationLabel,
        public string $taxableLabel,
        public string $statutoryLabel,
        public array $rules,
    ) {}
}
