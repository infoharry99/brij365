<?php

namespace App\Domain\Scoring\Services;

use App\Application\Scoring\DTOs\LogicVariablePackRowData;
use App\Domain\Hr\Services\AttendanceRosterRulePackValidator;
use App\Domain\Payroll\Services\CanonicalPayrollHasher;
use App\Domain\Payroll\Services\StatutoryRulePackDefinitionValidator;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Security\CompanyScopeService;
use Illuminate\Validation\ValidationException;

final class LogicCenterRegister
{
    public const STATUTORY_KEYS = [
        'payroll.tax_rules',
        'hr.statutory.pf',
        'hr.statutory.esic',
        'hr.statutory.professional_tax',
        'hr.statutory.labour_welfare_fund',
        'hr.statutory.gratuity_bonus',
        'hr.leave.rules',
    ];

    public const ROSTER_KEYS = [
        'hr.attendance.rules',
        'hr.attendance.roster_rules',
    ];

    public function __construct(
        private readonly CompanyScopeService $companyScope,
        private readonly CanonicalPayrollHasher $hasher,
        private readonly StatutoryRulePackDefinitionValidator $statutoryValidator,
        private readonly AttendanceRosterRulePackValidator $attendanceRosterValidator,
        private readonly LogicCenterAccessService $access,
    ) {}

    /** @return list<LogicVariablePackRowData> */
    public function variablePacks(User $user, string $view): array
    {
        $canViewStatutory = $this->access->canViewSection($user, 'statutory');
        $canViewRoster = $this->access->canViewSection($user, 'roster');
        $canSimulateStatutory = $this->access->capabilities($user)['simulateStatutory'];

        $keys = match ($view) {
            'statutory' => $canViewStatutory ? self::STATUTORY_KEYS : [],
            'roster' => $canViewRoster ? self::ROSTER_KEYS : [],
            'simulation' => $canSimulateStatutory ? StatutoryRulePackDefinitionValidator::GOVERNED_SETTING_KEYS : [],
            'overview', 'audit' => array_values(array_merge(
                $canViewStatutory ? self::STATUTORY_KEYS : [],
                $canViewRoster ? self::ROSTER_KEYS : [],
            )),
            default => [],
        };

        if ($keys === []) {
            return [];
        }

        $companyId = $this->companyScope->settingCompanyIdFor($user);
        $query = SystemSetting::query()
            ->with([
                'createdBy:id,name',
                'approvedBy:id,name',
                'statutoryVerification:id,system_setting_id,verified_by_user_id,configuration_checksum,verified_at',
            ])
            ->whereIn('setting_key', $keys)
            ->when($companyId === null, fn ($builder) => $builder->whereNull('company_id'))
            ->when(is_int($companyId) && $companyId > 0, fn ($builder) => $builder->where(fn ($scope) => $scope
                ->where('company_id', $companyId)
                ->orWhereNull('company_id')))
            ->when($companyId === 0, fn ($builder) => $builder->whereRaw('1 = 0'))
            ->orderBy('setting_key')
            ->orderByDesc('version');

        return $query->limit(100)->get()->map(function (SystemSetting $setting) use ($user): LogicVariablePackRowData {
            $value = is_array($setting->value) ? $setting->value : [];
            $requiresVerification = in_array($setting->setting_key, StatutoryRulePackDefinitionValidator::GOVERNED_SETTING_KEYS, true);
            $source = $this->officialSource($value);
            $checksum = $this->hasher->hash($value);
            $verified = $requiresVerification && $this->hasCurrentIndependentVerification($setting, $value, $checksum);

            return new LogicVariablePackRowData(
                id: (int) $setting->id,
                settingKey: $setting->setting_key,
                label: $setting->label,
                domain: in_array($setting->setting_key, self::STATUTORY_KEYS, true) ? 'Statutory & Payroll' : 'Attendance & Roster',
                status: str($setting->status)->headline()->toString(),
                version: (int) $setting->version,
                effectivePeriod: $this->effectivePeriod($setting),
                sourceAuthority: $source['authority'],
                sourceReference: $source['reference'],
                checksum: substr($checksum, 0, 12),
                verified: $verified,
                requiresVerification: $requiresVerification,
                variables: $this->packVariables($setting->setting_key, $value),
                canApprove: $user->can('approve', $setting),
                reviewUrl: $this->reviewUrl($user, $setting),
            );
        })->all();
    }

