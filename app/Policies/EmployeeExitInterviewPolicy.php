<?php

namespace App\Policies;

use App\Models\EmployeeExitInterview;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

class EmployeeExitInterviewPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('hr.view')
            || $user->hasPermission('hr.manage')
            || $user->hasPermission('employee.self_service');
    }

    public function view(User $user, EmployeeExitInterview $exitInterview): bool
    {
        if ($exitInterview->employee?->user_id === $user->id) {
            return true;
        }

        return ($user->hasPermission('hr.view') || $user->hasPermission('hr.manage') || $user->hasPermission('*'))
            && app(CompanyScopeService::class)->allows($user, $exitInterview->company_id);
    }

    public function viewConfidential(User $user, EmployeeExitInterview $exitInterview): bool
    {
        return ($user->hasPermission('hr.manage') || $user->hasPermission('*'))
            && app(CompanyScopeService::class)->allows($user, $exitInterview->company_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('hr.manage');
    }

    public function submit(User $user, EmployeeExitInterview $exitInterview): bool
    {
        if ($exitInterview->status !== 'scheduled') {
            return false;
        }

        if ($exitInterview->employee?->user_id === $user->id) {
            return true;
        }

        return ($user->hasPermission('hr.manage') || $user->hasPermission('*'))
            && app(CompanyScopeService::class)->allows($user, $exitInterview->company_id);
    }

    public function review(User $user, EmployeeExitInterview $exitInterview): bool
    {
        return $exitInterview->status === 'submitted'
            && ($user->hasPermission('hr.manage') || $user->hasPermission('*'))
            && app(CompanyScopeService::class)->allows($user, $exitInterview->company_id);
    }

    public function viewSummary(User $user): bool
    {
        return $user->hasPermission('hr.view') || $user->hasPermission('hr.manage') || $user->hasPermission('*');
    }
}
