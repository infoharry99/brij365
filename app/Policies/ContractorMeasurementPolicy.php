<?php

namespace App\Policies;

use App\Models\ContractorMeasurement;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

class ContractorMeasurementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('construction.view')
            || $user->hasPermission('construction.manage')
            || $user->hasPermission('construction.approve')
            || $user->hasPermission('finance.view');
    }

    public function view(User $user, ContractorMeasurement $measurement): bool
    {
        return $this->viewAny($user)
            && app(CompanyScopeService::class)->allows($user, $measurement->company_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('construction.manage');
    }

    public function approve(User $user, ContractorMeasurement $measurement): bool
    {
        return $user->hasPermission('construction.approve')
            && $measurement->status === 'submitted'
            && app(CompanyScopeService::class)->allows($user, $measurement->company_id)
            && $measurement->submitted_by_user_id !== $user->id;
    }

    public function reject(User $user, ContractorMeasurement $measurement): bool
    {
        return $this->approve($user, $measurement);
    }
}
