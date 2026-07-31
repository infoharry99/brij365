<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\EmployeeSelfServiceDashboardData;
use App\Domain\Hr\Services\EmployeeSelfServiceRegister;
use App\Models\User;

final class ViewEmployeeSelfServiceDashboard
{
    public function __construct(private readonly EmployeeSelfServiceRegister $register) {}

    public function execute(User $actor): ?EmployeeSelfServiceDashboardData
    {
        return $this->register->read($actor);
    }
}
