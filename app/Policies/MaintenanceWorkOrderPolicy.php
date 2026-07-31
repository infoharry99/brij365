<?php

namespace App\Policies;

use App\Models\MaintenanceWorkOrder;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

class MaintenanceWorkOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('after_sales.view')
            || $user->hasPermission('after_sales.manage')
            || $user->hasPermission('after_sales.approve');
    }

    public function view(User $user, MaintenanceWorkOrder $maintenanceWorkOrder): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        return app(CompanyScopeService::class)->allows($user, $maintenanceWorkOrder->company_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('after_sales.manage');
    }

    public function complete(User $user, MaintenanceWorkOrder $maintenanceWorkOrder): bool
    {
        return $user->hasPermission('after_sales.manage')
            && ! in_array($maintenanceWorkOrder->status, ['completed', 'cancelled'], true)
            && app(CompanyScopeService::class)->allows($user, $maintenanceWorkOrder->company_id);
    }
}
