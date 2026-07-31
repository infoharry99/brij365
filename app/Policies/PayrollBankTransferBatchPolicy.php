<?php

namespace App\Policies;

use App\Models\PayrollBankTransferBatch;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

class PayrollBankTransferBatchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('payroll.view')
            || $user->hasPermission('payroll.manage')
            || $user->hasPermission('payroll.approve');
    }

    public function view(User $user, PayrollBankTransferBatch $batch): bool
    {
        return $this->viewAny($user)
            && app(CompanyScopeService::class)->allows($user, $batch->company_id);
    }

    public function viewPayload(User $user): bool
    {
        return $user->hasPermission('*') || $user->hasPermission('payroll.approve');
    }

    public function create(User $user, PayrollRun $payrollRun): bool
    {
        return $user->hasPermission('payroll.manage')
            && $payrollRun->status === 'approved'
            && app(CompanyScopeService::class)->allows($user, $payrollRun->company_id);
    }

    public function release(User $user, PayrollBankTransferBatch $batch): bool
    {
        if (! $user->hasPermission('payroll.approve')) {
            return false;
        }

        return $batch->status === 'prepared'
            && app(CompanyScopeService::class)->allows($user, $batch->company_id)
            && $batch->prepared_by_user_id !== $user->id;
    }
}
