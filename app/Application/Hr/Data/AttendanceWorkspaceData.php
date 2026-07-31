<?php

namespace App\Application\Hr\Data;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final readonly class AttendanceWorkspaceData
{
    public function __construct(
        public string $activeRegister,
        public ?LengthAwarePaginator $shifts,
        public ?LengthAwarePaginator $records,
        public ?LengthAwarePaginator $calculationTraces,
        public ?LengthAwarePaginator $regularizations,
        public ?LengthAwarePaginator $assignments,
        public AttendanceSummaryData $summary,
        public Collection $siteAttendance,
        public Collection $companies,
        public Collection $employees,
        public array $statusFilters,
        public array $regularizationStatuses,
        public array $shiftTypes,
        public array $abilities,
    ) {}

    public function toView(): array
    {
        return get_object_vars($this);
    }
}
