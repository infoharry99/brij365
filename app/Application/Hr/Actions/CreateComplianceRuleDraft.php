<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\HrCommandData;
use App\Domain\Payroll\Services\StatutoryRulePackDefinitionValidator;
use App\Models\SystemSetting;
use App\Services\Settings\SystemSettingService;
use Illuminate\Validation\ValidationException;

final class CreateComplianceRuleDraft
{
    public function __construct(
        private readonly SystemSettingService $service,
        private readonly StatutoryRulePackDefinitionValidator $statutoryValidator,
    ) {}

    public function execute(HrCommandData $c): SystemSetting
    {
        if (data_get($c->attributes, 'value.governed_statutory_pack_version') === StatutoryRulePackDefinitionValidator::SCHEMA_VERSION) {
            if (! in_array((string) ($c->attributes['setting_key'] ?? ''), StatutoryRulePackDefinitionValidator::GOVERNED_SETTING_KEYS, true)) {
                throw ValidationException::withMessages([
                    'setting_key' => 'The selected setting key cannot be activated as a governed payroll rule pack.',
                ]);
            }

            $this->statutoryValidator->assertValid((array) data_get($c->attributes, 'value', []));
        }

        return $this->service->createDraft($c->attributes, $c->actor, $c->request);
    }
}
