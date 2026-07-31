<?php

namespace App\Application\Hr\Data;

use App\Models\Employee;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final readonly class EmployeeMovementWorkspaceData
{
    public function __construct(public Employee $employee, public LengthAwarePaginator $movements, public Collection $branches, public Collection $projects, public Collection $managers, public array $abilities, public array $movementActions, public array $profileNavigation) {}

    public function toView(): array { return get_object_vars($this); }
}
