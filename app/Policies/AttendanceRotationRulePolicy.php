<?php

namespace App\Policies;

use App\Domain\Scoring\Support\LogicCenterPermissions;
use App\Models\AttendanceRotationRule;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

final class AttendanceRotationRulePolicy
{
    public function create(User $user): bool
    {
        return $user->hasPermission('attendance.manage')
            || $user->hasPermission(LogicCenterPermissions::ROSTER_MANAGE);
    }

    public function view(User $user, AttendanceRotationRule $rotation): bool
    {
        if (! app(CompanyScopeService::class)->allows($user, $rotation->company_id)) {
            return false;
        }

        if ($user->hasPermission('attendance.view') || $this->create($user)) {
            return true;
        }

        return (int) $rotation->employee?->user_id === (int) $user->id;
    }
}
