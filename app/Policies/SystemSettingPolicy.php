<?php

namespace App\Policies;

use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

class SystemSettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('settings.view')
            || $user->hasPermission('settings.manage')
            || $user->hasPermission('settings.approve');
    }

    public function view(User $user, SystemSetting $systemSetting): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        return app(CompanyScopeService::class)->allowsSettingRead($user, $systemSetting->company_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('settings.manage');
    }

    public function approve(User $user, SystemSetting $systemSetting): bool
    {
        if (! $user->hasPermission('settings.approve')) {
            return false;
        }

        if ($systemSetting->status !== 'draft') {
            return false;
        }

        if ($systemSetting->created_by_user_id === $user->id) {
            return false;
        }

        return app(CompanyScopeService::class)->allowsSettingMutation($user, $systemSetting->company_id);
    }
}
