<?php

namespace App\Domain\Payroll\Data;

use App\Domain\Payroll\ValueObjects\MinorMoney;

final readonly class AnnualTaxProjectionResult
{
    /** @param array<string, mixed> $trace */
    public function __construct(
        public MinorMoney $currentWithholding,
        public MinorMoney $projectedAnnualTaxable,
        public MinorMoney $projectedAnnualTax,
        public MinorMoney $remainingLiability,
        public array $trace,
    ) {}
}
