<?php

namespace App\Policies;

use App\Models\MarketingCampaign;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

class MarketingCampaignPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('crm.view') || $user->hasPermission('crm.manage');
    }

    public function view(User $user, MarketingCampaign $marketingCampaign): bool
    {
        return ($user->hasPermission('crm.view') || $user->hasPermission('crm.manage'))
            && app(CompanyScopeService::class)->allows($user, $marketingCampaign->company_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('crm.manage');
    }

    public function update(User $user, MarketingCampaign $marketingCampaign): bool
    {
        return $user->hasPermission('crm.manage')
            && app(CompanyScopeService::class)->allows($user, $marketingCampaign->company_id);
    }
}
