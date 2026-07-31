<?php

namespace App\Application\Recruitment\Actions;

use App\Application\Recruitment\Data\RecruitmentCommandData;
use App\Models\Interview;
use App\Services\Recruitment\RecruitmentService;

final class ScheduleCandidateInterview
{
    public function __construct(private readonly RecruitmentService $service) {}

    public function execute(RecruitmentCommandData $c): Interview
    {
        return $this->service->scheduleInterview($c->attributes, $c->actor, $c->request);
    }
}
