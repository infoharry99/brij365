<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\HrCommandData;
use App\Domain\Payroll\Services\CanonicalPayrollHasher;
use App\Domain\Payroll\Services\StatutoryRulePackDefinitionValidator;
use App\Models\SystemSetting;
use App\Services\Settings\SystemSettingService;
use Illuminate\Validation\ValidationException;

final class ApproveComplianceRule
{
    public function __construct(
        private readonly SystemSettingService $service,
        private readonly StatutoryRulePackDefinitionValidator $statutoryValidator,
        private readonly CanonicalPayrollHasher $hasher,
    ) {}

    public function execute(SystemSetting $setting, HrCommandData $c): SystemSetting
    {
        $governed = data_get($setting->value, 'governed_statutory_pack_version') === StatutoryRulePackDefinitionValidator::SCHEMA_VERSION;
        if ($governed) {
            $definition = (array) $setting->value;
            $this->statutoryValidator->assertValid($definition);
            $setting->loadMissing('statutoryVerification');
            $verification = $setting->statutoryVerification;
            $checksum = $this->hasher->hash($definition);

            if ($verification === null || ! hash_equals($verification->configuration_checksum, $checksum)) {
                throw ValidationException::withMessages([
                    'setting' => 'The governed statutory pack requires current independent source verification before approval.',
                ]);
            }

            if ($verification->verified_by_user_id === $c->actor->id) {
                throw ValidationException::withMessages([
                    'setting' => 'The statutory source verifier cannot approve the same rule-pack version.',
                ]);
            }
        }

        if (
            ! $governed
            &&
            data_get($setting->value, 'statutory_validation_required') === true
            && data_get($setting->value, 'verified') !== true
        ) {
            throw ValidationException::withMessages([
                'setting' => 'Statutory validation must be verified before this compliance rule can be approved.',
            ]);
        }

        $approved = $this->service->approve($setting, $c->attributes, $c->actor, $c->request);

        return $governed
            ? $approved->loadMissing('statutoryVerification.verifiedBy')
            : $approved;
    }
}
