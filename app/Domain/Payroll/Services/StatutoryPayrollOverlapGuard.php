<?php

namespace App\Domain\Payroll\Services;

use App\Domain\Payroll\Data\GovernedStatutoryRuleSet;
use App\Domain\Payroll\Data\StatutoryPayrollCutoverManifest;
use App\Models\SalaryStructure;
use Illuminate\Validation\ValidationException;

final class StatutoryPayrollOverlapGuard
{
    /** @var array<string, list<string>> */
    private const PACK_ALIASES = [
        'payroll.tax_rules' => ['TDS', 'INCOME_TAX', 'TAX_DEDUCTED_AT_SOURCE'],
        'hr.statutory.pf' => ['PF', 'EPF', 'PROVIDENT_FUND', 'EMPLOYEE_PROVIDENT_FUND'],
        'hr.statutory.esic' => ['ESIC', 'ESI', 'EMPLOYEE_STATE_INSURANCE'],
        'hr.statutory.professional_tax' => ['PT', 'PROFESSIONAL_TAX'],
        'hr.statutory.labour_welfare_fund' => ['LWF', 'LABOUR_WELFARE_FUND'],
        'hr.statutory.gratuity_bonus' => ['GRATUITY', 'STATUTORY_BONUS', 'BONUS'],
        'hr.leave.rules' => ['LEAVE_ENCASHMENT'],
    ];

    public function assertNoUnmappedOverlap(SalaryStructure $structure, GovernedStatutoryRuleSet $ruleSet): void
    {
        if (! $ruleSet->isGoverned() || $ruleSet->cutoverMode !== StatutoryPayrollCutoverManifest::MODE_HYBRID) {
            return;
        }

        $structure->loadMissing('components.payrollComponent');
        $replacementCodes = collect($ruleSet->replacedLegacyComponentCodes)
            ->map(fn (mixed $code): string => $this->normalize((string) $code))
            ->filter()
            ->flip();
        $governedCodes = collect($ruleSet->rules)
            ->flatMap(fn (array $rule): array => (array) data_get($rule, 'jurisdiction.lines', []))
            ->map(fn (array $line): string => $this->normalize((string) ($line['code'] ?? '')))
            ->filter()
            ->unique()
            ->flip();
        $governedPackKeys = collect($ruleSet->rules)
            ->pluck('setting_key')
            ->filter()
            ->unique()
            ->values();

        $overlaps = [];
        foreach ($structure->components as $structureComponent) {
            $component = $structureComponent->payrollComponent;
            if ($component === null || ! (bool) $component->is_statutory) {
                continue;
            }

            $code = $this->normalize((string) $component->code);
            if ($code === '' || $replacementCodes->has($code)) {
                continue;
            }

            $name = $this->normalize((string) $component->name);
            $directOverlap = $governedCodes->has($code);
            $semanticOverlap = $governedPackKeys->contains(function (string $settingKey) use ($code, $name): bool {
                foreach (self::PACK_ALIASES[$settingKey] ?? [] as $alias) {
                    if ($this->matchesAlias($code, $alias) || $this->matchesAlias($name, $alias)) {
                        return true;
                    }
                }

                return false;
            });

            if ($directOverlap || $semanticOverlap) {
                $overlaps[] = strtoupper((string) $component->code);
            }
        }

        if ($overlaps !== []) {
            throw ValidationException::withMessages([
                'statutory_rules' => 'Hybrid statutory payroll would calculate both legacy and governed versions of these statutory components: '
                    .implode(', ', array_values(array_unique($overlaps)))
                    .'. Add every intentional replacement to payroll.statutory_cutover required_packs.replaces_component_codes before generation.',
            ]);
        }
    }

    private function matchesAlias(string $value, string $alias): bool
    {
        $alias = $this->normalize($alias);

        return $value === $alias
            || str_starts_with($value, $alias.'_')
            || str_ends_with($value, '_'.$alias)
            || str_contains($value, '_'.$alias.'_');
    }

    private function normalize(string $value): string
    {
        return trim((string) preg_replace('/[^A-Z0-9]+/', '_', strtoupper(trim($value))), '_');
    }
}
