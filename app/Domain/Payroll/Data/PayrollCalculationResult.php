<?php

namespace App\Domain\Payroll\Data;

use App\Domain\Payroll\ValueObjects\MinorMoney;

final readonly class PayrollCalculationResult
{
    /**
     * @param  list<array<string, mixed>>  $componentBreakup
     * @param  list<array<string, mixed>>  $calculationLines
     * @param  array<string, mixed>  $ruleContext
     * @param  array<string, mixed>  $inputSnapshot
     * @param  list<array<string, mixed>>  $trace
     */
    public function __construct(
        public MinorMoney $gross,
        public MinorMoney $deductions,
        public MinorMoney $employerContributions,
        public MinorMoney $net,
        public int $payableDaysHundredths,
        public array $componentBreakup,
        public array $calculationLines,
        public array $ruleContext,
        public array $inputSnapshot,
        public array $trace,
        public string $inputHash,
        public string $resultHash,
        public ?int $attendanceSnapshotId,
    ) {}
}
