<?php

namespace App\Policies;

use App\Models\EmployeeAsset;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

class EmployeeAssetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('assets.view')
            || $user->hasPermission('assets.manage')
            || $user->hasPermission('hr.manage')
            || $user->hasPermission('employee.self_service');
    }

    public function view(User $user, EmployeeAsset $employeeAsset): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        if ($user->hasPermission('assets.view') || $user->hasPermission('assets.manage') || $user->hasPermission('hr.manage')) {
            return app(CompanyScopeService::class)->allows($user, $employeeAsset->company_id);
        }

        return $user->hasPermission('employee.self_service')
            && $employeeAsset->employee()->where('user_id', $user->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('assets.manage') || $user->hasPermission('hr.manage');
    }

    public function assign(User $user, EmployeeAsset $employeeAsset): bool
    {
        return ($user->hasPermission('assets.manage') || $user->hasPermission('hr.manage'))
            && $employeeAsset->status === 'available'
            && app(CompanyScopeService::class)->allows($user, $employeeAsset->company_id);
    }

    public function recover(User $user, EmployeeAsset $employeeAsset): bool
    {
        return ($user->hasPermission('assets.manage') || $user->hasPermission('hr.manage'))
            && $employeeAsset->status === 'assigned'
            && app(CompanyScopeService::class)->allows($user, $employeeAsset->company_id);
    }
}
