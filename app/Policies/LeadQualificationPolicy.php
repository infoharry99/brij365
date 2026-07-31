<?php

namespace App\Policies;

use App\Models\LeadQualification;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

class LeadQualificationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('crm.view') || $user->hasPermission('crm.manage');
    }

    public function view(User $user, LeadQualification $leadQualification): bool
    {
        return $this->viewAny($user)
            && app(CompanyScopeService::class)->allows($user, $leadQualification->company_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('crm.manage');
    }
}
