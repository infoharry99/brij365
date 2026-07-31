<?php

namespace Tests\Unit;

use App\Domain\Payroll\Data\AnnualTaxProjectionContext;
use App\Domain\Payroll\Services\AnnualTaxProjectionEngine;
use App\Domain\Payroll\ValueObjects\MinorMoney;
use PHPUnit\Framework\TestCase;

class AnnualTaxProjectionEngineTest extends TestCase
{
    public function test_projection_is_deterministic_and_uses_only_integer_minor_units(): void
    {
        $definition = [
            'projection' => [
                'regime_slabs' => [
                    'CONTROLLED' => [
                        ['from_minor' => 0, 'to_minor' => 200_000, 'rate_ppm' => 100_000],
                        ['from_minor' => 200_000, 'to_minor' => null, 'rate_ppm' => 200_000],
                    ],
                ],
                'standard_deduction_minor' => ['CONTROLLED' => 20_000],
                'rebate' => ['CONTROLLED' => []],
                'post_tax_rate_ppm' => 0,
            ],
        ];
        $context = new AnnualTaxProjectionContext(
            employeeTaxProfileId: 71,
            employeeTaxProfileVersion: 3,
            employeeTaxProfileChecksum: str_repeat('a', 64),
            financialYear: '2026-27',
            regimeCode: 'CONTROLLED',
            actualYtdTaxableMinor: 100_000,
            actualYtdWithheldMinor: 10_000,
            previousEmployerIncomeMinor: 50_000,
            previousEmployerTdsMinor: 5_000,
            projectedOtherIncomeMinor: 0,
            verifiedDeductionMinor: 20_000,
            verifiedExemptionMinor: 10_000,
            remainingPayrollPeriods: 2,
        );
        $engine = new AnnualTaxProjectionEngine;

        $first = $engine->calculate($definition, MinorMoney::fromMinor(100_000), $context);
        $second = $engine->calculate($definition, MinorMoney::fromMinor(100_000), $context);

        $this->assertSame(300_000, $first->projectedAnnualTaxable->minor);
        $this->assertSame(40_000, $first->projectedAnnualTax->minor);
        $this->assertSame(25_000, $first->remainingLiability->minor);
        $this->assertSame(12_500, $first->currentWithholding->minor);
        $this->assertSame($first->trace, $second->trace);
        $this->assertSame($first->currentWithholding->minor, $second->currentWithholding->minor);
    }
}
