<?php

namespace App\Domain\Payroll\Services;

use App\Domain\Payroll\Data\AnnualTaxProjectionContext;
use App\Domain\Payroll\Data\AnnualTaxProjectionResult;
use App\Domain\Payroll\ValueObjects\MinorMoney;
use Illuminate\Validation\ValidationException;

final class AnnualTaxProjectionEngine
{
    /**
     * All money inputs are integer minor units. Numeric statutory values are
     * supplied exclusively by an independently verified rule-pack version.
     *
     * @param array<string, mixed> $definition
     */
    public function calculate(
        array $definition,
        MinorMoney $currentPeriodTaxable,
        AnnualTaxProjectionContext $context,
    ): AnnualTaxProjectionResult {
        $regime = strtoupper(trim($context->regimeCode));
        $slabs = (array) data_get($definition, 'projection.regime_slabs.'.$regime, []);
        if ($slabs === []) {
            throw ValidationException::withMessages([
                'tax_profile' => 'The locked employee tax regime is not configured in the active verified tax rule pack.',
            ]);
        }

        if ($context->remainingPayrollPeriods < 1 || $context->remainingPayrollPeriods > 12) {
            throw ValidationException::withMessages([
                'tax_profile' => 'The tax projection requires 1 to 12 remaining payroll periods.',
            ]);
        }

        $projectedRecurring = $currentPeriodTaxable->multiplyRatio($context->remainingPayrollPeriods, 1);
        $projectedTaxable = MinorMoney::fromMinor($context->actualYtdTaxableMinor)
            ->add($projectedRecurring)
            ->add(MinorMoney::fromMinor($context->previousEmployerIncomeMinor))
            ->add(MinorMoney::fromMinor($context->projectedOtherIncomeMinor));

        $deductions = MinorMoney::fromMinor($context->verifiedDeductionMinor)
            ->add(MinorMoney::fromMinor($context->verifiedExemptionMinor))
            ->add(MinorMoney::fromMinor((int) data_get($definition, 'projection.standard_deduction_minor.'.$regime, 0)));
        $taxableAfterDeductions = MinorMoney::fromMinor(max(0, $projectedTaxable->minor - $deductions->minor));
        $baseTax = $this->progressiveSlabAmount($taxableAfterDeductions, $slabs);

        $rebate = (array) data_get($definition, 'projection.rebate.'.$regime, []);
        if ($rebate !== [] && $taxableAfterDeductions->minor <= (int) ($rebate['taxable_income_max_minor'] ?? -1)) {
            $baseTax = MinorMoney::fromMinor(max(0, $baseTax->minor - (int) ($rebate['rebate_minor'] ?? 0)));
        }

        $postTaxRate = (int) data_get($definition, 'projection.post_tax_rate_ppm', 0);
        $projectedTax = $baseTax->add($baseTax->multiplyPpm($postTaxRate));
        $alreadyWithheld = MinorMoney::fromMinor($context->actualYtdWithheldMinor)
            ->add(MinorMoney::fromMinor($context->previousEmployerTdsMinor));
        $remaining = MinorMoney::fromMinor(max(0, $projectedTax->minor - $alreadyWithheld->minor));
        $currentWithholding = $remaining->multiplyRatio(1, $context->remainingPayrollPeriods);

        return new AnnualTaxProjectionResult(
            currentWithholding: $currentWithholding,
            projectedAnnualTaxable: $taxableAfterDeductions,
            projectedAnnualTax: $projectedTax,
            remainingLiability: $remaining,
            trace: [
                'method' => 'annual_tax_projection',
                'financial_year' => $context->financialYear,
                'regime_code' => $regime,
                'employee_tax_profile_id' => $context->employeeTaxProfileId,
                'employee_tax_profile_version' => $context->employeeTaxProfileVersion,
                'employee_tax_profile_checksum' => $context->employeeTaxProfileChecksum,
                'current_period_taxable_minor' => $currentPeriodTaxable->minor,
                'actual_ytd_taxable_minor' => $context->actualYtdTaxableMinor,
                'projected_recurring_minor' => $projectedRecurring->minor,
                'previous_employer_income_minor' => $context->previousEmployerIncomeMinor,
                'projected_other_income_minor' => $context->projectedOtherIncomeMinor,
                'verified_deduction_minor' => $context->verifiedDeductionMinor,
                'verified_exemption_minor' => $context->verifiedExemptionMinor,
                'standard_deduction_minor' => (int) data_get($definition, 'projection.standard_deduction_minor.'.$regime, 0),
                'projected_annual_taxable_minor' => $taxableAfterDeductions->minor,
                'base_tax_minor' => $baseTax->minor,
                'post_tax_rate_ppm' => $postTaxRate,
                'projected_annual_tax_minor' => $projectedTax->minor,
                'already_withheld_minor' => $alreadyWithheld->minor,
                'remaining_liability_minor' => $remaining->minor,
                'remaining_payroll_periods' => $context->remainingPayrollPeriods,
                'current_withholding_minor' => $currentWithholding->minor,
            ],
        );
    }

    /** @param list<array<string, mixed>> $slabs */
    private function progressiveSlabAmount(MinorMoney $basis, array $slabs): MinorMoney
    {
        $amount = MinorMoney::zero();
        foreach ($slabs as $slab) {
            $from = (int) $slab['from_minor'];
            $to = isset($slab['to_minor']) ? (int) $slab['to_minor'] : $basis->minor;
            if ($basis->minor <= $from) {
                continue;
            }

            $taxableMinor = min($basis->minor, $to) - $from;
            if ($taxableMinor > 0) {
                $amount = $amount->add(MinorMoney::fromMinor($taxableMinor)->multiplyPpm((int) $slab['rate_ppm']));
            }
        }

        return $amount;
    }
}
