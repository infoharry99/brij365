<?php

namespace App\Domain\Payroll\Services;

use App\Domain\Payroll\Data\StatutoryPayrollCutoverManifest;
use App\Services\Settings\SystemSettingResolver;
use Illuminate\Support\Carbon;

final class StatutoryPayrollCutoverManifestResolver
{
    public function __construct(
        private readonly SystemSettingResolver $settings,
        private readonly StatutoryPayrollCutoverManifestValidator $validator,
        private readonly CanonicalPayrollHasher $hasher,
    ) {}

    public function resolve(int $companyId, Carbon $effectiveOn): StatutoryPayrollCutoverManifest
    {
        $setting = $this->settings->active(
            $companyId,
            StatutoryPayrollCutoverManifest::SETTING_KEY,
            $effectiveOn,
            false,
        );

        // Backward-compatible default: verified packs may be introduced
        // incrementally, but none can replace a legacy component until an
        // approved manifest explicitly declares that replacement.
        if ($setting === null) {
            return new StatutoryPayrollCutoverManifest;
        }

        $definition = (array) $setting->value;
        $this->validator->assertValid($definition);

        $requiredPacks = collect((array) $definition['required_packs'])
            ->map(fn (array $pack): array => [
                'setting_key' => (string) $pack['setting_key'],
                'state_codes' => array_values((array) ($pack['state_codes'] ?? [])),
                'replaces_component_codes' => array_values((array) ($pack['replaces_component_codes'] ?? [])),
            ])
            ->values()
            ->all();

        return new StatutoryPayrollCutoverManifest(
            mode: (string) $definition['mode'],
            requiredPacks: $requiredPacks,
            settingId: $setting->id,
            settingVersion: $setting->version,
            checksum: $this->hasher->hash($definition),
        );
    }
}
