<?php

namespace App\Policies;

use App\Models\ProspectInquiry;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

class ProspectInquiryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('crm.view') || $user->hasPermission('crm.manage');
    }

    public function view(User $user, ProspectInquiry $prospectInquiry): bool
    {
        return ($user->hasPermission('crm.view') || $user->hasPermission('crm.manage'))
            && app(CompanyScopeService::class)->allows($user, $prospectInquiry->company_id);
    }

    public function update(User $user, ProspectInquiry $prospectInquiry): bool
    {
        return $user->hasPermission('crm.manage')
            && app(CompanyScopeService::class)->allows($user, $prospectInquiry->company_id);
    }

    public function convert(User $user, ProspectInquiry $prospectInquiry): bool
    {
        return $this->update($user, $prospectInquiry);
    }
}
