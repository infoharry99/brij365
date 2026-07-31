<?php

namespace App\Domain\Payroll\Services;

use App\Domain\Payroll\Data\StatutoryPayrollCutoverManifest;
use App\Domain\Payroll\Data\TaxDocumentPayrollSummary;
use App\Domain\Payroll\ValueObjects\MinorMoney;
use App\Models\PayrollCalculationSnapshot;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\SystemSetting;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class TaxDocumentPayrollSummarizer
{
    public function __construct(
        private readonly PayrollCalculationSnapshotVerifier $snapshotVerifier,
        private readonly GovernedTaxSettingVerifier $taxSettingVerifier,
    ) {}

    /**
     * @param  Collection<int, PayrollRun>  $payrollRuns
     */
    public function summarize(Collection $payrollRuns, SystemSetting $taxSetting): TaxDocumentPayrollSummary
    {
        $items = $payrollRuns
            ->flatMap(fn (PayrollRun $run): Collection => $run->items->map(fn (PayrollRunItem $item): array => [
                'run' => $run,
                'item' => $item,
                'snapshot' => $item->calculationSnapshot,
            ]))
            ->values();

        $governedRequired = $items->contains(function (array $entry): bool {
            $snapshot = $entry['snapshot'];

            return data_get($snapshot?->rule_context, 'cutover_mode') === StatutoryPayrollCutoverManifest::MODE_GOVERNED_REQUIRED
                || data_get($entry['run']->metadata, 'statutory_cutover_mode') === StatutoryPayrollCutoverManifest::MODE_GOVERNED_REQUIRED;
        });
        $governedSnapshots = $items->filter(fn (array $entry): bool => $this->isGovernedSnapshot($entry['snapshot']));

        if ($governedRequired) {
            $this->assertGovernedRequiredEvidence($items, $taxSetting);
        }

        $componentSummary = [];
        $grossMinor = 0;
        $netMinor = 0;
        $tdsMinor = 0;
        $pinnedTaxableMinor = 0;
        $hasCompletePinnedTaxBasis = $items->isNotEmpty();
        $snapshotProvenance = [];
        $tdsCodes = $this->tdsCodes($taxSetting, $governedRequired);

        foreach ($items as $entry) {
            /** @var PayrollRunItem $item */
            $item = $entry['item'];
            /** @var PayrollCalculationSnapshot|null $snapshot */
            $snapshot = $entry['snapshot'];

            if ($snapshot !== null) {
                $this->assertSnapshotTotals($snapshot, $governedRequired);
                $grossMinor = $this->safeAdd($grossMinor, (int) $snapshot->gross_minor);
                $netMinor = $this->safeAdd($netMinor, (int) $snapshot->net_minor);

                $taxLines = $snapshot->lines
                    ->filter(fn ($line): bool => in_array(strtoupper((string) $line->component_code), $tdsCodes, true));
                $tdsMinor = $this->safeAdd($tdsMinor, $this->sumMinor($taxLines, 'amount_minor'));

                $pinnedTaxLines = $this->pinnedTaxLines($snapshot, $taxLines);
                if ($this->isGovernedSnapshot($snapshot) && $pinnedTaxLines->isNotEmpty()) {
                    $pinnedTaxableMinor = $this->safeAdd($pinnedTaxableMinor, (int) $pinnedTaxLines->max('basis_minor'));
                } else {
                    $hasCompletePinnedTaxBasis = false;
                }

                foreach ($snapshot->lines as $line) {
                    $this->mergeComponent($componentSummary, [
                        'component_code' => $line->component_code,
                        'component_name' => $line->component_name,
                        'component_type' => $line->line_type === 'tax_adjustment' ? 'deduction' : $line->line_type,
                        'is_statutory' => $line->system_setting_id !== null
                            || data_get($line->trace, 'source') === 'verified_governed_statutory_pack',
                        'amount_minor' => (int) $line->amount_minor,
                    ]);
                }

                $snapshotProvenance[] = $this->snapshotProvenance($snapshot);
                continue;
            }

            if ($governedRequired) {
                throw ValidationException::withMessages([
                    'financial_year' => 'Governed-required Form 16 generation requires an immutable payroll calculation snapshot for every approved payroll item.',
                ]);
            }

            $grossMinor = $this->safeAdd($grossMinor, MinorMoney::fromDecimal((string) $item->gross_earnings)->minor);
            $netMinor = $this->safeAdd($netMinor, MinorMoney::fromDecimal((string) $item->net_payable)->minor);
            $hasCompletePinnedTaxBasis = false;

            foreach ((array) $item->component_breakup as $component) {
                $amountMinor = isset($component['amount_minor'])
                    ? (int) $component['amount_minor']
                    : MinorMoney::fromDecimal((string) ($component['amount'] ?? 0))->minor;
                $code = strtoupper((string) ($component['component_code'] ?? 'UNKNOWN'));
                if (in_array($code, $tdsCodes, true)) {
                    $tdsMinor = $this->safeAdd($tdsMinor, $amountMinor);
                }
                $this->mergeComponent($componentSummary, [
                    'component_code' => $code,
                    'component_name' => $component['component_name'] ?? $code,
                    'component_type' => $component['component_type'] ?? 'earning',
                    'is_statutory' => (bool) ($component['is_statutory'] ?? false),
                    'amount_minor' => $amountMinor,
                ]);
            }
        }

        $standardDeductionMinor = $this->standardDeductionMinor($taxSetting);
        $taxableIncomeMinor = $hasCompletePinnedTaxBasis
            ? $pinnedTaxableMinor
            : max($grossMinor - $standardDeductionMinor, 0);
        $calculationMode = $governedRequired
            ? StatutoryPayrollCutoverManifest::MODE_GOVERNED_REQUIRED
            : ($governedSnapshots->isNotEmpty()
                ? StatutoryPayrollCutoverManifest::MODE_HYBRID
                : StatutoryPayrollCutoverManifest::MODE_LEGACY);

        return new TaxDocumentPayrollSummary(
            grossMinor: $grossMinor,
            taxableIncomeMinor: $taxableIncomeMinor,
            tdsMinor: $tdsMinor,
            netMinor: $netMinor,
            componentSummary: collect($componentSummary)->values()->map(function (array $component): array {
                $component['amount'] = MinorMoney::fromMinor($component['amount_minor'])->toDecimal();

                return $component;
            })->all(),
            periods: $this->periods($payrollRuns),
            provenance: [
                'source' => $snapshotProvenance === [] ? 'legacy_payroll_run_items' : 'immutable_payroll_calculation_snapshots',
                'taxable_income_method' => $hasCompletePinnedTaxBasis ? 'pinned_tax_line_basis' : 'legacy_standard_deduction',
                'standard_deduction_minor' => $hasCompletePinnedTaxBasis ? null : $standardDeductionMinor,
                'snapshot_count' => count($snapshotProvenance),
                'snapshots' => $snapshotProvenance,
            ],
            calculationMode: $calculationMode,
        );
    }

    private function isGovernedSnapshot(?PayrollCalculationSnapshot $snapshot): bool
    {
        return $snapshot !== null && data_get($snapshot->rule_context, 'mode') === 'governed_verified';
    }

    /** @param Collection<int, array{run:PayrollRun,item:PayrollRunItem,snapshot:?PayrollCalculationSnapshot}> $items */
    private function assertGovernedRequiredEvidence(Collection $items, SystemSetting $taxSetting): void
    {
        $this->taxSettingVerifier->assertVerified($taxSetting, 'financial_year');

        foreach ($items as $entry) {
            $snapshot = $entry['snapshot'];
            if (! $this->isGovernedSnapshot($snapshot)
                || data_get($snapshot?->rule_context, 'cutover_mode') !== StatutoryPayrollCutoverManifest::MODE_GOVERNED_REQUIRED) {
                throw ValidationException::withMessages([
                    'financial_year' => 'Governed-required Form 16 generation cannot mix legacy or unverified payroll calculations.',
                ]);
            }

            $this->snapshotVerifier->assertGovernedIntegrity($snapshot, 'financial_year');

            $pinnedTaxSettings = collect((array) data_get($snapshot->rule_context, 'settings', []))
                ->filter(fn (mixed $setting): bool => is_array($setting) && ($setting['setting_key'] ?? null) === 'payroll.tax_rules')
                ->values();
            if ($pinnedTaxSettings->count() !== 1) {
                throw ValidationException::withMessages([
                    'financial_year' => 'Governed-required Form 16 generation requires exactly one pinned payroll.tax_rules version in every payroll calculation snapshot.',
                ]);
            }

            foreach ($pinnedTaxSettings as $pinned) {
                $this->taxSettingVerifier->assertPinnedMatches($taxSetting, $pinned, 'financial_year');
                if (! $this->hasOfficialSourceEvidence((array) ($pinned['source_evidence'] ?? []))) {
                    throw ValidationException::withMessages([
                        'financial_year' => 'Governed-required Form 16 generation requires verified official-source evidence in every pinned payroll tax rule.',
                    ]);
                }
            }

            $taxSettingIds = $pinnedTaxSettings->pluck('setting_id')->map(fn (mixed $id): int => (int) $id)->all();
            $hasPinnedTaxLine = $snapshot->lines->contains(fn ($line): bool => in_array((int) $line->system_setting_id, $taxSettingIds, true)
                && in_array(strtoupper((string) $line->component_code), $this->tdsCodes($taxSetting, true), true));
            if (! $hasPinnedTaxLine) {
                throw ValidationException::withMessages([
                    'financial_year' => 'Governed-required Form 16 generation requires a pinned tax calculation line and taxable basis for every payroll period.',
                ]);
            }
        }
    }

    private function assertSnapshotTotals(PayrollCalculationSnapshot $snapshot, bool $failClosed): void
    {
        if ((int) $snapshot->gross_minor - (int) $snapshot->deduction_minor === (int) $snapshot->net_minor) {
            return;
        }

        if ($failClosed) {
            throw ValidationException::withMessages([
                'financial_year' => 'A governed payroll calculation snapshot failed its gross, deduction, and net reconciliation.',
            ]);
        }
    }

    /** @param list<array<string, mixed>> $sources */
    private function hasOfficialSourceEvidence(array $sources): bool
    {
        if ($sources === []) {
            return false;
        }

        return collect($sources)->every(function (mixed $source): bool {
            if (! is_array($source)) {
                return false;
            }

            foreach (['authority', 'title', 'document_reference', 'published_or_accessed_on'] as $key) {
                if (trim((string) ($source[$key] ?? '')) === '') {
                    return false;
                }
            }

            if (preg_match('/^[a-f0-9]{64}$/i', (string) ($source['source_checksum'] ?? '')) !== 1) {
                return false;
            }

            $url = (string) ($source['url'] ?? '');
            $host = strtolower((string) parse_url($url, PHP_URL_HOST));

            return str_starts_with(strtolower($url), 'https://')
                && ($host === 'gov.in' || $host === 'nic.in' || str_ends_with($host, '.gov.in') || str_ends_with($host, '.nic.in'));
        });
    }

    private function pinnedTaxLines(PayrollCalculationSnapshot $snapshot, Collection $taxLines): Collection
    {
        $taxSettingIds = collect((array) data_get($snapshot->rule_context, 'settings', []))
            ->filter(fn (mixed $setting): bool => is_array($setting) && ($setting['setting_key'] ?? null) === 'payroll.tax_rules')
            ->pluck('setting_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        return $taxLines->filter(fn ($line): bool => in_array((int) $line->system_setting_id, $taxSettingIds, true));
    }

    /** @return list<string> */
    private function tdsCodes(SystemSetting $taxSetting, bool $required): array
    {
        $raw = data_get($taxSetting->value, 'tds_component_codes');
        if (! is_array($raw) || $raw === []) {
            if ($required) {
                throw ValidationException::withMessages([
                    'financial_year' => 'Governed-required Form 16 generation requires explicit TDS component codes in the verified payroll tax rule.',
                ]);
            }

            return ['TDS', 'INCOME_TAX'];
        }

        return collect($raw)
            ->map(fn (mixed $code): string => strtoupper(trim((string) $code)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function standardDeductionMinor(SystemSetting $taxSetting): int
    {
        $value = $taxSetting->value ?? [];
        if (isset($value['standard_deduction_minor'])) {
            return max((int) $value['standard_deduction_minor'], 0);
        }

        return MinorMoney::fromDecimal((string) ($value['standard_deduction'] ?? 0))->minor;
    }

    /** @param array<string, array<string, mixed>> $summary */
    private function mergeComponent(array &$summary, array $component): void
    {
        $code = strtoupper((string) $component['component_code']);
        $summary[$code] ??= [
            'component_code' => $code,
            'component_name' => $component['component_name'],
            'component_type' => $component['component_type'],
            'is_statutory' => (bool) $component['is_statutory'],
            'amount_minor' => 0,
        ];
        $summary[$code]['amount_minor'] = $this->safeAdd($summary[$code]['amount_minor'], (int) $component['amount_minor']);
        $summary[$code]['is_statutory'] = $summary[$code]['is_statutory'] || (bool) $component['is_statutory'];
    }

    /** @param Collection<int, PayrollRun> $payrollRuns */
    private function periods(Collection $payrollRuns): array
    {
        return $payrollRuns->map(function (PayrollRun $run): array {
            $grossMinor = 0;
            $deductionMinor = 0;
            $netMinor = 0;
            foreach ($run->items as $item) {
                $snapshot = $item->calculationSnapshot;
                $grossMinor = $this->safeAdd($grossMinor, $snapshot?->gross_minor ?? MinorMoney::fromDecimal((string) $item->gross_earnings)->minor);
                $deductionMinor = $this->safeAdd($deductionMinor, $snapshot?->deduction_minor ?? MinorMoney::fromDecimal((string) $item->total_deductions)->minor);
                $netMinor = $this->safeAdd($netMinor, $snapshot?->net_minor ?? MinorMoney::fromDecimal((string) $item->net_payable)->minor);
            }

            return [
                'run_number' => $run->run_number,
                'period_year' => $run->period_year,
                'period_month' => $run->period_month,
                'gross_earnings_minor' => $grossMinor,
                'total_deductions_minor' => $deductionMinor,
                'net_payable_minor' => $netMinor,
                'gross_earnings' => MinorMoney::fromMinor($grossMinor)->toDecimal(),
                'total_deductions' => MinorMoney::fromMinor($deductionMinor)->toDecimal(),
                'net_payable' => MinorMoney::fromMinor($netMinor)->toDecimal(),
            ];
        })->values()->all();
    }

    /** @return array<string, mixed> */
    private function snapshotProvenance(PayrollCalculationSnapshot $snapshot): array
    {
        return [
            'snapshot_id' => $snapshot->id,
            'calculation_version' => $snapshot->calculation_version,
            'input_hash' => $snapshot->input_hash,
            'result_hash' => $snapshot->result_hash,
            'calculation_mode' => data_get($snapshot->rule_context, 'mode'),
            'cutover_mode' => data_get($snapshot->rule_context, 'cutover_mode'),
            'cutover_manifest' => data_get($snapshot->rule_context, 'cutover_manifest'),
            'settings' => data_get($snapshot->rule_context, 'settings', []),
        ];
    }

    private function safeAdd(int $left, int $right): int
    {
        if ($right < 0 || $left > PHP_INT_MAX - $right) {
            throw ValidationException::withMessages([
                'financial_year' => 'Payroll values exceed the supported deterministic minor-unit range.',
            ]);
        }

        return $left + $right;
    }

    private function sumMinor(Collection $values, string $field): int
    {
        return $values->reduce(
            fn (int $total, mixed $value): int => $this->safeAdd($total, (int) data_get($value, $field, 0)),
            0,
        );
    }
}
