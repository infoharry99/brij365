<?php

namespace App\Domain\Payroll\Services;

use App\Domain\Payroll\Data\AnnualTaxProjectionContext;
use App\Domain\Payroll\Data\GovernedStatutoryRuleSet;
use App\Domain\Payroll\Data\PayrollCalculationResult;
use App\Domain\Payroll\ValueObjects\MinorMoney;
use App\Models\CommissionItem;
use App\Models\PayrollAttendanceSnapshot;
use App\Models\SalaryAssignment;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class StatutoryPayrollEngine
{
    public const CALCULATION_VERSION = 2;

    public function __construct(
        private readonly CanonicalPayrollHasher $hasher,
        private readonly StatutoryPayrollOverlapGuard $overlapGuard,
        private readonly AnnualTaxProjectionEngine $annualTaxProjectionEngine,
    ) {}

    /**
     * Simulate a definition without reading or mutating payroll records.
     * Component inputs and results are integer minor currency units.
     *
     * @param  array<string, mixed>  $definition
     * @param  array<string, int>  $componentMinor
     * @param  array<string, mixed>  $employeeContext
     * @return array<string, mixed>
     */
    public function simulate(array $definition, array $componentMinor, string $statutoryState, array $employeeContext = []): array
    {
        $state = strtoupper(trim($statutoryState));
        $components = [];
        foreach ($componentMinor as $code => $minor) {
            $components[strtoupper((string) $code)] = MinorMoney::fromMinor((int) $minor);
        }

        $gross = $components['GROSS_EARNINGS'] ?? collect($components)
            ->reject(fn (MinorMoney $amount, string $code): bool => in_array($code, ['TOTAL_DEDUCTIONS', 'TAXABLE_EARNINGS'], true))
            ->reduce(fn (MinorMoney $carry, MinorMoney $amount): MinorMoney => $carry->add($amount), MinorMoney::zero());
        $deductions = MinorMoney::zero();
        $employer = MinorMoney::zero();
        $lines = [];

        $jurisdictions = collect((array) ($definition['jurisdictions'] ?? []))
            ->filter(fn (array $jurisdiction): bool => $jurisdiction['type'] === 'central' || strtoupper((string) $jurisdiction['code']) === $state)
            ->values();
        $requiresState = collect((array) ($definition['jurisdictions'] ?? []))
            ->contains(fn (array $jurisdiction): bool => $jurisdiction['type'] === 'state' && $jurisdiction['state_resolution'] === 'required_match');
        if ($requiresState && ! $jurisdictions->contains(fn (array $jurisdiction): bool => $jurisdiction['type'] === 'state')) {
            throw ValidationException::withMessages(['statutory_state' => 'No state jurisdiction in this pack matches '.$state.'.']);
        }

        foreach ($jurisdictions as $jurisdiction) {
            if (! $this->appliesToContext((array) ($jurisdiction['applicability'] ?? []), $employeeContext, true)) {
                continue;
            }

            foreach ((array) $jurisdiction['lines'] as $definitionLine) {
                $basis = $this->basisAmount((array) ($definitionLine['basis_codes'] ?? []), $components, $gross, $deductions);
                $projection = null;
                if (($definitionLine['method'] ?? null) === 'annual_tax_projection') {
                    $projectionContext = $this->simulationTaxProjectionContext((array) ($employeeContext['tax_projection'] ?? []));
                    $projection = $this->annualTaxProjectionEngine->calculate($definitionLine, $basis, $projectionContext);
                    $amount = $projection->currentWithholding;
                } else {
                    $amount = $this->calculateDefinition($definitionLine, $basis);
                }
                $type = (string) $definitionLine['line_type'];
                $components[strtoupper((string) $definitionLine['code'])] = $amount;
                if ($type === 'earning') {
                    $gross = $gross->add($amount);
                } elseif (in_array($type, ['deduction', 'tax_adjustment'], true)) {
                    $deductions = $deductions->add($amount);
                } elseif ($type === 'employer_contribution') {
                    $employer = $employer->add($amount);
                }
                $lines[] = [
                    'component_code' => strtoupper((string) $definitionLine['code']),
                    'component_name' => $definitionLine['name'],
                    'line_type' => $type,
                    'method' => $definitionLine['method'],
                    'basis_minor' => $basis->minor,
                    'amount_minor' => $amount->minor,
                    'rate_ppm' => $definitionLine['rate_ppm'] ?? null,
                    'jurisdiction_type' => $jurisdiction['type'],
                    'jurisdiction_code' => $jurisdiction['code'],
                    'projection' => $projection?->trace,
                ];
            }
        }

        if ($deductions->minor > $gross->minor) {
            throw ValidationException::withMessages(['components' => 'The simulated deductions exceed simulated gross earnings.']);
        }

        $checksum = $this->hasher->hash($definition);
        $inputHash = $this->hasher->hash([
            'statutory_state' => $state,
            'components' => $componentMinor,
            'employee_context' => $employeeContext,
            'definition_checksum' => $checksum,
        ]);

        return [
            'authoritative' => false,
            'mutated_records' => 0,
            'statutory_state' => $state,
            'definition_checksum' => $checksum,
            'input_hash' => $inputHash,
            'gross_minor' => $gross->minor,
            'deduction_minor' => $deductions->minor,
            'employer_contribution_minor' => $employer->minor,
            'net_minor' => $gross->subtract($deductions)->minor,
            'lines' => $lines,
            'result_hash' => $this->hasher->hash([$gross->minor, $deductions->minor, $employer->minor, $lines, $inputHash]),
        ];
    }

    /** @param Collection<int, CommissionItem> $commissionItems */
    public function calculate(
        SalaryAssignment $assignment,
        Collection $commissionItems,
        int $fallbackWorkingDays,
        GovernedStatutoryRuleSet $ruleSet,
        ?PayrollAttendanceSnapshot $attendanceSnapshot,
        ?AnnualTaxProjectionContext $taxProjectionContext = null,
    ): PayrollCalculationResult {
        $structure = $assignment->salaryStructure;
        $this->overlapGuard->assertNoUnmappedOverlap($structure, $ruleSet);
        $governed = $ruleSet->isGoverned();
        $replacedLegacyCodes = array_fill_keys($ruleSet->replacedLegacyComponentCodes, true);
        $proratedCodes = $this->proratedComponentCodes($ruleSet);
        $ratio = $this->attendanceRatio($governed, $attendanceSnapshot, $fallbackWorkingDays);
        $componentAmounts = [];
        $breakup = [];
        $lines = [];
        $trace = [];
        $gross = MinorMoney::zero();
        $deductions = MinorMoney::zero();
        $employerContributions = MinorMoney::zero();
        $sortOrder = 0;

        foreach ($structure->components as $structureComponent) {
            $component = $structureComponent->payrollComponent;
            $code = strtoupper((string) $component->code);
            if ($governed && (bool) $component->is_statutory && isset($replacedLegacyCodes[$code])) {
                $trace[] = [
                    'step' => 'legacy_statutory_component_excluded',
                    'component_code' => $component->code,
                    'reason' => 'The active cutover manifest explicitly replaces this legacy statutory component.',
                ];
                continue;
            }

            $amount = MinorMoney::fromDecimal((string) $structureComponent->amount);
            $wasProrated = $governed && in_array(strtoupper((string) $component->code), $proratedCodes, true);
            if ($wasProrated) {
                $amount = $amount->multiplyRatio($ratio['numerator'], $ratio['denominator']);
            }

            $componentAmounts[$code] = $amount;
            $lineType = (string) $component->component_type;
            if ($lineType === 'earning') {
                $gross = $gross->add($amount);
            } elseif ($lineType === 'deduction') {
                $deductions = $deductions->add($amount);
            }

            $breakup[] = [
                'component_code' => $component->code,
                'component_name' => $component->name,
                'component_type' => $lineType,
                'is_statutory' => (bool) $component->is_statutory,
                'amount' => $amount->toDecimal(),
                'amount_minor' => $amount->minor,
                'source' => 'salary_structure',
                'attendance_prorated' => $wasProrated,
            ];
            $lines[] = $this->line(
                null,
                $component->code,
                $component->name,
                $lineType,
                $amount,
                $amount,
                null,
                $sortOrder++,
                ['source' => 'salary_structure', 'attendance_prorated' => $wasProrated],
            );
        }

        foreach ($commissionItems as $commissionItem) {
            $amount = MinorMoney::fromDecimal((string) $commissionItem->commission_amount);
            $gross = $gross->add($amount);
            $componentAmounts['COMM'] = ($componentAmounts['COMM'] ?? MinorMoney::zero())->add($amount);
        }

        if ($commissionItems->isNotEmpty()) {
            $commission = $componentAmounts['COMM'];
            $ids = $commissionItems->pluck('id')->values()->all();
            $breakup[] = [
                'component_code' => 'COMM',
                'component_name' => 'Approved Sales Commission',
                'component_type' => 'earning',
                'is_statutory' => false,
                'amount' => $commission->toDecimal(),
                'amount_minor' => $commission->minor,
                'source' => 'approved_commission_items',
                'commission_item_ids' => $ids,
            ];
            $lines[] = $this->line(null, 'COMM', 'Approved Sales Commission', 'earning', $commission, $commission, null, $sortOrder++, [
                'source' => 'approved_commission_items',
                'commission_item_ids' => $ids,
            ]);
        }

        foreach ($ruleSet->rules as $resolvedRule) {
            $applicability = (array) data_get($resolvedRule, 'jurisdiction.applicability', []);
            $employeeContext = [
                'employee_id' => $assignment->employee_id,
                'employment_type' => $assignment->employee?->employment_type,
                'department' => $assignment->employee?->department,
            ];
            if (! $this->appliesToContext($applicability, $employeeContext, false)) {
                $trace[] = [
                    'step' => 'governed_jurisdiction_not_applicable',
                    'setting_id' => $resolvedRule['setting_id'],
                    'setting_key' => $resolvedRule['setting_key'],
                    'jurisdiction_type' => data_get($resolvedRule, 'jurisdiction.type'),
                    'jurisdiction_code' => data_get($resolvedRule, 'jurisdiction.code'),
                ];
                continue;
            }

            foreach ((array) data_get($resolvedRule, 'jurisdiction.lines', []) as $definition) {
                $basis = $this->basisAmount((array) ($definition['basis_codes'] ?? []), $componentAmounts, $gross, $deductions);
                $projection = null;
                if (($definition['method'] ?? null) === 'annual_tax_projection') {
                    if ($taxProjectionContext === null) {
                        throw ValidationException::withMessages([
                            'tax_profile' => 'The active verified tax rule requires a locked employee tax profile before payroll generation.',
                        ]);
                    }
                    $projection = $this->annualTaxProjectionEngine->calculate($definition, $basis, $taxProjectionContext);
                    $amount = $projection->currentWithholding;
                } else {
                    $amount = $this->calculateDefinition($definition, $basis);
                }
                $code = strtoupper((string) $definition['code']);
                $lineType = (string) $definition['line_type'];
                $componentAmounts[$code] = $amount;

                if ($lineType === 'earning') {
                    $gross = $gross->add($amount);
                } elseif (in_array($lineType, ['deduction', 'tax_adjustment'], true)) {
                    $deductions = $deductions->add($amount);
                } elseif ($lineType === 'employer_contribution') {
                    $employerContributions = $employerContributions->add($amount);
                }

                $lineTrace = [
                    'source' => 'verified_governed_statutory_pack',
                    'setting_id' => $resolvedRule['setting_id'],
                    'setting_key' => $resolvedRule['setting_key'],
                    'setting_version' => $resolvedRule['version'],
                    'setting_checksum' => $resolvedRule['checksum'],
                    'jurisdiction_type' => data_get($resolvedRule, 'jurisdiction.type'),
                    'jurisdiction_code' => data_get($resolvedRule, 'jurisdiction.code'),
                    'method' => $definition['method'],
                    'basis_codes' => $definition['basis_codes'] ?? [],
                    'annual_tax_projection' => $projection?->trace,
                ];

                $breakup[] = [
                    'component_code' => $code,
                    'component_name' => $definition['name'],
                    'component_type' => $lineType === 'tax_adjustment' ? 'deduction' : $lineType,
                    'is_statutory' => true,
                    'amount' => $amount->toDecimal(),
                    'amount_minor' => $amount->minor,
                    'source' => 'verified_governed_statutory_pack',
                    'setting_id' => $resolvedRule['setting_id'],
                ];
                $lines[] = $this->line(
                    $resolvedRule['setting_id'],
                    $code,
                    (string) $definition['name'],
                    $lineType,
                    $amount,
                    $basis,
                    isset($definition['rate_ppm']) ? (int) $definition['rate_ppm'] : null,
                    $sortOrder++,
                    $lineTrace,
                );
                $trace[] = ['step' => 'governed_statutory_line', 'component_code' => $code, 'basis_minor' => $basis->minor, 'amount_minor' => $amount->minor] + $lineTrace;
            }
        }

        if ($deductions->minor > $gross->minor) {
            throw ValidationException::withMessages([
                'payroll' => 'Payroll deductions exceed gross earnings for employee '.$assignment->employee_id.'. Resolve the statutory inputs before generation.',
            ]);
        }

        $net = $gross->subtract($deductions);
        $payableDaysHundredths = $governed
            ? $this->decimalHundredths((string) $attendanceSnapshot?->payable_days)
            : $fallbackWorkingDays * 100;
        $ruleContext = [
            'mode' => $governed ? 'governed_verified' : 'legacy_non_authoritative',
            'cutover_mode' => $ruleSet->cutoverMode,
            'calculation_version' => self::CALCULATION_VERSION,
            'setting_ids' => $ruleSet->settingIds(),
            'replaced_legacy_component_codes' => $ruleSet->replacedLegacyComponentCodes,
            'cutover_manifest' => [
                'setting_id' => $ruleSet->manifestSettingId,
                'version' => $ruleSet->manifestSettingVersion,
                'checksum' => $ruleSet->manifestChecksum,
            ],
            'settings' => collect($ruleSet->rules)->map(fn (array $rule): array => [
                'setting_id' => $rule['setting_id'],
                'setting_key' => $rule['setting_key'],
                'version' => $rule['version'],
                'checksum' => $rule['checksum'],
                'jurisdiction_type' => data_get($rule, 'jurisdiction.type'),
                'jurisdiction_code' => data_get($rule, 'jurisdiction.code'),
                'source_evidence' => $rule['source_evidence'] ?? [],
            ])->values()->all(),
        ];
        $input = [
            'employee_id' => $assignment->employee_id,
            'employee_context' => [
                'employment_type' => $assignment->employee?->employment_type,
                'department' => $assignment->employee?->department,
                'statutory_state' => $assignment->employee?->statutory_state,
            ],
            'salary_assignment_id' => $assignment->id,
            'salary_structure_id' => $structure->id,
            'component_minor' => collect($lines)->whereNull('system_setting_id')->mapWithKeys(fn (array $line): array => [$line['component_code'] => $line['amount_minor']])->all(),
            'commission_item_ids' => $commissionItems->pluck('id')->values()->all(),
            'attendance_snapshot' => $attendanceSnapshot === null ? null : [
                'id' => $attendanceSnapshot->id,
                'source_hash' => $attendanceSnapshot->source_hash,
                'payable_days' => (string) $attendanceSnapshot->payable_days,
                'scheduled_days' => $attendanceSnapshot->scheduled_days,
            ],
            'tax_projection' => $taxProjectionContext?->canonical(),
            'rule_context' => $ruleContext,
        ];
        $inputHash = $this->hasher->hash($input);
        $resultHash = $this->hasher->hash([
            'gross_minor' => $gross->minor,
            'deduction_minor' => $deductions->minor,
            'employer_contribution_minor' => $employerContributions->minor,
            'net_minor' => $net->minor,
            'lines' => $lines,
            'input_hash' => $inputHash,
        ]);

        return new PayrollCalculationResult(
            $gross,
            $deductions,
            $employerContributions,
            $net,
            $payableDaysHundredths,
            $breakup,
            $lines,
            $ruleContext,
            $input,
            $trace,
            $inputHash,
            $resultHash,
            $attendanceSnapshot?->id,
        );
    }

    /** @return array{numerator:int, denominator:int} */
    private function attendanceRatio(bool $governed, ?PayrollAttendanceSnapshot $snapshot, int $fallbackWorkingDays): array
    {
        if (! $governed) {
            return ['numerator' => $fallbackWorkingDays, 'denominator' => max($fallbackWorkingDays, 1)];
        }

        if ($snapshot === null || $snapshot->scheduled_days < 1) {
            throw ValidationException::withMessages(['attendance' => 'A finalized attendance snapshot with scheduled days is required for governed payroll.']);
        }

        return [
            'numerator' => $this->decimalHundredths((string) $snapshot->payable_days),
            'denominator' => ((int) $snapshot->scheduled_days) * 100,
        ];
    }

    /** @return list<string> */
    private function proratedComponentCodes(GovernedStatutoryRuleSet $rules): array
    {
        return collect($rules->rules)
            ->filter(fn (array $rule): bool => data_get($rule, 'attendance_proration.enabled') === true)
            ->flatMap(fn (array $rule): array => (array) data_get($rule, 'attendance_proration.component_codes', []))
            ->map(fn (mixed $code): string => strtoupper((string) $code))
            ->unique()
            ->values()
            ->all();
    }

    /** @param array<string, MinorMoney> $components */
    private function basisAmount(array $codes, array $components, MinorMoney $gross, MinorMoney $deductions): MinorMoney
    {
        $basis = MinorMoney::zero();
        foreach ($codes as $rawCode) {
            $code = strtoupper((string) $rawCode);
            $amount = match ($code) {
                'GROSS_EARNINGS', 'TAXABLE_EARNINGS' => $gross,
                'TOTAL_DEDUCTIONS' => $deductions,
                default => $components[$code] ?? MinorMoney::zero(),
            };
            $basis = $basis->add($amount);
        }

        return $basis;
    }

    /** @param array<string, mixed> $definition */
    private function calculateDefinition(array $definition, MinorMoney $basis): MinorMoney
    {
        if (isset($definition['threshold_min_minor']) && $basis->minor < (int) $definition['threshold_min_minor']) {
            return MinorMoney::zero();
        }
        if (isset($definition['threshold_max_minor']) && $basis->minor > (int) $definition['threshold_max_minor']) {
            return MinorMoney::zero();
        }

        $amount = match ($definition['method']) {
            'rate_ppm' => $basis->multiplyPpm((int) $definition['rate_ppm']),
            'fixed_minor' => MinorMoney::fromMinor((int) $definition['fixed_minor']),
            'slab' => $this->progressiveSlabAmount($basis, (array) $definition['slabs']),
            default => throw ValidationException::withMessages([
                'statutory_rules' => 'The active statutory rule contains an unsupported calculation method.',
            ]),
        };

        return isset($definition['cap_minor'])
            ? $amount->min(MinorMoney::fromMinor((int) $definition['cap_minor']))
            : $amount;
    }

    /** @param array<string, mixed> $input */
    private function simulationTaxProjectionContext(array $input): AnnualTaxProjectionContext
    {
        $required = [
            'employee_tax_profile_id', 'employee_tax_profile_version', 'employee_tax_profile_checksum',
            'financial_year', 'regime_code', 'actual_ytd_taxable_minor', 'actual_ytd_withheld_minor',
            'previous_employer_income_minor', 'previous_employer_tds_minor', 'projected_other_income_minor',
            'verified_deduction_minor', 'verified_exemption_minor', 'remaining_payroll_periods',
        ];
        if ($input === [] || collect($required)->contains(fn (string $key): bool => ! array_key_exists($key, $input))) {
            throw ValidationException::withMessages([
                'employee_context.tax_projection' => 'Annual tax simulation requires a complete, explicit non-authoritative tax projection context.',
            ]);
        }

        foreach (array_diff($required, ['employee_tax_profile_checksum', 'financial_year', 'regime_code']) as $key) {
            if (! is_int($input[$key])) {
                throw ValidationException::withMessages([
                    'employee_context.tax_projection.'.$key => 'Tax projection amounts and identifiers must use integer minor units.',
                ]);
            }
        }

        return new AnnualTaxProjectionContext(
            employeeTaxProfileId: $input['employee_tax_profile_id'],
            employeeTaxProfileVersion: $input['employee_tax_profile_version'],
            employeeTaxProfileChecksum: (string) $input['employee_tax_profile_checksum'],
            financialYear: (string) $input['financial_year'],
            regimeCode: (string) $input['regime_code'],
            actualYtdTaxableMinor: $input['actual_ytd_taxable_minor'],
            actualYtdWithheldMinor: $input['actual_ytd_withheld_minor'],
            previousEmployerIncomeMinor: $input['previous_employer_income_minor'],
            previousEmployerTdsMinor: $input['previous_employer_tds_minor'],
            projectedOtherIncomeMinor: $input['projected_other_income_minor'],
            verifiedDeductionMinor: $input['verified_deduction_minor'],
            verifiedExemptionMinor: $input['verified_exemption_minor'],
            remainingPayrollPeriods: $input['remaining_payroll_periods'],
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

    /** @return array<string, mixed> */
    private function line(?int $settingId, string $code, string $name, string $type, MinorMoney $amount, MinorMoney $basis, ?int $ratePpm, int $order, array $trace): array
    {
        return [
            'system_setting_id' => $settingId,
            'component_code' => strtoupper($code),
            'component_name' => $name,
            'line_type' => $type,
            'amount_minor' => $amount->minor,
            'basis_minor' => $basis->minor,
            'rate_ppm' => $ratePpm,
            'sort_order' => $order,
            'trace' => $trace,
        ];
    }

    private function decimalHundredths(string $value): int
    {
        if (! preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $value, $matches)) {
            throw ValidationException::withMessages(['attendance' => 'Attendance payable days must be a non-negative two-decimal value.']);
        }

        return ((int) $matches[1] * 100) + (int) str_pad($matches[2] ?? '', 2, '0');
    }

    /**
     * @param array<string, mixed> $applicability
     * @param array<string, mixed> $context
     */
    private function appliesToContext(array $applicability, array $context, bool $requireContext): bool
    {
        if ($applicability === []) {
            return true;
        }

        $employeeFilters = array_intersect(['employee_ids', 'excluded_employee_ids'], array_keys($applicability));
        if ($employeeFilters !== [] && ! isset($context['employee_id'])) {
            if ($requireContext) {
                throw ValidationException::withMessages(['employee_context.employee_id' => 'Employee ID is required to simulate this population-scoped statutory rule.']);
            }

            return false;
        }

        $employeeId = isset($context['employee_id']) ? (int) $context['employee_id'] : null;
        $includedIds = array_map('intval', (array) ($applicability['employee_ids'] ?? []));
        $excludedIds = array_map('intval', (array) ($applicability['excluded_employee_ids'] ?? []));
        if ($includedIds !== [] && ! in_array($employeeId, $includedIds, true)) {
            return false;
        }
        if ($excludedIds !== [] && in_array($employeeId, $excludedIds, true)) {
            return false;
        }

        foreach (['employment_types' => 'employment_type', 'departments' => 'department'] as $filter => $contextKey) {
            if (! array_key_exists($filter, $applicability)) {
                continue;
            }
            if (! isset($context[$contextKey]) || trim((string) $context[$contextKey]) === '') {
                if ($requireContext) {
                    throw ValidationException::withMessages(["employee_context.$contextKey" => ucfirst(str_replace('_', ' ', $contextKey)).' is required to simulate this population-scoped statutory rule.']);
                }

                return false;
            }

            $value = mb_strtolower(trim((string) $context[$contextKey]));
            $allowed = collect((array) $applicability[$filter])
                ->map(fn (mixed $entry): string => mb_strtolower(trim((string) $entry)))
                ->all();
            if (! in_array($value, $allowed, true)) {
                return false;
            }
        }

        return true;
    }
}
