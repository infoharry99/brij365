<?php

namespace App\Application\Hr\Data;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final readonly class LeaveWorkspaceData
{
    public function __construct(
        public string $activeRegister,
        public LeaveSummaryData $summary,
        public ?LengthAwarePaginator $types,
        public ?LengthAwarePaginator $balances,
        public ?LengthAwarePaginator $leaveRequests,
        public ?LengthAwarePaginator $processingRuns,
        public ?LengthAwarePaginator $encashments,
        public Collection $companies,
        public Collection $employees,
        public Collection $leaveTypeOptions,
        public array $requestStatuses,
        public array $processingStatuses,
        public array $processingTypes,
        public array $encashmentStatuses,
        public array $abilities,
    ) {}

    public function toView(): array
    {
        return get_object_vars($this);
    }
}