    /** @return array<string, int> */
    public function readiness(User $user): array
    {
        $packs = collect($this->variablePacks($user, 'overview'));

        return [
            'variablePacks' => $packs->count(),
            'activePacks' => $packs->where('status', 'Active')->count(),
            'unverifiedPacks' => $packs->filter(fn (LogicVariablePackRowData $pack): bool => $pack->requiresVerification && ! $pack->verified)->count(),
            'draftPacks' => $packs->where('status', 'Draft')->count(),
        ];
    }

    private function effectivePeriod(SystemSetting $setting): string
    {
        $from = $setting->effective_from?->format('d M Y') ?? 'Immediate';
        $to = $setting->effective_to?->format('d M Y') ?? 'Open ended';

        return $from.' - '.$to;
    }

    private function reviewUrl(User $user, SystemSetting $setting): ?string
    {
        if (in_array($setting->setting_key, self::STATUTORY_KEYS, true)) {
            return $this->access->canViewSection($user, 'statutory')
                ? route('hr.compliance-rule-settings.index', ['setting_key' => $setting->setting_key])
                : null;
        }

        return $user->can('viewAny', SystemSetting::class)
            ? route('settings.system-settings.index', ['setting_key' => $setting->setting_key])
            : null;
    }

    /**
     * Display evidence only from the governed source-evidence contract. Legacy
     * metadata flags are deliberately ignored because they are not an
     * independent statutory attestation.
     *
     * @param  array<string, mixed>  $value
     * @return array{authority:string, reference:string}
     */
    private function officialSource(array $value): array
    {
        $source = collect((array) ($value['source_evidence'] ?? []))
            ->first(fn (mixed $item): bool => is_array($item) && ($item['source_type'] ?? null) === 'official_government');

        if (! is_array($source)) {
            return ['authority' => 'Not verified', 'reference' => 'No governed official source recorded'];
        }

        $title = trim((string) ($source['title'] ?? ''));
        $reference = trim((string) ($source['document_reference'] ?? ''));

        return [
            'authority' => trim((string) ($source['authority'] ?? '')) ?: 'Not verified',
            'reference' => collect([$title, $reference])->filter()->implode(' / ') ?: 'No official source reference recorded',
        ];
    }

    /**
     * Expose only normalized, non-sensitive attendance and roster variables.
     * Invalid drafts remain reviewable without making the Logic Center fail.
     *
     * @param  array<string, mixed>  $value
     * @return list<array{key:string,label:string,value:string}>
     */
    private function packVariables(string $settingKey, array $value): array
    {
        if (in_array($settingKey, self::STATUTORY_KEYS, true)) {
            return $this->statutoryVariables($value);
        }

        if (! in_array($settingKey, [
            AttendanceRosterRulePackValidator::ATTENDANCE_KEY,
            AttendanceRosterRulePackValidator::ROSTER_KEY,
        ], true)) {
            return [];
        }

        try {
            $normalized = $this->attendanceRosterValidator->normalize($settingKey, $value);
        } catch (ValidationException) {
            return [[
                'key' => 'configuration_status',
                'label' => 'Configuration status',
                'value' => 'Invalid - review required',
            ]];
        }

        return collect($normalized)
            ->map(fn (mixed $variable, string $key): array => [
                'key' => $key,
                'label' => str($key)->replace('_', ' ')->headline()->toString(),
                'value' => $this->displayVariableValue($key, $variable),
            ])
            ->values()
            ->all();
    }

