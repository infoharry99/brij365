<?php

namespace App\Application\Hr\Data;

final readonly class LifecycleSummaryData
{
    public function __construct(
        public int $totalEvents,
        public int $pendingMovements,
        public int $openConfirmations,
        public int $openSeparations,
        public int $openExitInterviews,
    ) {}
}
