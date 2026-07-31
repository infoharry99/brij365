<?php

namespace App\Domain\Payroll\Data;

final readonly class GovernedStatutoryRuleSet
{
    /**
     * @param list<array{setting_id:int, setting_key:string, version:int, checksum:string, jurisdiction:array<string, mixed>, attendance_proration:array<string, mixed>}> $rules
     * @param list<string> $replacedLegacyComponentCodes
     */
    public function __construct(
        public array $rules = [],
        public array $replacedLegacyComponentCodes = [],
        public string $cutoverMode = StatutoryPayrollCutoverManifest::MODE_HYBRID,
        public ?int $manifestSettingId = null,
        public ?int $manifestSettingVersion = null,
        public ?string $manifestChecksum = null,
    ) {}

    public function isGoverned(): bool
    {
        return $this->rules !== [];
    }

    /** @return list<int> */
    public function settingIds(): array
    {
        return array_values(array_unique(array_column($this->rules, 'setting_id')));
    }

    /** @return list<string> */
    public function settingKeys(): array
    {
        return array_values(array_unique(array_column($this->rules, 'setting_key')));
    }

    /** @param list<string> $replacementCodes */
    public function withCutoverManifest(StatutoryPayrollCutoverManifest $manifest, array $replacementCodes): self
    {
        return new self(
            rules: $this->rules,
            replacedLegacyComponentCodes: array_values(array_unique($replacementCodes)),
            cutoverMode: $manifest->mode,
            manifestSettingId: $manifest->settingId,
            manifestSettingVersion: $manifest->settingVersion,
            manifestChecksum: $manifest->checksum,
        );
    }
}
