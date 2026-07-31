<?php

namespace App\Application\Hr\Data;

use App\Models\Employee;
use Illuminate\Support\Collection;

final readonly class EmployeeProfilePageData
{
    public function __construct(
        public Employee $employee,
        public Collection $branches,
        public Collection $projects,
        public Collection $users,
        public Collection $managers,
        public array $employmentTypes,
        public array $statuses,
        public array $abilities,
        public bool $selfService,
        public array $profileNavigation,
    ) {}

    public function toView(): array
    {
        return get_object_vars($this);
    }
}
