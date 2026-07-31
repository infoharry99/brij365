<?php

namespace App\Application\Hr\Actions;

use App\Domain\Hr\Services\EmployeeFieldVisibility;
use App\Models\Employee;
use App\Models\User;
use App\Services\Hr\EmployeeProfileSectionService;
use Illuminate\Auth\Access\AuthorizationException;

final class ViewEmployeeProfileSections
{
    public function __construct(
        private readonly EmployeeProfileSectionService $service,
        private readonly EmployeeFieldVisibility $fieldVisibility,
    ) {}

    public function execute(Employee $employee, User $actor): array
    {
        if (! $actor->can('view', $employee) || ! $this->fieldVisibility->canViewSensitiveProfile($actor, $employee)) {
            throw new AuthorizationException('Employee profile details are not available for this role.');
        }

        return ['employee_id' => $employee->id, 'employee_code' => $employee->employee_code, 'sections' => $this->service->sectionsFor($employee)];
    }
}
