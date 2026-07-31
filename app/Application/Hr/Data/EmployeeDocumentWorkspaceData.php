<?php

namespace App\Application\Hr\Data;

use App\Models\Employee;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final readonly class EmployeeDocumentWorkspaceData
{
    public function __construct(
        public LengthAwarePaginator $documents,
        public Collection $employees,
        public Collection $categories,
        public ?Employee $employee,
        public EmployeeDocumentSummaryData $summary,
        public array $abilities,
    ) {}

    public function toView(): array
    {
        return get_object_vars($this);
    }
}
