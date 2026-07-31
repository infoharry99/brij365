<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\EmployeeProfileSectionsPageData;
use App\Domain\Hr\Services\EmployeeFieldVisibility;
use App\Domain\Hr\Services\EmployeeProfileNavigation;
use App\Models\Employee;
use App\Models\User;
use App\Services\Hr\EmployeeProfileSectionService;
use Illuminate\Auth\Access\AuthorizationException;

final class ViewEmployeeProfileSectionsPage
{
    public function __construct(
        private readonly EmployeeProfileSectionService $sections,
        private readonly EmployeeProfileNavigation $navigation,
        private readonly EmployeeFieldVisibility $fieldVisibility,
    ) {}

    public function execute(Employee $employee, User $actor): EmployeeProfileSectionsPageData
    {
        if (! $actor->can('view', $employee) || ! $this->fieldVisibility->canViewSensitiveProfile($actor, $employee)) {
            throw new AuthorizationException('Employee profile details are not available for this role.');
        }

        return new EmployeeProfileSectionsPageData(
            employee: $employee,
            sections: $this->sections->sectionsFor($employee),
            abilities: ['canUpdate' => $actor->can('update', $employee)],
            profileNavigation: $this->navigation->links($employee, $actor, $this->navigation->isSelfServiceOnly($employee, $actor)),
        );
    }
}
