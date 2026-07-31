<?php

namespace App\Policies;

use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

class PurchaseOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('procurement.view')
            || $user->hasPermission('procurement.manage')
            || $user->hasPermission('procurement.approve');
    }

    public function view(User $user, PurchaseOrder $purchaseOrder): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        return app(CompanyScopeService::class)->allows($user, $purchaseOrder->company_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('procurement.manage');
    }

    public function approve(User $user, PurchaseOrder $purchaseOrder): bool
    {
        if (! $user->hasPermission('procurement.approve')) {
            return false;
        }

        return $purchaseOrder->status === 'draft'
            && app(CompanyScopeService::class)->allows($user, $purchaseOrder->company_id)
            && $purchaseOrder->created_by_user_id !== $user->id;
    }

    public function receive(User $user, PurchaseOrder $purchaseOrder): bool
    {
        if (! $user->hasPermission('procurement.manage')) {
            return false;
        }

        return in_array($purchaseOrder->status, ['approved', 'partially_received'], true)
            && app(CompanyScopeService::class)->allows($user, $purchaseOrder->company_id);
    }
}
