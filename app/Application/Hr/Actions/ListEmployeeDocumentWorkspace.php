<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\EmployeeDocumentWorkspaceData;
use App\Domain\Hr\Services\EmployeeDocumentRegister;
use App\Models\Employee;
use App\Models\User;

final class ListEmployeeDocumentWorkspace
{
    public function __construct(private readonly EmployeeDocumentRegister $register) {}

    public function execute(User $actor, array $filters, ?Employee $employee = null): EmployeeDocumentWorkspaceData
    {
        $documents = $employee
            ? $this->register->employeeDocuments($employee, $filters)
            : $this->register->companyDocuments($actor, $filters);

        $documents = $this->register->present($actor, $documents);
        $canSubmit = $employee !== null && $actor->can('update', $employee);

        return new EmployeeDocumentWorkspaceData(
            documents: $documents,
            employees: $employee === null ? $this->register->employees($actor) : collect(),
            categories: $employee === null || $canSubmit ? $this->register->categories($actor) : collect(),
            employee: $employee?->loadMissing(['branch', 'project', 'manager']),
            summary: $this->register->summary($actor, $filters, $employee),
            abilities: [
                'canSubmit' => $canSubmit,
            ],
        );
    }
}
