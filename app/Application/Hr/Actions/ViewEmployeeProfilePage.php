<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\EmployeeProfilePageData;
use App\Domain\Hr\Services\EmployeeFieldVisibility;
use App\Domain\Hr\Services\EmployeeProfileNavigation;
use App\Domain\Hr\Services\EmployeeRegister;
use App\Models\Employee;
use App\Models\User;

final class ViewEmployeeProfilePage
{
    public function __construct(
        private readonly EmployeeRegister $register,
        private readonly EmployeeFieldVisibility $visibility,
        private readonly EmployeeProfileNavigation $navigation,
    ) {}

    public function execute(Employee $employee, User $actor, bool $selfService = false): EmployeeProfilePageData
    {
        $employee = $this->register->detail($employee);

        return new EmployeeProfilePageData(
            employee: $employee,
            branches: $this->register->branches($actor),
            projects: $this->register->projects($actor),
            users: $this->register->availableUsers($actor, $employee->id),
            managers: $this->register->managers($actor, $employee->id),
            employmentTypes: ['full_time' => 'Full time', 'part_time' => 'Part time', 'contract' => 'Contract', 'intern' => 'Intern', 'consultant' => 'Consultant'],
            statuses: ['active' => 'Active', 'inactive' => 'Inactive', 'on_notice' => 'On notice', 'separated' => 'Separated'],
            abilities: [
                'canUpdate' => $actor->can('update', $employee),
                'canViewPayroll' => $this->visibility->canViewCompensation($actor, $employee),
                'canViewAudit' => $actor->hasPermission('*') || $actor->hasPermission('audit.view') || $actor->hasPermission('hr.manage'),
            ],
            selfService: $selfService,
            profileNavigation: $this->navigation->links($employee, $actor, $selfService),
        );
    }
}
