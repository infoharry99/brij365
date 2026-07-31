<?php

namespace App\Policies;

use App\Models\User;

class CompanyPolicy
{
    public function create(User $user): bool
    {
        if (config('builder360.single_company.enabled', true)) {
            return false;
        }

        return $user->hasPermission('*')
            || (
                $user->role?->scope_level === 'global'
                && $user->hasPermission('settings.manage')
                && $user->hasPermission('users.manage')
                && $user->hasPermission('roles.manage')
            );
    }
}
