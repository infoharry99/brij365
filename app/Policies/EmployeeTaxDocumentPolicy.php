<?php

namespace App\Policies;

use App\Models\EmployeeTaxDocument;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

class EmployeeTaxDocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('payroll.view')
            || $user->hasPermission('payroll.manage')
            || $user->hasPermission('payroll.approve')
            || $user->hasPermission('compliance.view')
            || $user->hasPermission('compliance.manage')
            || $user->hasPermission('employee.self_service');
    }

    public function view(User $user, EmployeeTaxDocument $taxDocument): bool
    {
        if ($taxDocument->employee?->user_id === $user->id) {
            return true;
        }

        return $this->viewAny($user)
            && app(CompanyScopeService::class)->allows($user, $taxDocument->company_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('payroll.manage') || $user->hasPermission('compliance.manage');
    }

    public function issue(User $user, EmployeeTaxDocument $taxDocument): bool
    {
        return $taxDocument->status === 'generated'
            && ($user->hasPermission('payroll.approve') || $user->hasPermission('compliance.manage') || $user->hasPermission('*'))
            && app(CompanyScopeService::class)->allows($user, $taxDocument->company_id)
            && $taxDocument->generated_by_user_id !== $user->id;
    }

    public function acknowledge(User $user, EmployeeTaxDocument $taxDocument): bool
    {
        return $taxDocument->status === 'issued'
            && $taxDocument->employee?->user_id === $user->id;
    }
}
