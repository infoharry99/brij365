<?php

namespace App\Application\Recruitment\Actions;

use App\Application\Recruitment\Data\RecruitmentCommandData;
use App\Models\JobOpening;
use App\Services\Recruitment\RecruitmentService;

final class CreateJobOpening
{
    public function __construct(private readonly RecruitmentService $service) {}

    public function execute(RecruitmentCommandData $c): JobOpening
    {
        return $this->service->createJobOpening($c->attributes, $c->actor, $c->request);
    }
}
