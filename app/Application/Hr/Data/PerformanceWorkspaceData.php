<?php

namespace App\Application\Hr\Data;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final readonly class PerformanceWorkspaceData
{
    public function __construct(
        public string $activeRegister,
        public PerformanceSummaryData $summary,
        public ?LengthAwarePaginator $cycles,
        public ?LengthAwarePaginator $reviews,
        public Collection $departmentRows,
        public Collection $companies,
        public Collection $projects,
        public Collection $employees,
        public Collection $managers,
        public Collection $departments,
        public Collection $activeCycles,
        public array $abilities,
    ) {}

    public function toView(): array
    {
        return get_object_vars($this);
    }
}
