<?php

namespace App\Policies;

use App\Models\PayrollRun;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

class PayrollRunPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('payroll.view')
            || $user->hasPermission('payroll.manage')
            || $user->hasPermission('payroll.approve');
    }

    public function view(User $user, PayrollRun $payrollRun): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        return app(CompanyScopeService::class)->allows($user, $payrollRun->company_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('payroll.manage');
    }

    public function approve(User $user, PayrollRun $payrollRun): bool
    {
        if (! $user->hasPermission('payroll.approve')) {
            return false;
        }

        return $payrollRun->status === 'generated'
            && app(CompanyScopeService::class)->allows($user, $payrollRun->company_id)
            && $payrollRun->generated_by_user_id !== $user->id;
    }
}
