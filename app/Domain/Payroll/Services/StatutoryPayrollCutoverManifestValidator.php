<?php

namespace App\Domain\Payroll\Services;

use App\Domain\Payroll\Data\StatutoryPayrollCutoverManifest;
use Illuminate\Validation\ValidationException;

final class StatutoryPayrollCutoverManifestValidator
{
    /** @param array<string, mixed> $definition */
    public function assertValid(array $definition): void
    {
        $errors = [];

        if (($definition['schema_version'] ?? null) !== StatutoryPayrollCutoverManifest::SCHEMA_VERSION) {
            $errors['value.schema_version'][] = 'The statutory payroll cutover manifest schema version must be 1.';
        }

        $mode = $definition['mode'] ?? null;
        if (! in_array($mode, [
            StatutoryPayrollCutoverManifest::MODE_LEGACY,
            StatutoryPayrollCutoverManifest::MODE_HYBRID,
            StatutoryPayrollCutoverManifest::MODE_GOVERNED_REQUIRED,
        ], true)) {
            $errors['value.mode'][] = 'Cutover mode must be legacy, hybrid, or governed_required.';
        }

        $requiredPacks = $definition['required_packs'] ?? null;
        if (! is_array($requiredPacks)) {
            $errors['value.required_packs'][] = 'Required packs must be an array.';
            $requiredPacks = [];
        }

        if ($mode === StatutoryPayrollCutoverManifest::MODE_LEGACY && $requiredPacks !== []) {
            $errors['value.required_packs'][] = 'Legacy mode cannot declare governed statutory packs.';
        }

        if ($mode === StatutoryPayrollCutoverManifest::MODE_GOVERNED_REQUIRED && $requiredPacks === []) {
            $errors['value.required_packs'][] = 'Governed-required mode must declare at least one required pack.';
        }

        $seenKeys = [];
        foreach ($requiredPacks as $index => $pack) {
            $prefix = "value.required_packs.$index";
            if (! is_array($pack)) {
                $errors[$prefix][] = 'Each required pack must be a structured object.';
                continue;
            }

            $settingKey = trim((string) ($pack['setting_key'] ?? ''));
            if (! in_array($settingKey, StatutoryRulePackDefinitionValidator::GOVERNED_SETTING_KEYS, true)) {
                $errors["$prefix.setting_key"][] = 'The required pack key is not a supported governed statutory setting.';
            } elseif (in_array($settingKey, $seenKeys, true)) {
                $errors["$prefix.setting_key"][] = 'Required pack keys must be unique within a cutover manifest.';
            }
            $seenKeys[] = $settingKey;

            $this->validateCodes($pack['state_codes'] ?? [], "$prefix.state_codes", '/^[A-Z]{2,8}$/', 'state', $errors);
            $this->validateCodes(
                $pack['replaces_component_codes'] ?? [],
                "$prefix.replaces_component_codes",
                '/^[A-Z0-9_-]{2,64}$/',
                'payroll component',
                $errors,
            );

            $unknown = array_diff(array_keys($pack), ['setting_key', 'state_codes', 'replaces_component_codes']);
            foreach ($unknown as $field) {
                $errors["$prefix.$field"][] = 'Unsupported cutover manifest field.';
            }
        }

        $unknown = array_diff(array_keys($definition), ['schema_version', 'mode', 'required_packs']);
        foreach ($unknown as $field) {
            $errors["value.$field"][] = 'Unsupported cutover manifest field.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param mixed $codes
     * @param array<string, list<string>> $errors
     */
    private function validateCodes(mixed $codes, string $key, string $pattern, string $label, array &$errors): void
    {
        if (! is_array($codes)) {
            $errors[$key][] = ucfirst($label).' codes must be an array.';
            return;
        }

        $normalized = [];
        foreach ($codes as $index => $code) {
            if (! is_string($code) || ! preg_match($pattern, $code)) {
                $errors["$key.$index"][] = ucfirst($label).' codes must use the governed uppercase code format.';
                continue;
            }
            $normalized[] = $code;
        }

        if (count($normalized) !== count(array_unique($normalized))) {
            $errors[$key][] = ucfirst($label).' codes must be unique.';
        }
    }
}
