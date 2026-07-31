<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\EmployeeActiveFilterData;
use App\Application\Hr\Data\EmployeeDirectoryRowData;
use App\Application\Hr\Data\EmployeeWorkspaceData;
use App\Domain\Hr\Services\EmployeeFieldVisibility;
use App\Domain\Hr\Services\EmployeeRegister;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Collection;

final class ListEmployeeWorkspace
{
    public function __construct(
        private readonly EmployeeRegister $register,
        private readonly EmployeeFieldVisibility $visibility,
    ) {}

    public function execute(User $actor, array $filters): EmployeeWorkspaceData
    {
        $canViewCompensation = $this->visibility->canViewCompensation($actor);
        $employees = $this->register->paginate($actor, $filters, $canViewCompensation);
        $branches = $this->register->branches($actor);
        $projects = $this->register->projects($actor);
        $statuses = ['active' => 'Active', 'inactive' => 'Inactive', 'on_notice' => 'On notice', 'separated' => 'Separated'];
        $directoryRows = $employees->getCollection()->mapWithKeys(
            fn (Employee $employee): array => [$employee->id => EmployeeDirectoryRowData::fromEmployee($employee, $canViewCompensation)],
        );

        return new EmployeeWorkspaceData(
            employees: $employees,
            companies: $this->register->companies($actor),
            branches: $branches,
            projects: $projects,
            users: $this->register->availableUsers($actor),
            managers: $this->register->managers($actor),
            departments: $this->register->departments($actor),
            designations: $this->register->designations($actor),
            directoryRows: $directoryRows,
            employmentTypes: ['full_time' => 'Full time', 'part_time' => 'Part time', 'contract' => 'Contract', 'intern' => 'Intern', 'consultant' => 'Consultant'],
            statuses: $statuses,
            activeFilters: $this->activeFilters($filters, $branches, $projects, $statuses),
            abilities: [
                'canCreate' => $actor->can('create', Employee::class),
                'canExport' => $this->visibility->canExportRegister($actor),
                'canViewCompensation' => $canViewCompensation,
            ],
        );
    }

    /**
     * @return array<int, EmployeeActiveFilterData>
     */
    private function activeFilters(array $filters, Collection $branches, Collection $projects, array $statuses): array
    {
        $definitions = [
            'search' => ['Search', fn (mixed $value): string => (string) $value],
            'department' => ['Department', fn (mixed $value): string => (string) $value],
            'designation' => ['Designation', fn (mixed $value): string => (string) $value],
            'branch_id' => ['Branch', fn (mixed $value): string => $branches->firstWhere('id', (int) $value)?->name ?? (string) $value],
            'project_id' => ['Project', fn (mixed $value): string => $projects->firstWhere('id', (int) $value)?->name ?? (string) $value],
            'status' => ['Status', fn (mixed $value): string => $statuses[(string) $value] ?? str((string) $value)->replace('_', ' ')->title()->toString()],
        ];

        $active = [];

        foreach ($definitions as $key => [$label, $present]) {
            if (! array_key_exists($key, $filters) || blank($filters[$key])) {
                continue;
            }

            $active[] = new EmployeeActiveFilterData(
                key: $key,
                label: $label,
                value: $present($filters[$key]),
            );
        }

        return $active;
    }
}
