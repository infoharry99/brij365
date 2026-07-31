<?php

namespace App\Http\Requests\Hr;

use App\Domain\Payroll\Services\StatutoryRulePackDefinitionValidator;
use App\Domain\Scoring\Support\LogicCenterPermissions;
use App\Services\Security\CompanyScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreComplianceRuleSettingRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $value = $this->input('value');
        if (! is_array($value)) {
            return;
        }

        if (isset($value['governed_statutory_pack_version']) && is_numeric($value['governed_statutory_pack_version'])) {
            $value['governed_statutory_pack_version'] = (int) $value['governed_statutory_pack_version'];
        }

        if (($value['governed_statutory_pack_version'] ?? null) === StatutoryRulePackDefinitionValidator::SCHEMA_VERSION) {
            $value = $this->normalizeGovernedPack($value);
        }

        foreach (['verified', 'statutory_validation_required', 'payroll_year_locked'] as $key) {
            if (! array_key_exists($key, $value)) {
                continue;
            }

            $boolean = filter_var($value[$key], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($boolean !== null) {
                $value[$key] = $boolean;
            }
        }

        $this->merge(['value' => $value]);
    }

    public function authorize(): bool
    {
        $user = $this->user();

        return $user?->hasPermission('compliance.manage') === true
            || $user?->hasPermission('settings.manage') === true
            || $user?->hasPermission(LogicCenterPermissions::STATUTORY_MANAGE) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')],
            'setting_key' => ['required', 'string', Rule::in(ComplianceRuleSettingIndexRequest::ALLOWED_SETTING_KEYS)],
            'label' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'value' => ['required', 'array'],
            'effective_from' => ['required', 'date'],
            'metadata' => ['nullable', 'array'],
            'return_to' => ['nullable', 'string', Rule::in(['compliance', 'logic_center'])],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $user = $this->user();
                $scope = app(CompanyScopeService::class);

                if (! $user || $scope->settingCompanyIdFor($user) === 0) {
                    $validator->errors()->add('company_id', 'A valid company assignment is required before creating compliance rule settings.');

                    return;
                }

                if ($this->filled('company_id') && ! $scope->allowsSettingMutation($user, $this->integer('company_id'))) {
                    $validator->errors()->add('company_id', 'You can create compliance rules only for your active company scope.');
                }

                $value = $this->input('value');
                if (! is_array($value)) {
                    return;
                }

                foreach (['approval_chain', 'statutory_validation_required'] as $requiredKey) {
                    if (! array_key_exists($requiredKey, $value)) {
                        $validator->errors()->add('value.'.$requiredKey, 'Compliance rule settings must include '.$requiredKey.'.');
                    }
                }

                if (isset($value['approval_chain']) && (! is_array($value['approval_chain']) || count($value['approval_chain']) < 1)) {
                    $validator->errors()->add('value.approval_chain', 'At least one approval step is required.');
                }

                if (isset($value['verified']) && ! is_bool($value['verified'])) {
                    $validator->errors()->add('value.verified', 'The verified flag must be boolean.');
                }

                if (isset($value['statutory_validation_required']) && ! is_bool($value['statutory_validation_required'])) {
                    $validator->errors()->add('value.statutory_validation_required', 'The statutory validation flag must be boolean.');
                }

                if (($value['governed_statutory_pack_version'] ?? null) === StatutoryRulePackDefinitionValidator::SCHEMA_VERSION) {
                    if (! in_array($this->input('setting_key'), StatutoryRulePackDefinitionValidator::GOVERNED_SETTING_KEYS, true)) {
                        $validator->errors()->add('setting_key', 'The selected setting key cannot be activated as a governed payroll rule pack.');
                    }

                    try {
                        app(StatutoryRulePackDefinitionValidator::class)->assertValid($value);
                    } catch (\Illuminate\Validation\ValidationException $exception) {
                        foreach ($exception->errors() as $key => $messages) {
                            foreach ($messages as $message) {
                                $validator->errors()->add($key, $message);
                            }
                        }
                    }

                    return;
                }

                match ($this->input('setting_key')) {
                    'payroll.tax_rules' => $this->validatePayrollTaxRules($validator, $value),
                    'finance.gst_rules' => $this->validateGstRules($validator, $value),
                    'hr.leave.rules' => $this->validateLeaveRules($validator, $value),
                    default => $this->validateHrStatutoryRules($validator, $value),
                };
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function normalizedPayload(): array
    {
        $settingKey = (string) $this->input('setting_key');
        $group = str($settingKey)->before('.')->toString();

        return [
            'company_id' => $this->filled('company_id') ? (int) $this->input('company_id') : null,
            'setting_group' => $group,
            'setting_key' => $settingKey,
            'label' => $this->string('label')->toString(),
            'description' => $this->input('description'),
            'value_type' => 'object',
            'value' => $this->input('value'),
            'effective_from' => $this->input('effective_from'),
            'metadata' => array_merge(
                [
                    'source' => $this->input('return_to') === 'logic_center' ? 'people_logic_center' : 'hr_compliance_center',
                    'restricted_to_statutory_rule_keys' => true,
                ],
                (array) $this->input('metadata', []),
            ),
        ];
    }

    /** @param array<string, mixed> $value
     *  @return array<string, mixed>
     */
    private function normalizeGovernedPack(array $value): array
    {
        $value['statutory_validation_required'] = true;
        $value['approval_chain'] = collect((array) ($value['approval_chain'] ?? []))
            ->map(fn (mixed $item): string => trim((string) $item))
            ->filter()->values()->all();

        if (isset($value['attendance_proration']) && is_array($value['attendance_proration'])) {
            $value['attendance_proration']['enabled'] = filter_var(
                $value['attendance_proration']['enabled'] ?? false,
                FILTER_VALIDATE_BOOLEAN,
            );
            $value['attendance_proration']['component_codes'] = $this->stringList(
                $value['attendance_proration']['component_codes'] ?? [],
                true,
            );
        }

        $value['source_evidence'] = collect((array) ($value['source_evidence'] ?? []))
            ->filter(fn (mixed $source): bool => is_array($source) && collect($source)->filter(fn (mixed $entry): bool => trim((string) $entry) !== '')->isNotEmpty())
            ->map(function (array $source): array {
                $source['source_type'] = 'official_government';
                if (isset($source['source_checksum'])) {
                    $source['source_checksum'] = strtolower(trim((string) $source['source_checksum']));
                }

                return $source;
            })->values()->all();

        $value['jurisdictions'] = collect((array) ($value['jurisdictions'] ?? []))
            ->filter(fn (mixed $jurisdiction): bool => is_array($jurisdiction))
            ->map(function (array $jurisdiction): array {
                $jurisdiction['code'] = strtoupper(trim((string) ($jurisdiction['code'] ?? '')));
                $applicability = is_array($jurisdiction['applicability'] ?? null) ? $jurisdiction['applicability'] : [];
                foreach (['employee_ids', 'excluded_employee_ids'] as $field) {
                    if (array_key_exists($field, $applicability)) {
                        $applicability[$field] = collect($this->stringList($applicability[$field]))
                            ->filter(fn (string $entry): bool => ctype_digit($entry) && (int) $entry > 0)
                            ->map(fn (string $entry): int => (int) $entry)->unique()->values()->all();
                    }
                }
                foreach (['employment_types', 'departments'] as $field) {
                    if (array_key_exists($field, $applicability)) {
                        $applicability[$field] = $this->stringList($applicability[$field]);
                    }
                }
                $jurisdiction['applicability'] = collect($applicability)->filter(fn (mixed $items): bool => is_array($items) && $items !== [])->all();
                $jurisdiction['lines'] = collect((array) ($jurisdiction['lines'] ?? []))
                    ->filter(fn (mixed $line): bool => is_array($line) && trim((string) ($line['code'] ?? '')) !== '' && trim((string) ($line['name'] ?? '')) !== '')
                    ->map(function (array $line): array {
                        $line['code'] = strtoupper(trim((string) $line['code']));
                        $line['basis_codes'] = $this->stringList($line['basis_codes'] ?? [], true);
                        foreach (['rate_ppm', 'fixed_minor', 'threshold_min_minor', 'threshold_max_minor', 'cap_minor'] as $field) {
                            if (array_key_exists($field, $line) && $line[$field] !== '' && is_numeric($line[$field])) {
                                $line[$field] = (int) $line[$field];
                            } elseif (array_key_exists($field, $line) && $line[$field] === '') {
                                unset($line[$field]);
                            }
                        }

                        if (($line['method'] ?? null) === 'fixed_minor') {
                            unset($line['rate_ppm'], $line['slabs']);
                        } elseif (($line['method'] ?? null) === 'rate_ppm') {
                            unset($line['fixed_minor'], $line['slabs']);
                        }

                        return $line;
                    })->values()->all();

                return $jurisdiction;
            })->values()->all();

        return $value;
    }

    /** @return list<string> */
    private function stringList(mixed $value, bool $uppercase = false): array
    {
        $items = is_array($value) ? $value : explode(',', (string) $value);

        return collect($items)
            ->flatMap(fn (mixed $item): array => is_string($item) ? explode(',', $item) : [(string) $item])
            ->map(fn (string $item): string => $uppercase ? strtoupper(trim($item)) : trim($item))
            ->filter()->unique()->values()->all();
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function validatePayrollTaxRules(Validator $validator, array $value): void
    {
        foreach (['financial_year', 'form16_template_version'] as $key) {
            if (! is_string($value[$key] ?? null) || trim((string) $value[$key]) === '') {
                $validator->errors()->add('value.'.$key, $key.' is required for payroll tax rules.');
            }
        }

        if (! is_bool($value['payroll_year_locked'] ?? null)) {
            $validator->errors()->add('value.payroll_year_locked', 'payroll_year_locked must be boolean for Form 16 readiness.');
        }
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function validateGstRules(Validator $validator, array $value): void
    {
        foreach (['supported_transaction_types', 'default_tax_rates'] as $key) {
            if (! is_array($value[$key] ?? null) || count($value[$key]) < 1) {
                $validator->errors()->add('value.'.$key, $key.' must contain at least one configured value.');
            }
        }
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function validateLeaveRules(Validator $validator, array $value): void
    {
        if (! array_key_exists('encashment_formula', $value) || ! is_string($value['encashment_formula'])) {
            $validator->errors()->add('value.encashment_formula', 'Leave rules must include an encashment formula.');
        }
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function validateHrStatutoryRules(Validator $validator, array $value): void
    {
        foreach (['applicability', 'wage_basis', 'calculation_method', 'rates'] as $key) {
            if (! array_key_exists($key, $value)) {
                $validator->errors()->add('value.'.$key, 'HR statutory packs must include '.$key.'.');
            }
        }

        if (isset($value['rates']) && ! is_array($value['rates'])) {
            $validator->errors()->add('value.rates', 'Rates must be provided as a structured object.');
        }
    }
}
