<?php

namespace App\Application\Hr\Data;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final readonly class EmployeeWorkspaceData
{
    public function __construct(
        public LengthAwarePaginator $employees,
        public Collection $companies,
        public Collection $branches,
        public Collection $projects,
        public Collection $users,
        public Collection $managers,
        public Collection $departments,
        public Collection $designations,
        public Collection $directoryRows,
        public array $employmentTypes,
        public array $statuses,
        /** @var array<int, EmployeeActiveFilterData> */
        public array $activeFilters,
        public array $abilities,
    ) {}

    public function toView(): array
    {
        return get_object_vars($this);
    }
}
