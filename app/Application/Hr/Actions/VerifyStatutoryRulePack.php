<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\HrCommandData;
use App\Domain\Payroll\Services\CanonicalPayrollHasher;
use App\Domain\Payroll\Services\StatutoryRulePackDefinitionValidator;
use App\Models\StatutoryRuleVerification;
use App\Models\SystemSetting;
use App\Services\Audit\AuditLogger;
use App\Services\Security\CompanyScopeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class VerifyStatutoryRulePack
{
    public function __construct(
        private readonly StatutoryRulePackDefinitionValidator $validator,
        private readonly CanonicalPayrollHasher $hasher,
        private readonly AuditLogger $auditLogger,
        private readonly CompanyScopeService $companyScope,
    ) {}

    public function execute(SystemSetting $systemSetting, HrCommandData $command): SystemSetting
    {
        return DB::transaction(function () use ($systemSetting, $command): SystemSetting {
            $setting = SystemSetting::query()->whereKey($systemSetting->id)->lockForUpdate()->firstOrFail();

            if (! $this->companyScope->allowsSettingMutation($command->actor, $setting->company_id)) {
                throw ValidationException::withMessages(['setting' => 'The statutory pack is outside your active company scope.']);
            }

            if (! in_array($setting->setting_key, StatutoryRulePackDefinitionValidator::GOVERNED_SETTING_KEYS, true)) {
                throw ValidationException::withMessages(['setting' => 'The selected setting is not a governed payroll rule pack.']);
            }

            if ($setting->status !== 'draft') {
                throw ValidationException::withMessages(['setting' => 'Only draft statutory rule packs may be verified.']);
            }
            if ($setting->created_by_user_id === $command->actor->id) {
                throw ValidationException::withMessages(['setting' => 'The statutory pack creator cannot independently verify the same version.']);
            }

            $definition = (array) $setting->value;
            $this->validator->assertValid($definition);
            $checksum = $this->hasher->hash($definition);

            StatutoryRuleVerification::query()->updateOrCreate(
                ['system_setting_id' => $setting->id],
                [
                    'company_id' => $setting->company_id,
                    'verified_by_user_id' => $command->actor->id,
                    'configuration_checksum' => $checksum,
                    'attestation' => (string) $command->attributes['attestation'],
                    'verified_at' => now(),
                ],
            );

            $history = $setting->workflow_history ?? [];
            $history[] = [
                'status' => 'verified',
                'actor' => $command->actor->name,
                'note' => 'Official-source statutory definition independently verified.',
                'at' => now()->toISOString(),
                'configuration_checksum' => $checksum,
            ];
            $setting->forceFill(['workflow_history' => $history])->save();

            $this->auditLogger->record(
                $command->actor,
                'payroll.statutory_rule_pack.verified',
                'Verified governed statutory rule pack sources',
                $setting,
                ['setting_key' => $setting->setting_key, 'version' => $setting->version, 'configuration_checksum' => $checksum],
                $command->request,
            );

            return $setting->load(['company', 'createdBy', 'approvedBy', 'statutoryVerification.verifiedBy']);
        });
    }
}
