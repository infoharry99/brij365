<?php

namespace App\Application\Construction\Data;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final readonly class ConstructionProgressWorkspaceData
{
    /** @param array<string, mixed> $filters */
    public function __construct(
        public string $activeRegister,
        public array $filters,
        public LengthAwarePaginator $milestones,
        public LengthAwarePaginator $dailyReports,
        public Collection $projects,
        public Collection $milestoneOptions,
        public Collection $phases,
        public array $milestoneStatuses,
        public array $dailyReportStatuses,
        public Collection $milestoneMetrics,
        public Collection $dailyReportMetrics,
        public bool $canCreateMilestone,
        public bool $canCreateDailyReport,
    ) {}

    /** @return array<string, mixed> */
    public function toView(): array
    {
        return get_object_vars($this);
    }
}
