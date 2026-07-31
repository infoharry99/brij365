<?php

namespace App\Services\Settings;

use App\Domain\Hr\Services\AttendanceRosterRulePackValidator;
use App\Domain\Payroll\Data\StatutoryPayrollCutoverManifest;
use App\Domain\Payroll\Services\StatutoryPayrollCutoverManifestValidator;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Security\CompanyScopeService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SystemSettingService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly CompanyScopeService $companyScope,
        private readonly StatutoryPayrollCutoverManifestValidator $statutoryCutoverManifestValidator,
        private readonly AttendanceRosterRulePackValidator $attendanceRosterRulePackValidator,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createDraft(array $data, User $actor, ?Request $request = null): SystemSetting
    {
        $data['value'] = $this->normalizeDomainSettingValue(
            (string) $data['setting_key'],
            $data['value'] ?? null,
        );

        return DB::transaction(function () use ($data, $actor, $request): SystemSetting {
            $companyId = $this->companyId($data, $actor);
            $scopeKey = $this->scopeKey($companyId);
            $version = $this->nextVersion($scopeKey, $data['setting_key']);

            $setting = SystemSetting::create([
                'company_id' => $companyId,
                'created_by_user_id' => $actor->id,
                'scope_key' => $scopeKey,
                'setting_group' => $data['setting_group'],
                'setting_key' => $data['setting_key'],
                'label' => $data['label'],
                'description' => $data['description'] ?? null,
                'value_type' => $data['value_type'],
                'value' => $this->normalizeValue($data['value']),
                'status' => 'draft',
                'version' => $version,
                'effective_from' => $data['effective_from'] ?? null,
                'workflow_history' => [
                    $this->workflowEvent('draft', $actor, 'Setting draft created'),
                ],
                'metadata' => $data['metadata'] ?? [],
            ]);

            $this->auditLogger->record(
                $actor,
                'settings.system_setting.draft_created',
                'Created system setting draft',
                $setting,
                ['setting_key' => $setting->setting_key, 'scope_key' => $setting->scope_key, 'version' => $setting->version],
                $request,
            );

            return $setting->load(['company', 'createdBy', 'approvedBy']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function approve(SystemSetting $systemSetting, array $data, User $actor, ?Request $request = null): SystemSetting
    {
        return DB::transaction(function () use ($systemSetting, $data, $actor, $request): SystemSetting {
            // Lock every version for this setting in a stable order. Locking only the
            // selected draft allows two approvers to activate different versions at
            // the same time and lets a stale, older draft retire a newer active one.
            $versions = SystemSetting::query()
                ->where('scope_key', $systemSetting->scope_key)
                ->where('setting_key', $systemSetting->setting_key)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $setting = $versions->firstWhere('id', $systemSetting->id);

            if (! $setting instanceof SystemSetting) {
                throw (new ModelNotFoundException)->setModel(SystemSetting::class, [$systemSetting->id]);
            }

            if ($setting->status !== 'draft') {
                throw ValidationException::withMessages(['setting' => 'Only draft settings can be approved.']);
            }

            $this->assertCanMutateSetting($setting, $actor);

            if ($setting->created_by_user_id === $actor->id) {
                throw ValidationException::withMessages(['setting' => 'The setting creator cannot approve the same setting.']);
            }

            $normalizedDomainValue = $this->normalizeDomainSettingValue($setting->setting_key, $setting->value);

            // Legacy drafts may predate the typed pack boundary. Persist the
            // canonical shape at approval so every active version has the same
            // deterministic contract regardless of which authorized settings
            // screen originally created it.
            if (is_array($normalizedDomainValue) && $normalizedDomainValue !== $setting->value) {
                $setting->forceFill([
                    'value' => $this->normalizeValue($normalizedDomainValue),
                ])->save();
            }

            if ($versions->contains(
                fn (SystemSetting $version): bool => $version->status === 'active'
                    && $version->version > $setting->version,
            )) {
                throw ValidationException::withMessages([
                    'setting' => 'A newer setting version is already active. Refresh the register and review the latest version.',
                ]);
            }

            $this->retireOverlappingActiveVersions($setting);

            $history = $setting->workflow_history ?? [];
            $history[] = $this->workflowEvent('active', $actor, $data['note'] ?? 'Setting approved and activated');

            $setting->forceFill([
                'status' => 'active',
                'approved_by_user_id' => $actor->id,
                'approved_at' => now(),
                'workflow_history' => $history,
            ])->save();

            $this->auditLogger->record(
                $actor,
                'settings.system_setting.approved',
                'Approved system setting',
                $setting,
                ['setting_key' => $setting->setting_key, 'scope_key' => $setting->scope_key, 'version' => $setting->version],
                $request,
            );

            return $setting->load(['company', 'createdBy', 'approvedBy']);
        });
    }

    private function retireOverlappingActiveVersions(SystemSetting $replacement): void
    {
        $today = now()->startOfDay();
        $replacementStarts = $replacement->effective_from instanceof Carbon
            ? $replacement->effective_from->copy()->startOfDay()
            : $today->copy();

        $activeSettings = SystemSetting::query()
            ->where('scope_key', $replacement->scope_key)
            ->where('setting_key', $replacement->setting_key)
            ->where('status', 'active')
            ->whereKeyNot($replacement->id)
            ->lockForUpdate()
            ->get();

        foreach ($activeSettings as $activeSetting) {
            if ($replacementStarts->lte($today)) {
                $activeSetting->forceFill([
                    'status' => 'archived',
                    'effective_to' => $today->toDateString(),
                ])->save();

                continue;
            }

            $effectiveTo = $replacementStarts->copy()->subDay();
            $existingEffectiveTo = $activeSetting->effective_to?->copy()->startOfDay();

            if ($existingEffectiveTo !== null && $existingEffectiveTo->lt($effectiveTo)) {
                $effectiveTo = $existingEffectiveTo;
            }

            $activeSetting->forceFill([
                'status' => $effectiveTo->lt($today) ? 'archived' : 'active',
                'effective_to' => $effectiveTo->toDateString(),
            ])->save();
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function companyId(array $data, User $actor): ?int
    {
        $resolvedCompanyId = $this->companyScope->settingCompanyIdFor($actor);

        if ($resolvedCompanyId === 0) {
            throw ValidationException::withMessages([
                'company_id' => 'A valid company assignment is required before creating company-scoped settings.',
            ]);
        }

        $requestedCompanyId = isset($data['company_id']) ? (int) $data['company_id'] : null;

        if ($requestedCompanyId !== null) {
            if (! $this->companyScope->allowsSettingMutation($actor, $requestedCompanyId)) {
                throw ValidationException::withMessages([
                    'company_id' => 'The selected company is outside your active company scope.',
                ]);
            }

            return $requestedCompanyId;
        }

        return $resolvedCompanyId;
    }

    private function assertCanMutateSetting(SystemSetting $setting, User $actor): void
    {
        if (! $this->companyScope->allowsSettingMutation($actor, $setting->company_id)) {
            throw ValidationException::withMessages(['setting' => 'The selected setting is outside your company scope.']);
        }
    }

    private function scopeKey(?int $companyId): string
    {
        return $companyId === null ? 'global' : 'company:'.$companyId;
    }

    private function nextVersion(string $scopeKey, string $settingKey): int
    {
        return (int) SystemSetting::query()
            ->where('scope_key', $scopeKey)
            ->where('setting_key', $settingKey)
            ->max('version') + 1;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeValue(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        return ['value' => $value];
    }

    private function normalizeDomainSettingValue(string $settingKey, mixed $value): mixed
    {
        if (in_array($settingKey, [
            AttendanceRosterRulePackValidator::ATTENDANCE_KEY,
            AttendanceRosterRulePackValidator::ROSTER_KEY,
        ], true)) {
            if (! is_array($value)) {
                throw ValidationException::withMessages([
                    'value' => 'Attendance and roster rule packs must contain a structured object.',
                ]);
            }

            return $this->attendanceRosterRulePackValidator->normalize($settingKey, $value);
        }

        if ($settingKey !== StatutoryPayrollCutoverManifest::SETTING_KEY) {
            return $value;
        }

        if (! is_array($value)) {
            throw ValidationException::withMessages([
                'value' => 'The statutory payroll cutover manifest must contain a structured object.',
            ]);
        }

        $this->statutoryCutoverManifestValidator->assertValid($value);

        return $value;
    }

    /**
     * @return array<string, string>
     */
    private function workflowEvent(string $status, User $actor, string $note): array
    {
        return [
            'status' => $status,
            'actor' => $actor->name,
            'note' => $note,
            'at' => now()->toISOString(),
        ];
    }
}
