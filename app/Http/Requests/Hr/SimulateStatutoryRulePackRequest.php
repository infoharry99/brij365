<?php

namespace App\Http\Requests\Hr;

use App\Domain\Payroll\Services\StatutoryRulePackDefinitionValidator;
use App\Domain\Payroll\ValueObjects\MinorMoney;
use App\Domain\Scoring\Support\LogicCenterPermissions;
use App\Models\SystemSetting;
use App\Services\Security\CompanyScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class SimulateStatutoryRulePackRequest extends FormRequest
{
    public function authorize(): bool
    {
        $setting = $this->route('systemSetting');
        $user = $this->user();

        return $setting instanceof SystemSetting
            && $user !== null
            && ($user->hasPermission(LogicCenterPermissions::STATUTORY_SIMULATE) || $user->hasPermission('payroll.manage'))
            && app(CompanyScopeService::class)->allowsSettingRead($user, $setting->company_id);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'statutory_state' => ['required', 'string', 'regex:/^[A-Za-z]{2,8}$/'],
            'components' => ['required_without:component_codes', 'array', 'min:1', 'max:100'],
            'components.*' => ['required', 'integer', 'min:0'],
            'component_codes' => ['required_without:components', 'array', 'min:1', 'max:20'],
            'component_codes.*' => ['nullable', 'string', 'regex:/^[A-Za-z][A-Za-z0-9_]{1,63}$/'],
            'component_amounts' => ['required_with:component_codes', 'array', 'max:20'],
            'component_amounts.*' => ['nullable', 'string', 'regex:/^\d+(?:\.\d{1,2})?$/'],
            'employee_context' => ['sometimes', 'array'],
            'employee_context.employee_id' => ['sometimes', 'integer', 'min:1'],
            'employee_context.employment_type' => ['sometimes', 'string', 'max:100'],
            'employee_context.department' => ['sometimes', 'string', 'max:160'],
            'employee_context.tax_projection' => ['sometimes', 'array'],
            'employee_context.tax_projection.employee_tax_profile_id' => ['required_with:employee_context.tax_projection', 'integer', 'min:1'],
            'employee_context.tax_projection.employee_tax_profile_version' => ['required_with:employee_context.tax_projection', 'integer', 'min:1'],
            'employee_context.tax_projection.employee_tax_profile_checksum' => ['required_with:employee_context.tax_projection', 'string', 'regex:/^[a-f0-9]{64}$/i'],
            'employee_context.tax_projection.financial_year' => ['required_with:employee_context.tax_projection', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'employee_context.tax_projection.regime_code' => ['required_with:employee_context.tax_projection', 'string', 'regex:/^[A-Za-z0-9_\-]{2,64}$/'],
            'employee_context.tax_projection.actual_ytd_taxable_minor' => ['required_with:employee_context.tax_projection', 'integer', 'min:0'],
            'employee_context.tax_projection.actual_ytd_withheld_minor' => ['required_with:employee_context.tax_projection', 'integer', 'min:0'],
            'employee_context.tax_projection.previous_employer_income_minor' => ['required_with:employee_context.tax_projection', 'integer', 'min:0'],
            'employee_context.tax_projection.previous_employer_tds_minor' => ['required_with:employee_context.tax_projection', 'integer', 'min:0'],
            'employee_context.tax_projection.projected_other_income_minor' => ['required_with:employee_context.tax_projection', 'integer', 'min:0'],
            'employee_context.tax_projection.verified_deduction_minor' => ['required_with:employee_context.tax_projection', 'integer', 'min:0'],
            'employee_context.tax_projection.verified_exemption_minor' => ['required_with:employee_context.tax_projection', 'integer', 'min:0'],
            'employee_context.tax_projection.remaining_payroll_periods' => ['required_with:employee_context.tax_projection', 'integer', 'between:1,12'],
            'return_to' => ['nullable', 'string', 'in:logic_center'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $setting = $this->route('systemSetting');
                if ($setting instanceof SystemSetting
                    && data_get($setting->value, 'governed_statutory_pack_version') !== StatutoryRulePackDefinitionValidator::SCHEMA_VERSION) {
                    $validator->errors()->add('setting', 'Only governed statutory packs support deterministic simulation.');
                }

                if (! $this->filled('component_codes')) {
                    return;
                }

                $codes = (array) $this->input('component_codes', []);
                $amounts = (array) $this->input('component_amounts', []);
                $pairs = collect($codes)->map(fn (mixed $code, int $index): array => [
                    'code' => strtoupper(trim((string) $code)),
                    'amount' => trim((string) ($amounts[$index] ?? '')),
                ])->filter(fn (array $pair): bool => $pair['code'] !== '' || $pair['amount'] !== '');

                if ($pairs->isEmpty()) {
                    $validator->errors()->add('component_codes', 'Add at least one earnings component for the simulation.');
                }

                if ($pairs->contains(fn (array $pair): bool => $pair['code'] === '' || $pair['amount'] === '')) {
                    $validator->errors()->add('component_codes', 'Every simulation component requires both a code and an amount.');
                }

                if ($pairs->pluck('code')->duplicates()->isNotEmpty()) {
                    $validator->errors()->add('component_codes', 'Simulation component codes must be unique.');
                }
            },
        ];
    }

    /** @return array<string, mixed> */
    public function simulationPayload(): array
    {
        $validated = $this->validated();
        if (isset($validated['components'])) {
            return collect($validated)->only(['statutory_state', 'components', 'employee_context'])->all();
        }

        $amounts = (array) ($validated['component_amounts'] ?? []);
        $components = collect((array) ($validated['component_codes'] ?? []))
            ->mapWithKeys(function (mixed $code, int $index) use ($amounts): array {
                $normalizedCode = strtoupper(trim((string) $code));
                if ($normalizedCode === '') {
                    return [];
                }

                return [$normalizedCode => MinorMoney::fromDecimal((string) ($amounts[$index] ?? '0'))->minor];
            })->all();

        return [
            'statutory_state' => strtoupper((string) $validated['statutory_state']),
            'components' => $components,
            'employee_context' => (array) ($validated['employee_context'] ?? []),
        ];
    }
}
