<?php

namespace App\Application\Recruitment\Data;

final readonly class CandidateRowData
{
    /** @param array<string, string> $allowedStages */
    public function __construct(
        public int $id,
        public string $code,
        public string $name,
        public string $initials,
        public string $email,
        public string $phone,
        public string $source,
        public string $currentCompany,
        public string $experience,
        public ?string $ctcSummary,
        public string $openingCode,
        public string $openingTitle,
        public string $department,
        public string $stage,
        public string $stageLabel,
        public string $stageTone,
        public string $status,
        public string $owner,
        public int $interviewCount,
        public string $offerStatus,
        public array $allowedStages,
        public bool $canConvert,
    ) {}
}
