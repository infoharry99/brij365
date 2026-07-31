<?php

namespace App\Policies;

use App\Models\ContractorBill;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

class ContractorBillPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('construction.view')
            || $user->hasPermission('construction.manage')
            || $user->hasPermission('construction.approve')
            || $user->hasPermission('finance.view')
            || $user->hasPermission('finance.manage')
            || $user->hasPermission('finance.approve');
    }

    public function view(User $user, ContractorBill $bill): bool
    {
        return $this->viewAny($user)
            && app(CompanyScopeService::class)->allows($user, $bill->company_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('construction.manage');
    }

    public function approve(User $user, ContractorBill $bill): bool
    {
        return $user->hasPermission('construction.approve')
            && $bill->status === 'submitted'
            && app(CompanyScopeService::class)->allows($user, $bill->company_id)
            && $bill->prepared_by_user_id !== $user->id;
    }

    public function markPaid(User $user, ContractorBill $bill): bool
    {
        return ($user->hasPermission('finance.manage') || $user->hasPermission('finance.approve'))
            && in_array($bill->status, ['approved', 'partially_paid'], true)
            && app(CompanyScopeService::class)->allows($user, $bill->company_id);
    }
}
