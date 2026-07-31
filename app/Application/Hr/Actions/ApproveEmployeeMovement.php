<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\HrCommandData;
use App\Models\Employee;
use App\Models\EmployeeMovement;
use App\Services\Hr\EmployeeMovementService;

final class ApproveEmployeeMovement
{
    public function __construct(private readonly EmployeeMovementService $service) {}

    public function execute(Employee $employee, EmployeeMovement $movement, HrCommandData $c): EmployeeMovement
    {
        abort_unless((int) $movement->employee_id === (int) $employee->id, 404);

        return $this->service->approve($movement, $c->actor, $c->attributes['remarks'] ?? null, $c->request);
    }
}
