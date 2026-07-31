<?php

namespace App\Policies;

use App\Domain\Scoring\Support\LogicCenterPermissions;
use App\Models\EmployeeShiftAssignment;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

final class EmployeeShiftAssignmentPolicy
{
    public function create(User $user): bool
    {
        return $user->hasPermission('attendance.manage')
            || $user->hasPermission(LogicCenterPermissions::ROSTER_MANAGE);
    }

    public function view(User $user, EmployeeShiftAssignment $assignment): bool
    {
        return ($user->hasPermission('attendance.view') || $this->create($user))
            && app(CompanyScopeService::class)->allows($user, $assignment->company_id);
    }
}
