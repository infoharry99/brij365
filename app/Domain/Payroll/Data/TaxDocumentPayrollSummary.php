<?php

namespace App\Domain\Payroll\Data;

final readonly class TaxDocumentPayrollSummary
{
    /**
     * @param  list<array<string, mixed>>  $componentSummary
     * @param  list<array<string, mixed>>  $periods
     * @param  array<string, mixed>  $provenance
     */
    public function __construct(
        public int $grossMinor,
        public int $taxableIncomeMinor,
        public int $tdsMinor,
        public int $netMinor,
        public array $componentSummary,
        public array $periods,
        public array $provenance,
        public string $calculationMode,
    ) {}
}
