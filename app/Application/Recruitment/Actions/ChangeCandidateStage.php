<?php

namespace App\Application\Recruitment\Actions;

use App\Application\Recruitment\Data\RecruitmentCommandData;
use App\Models\Candidate;
use App\Services\Recruitment\RecruitmentService;

final class ChangeCandidateStage
{
    public function __construct(private readonly RecruitmentService $service) {}

    public function execute(Candidate $candidate, RecruitmentCommandData $c): Candidate
    {
        return $this->service->updateCandidateStage($candidate, $c->attributes, $c->actor, $c->request);
    }
}
