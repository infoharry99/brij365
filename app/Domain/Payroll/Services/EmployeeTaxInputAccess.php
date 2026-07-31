<?php

namespace App\Domain\Payroll\Services;

use App\Models\User;

final class EmployeeTaxInputAccess
{
    /** @param list<string> $permissions */
    public function hasAnyExplicit(User $user, array $permissions): bool
    {
        $assigned = $user->role?->permissions ?? [];

        return collect($permissions)->contains(fn (string $permission): bool => in_array($permission, $assigned, true));
    }

    public function canReview(User $user): bool
    {
        return $this->hasAnyExplicit($user, [
            'payroll.manage',
            'payroll.approve',
            'compliance.view',
            'compliance.manage',
        ]);
    }

    public function canApproveProof(User $user): bool
    {
        return $this->hasAnyExplicit($user, [
            'payroll.manage',
            'payroll.approve',
            'compliance.manage',
        ]);
    }
}
