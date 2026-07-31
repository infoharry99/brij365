<?php

namespace App\Domain\Payroll\Services;

use Illuminate\Validation\ValidationException;

final class StatutoryRulePackDefinitionValidator
{
    public const SCHEMA_VERSION = 1;

    /**
     * Some statutory authorities operate on a narrowly verified official
     * host outside the generic Government domains. Keep this explicit so a
     * lookalike domain cannot become trusted through a suffix match.
     *
     * @var list<string>
     */
    private const AUTHORITATIVE_SOURCE_HOSTS = [
        'mlwb.in',
    ];

    /**
     * Governed payroll packs are deliberately narrower than the legacy
     * compliance register. GST is not a payroll rule and must never enter
     * employee payroll merely because it shares the settings workflow.
     *
     * @var list<string>
     */
    public const GOVERNED_SETTING_KEYS = [
        'payroll.tax_rules',
        'hr.statutory.pf',
        'hr.statutory.esic',
        'hr.statutory.professional_tax',
        'hr.statutory.labour_welfare_fund',
        'hr.statutory.gratuity_bonus',
        'hr.leave.rules',
    ];

    /** @param array<string, mixed> $definition */
    public function assertValid(array $definition): void
    {
        $errors = [];

        if (($definition['governed_statutory_pack_version'] ?? null) !== self::SCHEMA_VERSION) {
            $errors['value.governed_statutory_pack_version'][] = 'The governed statutory pack schema version must be 1.';
        }

        if (($definition['statutory_validation_required'] ?? null) !== true) {
            $errors['value.statutory_validation_required'][] = 'Governed statutory packs must require independent statutory validation.';
        }

        if (! is_array($definition['approval_chain'] ?? null) || count($definition['approval_chain']) < 2) {
            $errors['value.approval_chain'][] = 'Governed statutory packs require a verifier and a separate approver in the approval chain.';
        }

        $evidence = $definition['source_evidence'] ?? null;
        if (! is_array($evidence) || $evidence === []) {
            $errors['value.source_evidence'][] = 'At least one official Government source is required.';
        } else {
            foreach ($evidence as $index => $source) {
                if (! is_array($source)) {
                    $errors["value.source_evidence.$index"][] = 'Each source must be a structured object.';
                    continue;
                }

                foreach (['authority', 'title', 'document_reference'] as $field) {
                    if (! is_string($source[$field] ?? null) || trim((string) $source[$field]) === '') {
                        $errors["value.source_evidence.$index.$field"][] = ucfirst(str_replace('_', ' ', $field)).' is required.';
                    }
                }

                if (($source['source_type'] ?? null) !== 'official_government') {
                    $errors["value.source_evidence.$index.source_type"][] = 'The source type must be official_government.';
                }

                if (! is_string($source['source_checksum'] ?? null)
                    || ! preg_match('/^[a-f0-9]{64}$/i', (string) $source['source_checksum'])) {
                    $errors["value.source_evidence.$index.source_checksum"][] = 'A SHA-256 checksum of the reviewed official source document or captured evidence is required.';
                }

                $sourceDate = (string) ($source['published_or_accessed_on'] ?? '');
                $parsedSourceDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $sourceDate);
                if ($parsedSourceDate === false || $parsedSourceDate->format('Y-m-d') !== $sourceDate) {
                    $errors["value.source_evidence.$index.published_or_accessed_on"][] = 'The official source publication or access date must use YYYY-MM-DD.';
                }

                $url = (string) ($source['url'] ?? '');
                if (! filter_var($url, FILTER_VALIDATE_URL) || ! str_starts_with(strtolower($url), 'https://')) {
                    $errors["value.source_evidence.$index.url"][] = 'An HTTPS official-source URL is required.';
                } else {
                    $host = $this->normalizedSourceHost((string) parse_url($url, PHP_URL_HOST));
                    if (! $this->isAuthoritativeSourceHost($host)) {
                        $errors["value.source_evidence.$index.url"][] = 'India statutory sources must use an approved official authority host.';
                    }
                }
            }
        }

