<?php

namespace App\Application\Hr\Data;

final readonly class LeaveSummaryData
{
    public function __construct(
        public int $pendingRequests,
        public int $onLeaveToday,
        public int $upcoming,
        public int $pendingEncashments,
    ) {}
}
