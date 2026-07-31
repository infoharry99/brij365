<?php

namespace App\Policies;

use App\Domain\Scoring\Support\LogicCenterPermissions;
use App\Models\PerformanceReview;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

class PerformanceReviewPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('performance.view')
            || $user->hasPermission('performance.manage')
            || $user->hasPermission('performance.approve')
            || $user->hasPermission(LogicCenterPermissions::PERFORMANCE_MANAGE)
            || $user->hasPermission(LogicCenterPermissions::PERFORMANCE_APPROVE)
            || $user->hasPermission(LogicCenterPermissions::PERFORMANCE_OVERRIDE_REQUEST)
            || $user->hasPermission(LogicCenterPermissions::PERFORMANCE_OVERRIDE_APPROVE)
            || $user->hasPermission('employee.self_service');
    }

    public function view(User $user, PerformanceReview $performanceReview): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        if ($performanceReview->employee?->user_id === $user->id) {
            return true;
        }

        if ($performanceReview->managerEmployee?->user_id === $user->id && $this->canManagePerformance($user)) {
            return true;
        }

        $companyScope = app(CompanyScopeService::class);

        return ($companyScope->hasGlobalScope($user)
            || $user->hasPermission('performance.view')
            || $user->hasPermission('performance.manage')
            || $user->hasPermission('performance.approve')
            || $user->hasPermission(LogicCenterPermissions::PERFORMANCE_MANAGE)
            || $user->hasPermission(LogicCenterPermissions::PERFORMANCE_APPROVE)
            || $user->hasPermission(LogicCenterPermissions::PERFORMANCE_OVERRIDE_REQUEST)
            || $user->hasPermission(LogicCenterPermissions::PERFORMANCE_OVERRIDE_APPROVE)
            || $user->hasPermission(LogicCenterPermissions::AUDIT_VIEW))
            && $companyScope->allows($user, $performanceReview->company_id);
    }

    public function create(User $user): bool
    {
        return $this->canManagePerformance($user)
            || $user->hasPermission('hr.manage');
    }

    public function submitSelf(User $user, PerformanceReview $performanceReview): bool
    {
        return $performanceReview->employee?->user_id === $user->id
            && $user->hasPermission('employee.self_service');
    }

    public function submitManager(User $user, PerformanceReview $performanceReview): bool
    {
        if ($performanceReview->employee?->user_id === $user->id) {
            return false;
        }

        return ($performanceReview->managerEmployee?->user_id === $user->id && $this->canManagePerformance($user))
            || ($this->canApprovePerformance($user) && app(CompanyScopeService::class)->allows($user, $performanceReview->company_id));
    }

    public function close(User $user, PerformanceReview $performanceReview): bool
    {
        if ($performanceReview->employee?->user_id === $user->id || $performanceReview->managerEmployee?->user_id === $user->id) {
            return false;
        }

        return $this->canApprovePerformance($user)
            && app(CompanyScopeService::class)->allows($user, $performanceReview->company_id);
    }

    public function calibrate(User $user, PerformanceReview $performanceReview): bool
    {
        if ($performanceReview->employee?->user_id === $user->id || $performanceReview->managerEmployee?->user_id === $user->id) {
            return false;
        }

        return $performanceReview->status === 'manager_submitted'
            && ($this->canManagePerformance($user) || $this->canApprovePerformance($user))
            && app(CompanyScopeService::class)->allows($user, $performanceReview->company_id);
    }

    public function requestOverride(User $user, PerformanceReview $performanceReview): bool
    {
        return $performanceReview->status === 'manager_submitted'
            && $performanceReview->score_snapshot_id !== null
            && $this->hasAny($user, [
                LogicCenterPermissions::PERFORMANCE_OVERRIDE_REQUEST,
                'scoring.override',
                'performance.approve',
            ])
            && app(CompanyScopeService::class)->allows($user, $performanceReview->company_id);
    }

    public function approveOverride(User $user, PerformanceReview $performanceReview): bool
    {
        return $performanceReview->status === 'manager_submitted'
            && $this->hasAny($user, [
                LogicCenterPermissions::PERFORMANCE_OVERRIDE_APPROVE,
                'performance.approve',
            ])
            && app(CompanyScopeService::class)->allows($user, $performanceReview->company_id);
    }

    public function viewScoreTrace(User $user, PerformanceReview $performanceReview): bool
    {
        if (! $this->view($user, $performanceReview)) {
            return false;
        }

        if ($performanceReview->employee?->user_id === $user->id) {
            return $performanceReview->status === 'closed';
        }

        if ($performanceReview->managerEmployee?->user_id === $user->id) {
            return $this->canManagePerformance($user);
        }

        return $this->hasAny($user, [
            'performance.view', 'performance.approve',
            LogicCenterPermissions::PERFORMANCE_MANAGE,
            LogicCenterPermissions::PERFORMANCE_APPROVE,
            LogicCenterPermissions::AUDIT_VIEW,
        ])
            && app(CompanyScopeService::class)->allows($user, $performanceReview->company_id);
    }

    public function viewOverrideGovernance(User $user, PerformanceReview $performanceReview): bool
    {
        return $this->hasAny($user, [
            'performance.approve',
            LogicCenterPermissions::PERFORMANCE_MANAGE,
            LogicCenterPermissions::PERFORMANCE_APPROVE,
            LogicCenterPermissions::PERFORMANCE_OVERRIDE_REQUEST,
            LogicCenterPermissions::PERFORMANCE_OVERRIDE_APPROVE,
            LogicCenterPermissions::AUDIT_VIEW,
        ])
            && app(CompanyScopeService::class)->allows($user, $performanceReview->company_id);
    }

    private function canManagePerformance(User $user): bool
    {
        return $this->hasAny($user, ['performance.manage', LogicCenterPermissions::PERFORMANCE_MANAGE]);
    }

    private function canApprovePerformance(User $user): bool
    {
        return $this->hasAny($user, ['performance.approve', LogicCenterPermissions::PERFORMANCE_APPROVE]);
    }

    /** @param list<string> $permissions */
    private function hasAny(User $user, array $permissions): bool
    {
        return collect($permissions)->contains(fn (string $permission): bool => $user->hasPermission($permission));
    }
}
