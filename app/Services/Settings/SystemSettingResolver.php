<?php

namespace App\Services\Settings;

use App\Models\SystemSetting;
use Illuminate\Support\Carbon;

class SystemSettingResolver
{
    public function active(?int $companyId, string $settingKey, ?Carbon $effectiveOn = null, bool $fallbackToGlobal = true): ?SystemSetting
    {
        $effectiveOn ??= now();

        $companySetting = $companyId === null
            ? null
            : $this->activeForScope('company:'.$companyId, $settingKey, $effectiveOn);

        if ($companySetting !== null || ! $fallbackToGlobal) {
            return $companySetting;
        }

        return $this->activeForScope('global', $settingKey, $effectiveOn);
    }

    /**
     * @return array<string, mixed>
     */
    public function value(?int $companyId, string $settingKey, array $default = [], ?Carbon $effectiveOn = null, bool $fallbackToGlobal = true): array
    {
        return $this->active($companyId, $settingKey, $effectiveOn, $fallbackToGlobal)?->value ?? $default;
    }

    private function activeForScope(string $scopeKey, string $settingKey, Carbon $effectiveOn): ?SystemSetting
    {
        $effectiveDate = $effectiveOn->toDateString();

        return SystemSetting::query()
            ->where('scope_key', $scopeKey)
            ->where('setting_key', $settingKey)
            ->where('status', 'active')
            ->where(function ($query) use ($effectiveDate): void {
                $query->whereNull('effective_from')
                    ->orWhereDate('effective_from', '<=', $effectiveDate);
            })
            ->where(function ($query) use ($effectiveDate): void {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $effectiveDate);
            })
            ->orderByDesc('version')
            ->first();
    }
}
