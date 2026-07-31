<?php

namespace App\Application\Hr\Data;

final readonly class PerformanceSummaryData
{
    public function __construct(
        public int $cycles,
        public int $activeCycles,
        public int $reviews,
        public int $openReviews,
        public int $closedReviews,
        public int $pipRequired,
        public ?string $averageFinalScore,
    ) {}
}
