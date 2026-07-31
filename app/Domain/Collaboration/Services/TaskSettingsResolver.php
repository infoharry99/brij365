<?php

namespace App\Domain\Collaboration\Services;

use App\Models\SystemSetting;
use Illuminate\Database\Eloquent\Builder;

final class TaskSettingsResolver
{
    /** @return array<string, mixed> */
    public function forCompany(?int $companyId): array
    {
        $setting = SystemSetting::query()
            ->where('setting_key', 'collaboration.task_settings')
            ->where('status', 'active')
            ->where(fn (Builder $query) => $query->where('company_id', $companyId)->orWhereNull('company_id'))
            ->orderByRaw('case when company_id is null then 1 else 0 end')
            ->orderByDesc('version')
            ->first();

        return is_array($setting?->value) ? $setting->value : [];
    }
}
