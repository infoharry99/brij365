<?php

namespace App\Policies;

use App\Models\DailyProgressReport;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

class DailyProgressReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('construction.view')
            || $user->hasPermission('construction.manage')
            || $user->hasPermission('construction.approve');
    }

    public function view(User $user, DailyProgressReport $dailyProgressReport): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        return app(CompanyScopeService::class)->allows($user, $dailyProgressReport->company_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('construction.manage');
    }

    public function approve(User $user, DailyProgressReport $dailyProgressReport): bool
    {
        if (! $user->hasPermission('construction.approve')) {
            return false;
        }

        return $dailyProgressReport->status === 'submitted'
            && app(CompanyScopeService::class)->allows($user, $dailyProgressReport->company_id)
            && $dailyProgressReport->prepared_by_user_id !== $user->id;
    }
}
