<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\User;
use App\Services\Partner\PartnerScopeService;
use App\Services\Security\CompanyScopeService;

class LeadPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('crm.view') || $user->hasPermission('crm.manage');
    }

    public function view(User $user, Lead $lead): bool
    {
        if ($user->hasPermission('crm.view') || $user->hasPermission('crm.manage')) {
            return app(CompanyScopeService::class)->allows($user, $lead->company_id);
        }

        if (! $user->hasPermission('partner.portal')) {
            return false;
        }

        return in_array((int) $lead->partner_id, app(PartnerScopeService::class)->activePartnerIdsForUser($user), true);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('crm.manage');
    }

    public function update(User $user, Lead $lead): bool
    {
        return $user->hasPermission('crm.manage')
            && app(CompanyScopeService::class)->allows($user, $lead->company_id);
    }

    public function dispose(User $user, Lead $lead): bool
    {
        return $this->update($user, $lead);
    }
}
