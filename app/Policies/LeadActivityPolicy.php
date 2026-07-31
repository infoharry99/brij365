<?php

namespace App\Policies;

use App\Models\LeadActivity;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

class LeadActivityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('crm.view') || $user->hasPermission('crm.manage');
    }

    public function view(User $user, LeadActivity $leadActivity): bool
    {
        return ($user->hasPermission('crm.view') || $user->hasPermission('crm.manage'))
            && app(CompanyScopeService::class)->allows($user, $leadActivity->company_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('crm.manage');
    }
}
