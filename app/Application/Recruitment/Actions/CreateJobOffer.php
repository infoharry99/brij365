<?php

namespace App\Application\Recruitment\Actions;

use App\Application\Recruitment\Data\RecruitmentCommandData;
use App\Models\JobOffer;
use App\Services\Recruitment\RecruitmentService;

final class CreateJobOffer
{
    public function __construct(private readonly RecruitmentService $service) {}

    public function execute(RecruitmentCommandData $c): JobOffer
    {
        return $this->service->createOffer($c->attributes, $c->actor, $c->request);
    }
}
