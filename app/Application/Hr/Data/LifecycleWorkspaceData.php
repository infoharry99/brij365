<?php

namespace App\Application\Hr\Data;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final readonly class LifecycleWorkspaceData
{
    /**
     * @param LengthAwarePaginator<int, LifecycleTrackerRowData> $events
     * @param Collection<int, array{id: int, label: string}> $employees
     * @param Collection<int, string> $departments
     * @param array<string, mixed> $filters
     */
    public function __construct(
        public LifecycleSummaryData $summary,
        public LengthAwarePaginator $events,
        public Collection $employees,
        public Collection $departments,
        public array $filters,
    ) {}

    /** @return array<string, mixed> */
    public function toView(): array
    {
        return [
            'lifecycleSummary' => $this->summary,
            'lifecycleEvents' => $this->events,
            'employees' => $this->employees,
            'departments' => $this->departments,
            'filters' => $this->filters,
        ];
    }
}
