<?php

namespace App\Application\Recruitment\Data;

use Illuminate\Support\Collection;

final readonly class RecruitmentPipelineColumnData
{
    /** @param Collection<int, CandidateRowData> $candidates */
    public function __construct(
        public string $stage,
        public string $label,
        public string $tone,
        public int $total,
        public int $limit,
        public Collection $candidates,
    ) {}
}
