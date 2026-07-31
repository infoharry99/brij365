<?php

namespace App\Domain\Payroll\Data;

final readonly class StatutoryPayrollCutoverManifest
{
    public const SETTING_KEY = 'payroll.statutory_cutover';

    public const SCHEMA_VERSION = 1;

    public const MODE_LEGACY = 'legacy';

    public const MODE_HYBRID = 'hybrid';

    public const MODE_GOVERNED_REQUIRED = 'governed_required';

    /**
     * @param  list<array{setting_key:string, state_codes:list<string>, replaces_component_codes:list<string>}>  $requiredPacks
     */
    public function __construct(
        public string $mode = self::MODE_HYBRID,
        public array $requiredPacks = [],
        public ?int $settingId = null,
        public ?int $settingVersion = null,
        public ?string $checksum = null,
    ) {}

    public function usesGovernedPacks(): bool
    {
        return $this->mode !== self::MODE_LEGACY;
    }

    public function requiresCompleteCutover(): bool
    {
        return $this->mode === self::MODE_GOVERNED_REQUIRED;
    }

    /** @return list<string> */
    public function requiredPackKeysForState(?string $statutoryState): array
    {
        $state = strtoupper(trim((string) $statutoryState));

        return collect($this->requiredPacks)
            ->filter(function (array $pack) use ($state): bool {
                $states = $pack['state_codes'];

                return $states === [] || ($state !== '' && in_array($state, $states, true));
            })
            ->pluck('setting_key')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $resolvedPackKeys
     * @return list<string>
     */
    public function replacementComponentCodesForState(array $resolvedPackKeys, ?string $statutoryState): array
    {
        $state = strtoupper(trim((string) $statutoryState));

        return collect($this->requiredPacks)
            ->filter(function (array $pack) use ($resolvedPackKeys, $state): bool {
                $states = $pack['state_codes'];

                return in_array($pack['setting_key'], $resolvedPackKeys, true)
                    && ($states === [] || ($state !== '' && in_array($state, $states, true)));
            })
            ->flatMap(fn (array $pack): array => $pack['replaces_component_codes'])
            ->unique()
            ->values()
            ->all();
    }
}
