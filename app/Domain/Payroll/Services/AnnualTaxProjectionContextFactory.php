<?php

namespace App\Domain\Payroll\Services;

use App\Domain\Payroll\Data\AnnualTaxProjectionContext;
use App\Models\EmployeeTaxProfile;
use App\Models\PayrollRunItem;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

final class AnnualTaxProjectionContextFactory
{
    public function __construct(
        private readonly CanonicalPayrollHasher $hasher,
        private readonly EmployeeTaxProfileCanonicalPayload $canonicalPayload,
    ) {}

    /** @param array<string, mixed> $definition */
    public function build(int $companyId, int $employeeId, Carbon $periodStart, array $definition): AnnualTaxProjectionContext
    {
        $startMonth = (int) data_get($definition, 'projection.financial_year_start_month', 4);
        $financialYearStart = $periodStart->month >= $startMonth
            ? Carbon::create($periodStart->year, $startMonth, 1)->startOfDay()
            : Carbon::create($periodStart->year - 1, $startMonth, 1)->startOfDay();
        $financialYearEnd = $financialYearStart->copy()->addYear()->subDay()->endOfDay();
        $financialYear = $financialYearStart->format('Y').'-'.$financialYearEnd->format('y');

        $profile = EmployeeTaxProfile::query()
            ->with('declarations')
            ->where('company_id', $companyId)
            ->where('employee_id', $employeeId)
            ->where('financial_year', $financialYear)
            ->where('status', EmployeeTaxProfile::STATUS_LOCKED)
            ->orderByDesc('version')
            ->orderByDesc('id')
            ->first();
        if ($profile === null || ! hash_equals((string) $profile->input_checksum, $this->hasher->hash($this->canonicalPayload->for($profile)))) {
            throw ValidationException::withMessages([
                'tax_profile' => 'Annual tax projection requires an intact, locked, independently reviewed employee tax profile for '.$financialYear.'.',
            ]);
        }

        $allowedRegimes = collect(array_keys((array) data_get($definition, 'projection.regime_slabs', [])))
            ->map(fn (string $code): string => strtoupper($code));
        if (! $allowedRegimes->contains(strtoupper($profile->regime_code))) {
            throw ValidationException::withMessages([
                'tax_profile' => 'The locked employee tax regime is not available in the active verified tax rule version.',
            ]);
        }

        $priorItems = PayrollRunItem::query()
            ->with(['calculationSnapshot.lines', 'payrollRun'])
            ->where('company_id', $companyId)
            ->where('employee_id', $employeeId)
            ->whereHas('payrollRun', fn ($query) => $query
                ->where('status', 'approved')
                ->whereDate('period_start', '>=', $financialYearStart->toDateString())
                ->whereDate('period_start', '<', $periodStart->toDateString()))
            ->get();

        $basisCodes = collect((array) ($definition['basis_codes'] ?? []))->map(fn ($code): string => strtoupper((string) $code));
        $withholdingCodes = collect((array) data_get($definition, 'projection.withholding_component_codes', [$definition['code'] ?? 'TDS']))
            ->map(fn ($code): string => strtoupper((string) $code));
        $actualYtdTaxable = 0;
        $actualYtdWithheld = 0;
        foreach ($priorItems as $item) {
            $snapshot = $item->calculationSnapshot;
            if ($snapshot === null) {
                continue;
            }
            $components = collect((array) data_get($snapshot->input_snapshot, 'component_minor', []))
                ->mapWithKeys(fn ($amount, $code): array => [strtoupper((string) $code) => (int) $amount]);
            foreach ($basisCodes as $code) {
                $actualYtdTaxable += match ($code) {
                    'GROSS_EARNINGS', 'TAXABLE_EARNINGS' => (int) $snapshot->gross_minor,
                    default => (int) $components->get($code, 0),
                };
            }
            $actualYtdWithheld += (int) $snapshot->lines
                ->filter(fn ($line): bool => $withholdingCodes->contains(strtoupper((string) $line->component_code)))
                ->sum('amount_minor');
        }

        $verifiedDeduction = 0;
        $verifiedExemption = 0;
        foreach ($profile->declarations->where('status', 'verified') as $declaration) {
            $verified = (int) data_get($declaration->amount_payload, 'verified_minor', 0);
            if ($declaration->declaration_type === 'deduction') {
                $verifiedDeduction += $verified;
            } elseif ($declaration->declaration_type === 'exemption') {
                $verifiedExemption += $verified;
            }
        }

        $payload = (array) $profile->input_payload;
        $remainingPeriods = (($financialYearEnd->year - $periodStart->year) * 12) + $financialYearEnd->month - $periodStart->month + 1;

        return new AnnualTaxProjectionContext(
            employeeTaxProfileId: $profile->id,
            employeeTaxProfileVersion: $profile->version,
            employeeTaxProfileChecksum: $profile->input_checksum,
            financialYear: $financialYear,
            regimeCode: $profile->regime_code,
            actualYtdTaxableMinor: $actualYtdTaxable,
            actualYtdWithheldMinor: $actualYtdWithheld,
            previousEmployerIncomeMinor: (int) ($payload['previous_employer_income_minor'] ?? 0),
            previousEmployerTdsMinor: (int) ($payload['previous_employer_tds_minor'] ?? 0),
            projectedOtherIncomeMinor: (int) ($payload['projected_other_income_minor'] ?? 0),
            verifiedDeductionMinor: $verifiedDeduction,
            verifiedExemptionMinor: $verifiedExemption,
            remainingPayrollPeriods: $remainingPeriods,
        );
    }

}
