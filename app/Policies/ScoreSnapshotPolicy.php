<?php

namespace App\Policies;

use App\Domain\Scoring\Support\LogicCenterPermissions;
use App\Models\ScoreSnapshot;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

final class ScoreSnapshotPolicy
{
    public function view(User $user, ScoreSnapshot $snapshot): bool
    {
        $allowed = $this->ruleKey($snapshot) === 'employee_performance'
            ? $this->hasAny($user, [
                'scoring.view', 'scoring.manage', 'scoring.approve',
                'performance.view', 'performance.manage', 'performance.approve',
                LogicCenterPermissions::PERFORMANCE_MANAGE,
                LogicCenterPermissions::PERFORMANCE_APPROVE,
                LogicCenterPermissions::PERFORMANCE_OVERRIDE_REQUEST,
                LogicCenterPermissions::PERFORMANCE_OVERRIDE_APPROVE,
            ])
            : $this->hasAny($user, ['scoring.view', 'scoring.manage', 'scoring.approve']);

        return $allowed
            && app(CompanyScopeService::class)->allows($user, $snapshot->company_id);
    }

    public function override(User $user, ScoreSnapshot $snapshot): bool
    {
        if ($this->ruleKey($snapshot) === 'employee_performance') {
            // The review-scoped maker-checker workflow is the only supported path.
            return false;
        }

        return $user->hasPermission('scoring.override')
            && $snapshot->is_current
            && (bool) data_get($snapshot->scoringRule?->configuration, 'override.allowed', false)
            && app(CompanyScopeService::class)->allows($user, $snapshot->company_id);
    }

    /** @param list<string> $permissions */
    private function hasAny(User $user, array $permissions): bool
    {
        return collect($permissions)->contains(fn (string $permission): bool => $user->hasPermission($permission));
    }

    private function ruleKey(ScoreSnapshot $snapshot): ?string
    {
        if ($snapshot->relationLoaded('scoringRule')) {
            return $snapshot->scoringRule?->rule_key;
        }

        return $snapshot->scoringRule()->value('rule_key');
    }
}
