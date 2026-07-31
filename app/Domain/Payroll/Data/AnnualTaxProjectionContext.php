<?php

namespace App\Domain\Payroll\Data;

final readonly class AnnualTaxProjectionContext
{
    public function __construct(
        public int $employeeTaxProfileId,
        public int $employeeTaxProfileVersion,
        public string $employeeTaxProfileChecksum,
        public string $financialYear,
        public string $regimeCode,
        public int $actualYtdTaxableMinor,
        public int $actualYtdWithheldMinor,
        public int $previousEmployerIncomeMinor,
        public int $previousEmployerTdsMinor,
        public int $projectedOtherIncomeMinor,
        public int $verifiedDeductionMinor,
        public int $verifiedExemptionMinor,
        public int $remainingPayrollPeriods,
    ) {}

    /** @return array<string, int|string> */
    public function canonical(): array
    {
        return [
            'employee_tax_profile_id' => $this->employeeTaxProfileId,
            'employee_tax_profile_version' => $this->employeeTaxProfileVersion,
            'employee_tax_profile_checksum' => $this->employeeTaxProfileChecksum,
            'financial_year' => $this->financialYear,
            'regime_code' => $this->regimeCode,
            'actual_ytd_taxable_minor' => $this->actualYtdTaxableMinor,
            'actual_ytd_withheld_minor' => $this->actualYtdWithheldMinor,
            'previous_employer_income_minor' => $this->previousEmployerIncomeMinor,
            'previous_employer_tds_minor' => $this->previousEmployerTdsMinor,
            'projected_other_income_minor' => $this->projectedOtherIncomeMinor,
            'verified_deduction_minor' => $this->verifiedDeductionMinor,
            'verified_exemption_minor' => $this->verifiedExemptionMinor,
            'remaining_payroll_periods' => $this->remainingPayrollPeriods,
        ];
    }
}
