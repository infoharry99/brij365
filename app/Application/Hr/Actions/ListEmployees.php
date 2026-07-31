<?php

namespace App\Application\Hr\Actions;

use App\Domain\Hr\Services\EmployeeRegister;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListEmployees
{
    public function __construct(private readonly EmployeeRegister $register) {}

    public function execute(User $user, array $filters): LengthAwarePaginator
    {
        return $this->register->paginate($user, $filters);
    }
}
