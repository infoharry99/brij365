<?php

namespace App\Application\Payroll\Actions;

use App\Domain\Payroll\Services\PayrollWorkspaceRegister;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListCommissionRuns
{
    public function __construct(private readonly PayrollWorkspaceRegister $register) {}

    public function execute(User $user, array $filters): LengthAwarePaginator
    {
        return $this->register->commissionRuns($user, $filters);
    }
}
