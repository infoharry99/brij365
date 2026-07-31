<?php

namespace App\Application\Recruitment\Actions;

use App\Application\Recruitment\Data\RecruitmentCommandData;
use App\Models\Candidate;
use App\Services\Recruitment\RecruitmentService;

final class CreateCandidate
{
    public function __construct(private readonly RecruitmentService $service) {}

    public function execute(RecruitmentCommandData $c): Candidate
    {
        return $this->service->createCandidate($c->attributes, $c->actor, $c->request);
    }
}
