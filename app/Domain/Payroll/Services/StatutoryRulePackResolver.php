<?php

namespace App\Domain\Payroll\Services;

use App\Domain\Payroll\Data\GovernedStatutoryRuleSet;
use App\Models\SystemSetting;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

final class StatutoryRulePackResolver
{
    public function __construct(
        private readonly CanonicalPayrollHasher $hasher,
        private readonly StatutoryRulePackDefinitionValidator $validator,
    ) {}

    public function resolve(int $companyId, ?string $statutoryState, Carbon $effectiveOn): GovernedStatutoryRuleSet
    {
        $settings = SystemSetting::query()
            ->with('statutoryVerification')
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->whereIn('setting_key', StatutoryRulePackDefinitionValidator::GOVERNED_SETTING_KEYS)
            ->whereDate('effective_from', '<=', $effectiveOn->toDateString())
            ->where(function ($query) use ($effectiveOn): void {
                $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $effectiveOn->toDateString());
            })
            ->orderBy('setting_key')
            ->orderByDesc('version')
            ->get()
            ->unique('setting_key')
            ->filter(fn (SystemSetting $setting): bool => data_get($setting->value, 'governed_statutory_pack_version') === StatutoryRulePackDefinitionValidator::SCHEMA_VERSION)
            ->values();

        if ($settings->isEmpty()) {
            return new GovernedStatutoryRuleSet;
        }

        $state = strtoupper(trim((string) $statutoryState));
        if ($state === '') {
            throw ValidationException::withMessages([
                'employees' => 'Every employee in governed payroll requires an authoritative statutory state.',
            ]);
        }

        $resolved = [];
        foreach ($settings as $setting) {
            $definition = (array) $setting->value;
            $this->validator->assertValid($definition);
            $verification = $setting->statutoryVerification;
            $checksum = $this->hasher->hash($definition);

            if ($verification === null
                || $verification->verified_by_user_id === null
                || ! hash_equals($verification->configuration_checksum, $checksum)) {
                throw ValidationException::withMessages([
                    'statutory_rules' => 'Governed statutory pack '.$setting->setting_key.' is active without a current independent verification.',
                ]);
            }

            if ($setting->approved_by_user_id === null
                || $verification->verified_by_user_id === $setting->created_by_user_id
                || $verification->verified_by_user_id === $setting->approved_by_user_id) {
                throw ValidationException::withMessages([
                    'statutory_rules' => 'Governed statutory pack '.$setting->setting_key.' does not satisfy creator, verifier, and approver separation.',
                ]);
            }

            $jurisdictions = collect($definition['jurisdictions'])
                ->filter(function (array $jurisdiction) use ($state, $effectiveOn): bool {
                    $matchesCode = $jurisdiction['type'] === 'central'
                        || strtoupper((string) $jurisdiction['code']) === $state;
                    $starts = empty($jurisdiction['effective_from']) || Carbon::parse($jurisdiction['effective_from'])->startOfDay()->lte($effectiveOn);
                    $ends = empty($jurisdiction['effective_to']) || Carbon::parse($jurisdiction['effective_to'])->endOfDay()->gte($effectiveOn);

                    return $matchesCode && $starts && $ends;
                })
                ->values();

            $requiresStateMatch = collect($definition['jurisdictions'])
                ->contains(fn (array $jurisdiction): bool => $jurisdiction['type'] === 'state' && $jurisdiction['state_resolution'] === 'required_match');
            $hasStateMatch = $jurisdictions->contains(fn (array $jurisdiction): bool => $jurisdiction['type'] === 'state');

            if ($requiresStateMatch && ! $hasStateMatch) {
                throw ValidationException::withMessages([
                    'statutory_rules' => 'No verified '.$setting->setting_key.' jurisdiction applies to statutory state '.$state.'.',
                ]);
            }

            foreach ($jurisdictions as $jurisdiction) {
                $resolved[] = [
                    'setting_id' => $setting->id,
                    'setting_key' => $setting->setting_key,
                    'version' => $setting->version,
                    'checksum' => $checksum,
                    'jurisdiction' => $jurisdiction,
                    'attendance_proration' => (array) $definition['attendance_proration'],
                    'source_evidence' => collect((array) $definition['source_evidence'])
                        ->map(fn (array $source): array => [
                            'authority' => $source['authority'],
                            'title' => $source['title'],
                            'document_reference' => $source['document_reference'],
                            'url' => $source['url'],
                            'source_checksum' => strtolower((string) $source['source_checksum']),
                            'published_or_accessed_on' => $source['published_or_accessed_on'],
                        ])->values()->all(),
                ];
            }
        }

        return new GovernedStatutoryRuleSet($resolved);
    }
}
