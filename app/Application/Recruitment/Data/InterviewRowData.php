<?php

namespace App\Application\Recruitment\Data;

final readonly class InterviewRowData
{
    /** @param array<int, string> $panel */
    public function __construct(
        public int $id,
        public string $code,
        public string $round,
        public string $candidateCode,
        public string $candidateName,
        public string $openingTitle,
        public string $scheduledDate,
        public string $scheduledTime,
        public string $duration,
        public string $mode,
        public string $venue,
        public array $panel,
        public string $status,
        public string $statusLabel,
        public string $statusTone,
        public int $submittedFeedback,
        public int $expectedFeedback,
        public ?string $averageRating,
        public ?string $score,
        public ?string $scoreBand,
        public ?string $scoreRule,
        public bool $canSubmitFeedback,
    ) {}
}
