<?php

namespace App\Policies;

use App\Domain\Scoring\Support\LogicCenterPermissions;
use App\Models\ScoringRule;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

class ScoringRulePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasAny($user, [
            'scoring.view', 'scoring.manage', 'scoring.approve',
            'performance.view', 'performance.manage', 'performance.approve',
            LogicCenterPermissions::PERFORMANCE_MANAGE,
            LogicCenterPermissions::PERFORMANCE_APPROVE,
            LogicCenterPermissions::PERFORMANCE_OVERRIDE_REQUEST,
            LogicCenterPermissions::PERFORMANCE_OVERRIDE_APPROVE,
        ]);
    }

    public function view(User $user, ScoringRule $rule): bool
    {
        return $this->viewForKey($user, $rule->rule_key)
            && app(CompanyScopeService::class)->allows($user, $rule->company_id);
    }

    public function viewForKey(User $user, string $ruleKey): bool
    {
        if ($ruleKey === 'employee_performance') {
            return $this->hasAny($user, [
                'scoring.view', 'scoring.manage', 'scoring.approve',
                'performance.view', 'performance.manage', 'performance.approve',
                LogicCenterPermissions::PERFORMANCE_MANAGE,
                LogicCenterPermissions::PERFORMANCE_APPROVE,
                LogicCenterPermissions::PERFORMANCE_OVERRIDE_REQUEST,
                LogicCenterPermissions::PERFORMANCE_OVERRIDE_APPROVE,
            ]);
        }

        return $this->hasAny($user, ['scoring.view', 'scoring.manage', 'scoring.approve']);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('scoring.manage')
            || $user->hasPermission('performance.manage')
            || $user->hasPermission(LogicCenterPermissions::PERFORMANCE_MANAGE);
    }

    public function createForKey(User $user, string $ruleKey): bool
    {
        return $ruleKey === 'employee_performance'
            ? $this->canManagePerformance($user)
            : $user->hasPermission('scoring.manage');
    }

    public function clone(User $user, ScoringRule $rule): bool
    {
        return $this->canManageRule($user, $rule)
            && $this->view($user, $rule);
    }

    public function update(User $user, ScoringRule $rule): bool
    {
        return $this->canManageRule($user, $rule)
            && in_array($rule->status, ['draft', 'validated', 'rejected'], true)
            && app(CompanyScopeService::class)->allows($user, $rule->company_id);
    }

    public function submit(User $user, ScoringRule $rule): bool
    {
        return $this->update($user, $rule) && $rule->status === 'validated';
    }

    public function validate(User $user, ScoringRule $rule): bool
    {
        return $this->update($user, $rule) && in_array($rule->status, ['draft', 'rejected'], true);
    }

    public function approve(User $user, ScoringRule $rule): bool
    {
        return $this->canApproveRule($user, $rule)
            && $rule->status === 'pending_approval'
            && (int) $rule->created_by_user_id !== (int) $user->id
            && app(CompanyScopeService::class)->allows($user, $rule->company_id);
    }

    public function reject(User $user, ScoringRule $rule): bool
    {
        return $this->canApproveRule($user, $rule)
            && $rule->status === 'pending_approval'
            && (int) $rule->created_by_user_id !== (int) $user->id
            && app(CompanyScopeService::class)->allows($user, $rule->company_id);
    }

    public function activate(User $user, ScoringRule $rule): bool
    {
        return $this->canApproveRule($user, $rule)
            && in_array($rule->status, ['approved', 'scheduled'], true)
            && app(CompanyScopeService::class)->allows($user, $rule->company_id);
    }

    public function recalculate(User $user, ScoringRule $rule): bool
    {
        return $user->hasPermission('scoring.recalculate')
            && $rule->status === 'active'
            && app(CompanyScopeService::class)->allows($user, $rule->company_id);
    }

    public function override(User $user, ScoringRule $rule): bool
    {
        if ($rule->rule_key === 'employee_performance') {
            // Performance overrides use the dedicated request/decision workflow.
            return false;
        }

        return $user->hasPermission('scoring.override')
            && app(CompanyScopeService::class)->allows($user, $rule->company_id);
    }

    public function retire(User $user, ScoringRule $rule): bool
    {
        return $this->canApproveRule($user, $rule)
            && $rule->status === 'active'
            && app(CompanyScopeService::class)->allows($user, $rule->company_id);
    }

    private function canManageRule(User $user, ScoringRule $rule): bool
    {
        return $rule->rule_key === 'employee_performance'
            ? $this->canManagePerformance($user)
            : $user->hasPermission('scoring.manage');
    }

    private function canApproveRule(User $user, ScoringRule $rule): bool
    {
        return $rule->rule_key === 'employee_performance'
            ? $this->hasAny($user, ['scoring.approve', 'performance.approve', LogicCenterPermissions::PERFORMANCE_APPROVE])
            : $user->hasPermission('scoring.approve');
    }

    private function canManagePerformance(User $user): bool
    {
        return $this->hasAny($user, ['scoring.manage', 'performance.manage', LogicCenterPermissions::PERFORMANCE_MANAGE]);
    }

    /** @param list<string> $permissions */
    private function hasAny(User $user, array $permissions): bool
    {
        return collect($permissions)->contains(fn (string $permission): bool => $user->hasPermission($permission));
    }
}
