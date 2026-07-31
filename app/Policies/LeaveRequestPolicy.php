<?php

namespace App\Policies;

use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

class LeaveRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('leave.view')
            || $user->hasPermission('leave.request')
            || $user->hasPermission('leave.manage')
            || $user->hasPermission('leave.approve')
            || $user->hasPermission('employee.self_service');
    }

    public function view(User $user, LeaveRequest $leaveRequest): bool
    {
        if ($user->hasPermission('leave.manage') || $user->hasPermission('leave.approve')) {
            return app(CompanyScopeService::class)->allows($user, $leaveRequest->company_id);
        }

        if (
            ! $user->hasPermission('leave.view')
            && ! $user->hasPermission('leave.request')
            && ! $user->hasPermission('employee.self_service')
        ) {
            return false;
        }

        return $leaveRequest->employee?->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('leave.request')
            || $user->hasPermission('leave.manage')
            || $user->hasPermission('employee.self_service');
    }

    public function approve(User $user, LeaveRequest $leaveRequest): bool
    {
        if (! $user->hasPermission('leave.approve')) {
            return false;
        }

        return $leaveRequest->status === 'submitted'
            && app(CompanyScopeService::class)->allows($user, $leaveRequest->company_id)
            && $leaveRequest->requested_by_user_id !== $user->id;
    }

    public function reject(User $user, LeaveRequest $leaveRequest): bool
    {
        return $this->approve($user, $leaveRequest);
    }
}
