<?php

namespace App\Policies;

use App\Models\SiteVisit;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

class SiteVisitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('crm.view') || $user->hasPermission('crm.manage');
    }

    public function view(User $user, SiteVisit $siteVisit): bool
    {
        return $this->viewAny($user)
            && app(CompanyScopeService::class)->allows($user, $siteVisit->company_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('crm.manage');
    }

    public function update(User $user, SiteVisit $siteVisit): bool
    {
        return $user->hasPermission('crm.manage')
            && $siteVisit->status === 'scheduled'
            && app(CompanyScopeService::class)->allows($user, $siteVisit->company_id);
    }

    public function complete(User $user, SiteVisit $siteVisit): bool
    {
        return $user->hasPermission('crm.manage')
            && $siteVisit->status === 'scheduled'
            && app(CompanyScopeService::class)->allows($user, $siteVisit->company_id);
    }

    public function cancel(User $user, SiteVisit $siteVisit): bool
    {
        return $user->hasPermission('crm.manage')
            && $siteVisit->status === 'scheduled'
            && app(CompanyScopeService::class)->allows($user, $siteVisit->company_id);
    }
}
