<?php

namespace App\Policies;

use App\Models\CommissionRule;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

class CommissionRulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('payroll.view')
            || $user->hasPermission('payroll.manage')
            || $user->hasPermission('payroll.approve')
            || $user->hasPermission('reports.view');
    }

    public function view(User $user, CommissionRule $rule): bool
    {
        return $this->viewAny($user)
            && app(CompanyScopeService::class)->allows($user, $rule->company_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('payroll.manage');
    }
}