        $proration = $definition['attendance_proration'] ?? null;
        if (! is_array($proration) || ! is_bool($proration['enabled'] ?? null)) {
            $errors['value.attendance_proration'][] = 'Attendance proration must explicitly define an enabled boolean.';
        } elseif (($proration['enabled'] ?? false) === true && (! is_array($proration['component_codes'] ?? null) || $proration['component_codes'] === [])) {
            $errors['value.attendance_proration.component_codes'][] = 'Enabled attendance proration requires at least one component code.';
        }

        $jurisdictions = $definition['jurisdictions'] ?? null;
        if (! is_array($jurisdictions) || $jurisdictions === []) {
            $errors['value.jurisdictions'][] = 'At least one jurisdiction definition is required.';
        } else {
            foreach ($jurisdictions as $index => $jurisdiction) {
                $this->validateJurisdiction($jurisdiction, $index, $errors);
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function normalizedSourceHost(string $host): string
    {
        $normalized = strtolower(rtrim(trim($host), '.'));

        return str_starts_with($normalized, 'www.') ? substr($normalized, 4) : $normalized;
    }

    private function isAuthoritativeSourceHost(string $host): bool
    {
        return in_array($host, ['gov.in', 'nic.in'], true)
            || str_ends_with($host, '.gov.in')
            || str_ends_with($host, '.nic.in')
            || in_array($host, self::AUTHORITATIVE_SOURCE_HOSTS, true);
    }

    /**
     * @param mixed $jurisdiction
     * @param array<string, list<string>> $errors
     */
    private function validateJurisdiction(mixed $jurisdiction, int $index, array &$errors): void
    {
        $prefix = "value.jurisdictions.$index";

        if (! is_array($jurisdiction)) {
            $errors[$prefix][] = 'Each jurisdiction must be a structured object.';
            return;
        }


        $fromDate = $this->dateValue($jurisdiction['effective_from'] ?? null, "$prefix.effective_from", $errors);
        $toDate = $this->dateValue($jurisdiction['effective_to'] ?? null, "$prefix.effective_to", $errors);
        if ($fromDate !== null && $toDate !== null && $toDate < $fromDate) {
            $errors["$prefix.effective_to"][] = 'The jurisdiction effective end date cannot precede its start date.';
        }

        $type = $jurisdiction['type'] ?? null;
        if (! in_array($type, ['central', 'state'], true)) {
            $errors["$prefix.type"][] = 'Jurisdiction type must be central or state.';
        }

        $code = strtoupper(trim((string) ($jurisdiction['code'] ?? '')));
        if (! preg_match('/^[A-Z]{2,8}$/', $code)) {
            $errors["$prefix.code"][] = 'Jurisdiction code must contain 2 to 8 uppercase letters.';
        }

        if (! in_array($jurisdiction['state_resolution'] ?? null, ['required_match', 'allow_no_match'], true)) {
            $errors["$prefix.state_resolution"][] = 'State resolution must be required_match or allow_no_match.';
        }

        if (! is_array($jurisdiction['applicability'] ?? null)) {
            $errors["$prefix.applicability"][] = 'Applicability must be a structured object.';
        } else {
            $this->validateApplicability($jurisdiction['applicability'], "$prefix.applicability", $errors);
        }

        $lines = $jurisdiction['lines'] ?? null;
        if (! is_array($lines) || $lines === []) {
            $errors["$prefix.lines"][] = 'At least one calculation line is required.';
            return;
        }

        $codes = [];
        foreach ($lines as $lineIndex => $line) {
            $linePrefix = "$prefix.lines.$lineIndex";
            if (! is_array($line)) {
                $errors[$linePrefix][] = 'Each calculation line must be a structured object.';
                continue;
            }

            $lineCode = strtoupper(trim((string) ($line['code'] ?? '')));
            if (! preg_match('/^[A-Z0-9_\-]{2,64}$/', $lineCode)) {
                $errors["$linePrefix.code"][] = 'A stable calculation line code is required.';
            } elseif (isset($codes[$lineCode])) {
                $errors["$linePrefix.code"][] = 'Calculation line codes must be unique within a jurisdiction.';
            } else {
                $codes[$lineCode] = true;
            }

            if (! is_string($line['name'] ?? null) || trim((string) $line['name']) === '') {
                $errors["$linePrefix.name"][] = 'Calculation line name is required.';
            }

            if (! in_array($line['line_type'] ?? null, ['earning', 'deduction', 'employer_contribution', 'tax_adjustment'], true)) {
                $errors["$linePrefix.line_type"][] = 'Unsupported calculation line type.';
            }

            $method = $line['method'] ?? null;
            if (! in_array($method, ['rate_ppm', 'fixed_minor', 'slab', 'annual_tax_projection'], true)) {
                $errors["$linePrefix.method"][] = 'Calculation method must be rate_ppm, fixed_minor, slab, or annual_tax_projection.';
                continue;
            }

            $basisCodes = $line['basis_codes'] ?? null;
            if (in_array($method, ['rate_ppm', 'slab', 'annual_tax_projection'], true) && (! is_array($basisCodes) || $basisCodes === [])) {
                $errors["$linePrefix.basis_codes"][] = 'Rate, slab, and annual tax projection calculations require basis codes.';
            } elseif (is_array($basisCodes)) {
                $this->validateBasisCodes($basisCodes, "$linePrefix.basis_codes", $errors);
            }

            if ($method === 'rate_ppm' && (! is_int($line['rate_ppm'] ?? null) || $line['rate_ppm'] < 0 || $line['rate_ppm'] > 1_000_000)) {
                $errors["$linePrefix.rate_ppm"][] = 'Rate must be an integer from 0 to 1,000,000 parts per million.';
            }

            if ($method === 'fixed_minor' && (! is_int($line['fixed_minor'] ?? null) || $line['fixed_minor'] < 0)) {
                $errors["$linePrefix.fixed_minor"][] = 'Fixed amount must be a non-negative integer in minor currency units.';
            }

            if ($method === 'slab') {
                $this->validateSlabs($line['slabs'] ?? null, "$linePrefix.slabs", $errors);
            }

            if ($method === 'annual_tax_projection') {
                $this->validateAnnualTaxProjection($line, $linePrefix, $errors);
            }

            foreach (['threshold_min_minor', 'threshold_max_minor', 'cap_minor'] as $minorField) {
                if (array_key_exists($minorField, $line) && (! is_int($line[$minorField]) || $line[$minorField] < 0)) {
                    $errors["$linePrefix.$minorField"][] = 'Minor-unit limits must be non-negative integers.';
                }
            }
        }
    }

    /** @param array<string, mixed> $line @param array<string, list<string>> $errors */
    private function validateAnnualTaxProjection(array $line, string $prefix, array &$errors): void
    {
        if (! in_array($line['line_type'] ?? null, ['deduction', 'tax_adjustment'], true)) {
            $errors["$prefix.line_type"][] = 'Annual tax projection must produce a deduction or tax_adjustment line.';
        }

        $basisCodes = collect((array) ($line['basis_codes'] ?? []))
            ->filter(fn (mixed $code): bool => is_string($code))
            ->map(fn (string $code): string => strtoupper(trim($code)))
            ->all();
        if (in_array('GROSS_EARNINGS', $basisCodes, true) && in_array('TAXABLE_EARNINGS', $basisCodes, true)) {
            $errors["$prefix.basis_codes"][] = 'Annual tax projection cannot combine GROSS_EARNINGS and TAXABLE_EARNINGS because both resolve to the same gross basis.';
        }

        $projection = $line['projection'] ?? null;
        if (! is_array($projection)) {
            $errors["$prefix.projection"][] = 'Annual tax projection requires a structured projection configuration.';
            return;
        }

        $startMonth = $projection['financial_year_start_month'] ?? null;
        if (! is_int($startMonth) || $startMonth < 1 || $startMonth > 12) {
            $errors["$prefix.projection.financial_year_start_month"][] = 'Financial year start month must be an integer from 1 to 12.';
        }

        $regimes = $projection['regime_slabs'] ?? null;
        if (! is_array($regimes) || $regimes === []) {
            $errors["$prefix.projection.regime_slabs"][] = 'Annual tax projection requires at least one regime slab definition.';
        } else {
            foreach ($regimes as $regime => $slabs) {
                if (! is_string($regime) || ! preg_match('/^[A-Za-z0-9_\-]{2,64}$/', $regime)) {
                    $errors["$prefix.projection.regime_slabs"][] = 'Tax regime codes must be stable identifiers.';
                    continue;
                }
                $this->validateSlabs($slabs, "$prefix.projection.regime_slabs.$regime", $errors);
            }
        }

        foreach ((array) ($projection['standard_deduction_minor'] ?? []) as $regime => $amount) {
            if (! is_int($amount) || $amount < 0) {
                $errors["$prefix.projection.standard_deduction_minor.$regime"][] = 'Standard deductions must be non-negative integer minor units.';
            }
        }

        foreach ((array) ($projection['rebate'] ?? []) as $regime => $rebate) {
            if (! is_array($rebate)
                || ! is_int($rebate['taxable_income_max_minor'] ?? null)
                || $rebate['taxable_income_max_minor'] < 0
                || ! is_int($rebate['rebate_minor'] ?? null)
                || $rebate['rebate_minor'] < 0) {
                $errors["$prefix.projection.rebate.$regime"][] = 'Rebates require non-negative taxable_income_max_minor and rebate_minor integers.';
            }
        }

        if (! is_int($projection['post_tax_rate_ppm'] ?? null)
            || $projection['post_tax_rate_ppm'] < 0
            || $projection['post_tax_rate_ppm'] > 1_000_000) {
            $errors["$prefix.projection.post_tax_rate_ppm"][] = 'Post-tax rate must be an integer from 0 to 1,000,000 parts per million.';
        }

        $withholdingCodes = $projection['withholding_component_codes'] ?? null;
        if (! is_array($withholdingCodes) || $withholdingCodes === []
            || collect($withholdingCodes)->contains(fn ($code): bool => ! is_string($code) || ! preg_match('/^[A-Za-z0-9_\-]{2,64}$/', $code))) {
            $errors["$prefix.projection.withholding_component_codes"][] = 'At least one stable withholding component code is required.';
        } elseif (count($withholdingCodes) !== count(array_unique(array_map('strtoupper', $withholdingCodes)))) {
            $errors["$prefix.projection.withholding_component_codes"][] = 'Withholding component codes must be unique.';
        }
    }

    /**
     * @param array<int|string, mixed> $basisCodes
     * @param array<string, list<string>> $errors
     */
    private function validateBasisCodes(array $basisCodes, string $prefix, array &$errors): void
    {
        $normalized = [];

        foreach ($basisCodes as $basisCode) {
            if (! is_string($basisCode)) {
                $errors[$prefix][] = 'Basis codes must be stable, non-empty identifiers.';
                continue;
            }

            $code = strtoupper(trim($basisCode));
            if (! preg_match('/^[A-Z0-9_\-]{2,64}$/', $code)) {
                $errors[$prefix][] = 'Basis codes must be stable, non-empty identifiers.';
                continue;
            }

            if (isset($normalized[$code])) {
                $errors[$prefix][] = 'Basis codes must be unique without regard to letter case.';
                continue;
            }

            $normalized[$code] = true;
        }
    }

    /**
     * @param array<string, mixed> $applicability
     * @param array<string, list<string>> $errors
     */
    private function validateApplicability(array $applicability, string $prefix, array &$errors): void
    {
        $allowed = ['employee_ids', 'excluded_employee_ids', 'employment_types', 'departments'];
        foreach (array_diff(array_keys($applicability), $allowed) as $unknown) {
            $errors["$prefix.$unknown"][] = 'Unsupported statutory applicability selector.';
        }

        foreach (['employee_ids', 'excluded_employee_ids'] as $field) {
            if (! array_key_exists($field, $applicability)) {
                continue;
            }

            $values = $applicability[$field];
            if (! is_array($values) || $values === [] || collect($values)->contains(fn (mixed $value): bool => ! is_int($value) || $value < 1)) {
                $errors["$prefix.$field"][] = 'Employee applicability lists must contain unique positive integer employee IDs.';
                continue;
            }

            if (count($values) !== count(array_unique($values))) {
                $errors["$prefix.$field"][] = 'Employee applicability lists may not contain duplicate IDs.';
            }
        }

        foreach (['employment_types', 'departments'] as $field) {
            if (! array_key_exists($field, $applicability)) {
                continue;
            }

            $values = $applicability[$field];
            if (! is_array($values) || $values === [] || collect($values)->contains(fn (mixed $value): bool => ! is_string($value) || trim($value) === '')) {
                $errors["$prefix.$field"][] = 'Population applicability lists must contain non-empty strings.';
                continue;
            }

            $normalized = collect($values)->map(fn (string $value): string => mb_strtolower(trim($value)))->all();
            if (count($normalized) !== count(array_unique($normalized))) {
                $errors["$prefix.$field"][] = 'Population applicability lists may not contain duplicate values.';
            }
        }

        $included = array_map('intval', (array) ($applicability['employee_ids'] ?? []));
        $excluded = array_map('intval', (array) ($applicability['excluded_employee_ids'] ?? []));
        if (array_intersect($included, $excluded) !== []) {
            $errors[$prefix][] = 'The same employee cannot be both included and excluded by one statutory jurisdiction.';
        }
    }

    /** @param array<string, list<string>> $errors */
    private function validateSlabs(mixed $slabs, string $prefix, array &$errors): void
    {
        if (! is_array($slabs) || $slabs === []) {
            $errors[$prefix][] = 'Slab calculations require at least one slab.';
            return;
        }

        $previousTo = null;
        foreach ($slabs as $index => $slab) {
            if (! is_array($slab) || ! is_int($slab['from_minor'] ?? null) || ($slab['from_minor'] ?? -1) < 0) {
                $errors["$prefix.$index.from_minor"][] = 'Each slab requires a non-negative from_minor integer.';
                continue;
            }

            if (isset($slab['to_minor']) && (! is_int($slab['to_minor']) || $slab['to_minor'] < 0)) {
                $errors["$prefix.$index.to_minor"][] = 'to_minor must be null or a non-negative integer.';
            }

            $from = (int) $slab['from_minor'];
            $to = isset($slab['to_minor']) && is_int($slab['to_minor']) ? $slab['to_minor'] : null;
            if ($to !== null && $to <= $from) {
                $errors["$prefix.$index.to_minor"][] = 'to_minor must be greater than from_minor.';
            }
            if ($previousTo !== null && $from !== $previousTo) {
                $errors["$prefix.$index.from_minor"][] = 'Progressive slabs must be contiguous and ordered.';
            }
            if ($previousTo === null && $index > 0) {
                $errors["$prefix.$index.from_minor"][] = 'An open-ended slab must be the final slab.';
            }
            $previousTo = $to;

            if (! is_int($slab['rate_ppm'] ?? null) || ($slab['rate_ppm'] ?? -1) < 0 || ($slab['rate_ppm'] ?? 1_000_001) > 1_000_000) {
                $errors["$prefix.$index.rate_ppm"][] = 'Each slab rate must be an integer from 0 to 1,000,000 parts per million.';
            }
        }
    }

    /** @param array<string, list<string>> $errors */
    private function dateValue(mixed $value, string $key, array &$errors): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value)) {
            $errors[$key][] = 'Effective dates must use YYYY-MM-DD.';

            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date === false || $date->format('Y-m-d') !== $value) {
            $errors[$key][] = 'Effective dates must use YYYY-MM-DD.';

            return null;
        }

        return $date;
    }
}
