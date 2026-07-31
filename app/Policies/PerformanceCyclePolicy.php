<?php

namespace App\Policies;

use App\Models\PerformanceCycle;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

class PerformanceCyclePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('performance.view')
            || $user->hasPermission('performance.manage')
            || $user->hasPermission('performance.approve')
            || $user->hasPermission('employee.self_service');
    }

    public function view(User $user, PerformanceCycle $performanceCycle): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        return app(CompanyScopeService::class)->allows($user, $performanceCycle->company_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('performance.manage')
            || $user->hasPermission('hr.manage');
    }
}
