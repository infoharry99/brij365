<?php

namespace App\Application\Recruitment\Data;

final readonly class JobOfferRowData
{
    public function __construct(
        public int $id,
        public string $number,
        public string $candidateCode,
        public string $candidateName,
        public string $openingTitle,
        public string $department,
        public string $template,
        public ?string $offeredCtc,
        public string $joiningDate,
        public string $status,
        public string $statusLabel,
        public string $statusTone,
        public string $createdBy,
        public string $releasedBy,
        public string $releasedAt,
        public bool $canRelease,
    ) {}
}
