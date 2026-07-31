<?php

namespace App\Application\Recruitment\Actions;

use App\Application\Recruitment\Data\RecruitmentCommandData;
use App\Models\Candidate;
use App\Services\Recruitment\RecruitmentService;

final class ConvertCandidateToEmployee
{
    public function __construct(private readonly RecruitmentService $service) {}

    public function execute(Candidate $candidate, RecruitmentCommandData $c): Candidate
    {
        return $this->service->convertCandidateToEmployee($candidate, $c->attributes, $c->actor, $c->request);
    }
}
