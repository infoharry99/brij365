<?php

namespace App\Application\Payroll\Actions;

use App\Domain\Payroll\Services\PayrollWorkspaceRegister;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListPayrollBankBatches
{
    public function __construct(private readonly PayrollWorkspaceRegister $register) {}

    /** @param array<string,mixed> $filters */
    public function execute(User $user, array $filters): LengthAwarePaginator
    {
        return $this->register->bankBatches($user, $filters);
    }
}
