<?php

namespace App\Application\Recruitment\Actions;

use App\Application\Recruitment\Data\RecruitmentCommandData;
use App\Models\JobOpening;
use App\Services\Recruitment\RecruitmentService;

final class ApproveJobOpening
{
    public function __construct(private readonly RecruitmentService $service) {}

    public function execute(JobOpening $o, RecruitmentCommandData $c): JobOpening
    {
        return $this->service->approveJobOpening($o, $c->attributes, $c->actor, $c->request);
    }
}
