<?php

namespace App\Policies;

use App\Models\FinancialVoucher;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

class FinancialVoucherPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('finance.view')
            || $user->hasPermission('finance.manage')
            || $user->hasPermission('finance.approve');
    }

    public function view(User $user, FinancialVoucher $financialVoucher): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        return app(CompanyScopeService::class)->allows($user, $financialVoucher->company_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('finance.manage');
    }

    public function approve(User $user, FinancialVoucher $financialVoucher): bool
    {
        return $user->hasPermission('finance.approve')
            && $financialVoucher->status === 'submitted'
            && app(CompanyScopeService::class)->allows($user, $financialVoucher->company_id)
            && $financialVoucher->created_by_user_id !== $user->id;
    }

    public function reject(User $user, FinancialVoucher $financialVoucher): bool
    {
        return $this->approve($user, $financialVoucher);
    }
}
