<?php

namespace App\Policies;

use App\Models\ReraRegistration;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

class ReraRegistrationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('legal.view')
            || $user->hasPermission('legal.manage')
            || $user->hasPermission('legal.approve');
    }

    public function view(User $user, ReraRegistration $reraRegistration): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        return app(CompanyScopeService::class)->allows($user, $reraRegistration->company_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('legal.manage');
    }

    public function verify(User $user, ReraRegistration $reraRegistration): bool
    {
        if (! $user->hasPermission('legal.approve')) {
            return false;
        }

        return $reraRegistration->status === 'submitted'
            && app(CompanyScopeService::class)->allows($user, $reraRegistration->company_id)
            && $reraRegistration->created_by_user_id !== $user->id;
    }
}
