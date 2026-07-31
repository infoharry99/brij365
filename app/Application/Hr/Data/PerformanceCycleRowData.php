<?php

namespace App\Application\Hr\Data;

final readonly class PerformanceCycleRowData
{
    public function __construct(
        public int $id,
        public string $code,
        public string $name,
        public string $frequency,
        public string $period,
        public string $reviewDue,
        public string $department,
        public string $project,
        public string $scale,
        public string $passingScore,
        public string $status,
        public string $statusLabel,
        public int $reviewCount,
    ) {}
}
