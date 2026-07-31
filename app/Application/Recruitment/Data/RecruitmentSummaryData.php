<?php

namespace App\Application\Recruitment\Data;

final readonly class RecruitmentSummaryData
{
    /** @param array<string, int> $pipeline */
    public function __construct(
        public int $openRequisitions,
        public int $openPositions,
        public int $activeCandidates,
        public int $scheduledInterviews,
        public int $draftOffers,
        public int $convertedCandidates,
        public array $pipeline,
    ) {}
}
