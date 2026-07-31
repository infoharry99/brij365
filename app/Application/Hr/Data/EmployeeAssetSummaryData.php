<?php

namespace App\Application\Hr\Data;

final readonly class EmployeeAssetSummaryData
{
    public function __construct(
        public int $total,
        public int $available,
        public int $assigned,
        public int $recovered,
        public int $retired,
        public int $lost,
    ) {}
}
