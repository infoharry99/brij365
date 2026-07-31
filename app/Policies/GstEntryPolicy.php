<?php

namespace App\Policies;

use App\Models\GstEntry;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

class GstEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('finance.view')
            || $user->hasPermission('finance.manage')
            || $user->hasPermission('finance.approve')
            || $user->hasPermission('compliance.view')
            || $user->hasPermission('compliance.manage');
    }

    public function view(User $user, GstEntry $entry): bool
    {
        return $this->viewAny($user)
            && app(CompanyScopeService::class)->allows($user, $entry->company_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('finance.manage') || $user->hasPermission('compliance.manage');
    }

    public function approve(User $user, GstEntry $entry): bool
    {
        return ($user->hasPermission('finance.approve') || $user->hasPermission('compliance.manage'))
            && $entry->status === 'submitted'
            && app(CompanyScopeService::class)->allows($user, $entry->company_id)
            && $entry->created_by_user_id !== $user->id;
    }
}
