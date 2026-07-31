<?php

namespace App\Policies;

use App\Models\PurchaseRequisition;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

class PurchaseRequisitionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('procurement.view')
            || $user->hasPermission('procurement.manage')
            || $user->hasPermission('procurement.approve');
    }

    public function view(User $user, PurchaseRequisition $purchaseRequisition): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        return app(CompanyScopeService::class)->allows($user, $purchaseRequisition->company_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('procurement.manage');
    }

    public function approve(User $user, PurchaseRequisition $purchaseRequisition): bool
    {
        if (! $user->hasPermission('procurement.approve')) {
            return false;
        }

        return $purchaseRequisition->status === 'submitted'
            && app(CompanyScopeService::class)->allows($user, $purchaseRequisition->company_id)
            && $purchaseRequisition->requested_by_user_id !== $user->id;
    }
}
