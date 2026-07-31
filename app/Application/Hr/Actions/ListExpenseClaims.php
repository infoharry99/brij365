<?php

namespace App\Application\Hr\Actions;

use App\Domain\Hr\Services\EmployeeOperationsRegister;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListExpenseClaims
{
    public function __construct(private readonly EmployeeOperationsRegister $register) {}

    public function execute(User $actor, array $filters): LengthAwarePaginator
    {
        return $this->register->claims($actor, $filters);
    }
}
