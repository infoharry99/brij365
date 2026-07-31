<?php

namespace App\Application\Recruitment\Actions;

use App\Application\Recruitment\Data\RecruitmentCommandData;
use App\Models\JobOffer;
use App\Services\Recruitment\RecruitmentService;

final class ReleaseJobOffer
{
    public function __construct(private readonly RecruitmentService $service) {}

    public function execute(JobOffer $offer, RecruitmentCommandData $c): JobOffer
    {
        return $this->service->releaseOffer($offer, $c->attributes, $c->actor, $c->request);
    }
}
