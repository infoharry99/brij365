<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('roles.view') || $user->hasPermission('roles.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('roles.manage');
    }

    public function update(User $user, Role $role): bool
    {
        if (! $user->hasPermission('roles.manage')) {
            return false;
        }

        if ($role->id === $user->role_id && $role->is_active) {
            return false;
        }

        if (in_array('*', $role->permissions ?? [], true) && ! $user->hasPermission('*')) {
            return false;
        }

        if ($role->scope_level === 'global' && ! $user->hasPermission('*')) {
            return false;
        }

        return true;
    }
}