    /**
     * Expose a bounded, non-employee-specific formula summary. The payroll
     * engine remains authoritative; this register never evaluates payroll.
     *
     * @param  array<string, mixed>  $value
     * @return list<array{key:string,label:string,value:string}>
     */
    private function statutoryVariables(array $value): array
    {
        try {
            $this->statutoryValidator->assertValid($value);
        } catch (ValidationException) {
            return [[
                'key' => 'configuration_status',
                'label' => 'Configuration status',
                'value' => 'Invalid - review required',
            ]];
        }

        $variables = [[
            'key' => 'schema_version',
            'label' => 'Schema version',
            'value' => (string) ($value['governed_statutory_pack_version'] ?? 'Not configured'),
        ], [
            'key' => 'attendance_proration',
            'label' => 'Attendance proration',
            'value' => (bool) data_get($value, 'attendance_proration.enabled', false) ? 'Enabled' : 'Disabled',
        ], [
            'key' => 'jurisdictions',
            'label' => 'Jurisdictions',
            'value' => (string) count((array) ($value['jurisdictions'] ?? [])),
        ]];

        foreach ((array) ($value['jurisdictions'] ?? []) as $jurisdictionIndex => $jurisdiction) {
            if (! is_array($jurisdiction)) {
                continue;
            }

            $jurisdictionCode = trim((string) ($jurisdiction['code'] ?? '')) ?: 'Unspecified';
            $jurisdictionType = str((string) ($jurisdiction['type'] ?? ''))
                ->replace('_', ' ')
                ->headline()
                ->toString();
            $variables[] = [
                'key' => "jurisdiction_{$jurisdictionIndex}",
                'label' => "Jurisdiction {$jurisdictionCode}",
                'value' => trim($jurisdictionType.' · '.count((array) ($jurisdiction['lines'] ?? [])).' calculation lines', ' ·'),
            ];

            foreach ((array) ($jurisdiction['lines'] ?? []) as $lineIndex => $line) {
                if (! is_array($line)) {
                    continue;
                }

                $code = trim((string) ($line['code'] ?? '')) ?: 'line_'.($lineIndex + 1);
                $name = trim((string) ($line['name'] ?? ''))
                    ?: str($code)->replace('_', ' ')->headline()->toString();
                $variables[] = [
                    'key' => "jurisdiction_{$jurisdictionIndex}_line_{$code}",
                    'label' => $name,
                    'value' => $this->statutoryFormulaSummary($line),
                ];
            }
        }

        return array_slice($variables, 0, 30);
    }

    /** @param array<string, mixed> $line */
    private function statutoryFormulaSummary(array $line): string
    {
        $method = (string) ($line['method'] ?? '');
        $basisCodes = collect((array) ($line['basis_codes'] ?? []))
            ->filter(fn (mixed $code): bool => is_scalar($code) && trim((string) $code) !== '')
            ->map(fn (mixed $code): string => trim((string) $code))
            ->implode(', ');

        $summary = match ($method) {
            'rate_ppm' => number_format(((int) ($line['rate_ppm'] ?? 0)) / 10000, 4).'% of '.($basisCodes ?: 'configured basis'),
            'fixed_minor' => 'Fixed '.(int) ($line['fixed_minor'] ?? 0).' minor units',
            'slab' => count((array) ($line['slabs'] ?? [])).' deterministic slabs',
            'annual_tax_projection' => count((array) ($line['regimes'] ?? [])).' projected tax regimes',
            default => str($method)->replace('_', ' ')->headline()->toString() ?: 'Configured formula',
        };

        if (isset($line['cap_minor']) && $line['cap_minor'] !== null && $line['cap_minor'] !== '') {
            $summary .= ' · cap '.(int) $line['cap_minor'].' minor units';
        }

        if (isset($line['threshold_minor']) && $line['threshold_minor'] !== null && $line['threshold_minor'] !== '') {
            $summary .= ' · threshold '.(int) $line['threshold_minor'].' minor units';
        }

        return $summary;
    }

    private function displayVariableValue(string $key, mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'Not configured';
        }

        if (is_bool($value)) {
            return $value ? 'Enabled' : 'Disabled';
        }

        $unit = match (true) {
            str_ends_with($key, '_minutes') => ' min',
            str_ends_with($key, '_hours') => ' hr',
            str_ends_with($key, '_days') => ' days',
            default => '',
        };

        if (is_int($value) || is_float($value)) {
            return $value.$unit;
        }

        return str((string) $value)->replace('_', ' ')->headline()->toString();
    }

    /** @param array<string, mixed> $value */
    private function hasCurrentIndependentVerification(SystemSetting $setting, array $value, string $checksum): bool
    {
        if (($value['governed_statutory_pack_version'] ?? null) !== StatutoryRulePackDefinitionValidator::SCHEMA_VERSION) {
            return false;
        }

        try {
            $this->statutoryValidator->assertValid($value);
        } catch (ValidationException) {
            return false;
        }

        $verification = $setting->statutoryVerification;

        return $verification !== null
            && $verification->verified_by_user_id !== null
            && $verification->verified_by_user_id !== $setting->created_by_user_id
            && hash_equals((string) $verification->configuration_checksum, $checksum);
    }
}
