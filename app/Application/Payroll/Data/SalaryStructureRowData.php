<?php

namespace App\Application\Payroll\Data;

final readonly class SalaryStructureRowData
{
    /** @param array<int, string> $components */
    public function __construct(
        public int $id,
        public string $code,
        public string $name,
        public int $version,
        public string $effectiveRange,
        public string $monthlyCtc,
        public array $components,
    ) {}
}
