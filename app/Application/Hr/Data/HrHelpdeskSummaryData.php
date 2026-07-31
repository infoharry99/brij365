<?php

namespace App\Application\Hr\Data;

final readonly class HrHelpdeskSummaryData
{
    public function __construct(
        public int $total,
        public int $open,
        public int $assigned,
        public int $resolved,
        public int $closed,
        public int $critical,
    ) {}
}
