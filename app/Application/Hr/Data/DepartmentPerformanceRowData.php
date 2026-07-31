<?php

namespace App\Application\Hr\Data;

final readonly class DepartmentPerformanceRowData
{
    public function __construct(
        public string $department,
        public int $employees,
        public int $reviews,
        public int $openReviews,
        public int $closedReviews,
        public int $pipRequired,
        public string $completionRate,
        public ?string $averageFinalScore,
    ) {}
}
