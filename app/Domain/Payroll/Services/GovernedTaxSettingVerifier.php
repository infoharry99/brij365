<?php

namespace App\Domain\Payroll\Services;

use App\Models\SystemSetting;
use Illuminate\Validation\ValidationException;

final class GovernedTaxSettingVerifier
{
    public function __construct(
        private readonly CanonicalPayrollHasher $hasher,
        private readonly StatutoryRulePackDefinitionValidator $rulePackValidator,
    ) {}

    /** @return array<string, mixed> */
    public function assertVerified(SystemSetting $setting, string $field = 'financial_year'): array
    {
        if ($setting->setting_key !== 'payroll.tax_rules') {
            $this->fail($field, 'The selected Form 16 configuration is not a payroll tax rule.');
        }

        $definition = (array) $setting->value;
        try {
            $this->rulePackValidator->assertValid($definition);
        } catch (ValidationException) {
            $this->fail($field, 'Governed-required Form 16 generation requires a complete, independently verifiable payroll tax rule definition.');
        }
        $checksum = $this->hasher->hash($definition);
        $setting->loadMissing('statutoryVerification');
        $verification = $setting->statutoryVerification;

        if ($verification === null
            || $verification->verified_by_user_id === null
            || ! hash_equals((string) $verification->configuration_checksum, $checksum)
            || $setting->created_by_user_id === null
            || $setting->approved_by_user_id === null
            || $verification->verified_by_user_id === $setting->created_by_user_id
            || $verification->verified_by_user_id === $setting->approved_by_user_id
            || $setting->approved_by_user_id === $setting->created_by_user_id) {
            $this->fail($field, 'Governed-required Form 16 generation requires a payroll tax rule independently verified and approved by separate authorized users.');
        }

        return [
            'configuration_checksum' => $checksum,
            'maker_user_id' => $setting->created_by_user_id,
            'verifier_user_id' => $verification->verified_by_user_id,
            'approver_user_id' => $setting->approved_by_user_id,
            'verified_at' => $verification->verified_at?->toISOString(),
            'source_evidence' => (array) ($definition['source_evidence'] ?? []),
        ];
    }

    /** @param array<string, mixed> $pin */
    public function assertPinnedMatches(SystemSetting $setting, array $pin, string $field = 'financial_year'): void
    {
        $provenance = $this->assertVerified($setting, $field);
        if ((int) ($pin['setting_id'] ?? 0) !== (int) $setting->id
            || ($pin['setting_key'] ?? null) !== $setting->setting_key
            || (int) ($pin['version'] ?? 0) !== (int) $setting->version
            || ! hash_equals((string) ($pin['checksum'] ?? ''), (string) $provenance['configuration_checksum'])) {
            $this->fail($field, 'Every governed payroll snapshot must pin the exact active payroll tax rule version used for Form 16 generation.');
        }
    }

    /** @param array<string, mixed> $configurationSnapshot */
    public function assertIssuable(array $configurationSnapshot, string $field = 'tax_document'): void
    {
        if (($configurationSnapshot['calculation_mode'] ?? null) !== 'governed_required') {
            return;
        }

        $checksum = (string) ($configurationSnapshot['configuration_checksum'] ?? '');
        $templateStatus = strtolower(trim((string) ($configurationSnapshot['form16_template_status'] ?? '')));
        $legalApproved = ($configurationSnapshot['legal_template_approved'] ?? false) === true;
        $isPrototype = ($configurationSnapshot['is_prototype'] ?? false) === true;
        if (preg_match('/^[a-f0-9]{64}$/i', $checksum) !== 1
            || $templateStatus !== 'approved'
            || ! $legalApproved
            || $isPrototype) {
            $this->fail($field, 'This governed Form 16 cannot be issued until its non-prototype legal template is explicitly approved in the verified payroll tax rule.');
        }
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
