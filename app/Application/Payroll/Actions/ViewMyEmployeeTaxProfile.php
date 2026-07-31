<?php

namespace App\Application\Payroll\Actions;

use App\Application\Payroll\Data\EmployeeTaxProfilePageData;
use App\Domain\Payroll\Services\EmployeeTaxInputRegister;
use App\Models\User;

final class ViewMyEmployeeTaxProfile
{
    public function __construct(private readonly EmployeeTaxInputRegister $register) {}

    public function execute(User $actor, string $financialYear): EmployeeTaxProfilePageData
    {
        return $this->register->own($actor, $financialYear);
    }
}
