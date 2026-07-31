<?php

namespace App\Policies;

use App\Models\AttendanceRegularizationRequest;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

class AttendanceRegularizationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('attendance.view')
            || $user->hasPermission('attendance.request')
            || $user->hasPermission('attendance.manage')
            || $user->hasPermission('attendance.approve')
            || $user->hasPermission('employee.self_service');
    }

    public function view(User $user, AttendanceRegularizationRequest $regularization): bool
    {
        if ($user->hasPermission('attendance.manage') || $user->hasPermission('attendance.approve')) {
            return app(CompanyScopeService::class)->allows($user, $regularization->company_id);
        }

        if (
            ! $user->hasPermission('attendance.view')
            && ! $user->hasPermission('attendance.request')
            && ! $user->hasPermission('employee.self_service')
        ) {
            return false;
        }

        return $regularization->employee?->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('attendance.request')
            || $user->hasPermission('attendance.manage')
            || $user->hasPermission('employee.self_service');
    }

    public function approve(User $user, AttendanceRegularizationRequest $regularization): bool
    {
        if (! $user->hasPermission('attendance.approve')) {
            return false;
        }

        return $regularization->status === 'submitted'
            && app(CompanyScopeService::class)->allows($user, $regularization->company_id)
            && $regularization->requested_by_user_id !== $user->id;
    }

    public function reject(User $user, AttendanceRegularizationRequest $regularization): bool
    {
        return $this->approve($user, $regularization);
    }
}
