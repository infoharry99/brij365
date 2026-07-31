<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\HrCommandData;
use App\Models\Employee;
use App\Services\Hr\EmployeeProfileSectionService;

final class UpdateEmployeeProfileSections
{
    public function __construct(private readonly EmployeeProfileSectionService $service) {}

    public function execute(Employee $employee, HrCommandData $c): array
    {
        return ['employee_id' => $employee->id, 'employee_code' => $employee->employee_code, 'sections' => $this->service->save($employee, $c->attributes['sections'], $c->actor, $c->request)];
    }
}
