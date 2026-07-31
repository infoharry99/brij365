<?php

namespace App\Policies;

use App\Models\EmployeePolicyAcknowledgement;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

class EmployeePolicyAcknowledgementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('employee.self_service')
            || $user->hasPermission('hr.manage')
            || $user->hasPermission('audit.view');
    }

    public function view(User $user, EmployeePolicyAcknowledgement $acknowledgement): bool
    {
        if ($acknowledgement->employee?->user_id === $user->id && $user->hasPermission('employee.self_service')) {
            return true;
        }

        return ($user->hasPermission('hr.manage') || $user->hasPermission('audit.view'))
            && app(CompanyScopeService::class)->allows($user, $acknowledgement->company_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('employee.self_service');
    }
}
