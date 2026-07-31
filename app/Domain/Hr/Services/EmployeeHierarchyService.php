<?php

namespace App\Domain\Hr\Services;

use App\Models\Employee;

final class EmployeeHierarchyService
{
    public function wouldCreateCycle(Employee $employee, int $managerId): bool
    {
        if ($managerId === (int) $employee->id) {
            return true;
        }

        $visited = [];
        $currentId = $managerId;

        while ($currentId > 0 && ! isset($visited[$currentId])) {
            if ($currentId === (int) $employee->id) {
                return true;
            }

            $visited[$currentId] = true;
            $currentId = (int) (Employee::query()
                ->where('company_id', $employee->company_id)
                ->whereKey($currentId)
                ->value('manager_employee_id') ?? 0);
        }

        return false;
    }
}
