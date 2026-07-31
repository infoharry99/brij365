<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\EmployeeProfilePageData;
use App\Domain\Hr\Services\EmployeeFieldVisibility;
use App\Domain\Hr\Services\EmployeeProfileNavigation;
use App\Domain\Hr\Services\EmployeeRegister;
use App\Models\User;

final class ViewMyEmployeeProfilePage
{
    public function __construct(
        private readonly EmployeeRegister $register,
        private readonly EmployeeFieldVisibility $visibility,
        private readonly EmployeeProfileNavigation $navigation,
    ) {}

    public function execute(User $actor): ?EmployeeProfilePageData
    {
        $employee = $this->register->self($actor);

        if (! $employee) {
            return null;
        }

        return new EmployeeProfilePageData(
            employee: $this->register->detail($employee),
            branches: $this->register->branches($actor),
            projects: $this->register->projects($actor),
            users: collect([$actor]),
            managers: $this->register->managers($actor, $employee->id),
            employmentTypes: ['full_time' => 'Full time', 'part_time' => 'Part time', 'contract' => 'Contract', 'intern' => 'Intern', 'consultant' => 'Consultant'],
            statuses: ['active' => 'Active', 'inactive' => 'Inactive', 'on_notice' => 'On notice', 'separated' => 'Separated'],
            abilities: ['canUpdate' => false, 'canViewPayroll' => $this->visibility->canViewCompensation($actor, $employee), 'canViewAudit' => false],
            selfService: true,
            profileNavigation: $this->navigation->links($employee, $actor, true),
        );
    }
}
