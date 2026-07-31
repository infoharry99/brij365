<?php

namespace App\Application\Recruitment\Data;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final readonly class RecruitmentWorkspaceData
{
    public function __construct(
        public string $activeRegister,
        public array $filters,
        public RecruitmentSummaryData $summary,
        public ?LengthAwarePaginator $openings,
        public ?LengthAwarePaginator $candidates,
        /** @var array<int, RecruitmentPipelineColumnData> */
        public array $pipelineColumns,
        public ?LengthAwarePaginator $interviews,
        public ?LengthAwarePaginator $offers,
        public Collection $companies,
        public Collection $branches,
        public Collection $projects,
        public Collection $openOpeningOptions,
        public Collection $activeCandidateOptions,
        public Collection $offerCandidateOptions,
        public Collection $panelUsers,
        public Collection $departments,
        public Collection $sources,
        public array $openingStatuses,
        public array $candidateStages,
        public array $interviewStatuses,
        public array $offerStatuses,
        public array $employmentTypes,
        public array $interviewModes,
        public array $abilities,
    ) {}

    public function toView(): array
    {
        return get_object_vars($this);
    }
}
