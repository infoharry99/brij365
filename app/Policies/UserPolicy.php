<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewOwnProfile(User $user, User $profileUser): bool
    {
        return (int) $user->getKey() === (int) $profileUser->getKey()
            && $user->status === 'active';
    }

    public function viewProfilePhoto(User $user, User $profileUser): bool
    {
        if ($user->status !== 'active' || $profileUser->status !== 'active') {
            return false;
        }

        if ((int) $user->id === (int) $profileUser->id || $user->hasPermission('*')) {
            return true;
        }

        return $user->company_id !== null
            && $profileUser->company_id !== null
            && (int) $user->company_id === (int) $profileUser->company_id;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('users.view') || $user->hasPermission('users.manage');
    }

    public function view(User $user, User $managedUser): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        return $this->allowsManagedUserCompany($user, $managedUser);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('users.manage');
    }

    public function updateAccess(User $user, User $managedUser): bool
    {
        if (! $user->hasPermission('users.manage')) {
            return false;
        }

        if ($managedUser->id === $user->id) {
            return false;
        }

        if (! $user->hasPermission('*') && in_array('*', $managedUser->role?->permissions ?? [], true)) {
            return false;
        }

        return $this->allowsManagedUserCompany($user, $managedUser);
    }

    private function allowsManagedUserCompany(User $user, User $managedUser): bool
    {
        if ($user->hasPermission('*')) {
            return true;
        }

        if ($user->company_id === null || $managedUser->company_id === null) {
            return false;
        }

        return (int) $managedUser->company_id === (int) $user->company_id;
    }
}
